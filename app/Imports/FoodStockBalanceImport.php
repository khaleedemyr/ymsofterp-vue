<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Warehouse;
use App\Models\FoodStockBalance;
use App\Models\ActivityLog;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FoodStockBalanceImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    protected $errors = [];
    protected $successCount = 0;
    protected $errorCount = 0;

    public function collection(Collection $rows)
    {
        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                // Skip baris yang semua kolomnya kosong
                if (collect($row)->filter()->isEmpty()) {
                    continue;
                }
                try {
                    // Validasi data
                    $missingFields = [];
                    if (empty($row['sku']) || trim($row['sku']) === '') $missingFields[] = 'SKU';
                    if (empty($row['name']) || trim($row['name']) === '') $missingFields[] = 'Name';
                    if (empty($row['small_unit']) || trim($row['small_unit']) === '') $missingFields[] = 'Small Unit';
                    if (empty($row['warehouse']) || trim($row['warehouse']) === '') $missingFields[] = 'Warehouse';
                    if (!isset($row['quantity']) || $row['quantity'] === '' || $row['quantity'] === null) $missingFields[] = 'Quantity';
                    if (!isset($row['cost']) || $row['cost'] === '' || $row['cost'] === null) $missingFields[] = 'Cost';
                    
                    if (!empty($missingFields)) {
                        throw new \Exception('Field wajib diisi: ' . implode(', ', $missingFields));
                    }

                    // Cek item
                    $item = Item::where('sku', $row['sku'])
                        ->where('name', $row['name'])
                        ->where('status', 'active')
                        ->whereHas('category', function($query) {
                            $query->where('show_pos', '0');
                        })
                        ->first();
                    if (!$item) {
                        // Cek apakah item ada tapi tidak aktif
                        $inactiveItem = Item::where('sku', $row['sku'])
                            ->where('name', $row['name'])
                            ->first();
                        if ($inactiveItem) {
                            throw new \Exception("Item '{$row['name']}' (SKU: {$row['sku']}) ditemukan tapi status tidak aktif");
                        } else {
                            throw new \Exception("Item '{$row['name']}' (SKU: {$row['sku']}) tidak ditemukan dalam database");
                        }
                    }

                    // Cek warehouse
                    $warehouse = Warehouse::where('name', $row['warehouse'])
                        ->where('status', 'active')
                        ->first();
                    if (!$warehouse) {
                        // Cek apakah warehouse ada tapi tidak aktif
                        $inactiveWarehouse = Warehouse::where('name', $row['warehouse'])->first();
                        if ($inactiveWarehouse) {
                            throw new \Exception("Warehouse '{$row['warehouse']}' ditemukan tapi status tidak aktif");
                        } else {
                            throw new \Exception("Warehouse '{$row['warehouse']}' tidak ditemukan dalam database");
                        }
                    }

                    // Validasi quantity dan cost
                    if (!is_numeric($row['quantity'])) {
                        throw new \Exception("Quantity '{$row['quantity']}' harus berupa angka");
                    }
                    if (!is_numeric($row['cost'])) {
                        throw new \Exception("Cost '{$row['cost']}' harus berupa angka");
                    }
                    if ($row['cost'] < 0) {
                        throw new \Exception("Cost '{$row['cost']}' tidak boleh negatif");
                    }
                    if ($row['quantity'] < 0) {
                        throw new \Exception("Quantity '{$row['quantity']}' tidak boleh negatif");
                    }

                    // === INVENTORY LOGIC ===
                    // 1. Cek/insert food_inventory_items
                    $inventoryItem = DB::table('food_inventory_items')
                        ->where('item_id', $item->id)
                        ->first();
                    if (!$inventoryItem) {
                        $inventoryItemId = DB::table('food_inventory_items')->insertGetId([
                            'item_id' => $item->id,
                            'small_unit_id' => $item->small_unit_id,
                            'medium_unit_id' => $item->medium_unit_id,
                            'large_unit_id' => $item->large_unit_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $inventoryItemId = $inventoryItem->id;
                    }

                    // 2. Hitung qty (konversi ke semua unit)
                    $smallConv = $item->small_conversion_qty ?: 1;
                    $mediumConv = $item->medium_conversion_qty ?: 1;
                    $qty_small = (float) $row['quantity'];
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;

                    // 3. Hitung cost
                    $cost_small = (float) $row['cost'];
                    $cost_medium = $cost_small * ($item->small_conversion_qty ?: 1);
                    $cost_large = $cost_medium * ($item->medium_conversion_qty ?: 1);

                    // 4. Insert/update ke food_inventory_stocks
                    $existingStock = DB::table('food_inventory_stocks')
                        ->where('inventory_item_id', $inventoryItemId)
                        ->where('warehouse_id', $warehouse->id)
                        ->first();

                    $value = $qty_small * $cost_small;

                    if ($existingStock) {
                        DB::table('food_inventory_stocks')
                            ->where('id', $existingStock->id)
                            ->update([
                                'qty_small' => $qty_small,
                                'qty_medium' => $qty_medium,
                                'qty_large' => $qty_large,
                                'value' => $value,
                                'last_cost_small' => $cost_small,
                                'last_cost_medium' => $cost_medium,
                                'last_cost_large' => $cost_large,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('food_inventory_stocks')->insert([
                            'inventory_item_id' => $inventoryItemId,
                            'warehouse_id' => $warehouse->id,
                            'qty_small' => $qty_small,
                            'qty_medium' => $qty_medium,
                            'qty_large' => $qty_large,
                            'value' => $value,
                            'last_cost_small' => $cost_small,
                            'last_cost_medium' => $cost_medium,
                            'last_cost_large' => $cost_large,
                            'updated_at' => now(),
                        ]);
                    }

                    // 5. Insert ke food_inventory_cards
                    DB::table('food_inventory_cards')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'warehouse_id' => $warehouse->id,
                        'date' => now(),
                        'reference_type' => 'initial_balance',
                        'reference_id' => 0,
                        'in_qty_small' => $qty_small,
                        'in_qty_medium' => $qty_medium,
                        'in_qty_large' => $qty_large,
                        'out_qty_small' => 0,
                        'out_qty_medium' => 0,
                        'out_qty_large' => 0,
                        'cost_per_small' => $cost_small,
                        'cost_per_medium' => $cost_medium,
                        'cost_per_large' => $cost_large,
                        'value_in' => $value,
                        'value_out' => 0,
                        'saldo_qty_small' => $qty_small,
                        'saldo_qty_medium' => $qty_medium,
                        'saldo_qty_large' => $qty_large,
                        'saldo_value' => $value,
                        'description' => $row['notes'] ?? 'Initial Stock Balance',
                        'created_at' => now(),
                    ]);

                    // 6. Insert ke food_inventory_cost_histories
                    DB::table('food_inventory_cost_histories')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'warehouse_id' => $warehouse->id,
                        'date' => now(),
                        'old_cost' => 0,
                        'new_cost' => $cost_small,
                        'mac' => $cost_small,
                        'type' => 'initial_balance',
                        'reference_type' => 'initial_balance',
                        'reference_id' => 0,
                        'created_at' => now(),
                    ]);

                    $this->successCount++;
                } catch (\Exception $e) {
                    $this->errors[] = [
                        'row' => $index + 2,
                        'error' => $e->getMessage()
                    ];
                    $this->errorCount++;
                    \Log::error('Import Saldo Awal Stock Error: ' . $e->getMessage(), [
                        'row' => $index + 2,
                        'data' => $row
                    ]);
                }
            }

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'import',
                'module' => 'stock_balance',
                'description' => 'Import Initial Stock Balance',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => null,
                'new_data' => json_encode([
                    'success_count' => $this->successCount,
                    'error_count' => $this->errorCount
                ]),
                'created_at' => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getErrorCount()
    {
        return $this->errorCount;
    }

    public function sheets(): array
    {
        return [
            'StockBalance' => $this,
        ];
    }
} 