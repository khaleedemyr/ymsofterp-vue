<?php

namespace App\Imports;

use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\Outlet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OutletStockBalanceImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    protected $errors = [];
    protected $successCount = 0;
    protected $errorCount = 0;

    public function collection(Collection $rows)
    {
        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                if (collect($row)->filter()->isEmpty()) {
                    continue;
                }

                try {
                    $this->importRow($row);
                    $this->successCount++;
                } catch (\Exception $e) {
                    $this->errors[] = [
                        'row' => $index + 2,
                        'error' => $e->getMessage(),
                    ];
                    $this->errorCount++;
                    \Log::error('Import Saldo Awal Stock Outlet Error: '.$e->getMessage(), [
                        'row' => $index + 2,
                        'data' => $row,
                    ]);
                }
            }

            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'import',
                'module' => 'outlet_stock_balance',
                'description' => 'Import Initial Stock Balance Outlet',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => null,
                'new_data' => [
                    'success_count' => $this->successCount,
                    'error_count' => $this->errorCount,
                ],
                'created_at' => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function importRow($row): void
    {
        $missingFields = [];
        if (empty($row['sku']) || trim((string) $row['sku']) === '') {
            $missingFields[] = 'SKU';
        }
        if (empty($row['name']) || trim((string) $row['name']) === '') {
            $missingFields[] = 'Name';
        }
        if (empty($row['small_unit']) || trim((string) $row['small_unit']) === '') {
            $missingFields[] = 'Small Unit';
        }
        if (empty($row['outlet']) || trim((string) $row['outlet']) === '') {
            $missingFields[] = 'Outlet';
        }
        if (! isset($row['quantity']) || $row['quantity'] === '' || $row['quantity'] === null) {
            $missingFields[] = 'Quantity';
        }
        if (! isset($row['cost']) || $row['cost'] === '' || $row['cost'] === null) {
            $missingFields[] = 'Cost';
        }
        if (! empty($missingFields)) {
            throw new \Exception('Field wajib diisi: '.implode(', ', $missingFields));
        }
        if (empty($row['warehouse_outlet_id'])) {
            throw new \Exception('Warehouse Outlet ID tidak boleh kosong');
        }
        if (! is_numeric($row['quantity'])) {
            throw new \Exception("Quantity '{$row['quantity']}' harus berupa angka");
        }
        if (! is_numeric($row['cost'])) {
            throw new \Exception("Cost '{$row['cost']}' harus berupa angka");
        }
        if ((float) $row['cost'] < 0) {
            throw new \Exception("Cost '{$row['cost']}' tidak boleh negatif");
        }
        if ((float) $row['quantity'] < 0) {
            throw new \Exception("Quantity '{$row['quantity']}' tidak boleh negatif");
        }

        $item = Item::where('sku', $row['sku'])
            ->where('name', $row['name'])
            ->where('status', 'active')
            ->whereHas('category', function ($query) {
                $query->where('show_pos', '0');
            })
            ->first();
        if (! $item) {
            $inactiveItem = Item::where('sku', $row['sku'])->where('name', $row['name'])->first();
            if ($inactiveItem) {
                throw new \Exception("Item '{$row['name']}' (SKU: {$row['sku']}) ditemukan tapi status tidak aktif");
            }
            throw new \Exception("Item '{$row['name']}' (SKU: {$row['sku']}) tidak ditemukan dalam database");
        }

        $outlet = Outlet::where('nama_outlet', $row['outlet'])->where('status', 'A')->first();
        if (! $outlet) {
            $inactiveOutlet = Outlet::where('nama_outlet', $row['outlet'])->first();
            if ($inactiveOutlet) {
                throw new \Exception("Outlet '{$row['outlet']}' ditemukan tapi status tidak aktif");
            }
            throw new \Exception("Outlet '{$row['outlet']}' tidak ditemukan dalam database");
        }

        $warehouseOutletId = (int) $row['warehouse_outlet_id'];
        $outletId = (int) $outlet->id_outlet;
        $today = now()->toDateString();

        // Lock per item+outlet+warehouse supaya import concurrent tidak desync stok vs kartu.
        $lockKey = 'outlet_ib:'.$outletId.':'.$warehouseOutletId.':'.$item->id;
        $lock = DB::selectOne('SELECT GET_LOCK(?, 30) AS acquired', [$lockKey]);
        if (! $lock || (int) $lock->acquired !== 1) {
            throw new \Exception("Gagal mengunci stok untuk item '{$row['name']}' (outlet {$outletId}, WH {$warehouseOutletId}). Coba ulang.");
        }

        try {
            $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item->id)->first();
            if (! $inventoryItem) {
                $inventoryItemId = DB::table('outlet_food_inventory_items')->insertGetId([
                    'item_id' => $item->id,
                    'small_unit_id' => $item->small_unit_id,
                    'medium_unit_id' => $item->medium_unit_id,
                    'large_unit_id' => $item->large_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $inventoryItemId = (int) $inventoryItem->id;
            }

            $smallConv = $item->small_conversion_qty ?: 1;
            $mediumConv = $item->medium_conversion_qty ?: 1;
            $qtySmall = (float) $row['quantity'];
            $qtyMedium = $smallConv > 0 ? $qtySmall / $smallConv : 0;
            $qtyLarge = ($smallConv > 0 && $mediumConv > 0) ? $qtySmall / ($smallConv * $mediumConv) : 0;
            $costSmall = (float) $row['cost'];
            $costMedium = $costSmall * ($item->small_conversion_qty ?: 1);
            $costLarge = $costMedium * ($item->medium_conversion_qty ?: 1);
            $value = $qtySmall * $costSmall;

            $existingStock = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->lockForUpdate()
                ->first();

            if ($existingStock) {
                DB::table('outlet_food_inventory_stocks')->where('id', $existingStock->id)->update([
                    'qty_small' => $qtySmall,
                    'qty_medium' => $qtyMedium,
                    'qty_large' => $qtyLarge,
                    'value' => $value,
                    'last_cost_small' => $costSmall,
                    'last_cost_medium' => $costMedium,
                    'last_cost_large' => $costLarge,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('outlet_food_inventory_stocks')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $outletId,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'qty_small' => $qtySmall,
                    'qty_medium' => $qtyMedium,
                    'qty_large' => $qtyLarge,
                    'value' => $value,
                    'last_cost_small' => $costSmall,
                    'last_cost_medium' => $costMedium,
                    'last_cost_large' => $costLarge,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $cardPayload = [
                'inventory_item_id' => $inventoryItemId,
                'id_outlet' => $outletId,
                'warehouse_outlet_id' => $warehouseOutletId,
                'date' => $today,
                'reference_type' => 'initial_balance',
                'reference_id' => 0,
                'in_qty_small' => $qtySmall,
                'in_qty_medium' => $qtyMedium,
                'in_qty_large' => $qtyLarge,
                'out_qty_small' => 0,
                'out_qty_medium' => 0,
                'out_qty_large' => 0,
                'cost_per_small' => $costSmall,
                'cost_per_medium' => $costMedium,
                'cost_per_large' => $costLarge,
                'value_in' => $value,
                'value_out' => 0,
                'saldo_qty_small' => $qtySmall,
                'saldo_qty_medium' => $qtyMedium,
                'saldo_qty_large' => $qtyLarge,
                'saldo_value' => $value,
                'description' => $row['notes'] ?? 'Initial Stock Balance Outlet',
                'updated_at' => now(),
            ];

            // Ambil kartu IB tertua hari ini; hapus duplikat concurrent bila ada.
            $existingCards = DB::table('outlet_food_inventory_cards')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->where('reference_type', 'initial_balance')
                ->whereDate('date', $today)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($existingCards->isNotEmpty()) {
                $keepId = (int) $existingCards->first()->id;
                $duplicateIds = $existingCards->pluck('id')->skip(1)->values()->all();
                if ($duplicateIds !== []) {
                    DB::table('outlet_food_inventory_cards')->whereIn('id', $duplicateIds)->delete();
                }
                DB::table('outlet_food_inventory_cards')->where('id', $keepId)->update($cardPayload);
            } else {
                $cardPayload['created_at'] = now();
                DB::table('outlet_food_inventory_cards')->insert($cardPayload);
            }

            $costHistoryPayload = [
                'inventory_item_id' => $inventoryItemId,
                'id_outlet' => $outletId,
                'warehouse_outlet_id' => $warehouseOutletId,
                'date' => $today,
                'old_cost' => 0,
                'new_cost' => $costSmall,
                'mac' => $costSmall,
                'type' => 'initial_balance',
                'reference_type' => 'initial_balance',
                'reference_id' => 0,
            ];

            $existingHistories = DB::table('outlet_food_inventory_cost_histories')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->where('reference_type', 'initial_balance')
                ->whereDate('date', $today)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($existingHistories->isNotEmpty()) {
                $keepHistId = (int) $existingHistories->first()->id;
                $dupHistIds = $existingHistories->pluck('id')->skip(1)->values()->all();
                if ($dupHistIds !== []) {
                    DB::table('outlet_food_inventory_cost_histories')->whereIn('id', $dupHistIds)->delete();
                }
                DB::table('outlet_food_inventory_cost_histories')->where('id', $keepHistId)->update($costHistoryPayload);
            } else {
                $costHistoryPayload['created_at'] = now();
                DB::table('outlet_food_inventory_cost_histories')->insert($costHistoryPayload);
            }
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$lockKey]);
        }
    }

    public function sheets(): array
    {
        return [
            'StockBalance' => $this,
        ];
    }

    public function getSuccessCount()
    {
        return $this->successCount ?? 0;
    }

    public function getErrorCount()
    {
        return $this->errorCount ?? 0;
    }

    public function getErrors()
    {
        return $this->errors ?? [];
    }
}
