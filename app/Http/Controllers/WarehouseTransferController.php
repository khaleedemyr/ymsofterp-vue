<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferItem;
use App\Models\FoodInventoryStock;
use App\Models\FoodInventoryItem;
use App\Models\FoodInventoryCard;
use Illuminate\Support\Facades\Log;

class WarehouseTransferController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'transfer_date' => 'required|date',
            'warehouse_from_id' => 'required|integer|different:warehouse_to_id',
            'warehouse_to_id' => 'required|integer|different:warehouse_from_id',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.note' => 'nullable|string',
            'serial_items' => 'nullable|array',
            'serial_items.*.serial_id' => 'required|integer',
            'serial_items.*.serial_number' => 'required|string',
            'serial_items.*.item_id' => 'required|integer',
            'serial_items.*.qty' => 'required|numeric',
            'serial_items.*.qty_small' => 'required|numeric',
            'serial_items.*.unit_id' => 'nullable|integer',
        ]);

        $hasItems = !empty($validated['items']);
        $hasSerials = !empty($validated['serial_items']);
        if (!$hasItems && !$hasSerials) {
            return redirect()->back()->with('error', 'Minimal harus ada 1 item (mode qty) atau 1 nomor seri (mode serial).');
        }

        $transferMode = 'normal';
        if ($hasItems && $hasSerials) {
            $transferMode = 'mixed';
        } elseif ($hasSerials) {
            $transferMode = 'serial';
        }

        DB::beginTransaction();
        try {
            $transferNumber = $this->generateWarehouseTransferNumber($validated['transfer_date']);

            $transfer = WarehouseTransfer::create([
                'transfer_number' => $transferNumber,
                'transfer_date' => $validated['transfer_date'],
                'warehouse_from_id' => $validated['warehouse_from_id'],
                'warehouse_to_id' => $validated['warehouse_to_id'],
                'notes' => $validated['notes'] ?? null,
                'transfer_mode' => $transferMode,
                'created_by' => Auth::id(),
            ]);

            if ($hasItems) {
                $this->processNormalItemsForWT($transfer, $validated);
            }
            if ($hasSerials) {
                $this->processSerialItemsForWT($transfer, $validated);
            }

            DB::commit();
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'warehouse_transfer',
                'description' => 'Membuat transfer gudang: ' . $transfer->transfer_number . ' (' . $transferMode . ')',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $transfer->toArray(),
            ]);

            return redirect()->route('warehouse-transfer.index')->with('success', 'Pindah Gudang berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    /**
     * Validasi nomor seri untuk pindah gudang (serial masih di gudang asal, belum keluar DO).
     */
    public function validateSerialForWT(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'warehouse_from_id' => 'required|integer',
        ]);

        $serialNumber = trim($request->serial_number);
        $warehouseFromId = (int) $request->warehouse_from_id;

        $serial = DB::table('inventory_item_serials as s')
            ->join('items as i', 's.item_id', '=', 'i.id')
            ->leftJoin('units as u', 's.unit_id', '=', 'u.id')
            ->leftJoin('units as ru', 'ru.id', '=', 's.repack_unit_id')
            ->where('s.serial_number', $serialNumber)
            ->select(
                's.id',
                's.serial_number',
                's.item_id',
                's.warehouse_id',
                's.is_out',
                's.is_transferred',
                's.repack_unit_id',
                's.repack_qty',
                's.unit_id',
                'i.name as item_name',
                'i.sku',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'u.name as unit_name',
                'ru.name as repack_unit_name'
            )
            ->first();

        if (!$serial) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri tidak ditemukan.']);
        }

        if ($serial->is_out) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri sudah keluar gudang (sudah dipakai DO / outlet).']);
        }

        if ($serial->is_transferred) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri sudah pernah di-transfer.']);
        }

        if ((int) $serial->warehouse_id !== $warehouseFromId) {
            $whName = DB::table('warehouses')->where('id', $serial->warehouse_id)->value('name') ?? $serial->warehouse_id;

            return response()->json([
                'valid' => false,
                'message' => "Serial berada di gudang {$whName}, bukan gudang asal yang dipilih.",
            ]);
        }

        $qty = 1.0;
        $unitId = $serial->unit_id;
        $unitName = $serial->unit_name ?? '';
        if ($serial->repack_qty && $serial->repack_unit_id) {
            $qty = (float) $serial->repack_qty;
            $unitId = $serial->repack_unit_id;
            $unitName = $serial->repack_unit_name ?? $unitName;
        }

        $smallConv = $serial->small_conversion_qty ?: 1;
        $mediumConv = $serial->medium_conversion_qty ?: 1;
        $qty_small = $qty;
        if ($unitId == $serial->medium_unit_id) {
            $qty_small = $qty * $smallConv;
        } elseif ($unitId == $serial->large_unit_id) {
            $qty_small = $qty * $smallConv * $mediumConv;
        }

        return response()->json([
            'valid' => true,
            'serial' => [
                'id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'item_id' => $serial->item_id,
                'item_name' => $serial->item_name,
                'sku' => $serial->sku,
                'qty' => $qty,
                'qty_small' => $qty_small,
                'unit_id' => $unitId,
                'unit_name' => $unitName,
            ],
        ]);
    }

    private function generateWarehouseTransferNumber(string $transferDate): string
    {
        $dateStr = date('Ymd', strtotime($transferDate));
        $existingNumbers = WarehouseTransfer::whereDate('transfer_date', $transferDate)
            ->where('transfer_number', 'like', 'WT-' . $dateStr . '-%')
            ->pluck('transfer_number')
            ->toArray();

        $maxNumber = 0;
        foreach ($existingNumbers as $number) {
            $parts = explode('-', $number);
            if (count($parts) === 3 && $parts[0] === 'WT' && $parts[1] === $dateStr) {
                $currentNumber = (int) $parts[2];
                if ($currentNumber > $maxNumber) {
                    $maxNumber = $currentNumber;
                }
            }
        }

        $nextNumber = $maxNumber + 1;
        $transferNumber = 'WT-' . $dateStr . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        $attempts = 0;
        $maxAttempts = 5;
        while (WarehouseTransfer::where('transfer_number', $transferNumber)->exists() && $attempts < $maxAttempts) {
            $nextNumber++;
            $transferNumber = 'WT-' . $dateStr . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $attempts++;
        }

        if ($attempts >= $maxAttempts) {
            $transferNumber = 'WT-' . $dateStr . '-' . substr((string) time(), -4);
        }

        return $transferNumber;
    }

    private function processNormalItemsForWT(WarehouseTransfer $transfer, array $validated): void
    {
        $warehouseFromName = DB::table('warehouses')->where('id', $validated['warehouse_from_id'])->value('name');
        $warehouseToName = DB::table('warehouses')->where('id', $validated['warehouse_to_id'])->value('name');

        foreach ($validated['items'] as $item) {
                // Cari inventory_item_id dari food_inventory_items
                $inventoryItem = FoodInventoryItem::where('item_id', $item['item_id'])->first();

                if (!$inventoryItem) {
                    throw new \Exception('Inventory item not found for item_id: ' . $item['item_id']);
                }
                $inventory_item_id = $inventoryItem->id;

                // Ambil data konversi dari tabel items
                $itemMaster = \App\Models\Item::find($item['item_id']);
                $unit = $item['unit']; // Nama unit dari input user (misal: 'Pack', 'Gram', 'Kilogram')
                $qty_input = $item['qty'];
                $qty_small = 0;
                $qty_medium = 0;
                $qty_large = 0;

                // Ambil nama unit dari master
                $unitSmall = optional($itemMaster->smallUnit)->name;
                $unitMedium = optional($itemMaster->mediumUnit)->name;
                $unitLarge = optional($itemMaster->largeUnit)->name;
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

                if ($unit === $unitSmall) {
                    $qty_small = $qty_input;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                } elseif ($unit === $unitMedium) {
                    $qty_medium = $qty_input;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                } elseif ($unit === $unitLarge) {
                    $qty_large = $qty_input;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                } else {
                    // fallback: treat as small
                    $qty_small = $qty_input;
                }

                // Simpan detail transfer
                WarehouseTransferItem::create([
                    'warehouse_transfer_id' => $transfer->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['qty'],
                    'unit_id' => $inventoryItem->small_unit_id, // asumsikan unit small
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'note' => $item['note'] ?? null,
                ]);

                // Update stok di warehouse asal (kurangi)
                $stockFrom = FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $validated['warehouse_from_id'])->first();

                if (!$stockFrom) {
                    throw new \Exception('Stok tidak ditemukan di gudang asal');
                }
                $incomingCostPerSmall = $this->foodStockImpliedCostPerSmall($stockFrom);
                $stockFrom->qty_small -= $qty_small;
                $stockFrom->qty_medium -= $qty_medium;
                $stockFrom->qty_large -= $qty_large;
                $stockFrom->save();

                // Update stok di warehouse tujuan (tambah) dengan MAC jika ada penambahan stok
                $stockTo = FoodInventoryStock::firstOrCreate(
                    [
                        'inventory_item_id' => $inventory_item_id,
                        'warehouse_id' => $validated['warehouse_to_id']
                    ],
                    [
                        'qty_small' => 0,
                        'qty_medium' => 0,
                        'qty_large' => 0,
                        'value' => 0,
                        'last_cost_small' => 0,
                        'last_cost_medium' => 0,
                        'last_cost_large' => 0,
                    ]
                );
                $qty_lama = $stockTo->qty_small;
                $nilai_lama = $stockTo->value;
                $qty_baru = $qty_small;
                $nilai_baru = $qty_small * $incomingCostPerSmall;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $incomingCostPerSmall;
                // Update stok tujuan
                $stockTo->qty_small = $total_qty;
                $stockTo->qty_medium += $qty_medium;
                $stockTo->qty_large += $qty_large;
                $stockTo->last_cost_small = $mac;
                $stockTo->last_cost_medium = $stockFrom->last_cost_medium;
                $stockTo->last_cost_large = $stockFrom->last_cost_large;
                $stockTo->value = $total_nilai;
                $stockTo->save();

                // Insert kartu stok OUT (gudang asal)
                FoodInventoryCard::create([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['warehouse_from_id'],
                    'date' => $validated['transfer_date'],
                    'reference_type' => 'warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'out_qty_small' => $qty_small,
                    'out_qty_medium' => $qty_medium,
                    'out_qty_large' => $qty_large,
                    'cost_per_small' => $incomingCostPerSmall,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_out' => $qty_small * $incomingCostPerSmall,
                    'saldo_qty_small' => $stockFrom->qty_small,
                    'saldo_qty_medium' => $stockFrom->qty_medium,
                    'saldo_qty_large' => $stockFrom->qty_large,
                    'saldo_value' => $stockFrom->qty_small * $incomingCostPerSmall,
                    'description' => 'Transfer ke Gudang ' . $warehouseToName,
                ]);

                // Insert kartu stok IN (gudang tujuan)
                FoodInventoryCard::create([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['warehouse_to_id'],
                    'date' => $validated['transfer_date'],
                    'reference_type' => 'warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'cost_per_small' => $incomingCostPerSmall,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_in' => $qty_small * $incomingCostPerSmall,
                    'saldo_qty_small' => $stockTo->qty_small,
                    'saldo_qty_medium' => $stockTo->qty_medium,
                    'saldo_qty_large' => $stockTo->qty_large,
                    'saldo_value' => $total_nilai,
                    'description' => 'Transfer dari Gudang ' . $warehouseFromName,
                ]);
                // Insert cost history untuk warehouse tujuan pakai MAC
                $old_cost = $qty_lama > 0 ? ((float) $nilai_lama / (float) $qty_lama) : 0;
                \DB::table('food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['warehouse_to_id'],
                    'date' => $validated['transfer_date'],
                    'old_cost' => $old_cost,
                    'new_cost' => $incomingCostPerSmall,
                    'mac' => $mac,
                    'type' => 'warehouse_transfer',
                    'reference_type' => 'warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'created_at' => now(),
                ]);
        }
    }

    private function processSerialItemsForWT(WarehouseTransfer $transfer, array $validated): void
    {
        $warehouseFromName = DB::table('warehouses')->where('id', $validated['warehouse_from_id'])->value('name');
        $warehouseToName = DB::table('warehouses')->where('id', $validated['warehouse_to_id'])->value('name');
        $userId = Auth::id();
        $now = now();

        foreach ($validated['serial_items'] as $si) {
            $itemMaster = DB::table('items')->where('id', $si['item_id'])->first();
            if (!$itemMaster) {
                throw new \Exception('Item tidak ditemukan: ' . $si['item_id']);
            }

            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
            $qty_small = (float) $si['qty_small'];
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;

            $inventoryItem = FoodInventoryItem::where('item_id', $si['item_id'])->first();
            if (!$inventoryItem) {
                throw new \Exception('Inventory item not found for item_id: ' . $si['item_id']);
            }
            $inventory_item_id = $inventoryItem->id;

            $stockFrom = FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                ->where('warehouse_id', $validated['warehouse_from_id'])
                ->lockForUpdate()
                ->first();

            if (!$stockFrom) {
                throw new \Exception('Stok tidak ditemukan di gudang asal untuk serial ' . $si['serial_number']);
            }

            $incomingCostPerSmall = $this->foodStockImpliedCostPerSmall($stockFrom);

            DB::table('warehouse_transfer_serial_items')->insert([
                'warehouse_transfer_id' => $transfer->id,
                'serial_id' => $si['serial_id'],
                'serial_number' => $si['serial_number'],
                'item_id' => $si['item_id'],
                'unit_id' => $si['unit_id'] ?? null,
                'qty' => $si['qty'],
                'qty_small' => $qty_small,
                'qty_medium' => $qty_medium,
                'qty_large' => $qty_large,
                'cost_small' => $incomingCostPerSmall,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $stockFrom->qty_small -= $qty_small;
            $stockFrom->qty_medium -= $qty_medium;
            $stockFrom->qty_large -= $qty_large;
            $stockFrom->save();

            $stockTo = FoodInventoryStock::firstOrCreate(
                [
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['warehouse_to_id'],
                ],
                [
                    'qty_small' => 0,
                    'qty_medium' => 0,
                    'qty_large' => 0,
                    'value' => 0,
                    'last_cost_small' => 0,
                    'last_cost_medium' => 0,
                    'last_cost_large' => 0,
                ]
            );

            $qty_lama = $stockTo->qty_small;
            $nilai_lama = $stockTo->value;
            $nilai_baru = $qty_small * $incomingCostPerSmall;
            $total_qty = $qty_lama + $qty_small;
            $total_nilai = $nilai_lama + $nilai_baru;
            $mac = $total_qty > 0 ? $total_nilai / $total_qty : $incomingCostPerSmall;

            $stockTo->qty_small = $total_qty;
            $stockTo->qty_medium += $qty_medium;
            $stockTo->qty_large += $qty_large;
            $stockTo->last_cost_small = $mac;
            $stockTo->last_cost_medium = $stockFrom->last_cost_medium;
            $stockTo->last_cost_large = $stockFrom->last_cost_large;
            $stockTo->value = $total_nilai;
            $stockTo->save();

            FoodInventoryCard::create([
                'inventory_item_id' => $inventory_item_id,
                'warehouse_id' => $validated['warehouse_from_id'],
                'date' => $validated['transfer_date'],
                'reference_type' => 'warehouse_transfer',
                'reference_id' => $transfer->id,
                'out_qty_small' => $qty_small,
                'out_qty_medium' => $qty_medium,
                'out_qty_large' => $qty_large,
                'cost_per_small' => $incomingCostPerSmall,
                'cost_per_medium' => $stockFrom->last_cost_medium,
                'cost_per_large' => $stockFrom->last_cost_large,
                'value_out' => $qty_small * $incomingCostPerSmall,
                'saldo_qty_small' => $stockFrom->qty_small,
                'saldo_qty_medium' => $stockFrom->qty_medium,
                'saldo_qty_large' => $stockFrom->qty_large,
                'saldo_value' => $stockFrom->qty_small * $incomingCostPerSmall,
                'description' => 'WT Serial OUT: ' . $si['serial_number'] . ' → ' . $warehouseToName,
            ]);

            FoodInventoryCard::create([
                'inventory_item_id' => $inventory_item_id,
                'warehouse_id' => $validated['warehouse_to_id'],
                'date' => $validated['transfer_date'],
                'reference_type' => 'warehouse_transfer',
                'reference_id' => $transfer->id,
                'in_qty_small' => $qty_small,
                'in_qty_medium' => $qty_medium,
                'in_qty_large' => $qty_large,
                'cost_per_small' => $incomingCostPerSmall,
                'cost_per_medium' => $stockFrom->last_cost_medium,
                'cost_per_large' => $stockFrom->last_cost_large,
                'value_in' => $qty_small * $incomingCostPerSmall,
                'saldo_qty_small' => $stockTo->qty_small,
                'saldo_qty_medium' => $stockTo->qty_medium,
                'saldo_qty_large' => $stockTo->qty_large,
                'saldo_value' => $total_nilai,
                'description' => 'WT Serial IN: ' . $si['serial_number'] . ' dari ' . $warehouseFromName,
            ]);

            $old_cost = $qty_lama > 0 ? ((float) $nilai_lama / (float) $qty_lama) : 0;
            DB::table('food_inventory_cost_histories')->insert([
                'inventory_item_id' => $inventory_item_id,
                'warehouse_id' => $validated['warehouse_to_id'],
                'date' => $validated['transfer_date'],
                'old_cost' => $old_cost,
                'new_cost' => $incomingCostPerSmall,
                'mac' => $mac,
                'type' => 'warehouse_transfer',
                'reference_type' => 'warehouse_transfer',
                'reference_id' => $transfer->id,
                'created_at' => $now,
            ]);

            DB::table('inventory_item_serials')->where('id', $si['serial_id'])->update([
                'warehouse_id' => $validated['warehouse_to_id'],
                'updated_at' => $now,
            ]);

            DB::table('inventory_serial_movements')->insert([
                'serial_id' => $si['serial_id'],
                'serial_number' => $si['serial_number'],
                'movement_type' => 'wt_out',
                'warehouse_transfer_id' => $transfer->id,
                'item_id' => $si['item_id'],
                'qty' => $si['qty'],
                'unit_id' => $si['unit_id'] ?? null,
                'moved_by' => $userId,
                'moved_at' => $now,
                'notes' => 'Pindah gudang OUT',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('inventory_serial_movements')->insert([
                'serial_id' => $si['serial_id'],
                'serial_number' => $si['serial_number'],
                'movement_type' => 'wt_in',
                'warehouse_transfer_id' => $transfer->id,
                'item_id' => $si['item_id'],
                'qty' => $si['qty'],
                'unit_id' => $si['unit_id'] ?? null,
                'moved_by' => $userId,
                'moved_at' => $now,
                'notes' => 'Pindah gudang IN',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Rollback stok & lokasi serial saat hapus transfer mode serial/mixed.
     */
    private function rollbackSerialItemsForWT(WarehouseTransfer $transfer): void
    {
        $serialItems = DB::table('warehouse_transfer_serial_items')
            ->where('warehouse_transfer_id', $transfer->id)
            ->get();

        foreach ($serialItems as $si) {
            $inventoryItem = FoodInventoryItem::where('item_id', $si->item_id)->first();
            if (!$inventoryItem) {
                continue;
            }

            $inventory_item_id = $inventoryItem->id;
            $qty_small = (float) ($si->qty_small ?? 0);
            $qty_medium = (float) ($si->qty_medium ?? 0);
            $qty_large = (float) ($si->qty_large ?? 0);

            $stockFrom = FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                ->where('warehouse_id', $transfer->warehouse_from_id)
                ->first();
            if ($stockFrom) {
                $stockFrom->qty_small += $qty_small;
                $stockFrom->qty_medium += $qty_medium;
                $stockFrom->qty_large += $qty_large;
                $stockFrom->value = ($stockFrom->qty_small * $stockFrom->last_cost_small)
                    + ($stockFrom->qty_medium * $stockFrom->last_cost_medium)
                    + ($stockFrom->qty_large * $stockFrom->last_cost_large);
                $stockFrom->save();
            }

            $stockTo = FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                ->where('warehouse_id', $transfer->warehouse_to_id)
                ->first();
            if ($stockTo) {
                $stockTo->qty_small -= $qty_small;
                $stockTo->qty_medium -= $qty_medium;
                $stockTo->qty_large -= $qty_large;
                $stockTo->value = ($stockTo->qty_small * $stockTo->last_cost_small)
                    + ($stockTo->qty_medium * $stockTo->last_cost_medium)
                    + ($stockTo->qty_large * $stockTo->last_cost_large);
                if (($stockTo->qty_small + $stockTo->qty_medium + $stockTo->qty_large) <= 0) {
                    $stockTo->last_cost_small = 0;
                    $stockTo->last_cost_medium = 0;
                    $stockTo->last_cost_large = 0;
                    $stockTo->value = 0;
                }
                $stockTo->save();
            }

            DB::table('inventory_item_serials')->where('id', $si->serial_id)->update([
                'warehouse_id' => $transfer->warehouse_from_id,
                'updated_at' => now(),
            ]);
        }
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Check if user can delete warehouse transfer
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        
        $query = WarehouseTransfer::with(['warehouseFrom', 'warehouseTo', 'creator']);

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('transfer_number', 'like', "%$search%")
                  ->orWhereHas('warehouseFrom', function($q2) use ($search) {
                      $q2->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('warehouseTo', function($q2) use ($search) {
                      $q2->where('name', 'like', "%$search%");
                  })
                  ->orWhere('notes', 'like', "%$search%")
                  ->orWhere('status', 'like', "%$search%")
                  ->orWhereHas('creator', function($q2) use ($search) {
                      $q2->where('nama_lengkap', 'like', "%$search%");
                  });
            });
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from) {
            $query->whereDate('transfer_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('transfer_date', '<=', $request->to);
        }
        $transfers = $query->orderByDesc('created_at')->paginate(10)->withQueryString();
        // Hitung total_items untuk setiap transfer
        $transfers->getCollection()->transform(function ($tr) {
            $normalCount = $tr->items()->count();
            $serialCount = DB::table('warehouse_transfer_serial_items')
                ->where('warehouse_transfer_id', $tr->id)
                ->count();
            $tr->total_items = $serialCount > 0 ? $serialCount : $normalCount;

            return $tr;
        });
        return inertia('WarehouseTransfer/Index', [
            'transfers' => $transfers,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
            'canDelete' => $canDelete,
        ]);
    }

    public function create()
    {
        $warehouses = \App\Models\Warehouse::all();
        return inertia('WarehouseTransfer/Form', [
            'warehouses' => $warehouses,
        ]);
    }

    public function show($id)
    {
        $transfer = WarehouseTransfer::with(['items.item', 'items.unit', 'warehouseFrom', 'warehouseTo', 'creator'])->findOrFail($id);

        $serialItems = DB::table('warehouse_transfer_serial_items as si')
            ->leftJoin('items as i', 'si.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
            ->where('si.warehouse_transfer_id', $id)
            ->select('si.*', 'i.name as item_name', 'u.name as unit_name')
            ->orderBy('si.serial_number')
            ->get();

        return inertia('WarehouseTransfer/Show', [
            'transfer' => $transfer,
            'serialItems' => $serialItems,
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            
            // Cek authorization: hanya superadmin atau user dengan division_id=11
            $isSuperAdmin = $user && $user->id_role === '5af56935b011a';
            $isWarehouseDivision11 = $user && $user->division_id == 11;
            
            if (!$isSuperAdmin && !$isWarehouseDivision11) {
                return redirect()->back()->with('error', 'Anda tidak memiliki akses untuk menghapus Warehouse Transfer. Hanya superadmin atau user dengan division warehouse yang dapat menghapus.');
            }
            
            $transfer = WarehouseTransfer::with('items')->findOrFail($id);

            $this->rollbackSerialItemsForWT($transfer);

            foreach ($transfer->items as $item) {
                $inventoryItem = FoodInventoryItem::where('item_id', $item->item_id)->first();
                if (!$inventoryItem) continue;
                $inventory_item_id = $inventoryItem->id;
                $qty_small = $item->qty_small ?? 0;
                $qty_medium = $item->qty_medium ?? 0;
                $qty_large = $item->qty_large ?? 0;
                // Tambah stok kembali ke warehouse asal
                $stockFrom = FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $transfer->warehouse_from_id)->first();
                if ($stockFrom) {
                    $stockFrom->qty_small += $qty_small;
                    $stockFrom->qty_medium += $qty_medium;
                    $stockFrom->qty_large += $qty_large;
                    $stockFrom->value = ($stockFrom->qty_small * $stockFrom->last_cost_small)
                        + ($stockFrom->qty_medium * $stockFrom->last_cost_medium)
                        + ($stockFrom->qty_large * $stockFrom->last_cost_large);
                    if (($stockFrom->qty_small + $stockFrom->qty_medium + $stockFrom->qty_large) == 0) {
                        $stockFrom->last_cost_small = 0;
                        $stockFrom->last_cost_medium = 0;
                        $stockFrom->last_cost_large = 0;
                    }
                    $stockFrom->save();
                }
                // Kurangi stok dari warehouse tujuan
                $stockTo = FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $transfer->warehouse_to_id)->first();
                if ($stockTo) {
                    $stockTo->qty_small -= $qty_small;
                    $stockTo->qty_medium -= $qty_medium;
                    $stockTo->qty_large -= $qty_large;
                    $stockTo->value = ($stockTo->qty_small * $stockTo->last_cost_small)
                        + ($stockTo->qty_medium * $stockTo->last_cost_medium)
                        + ($stockTo->qty_large * $stockTo->last_cost_large);
                    if (($stockTo->qty_small + $stockTo->qty_medium + $stockTo->qty_large) == 0) {
                        $stockTo->last_cost_small = 0;
                        $stockTo->last_cost_medium = 0;
                        $stockTo->last_cost_large = 0;
                    }
                    $stockTo->save();
                }
            }

            FoodInventoryCard::where('reference_type', 'warehouse_transfer')
                ->where('reference_id', $transfer->id)
                ->delete();

            DB::table('food_inventory_cost_histories')
                ->where('reference_type', 'warehouse_transfer')
                ->where('reference_id', $transfer->id)
                ->delete();

            DB::table('warehouse_transfer_serial_items')->where('warehouse_transfer_id', $transfer->id)->delete();
            DB::table('inventory_serial_movements')->where('warehouse_transfer_id', $transfer->id)->delete();

            $transfer->items()->delete();
            $transfer->delete();
            DB::commit();
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'warehouse_transfer',
                'description' => 'Menghapus transfer gudang: ' . $transfer->transfer_number,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => $transfer->toArray(),
                'new_data' => null,
            ]);
            return redirect()->route('warehouse-transfer.index')->with('success', 'Data berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'transfer_date' => 'required|date',
            'warehouse_from_id' => 'required|integer|different:warehouse_to_id',
            'warehouse_to_id' => 'required|integer|different:warehouse_from_id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.note' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $transfer = WarehouseTransfer::with('items')->findOrFail($id);
            // Rollback stok & kartu stok lama
            foreach ($transfer->items as $item) {
                $inventoryItem = FoodInventoryItem::where('item_id', $item->item_id)->first();
                if (!$inventoryItem) continue;
                $inventory_item_id = $inventoryItem->id;
                $qty_small = $item->qty_small ?? 0;
                $qty_medium = $item->qty_medium ?? 0;
                $qty_large = $item->qty_large ?? 0;
                // Rollback ke warehouse asal
                $stockFrom = FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $transfer->warehouse_from_id)->first();
                if ($stockFrom) {
                    $stockFrom->qty_small += $qty_small;
                    $stockFrom->qty_medium += $qty_medium;
                    $stockFrom->qty_large += $qty_large;
                    $stockFrom->value = ($stockFrom->qty_small * $stockFrom->last_cost_small)
                        + ($stockFrom->qty_medium * $stockFrom->last_cost_medium)
                        + ($stockFrom->qty_large * $stockFrom->last_cost_large);
                    // Jika qty semua 0, reset cost
                    if (($stockFrom->qty_small + $stockFrom->qty_medium + $stockFrom->qty_large) == 0) {
                        $stockFrom->last_cost_small = 0;
                        $stockFrom->last_cost_medium = 0;
                        $stockFrom->last_cost_large = 0;
                    }
                    $stockFrom->save();
                }
                // Rollback dari warehouse tujuan
                $stockTo = FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $transfer->warehouse_to_id)->first();
                if ($stockTo) {
                    $stockTo->qty_small -= $qty_small;
                    $stockTo->qty_medium -= $qty_medium;
                    $stockTo->qty_large -= $qty_large;
                    $stockTo->value = ($stockTo->qty_small * $stockTo->last_cost_small)
                        + ($stockTo->qty_medium * $stockTo->last_cost_medium)
                        + ($stockTo->qty_large * $stockTo->last_cost_large);
                    if (($stockTo->qty_small + $stockTo->qty_medium + $stockTo->qty_large) == 0) {
                        $stockTo->last_cost_small = 0;
                        $stockTo->last_cost_medium = 0;
                        $stockTo->last_cost_large = 0;
                    }
                    $stockTo->save();
                }
                // Hapus kartu stok
                FoodInventoryCard::where('reference_type', 'warehouse_transfer')
                    ->where('reference_id', $transfer->id)
                    ->where('inventory_item_id', $inventory_item_id)
                    ->delete();
            }
            // Hapus detail lama
            $transfer->items()->delete();
            // Update header
            $transfer->update([
                'transfer_date' => $validated['transfer_date'],
                'warehouse_from_id' => $validated['warehouse_from_id'],
                'warehouse_to_id' => $validated['warehouse_to_id'],
                'notes' => $validated['notes'] ?? null,
            ]);
            // Proses ulang transfer baru (reuse logic dari store)
            $warehouseFromName = \DB::table('warehouses')->where('id', $validated['warehouse_from_id'])->value('name');
            $warehouseToName = \DB::table('warehouses')->where('id', $validated['warehouse_to_id'])->value('name');
            foreach ($validated['items'] as $item) {
                $inventoryItem = FoodInventoryItem::where('item_id', $item['item_id'])->first();
                if (!$inventoryItem) throw new \Exception('Inventory item not found for item_id: ' . $item['item_id']);
                $inventory_item_id = $inventoryItem->id;
                $itemMaster = \App\Models\Item::find($item['item_id']);
                $unit = $item['unit'];
                $qty_input = $item['qty'];
                $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                $unitSmall = optional($itemMaster->smallUnit)->name;
                $unitMedium = optional($itemMaster->mediumUnit)->name;
                $unitLarge = optional($itemMaster->largeUnit)->name;
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                if ($unit === $unitSmall) {
                    $qty_small = $qty_input;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                } elseif ($unit === $unitMedium) {
                    $qty_medium = $qty_input;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                } elseif ($unit === $unitLarge) {
                    $qty_large = $qty_input;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                } else {
                    $qty_small = $qty_input;
                }
                WarehouseTransferItem::create([
                    'warehouse_transfer_id' => $transfer->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['qty'],
                    'unit_id' => $inventoryItem->small_unit_id,
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'note' => $item['note'] ?? null,
                ]);
                // Update stok asal (kurangi)
                $stockFrom = FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $validated['warehouse_from_id'])->first();
                if (!$stockFrom) {
                    throw new \Exception('Stok tidak ditemukan di gudang asal');
                }
                $incomingCostPerSmall = $this->foodStockImpliedCostPerSmall($stockFrom);
                $stockFrom->qty_small -= $qty_small;
                $stockFrom->qty_medium -= $qty_medium;
                $stockFrom->qty_large -= $qty_large;
                $stockFrom->save();
                // Update stok tujuan (tambah) — sama dengan store: MAC dari nilai/qty, bukan jumlah tier
                $stockTo = FoodInventoryStock::firstOrCreate(
                    [
                        'inventory_item_id' => $inventory_item_id,
                        'warehouse_id' => $validated['warehouse_to_id']
                    ],
                    [
                        'qty_small' => 0,
                        'qty_medium' => 0,
                        'qty_large' => 0,
                        'value' => 0,
                        'last_cost_small' => 0,
                        'last_cost_medium' => 0,
                        'last_cost_large' => 0,
                    ]
                );
                $qty_lama = $stockTo->qty_small;
                $nilai_lama = $stockTo->value;
                $qty_baru = $qty_small;
                $nilai_baru = $qty_small * $incomingCostPerSmall;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $incomingCostPerSmall;
                $stockTo->qty_small = $total_qty;
                $stockTo->qty_medium += $qty_medium;
                $stockTo->qty_large += $qty_large;
                $stockTo->last_cost_small = $mac;
                $stockTo->last_cost_medium = $stockFrom->last_cost_medium;
                $stockTo->last_cost_large = $stockFrom->last_cost_large;
                $stockTo->value = $total_nilai;
                $stockTo->save();
                // Insert kartu stok OUT (asal)
                FoodInventoryCard::create([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['warehouse_from_id'],
                    'date' => $validated['transfer_date'],
                    'reference_type' => 'warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'out_qty_small' => $qty_small,
                    'out_qty_medium' => $qty_medium,
                    'out_qty_large' => $qty_large,
                    'cost_per_small' => $incomingCostPerSmall,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_out' => $qty_small * $incomingCostPerSmall,
                    'saldo_qty_small' => $stockFrom->qty_small,
                    'saldo_qty_medium' => $stockFrom->qty_medium,
                    'saldo_qty_large' => $stockFrom->qty_large,
                    'saldo_value' => $stockFrom->qty_small * $incomingCostPerSmall,
                    'description' => 'Transfer ke Gudang ' . $warehouseToName,
                ]);
                // Insert kartu stok IN (tujuan)
                FoodInventoryCard::create([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['warehouse_to_id'],
                    'date' => $validated['transfer_date'],
                    'reference_type' => 'warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'cost_per_small' => $incomingCostPerSmall,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_in' => $qty_small * $incomingCostPerSmall,
                    'saldo_qty_small' => $stockTo->qty_small,
                    'saldo_qty_medium' => $stockTo->qty_medium,
                    'saldo_qty_large' => $stockTo->qty_large,
                    'saldo_value' => $total_nilai,
                    'description' => 'Transfer dari Gudang ' . $warehouseFromName,
                ]);
                // Insert cost history untuk warehouse tujuan
                $old_cost = $qty_lama > 0 ? ((float) $nilai_lama / (float) $qty_lama) : 0;
                \DB::table('food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['warehouse_to_id'],
                    'date' => $validated['transfer_date'],
                    'old_cost' => $old_cost,
                    'new_cost' => $incomingCostPerSmall,
                    'mac' => $mac,
                    'type' => 'warehouse_transfer',
                    'reference_type' => 'warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'created_at' => now(),
                ]);
            }
            DB::commit();
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'warehouse_transfer',
                'description' => 'Update transfer gudang: ' . $transfer->transfer_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $transfer->fresh()->toArray(),
            ]);
            return redirect()->route('warehouse-transfer.index')->with('success', 'Transfer berhasil diupdate!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal update: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $transfer = WarehouseTransfer::with(['items', 'warehouseFrom', 'warehouseTo'])->findOrFail($id);

        if (in_array($transfer->transfer_mode ?? 'normal', ['serial', 'mixed'], true)) {
            return redirect()->route('warehouse-transfer.show', $id)
                ->with('error', 'Transfer mode serial tidak dapat diedit. Hapus lalu buat ulang jika perlu.');
        }

        $warehouses = \App\Models\Warehouse::all();
        $items = \App\Models\Item::all();
        // Format items untuk prefill form
        $formItems = $transfer->items->map(function($item) {
            return [
                'item_id' => $item->item_id,
                'item_name' => $item->item->name ?? '',
                'qty' => $item->quantity,
                'unit' => $item->unit->name ?? '',
                'note' => $item->note,
                'suggestions' => [],
                'showDropdown' => false,
                'loading' => false,
                'highlightedIndex' => -1,
                'available_units' => [],
                'selected_unit' => $item->unit->name ?? '',
                '_rowKey' => now()->timestamp . '-' . rand(1000,9999),
            ];
        });
        return inertia('WarehouseTransfer/Form', [
            'warehouses' => $warehouses,
            'items' => $items,
            'editData' => [
                'id' => $transfer->id,
                'transfer_date' => $transfer->transfer_date,
                'warehouse_from_id' => $transfer->warehouse_from_id,
                'warehouse_to_id' => $transfer->warehouse_to_id,
                'notes' => $transfer->notes,
                'items' => $formItems,
            ]
        ]);
    }

    public function apiIndex(Request $request)
    {
        $query = WarehouseTransfer::with(['warehouseFrom', 'warehouseTo', 'creator']);

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transfer_number', 'like', "%$search%")
                    ->orWhereHas('warehouseFrom', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%$search%");
                    })
                    ->orWhereHas('warehouseTo', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%$search%");
                    })
                    ->orWhere('notes', 'like', "%$search%")
                    ->orWhere('status', 'like', "%$search%")
                    ->orWhereHas('creator', function ($q2) use ($search) {
                        $q2->where('nama_lengkap', 'like', "%$search%");
                    });
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->from) {
            $query->whereDate('transfer_date', '>=', $request->from);
        }

        if ($request->to) {
            $query->whereDate('transfer_date', '<=', $request->to);
        }

        $perPage = (int) $request->get('per_page', 20);
        $transfers = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();
        $transfers->getCollection()->transform(function ($tr) {
            $normalCount = $tr->items()->count();
            $serialCount = DB::table('warehouse_transfer_serial_items')
                ->where('warehouse_transfer_id', $tr->id)
                ->count();
            $tr->total_items = $serialCount > 0 ? $serialCount : $normalCount;

            return $tr;
        });

        return response()->json([
            'success' => true,
            'data' => $transfers,
        ]);
    }

    public function apiShow($id)
    {
        $transfer = WarehouseTransfer::with(['items.item', 'items.unit', 'warehouseFrom', 'warehouseTo', 'creator'])
            ->findOrFail($id);

        $serialItems = DB::table('warehouse_transfer_serial_items as si')
            ->leftJoin('items as i', 'si.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
            ->where('si.warehouse_transfer_id', $id)
            ->select('si.*', 'i.name as item_name', 'u.name as unit_name')
            ->orderBy('si.serial_number')
            ->get();

        return response()->json([
            'success' => true,
            'transfer' => $transfer,
            'serial_items' => $serialItems,
        ]);
    }

    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'transfer_date' => 'required|date',
            'warehouse_from_id' => 'required|integer|different:warehouse_to_id',
            'warehouse_to_id' => 'required|integer|different:warehouse_from_id',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.note' => 'nullable|string',
            'serial_items' => 'nullable|array',
            'serial_items.*.serial_id' => 'required|integer',
            'serial_items.*.serial_number' => 'required|string',
            'serial_items.*.item_id' => 'required|integer',
            'serial_items.*.qty' => 'required|numeric',
            'serial_items.*.qty_small' => 'required|numeric',
            'serial_items.*.unit_id' => 'nullable|integer',
        ]);

        $hasItems = !empty($validated['items']);
        $hasSerials = !empty($validated['serial_items']);
        if (!$hasItems && !$hasSerials) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal harus ada 1 item (mode qty) atau 1 nomor seri (mode serial).',
            ], 422);
        }

        $transferMode = 'normal';
        if ($hasItems && $hasSerials) {
            $transferMode = 'mixed';
        } elseif ($hasSerials) {
            $transferMode = 'serial';
        }

        DB::beginTransaction();
        try {
            $transferNumber = $this->generateWarehouseTransferNumber($validated['transfer_date']);

            $transfer = WarehouseTransfer::create([
                'transfer_number' => $transferNumber,
                'transfer_date' => $validated['transfer_date'],
                'warehouse_from_id' => $validated['warehouse_from_id'],
                'warehouse_to_id' => $validated['warehouse_to_id'],
                'notes' => $validated['notes'] ?? null,
                'transfer_mode' => $transferMode,
                'created_by' => Auth::id(),
            ]);

            if ($hasItems) {
                $this->processNormalItemsForWT($transfer, $validated);
            }
            if ($hasSerials) {
                $this->processSerialItemsForWT($transfer, $validated);
            }

            DB::commit();
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'warehouse_transfer',
                'description' => 'Membuat transfer gudang: ' . $transfer->transfer_number . ' (' . $transferMode . ')',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $transfer->toArray(),
            ]);

            return response()->json([
                'success' => true,
                'transfer' => $transfer,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WarehouseTransfer apiStore error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Biaya per unit kecil dari saldo (value / qty_small) bila konsisten; fallback last_cost_small.
     * Dipakai transfer supaya insert cost history tidak mengikuti last_cost_small yang sudah ngaco vs nilai stok.
     */
    private function foodStockImpliedCostPerSmall($stock): float
    {
        $q = (float) ($stock->qty_small ?? 0);
        $v = (float) ($stock->value ?? 0);
        if ($q > 0 && $v >= 0) {
            $implied = $v / $q;
            if (is_finite($implied) && $implied > 0) {
                return $implied;
            }
        }

        return max(0.0, (float) ($stock->last_cost_small ?? 0));
    }
} 