<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Warehouse;
use App\Models\FoodStockBalance;
use App\Models\ActivityLog;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FoodStockBalanceImport implements ToCollection, WithHeadingRow, WithValidation, WithMultipleSheets
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
                    if (empty($row['sku']) || empty($row['name']) || empty($row['small_unit']) || 
                        empty($row['warehouse']) || !isset($row['quantity']) || !isset($row['cost'])) {
                        throw new \Exception('Semua field wajib diisi kecuali Notes');
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
                        throw new \Exception('Item tidak ditemukan atau tidak aktif');
                    }

                    // Cek warehouse
                    $warehouse = Warehouse::where('name', $row['warehouse'])
                        ->where('status', 'active')
                        ->first();
                    if (!$warehouse) {
                        throw new \Exception('Gudang tidak ditemukan atau tidak aktif');
                    }

                    // Validasi quantity dan cost
                    if (!is_numeric($row['quantity'])) {
                        throw new \Exception('Quantity harus berupa angka');
                    }
                    if (!is_numeric($row['cost']) || $row['cost'] < 0) {
                        throw new \Exception('Cost harus berupa angka positif');
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

    public function rules(): array
    {
        return [
            'sku' => 'required|string',
            'name' => 'required|string',
            'small_unit' => 'required|string',
            'warehouse' => 'required|string',
            'quantity' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    protected function validateRow($row)
    {
        $validator = \Validator::make($row->toArray(), $this->rules());
        if ($validator->fails()) {
            throw new \Exception(implode(', ', $validator->errors()->all()));
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