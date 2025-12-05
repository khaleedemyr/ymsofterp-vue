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
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.note' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Generate transfer number using timestamp-based approach
            $dateStr = date('Ymd', strtotime($validated['transfer_date']));
            $transferNumber = null;
            
            // Get the highest existing number for this date
            $existingNumbers = WarehouseTransfer::whereDate('transfer_date', $validated['transfer_date'])
                ->where('transfer_number', 'like', 'WT-' . $dateStr . '-%')
                ->pluck('transfer_number')
                ->toArray();
            
            // Extract numbers and find the highest
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
            
            // Generate next number
            $nextNumber = $maxNumber + 1;
            $transferNumber = 'WT-' . $dateStr . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            
            // Double check if it exists (race condition protection)
            $attempts = 0;
            $maxAttempts = 5;
            while (WarehouseTransfer::where('transfer_number', $transferNumber)->exists() && $attempts < $maxAttempts) {
                $nextNumber++;
                $transferNumber = 'WT-' . $dateStr . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                $attempts++;
            }
            
            if ($attempts >= $maxAttempts) {
                // Fallback to timestamp-based unique number
                $timestamp = time();
                $transferNumber = 'WT-' . $dateStr . '-' . substr($timestamp, -4);
            }
            

            // Simpan header transfer
            $transfer = WarehouseTransfer::create([
                'transfer_number' => $transferNumber,
                'transfer_date' => $validated['transfer_date'],
                'warehouse_from_id' => $validated['warehouse_from_id'],
                'warehouse_to_id' => $validated['warehouse_to_id'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Ambil nama warehouse asal & tujuan
            $warehouseFromName = \DB::table('warehouses')->where('id', $validated['warehouse_from_id'])->value('name');
            $warehouseToName = \DB::table('warehouses')->where('id', $validated['warehouse_to_id'])->value('name');

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
                $nilai_baru = $qty_small * $stockFrom->last_cost_small;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $stockFrom->last_cost_small;
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
                    'cost_per_small' => $stockFrom->last_cost_small,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_out' => $qty_small * $stockFrom->last_cost_small,
                    'saldo_qty_small' => $stockFrom->qty_small,
                    'saldo_qty_medium' => $stockFrom->qty_medium,
                    'saldo_qty_large' => $stockFrom->qty_large,
                    'saldo_value' => $stockFrom->qty_small * $stockFrom->last_cost_small,
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
                    'cost_per_small' => $stockFrom->last_cost_small,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_in' => $qty_small * $stockFrom->last_cost_small,
                    'saldo_qty_small' => $stockTo->qty_small,
                    'saldo_qty_medium' => $stockTo->qty_medium,
                    'saldo_qty_large' => $stockTo->qty_large,
                    'saldo_value' => $stockTo->qty_small * $stockFrom->last_cost_small,
                    'description' => 'Transfer dari Gudang ' . $warehouseFromName,
                ]);
                // Insert cost history untuk warehouse tujuan pakai MAC
                $lastCostHistory = \DB::table('food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $validated['warehouse_to_id'])
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;
                \DB::table('food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['warehouse_to_id'],
                    'date' => $validated['transfer_date'],
                    'old_cost' => $old_cost,
                    'new_cost' => $stockFrom->last_cost_small,
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
                'activity_type' => 'create',
                'module' => 'warehouse_transfer',
                'description' => 'Membuat transfer gudang: ' . $transfer->transfer_number,
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

    public function index(Request $request)
    {
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
        $transfers->getCollection()->transform(function($tr) {
            $tr->total_items = $tr->items()->count();
            return $tr;
        });
        return inertia('WarehouseTransfer/Index', [
            'transfers' => $transfers,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
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
        return inertia('WarehouseTransfer/Show', [
            'transfer' => $transfer
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $transfer = WarehouseTransfer::with('items')->findOrFail($id);
            // Rollback stok dan kartu stok
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
                // Hapus kartu stok terkait transfer ini
                FoodInventoryCard::where('reference_type', 'warehouse_transfer')
                    ->where('reference_id', $transfer->id)
                    ->where('inventory_item_id', $inventory_item_id)
                    ->delete();
            }
            // Hapus detail dan header
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
                $stockFrom->qty_small -= $qty_small;
                $stockFrom->qty_medium -= $qty_medium;
                $stockFrom->qty_large -= $qty_large;
                $stockFrom->save();
                // Update stok tujuan (tambah)
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
                $stockTo->qty_small += $qty_small;
                $stockTo->qty_medium += $qty_medium;
                $stockTo->qty_large += $qty_large;
                $stockTo->last_cost_small = $stockFrom->last_cost_small;
                $stockTo->last_cost_medium = $stockFrom->last_cost_medium;
                $stockTo->last_cost_large = $stockFrom->last_cost_large;
                $stockTo->value = ($stockTo->qty_small * $stockTo->last_cost_small)
                    + ($stockTo->qty_medium * $stockTo->last_cost_medium)
                    + ($stockTo->qty_large * $stockTo->last_cost_large);
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
                    'cost_per_small' => $stockFrom->last_cost_small,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_out' => $qty_small * $stockFrom->last_cost_small,
                    'saldo_qty_small' => $stockFrom->qty_small,
                    'saldo_qty_medium' => $stockFrom->qty_medium,
                    'saldo_qty_large' => $stockFrom->qty_large,
                    'saldo_value' => $stockFrom->qty_small * $stockFrom->last_cost_small,
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
                    'cost_per_small' => $stockFrom->last_cost_small,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_in' => $qty_small * $stockFrom->last_cost_small,
                    'saldo_qty_small' => $stockTo->qty_small,
                    'saldo_qty_medium' => $stockTo->qty_medium,
                    'saldo_qty_large' => $stockTo->qty_large,
                    'saldo_value' => $stockTo->qty_small * $stockFrom->last_cost_small,
                    'description' => 'Transfer dari Gudang ' . $warehouseFromName,
                ]);
                // Insert cost history untuk warehouse tujuan
                $lastCostHistory = \DB::table('food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('warehouse_id', $validated['warehouse_to_id'])
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;
                \DB::table('food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $validated['warehouse_to_id'],
                    'date' => $validated['transfer_date'],
                    'old_cost' => $old_cost,
                    'new_cost' => $stockFrom->last_cost_small,
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
} 