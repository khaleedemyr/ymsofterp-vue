<?php

namespace App\Http\Controllers;

use App\Http\Traits\WritesActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\InternalWarehouseTransfer;
use App\Models\InternalWarehouseTransferItem;
use App\Support\OutletInventoryCostResolver;
use Illuminate\Support\Facades\Log;

class InternalWarehouseTransferController extends Controller
{
    use WritesActivityLogTrait;

    /**
     * Generate unique transfer number dengan retry mechanism untuk menghindari duplicate
     * Menggunakan lockForUpdate untuk mencegah race condition
     * Catatan: Fungsi ini dipanggil di dalam transaction, jadi tidak perlu nested transaction
     */
    private function generateTransferNumber($transferDate, $maxRetries = 10)
    {
        $dateStr = date('Ymd', strtotime($transferDate));
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Ambil nomor terakhir dengan lock untuk update (mencegah race condition)
                // lockForUpdate akan lock row sampai transaction commit/rollback
                $lastTransfer = InternalWarehouseTransfer::whereDate('transfer_date', $transferDate)
                    ->lockForUpdate()
                    ->orderBy('transfer_number', 'desc')
                    ->first();
                
                if ($lastTransfer) {
                    // Extract nomor urut dari transfer number terakhir
                    // Format: IWT-YYYYMMDD-XXXX
                    $lastNumber = (int) substr($lastTransfer->transfer_number, -4);
                    $nextNumber = $lastNumber + 1;
                } else {
                    $nextNumber = 1;
                }
                
                $transferNumber = 'IWT-' . $dateStr . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                
                // Double check: pastikan nomor belum ada (untuk safety)
                $exists = InternalWarehouseTransfer::where('transfer_number', $transferNumber)
                    ->lockForUpdate()
                    ->exists();
                
                if ($exists) {
                    // Jika nomor sudah ada, increment dan coba lagi
                    $nextNumber++;
                    $transferNumber = 'IWT-' . $dateStr . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                }
                
                return $transferNumber;
                
            } catch (\Illuminate\Database\QueryException $e) {
                // Jika error karena duplicate, retry dengan nomor berikutnya
                if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                    if ($attempt >= $maxRetries) {
                        Log::error('Gagal generate transfer number setelah ' . $maxRetries . ' percobaan (duplicate)', [
                            'error' => $e->getMessage(),
                            'transfer_date' => $transferDate,
                        ]);
                        throw new \Exception('Gagal generate nomor transfer karena duplicate. Silakan coba lagi.');
                    }
                    
                    // Tunggu sebentar sebelum retry (exponential backoff)
                    usleep(100000 * $attempt); // 100ms, 200ms, 300ms, etc.
                    continue;
                }
                
                // Jika error lain, throw langsung
                throw $e;
            } catch (\Exception $e) {
                if ($attempt >= $maxRetries) {
                    Log::error('Gagal generate transfer number setelah ' . $maxRetries . ' percobaan', [
                        'error' => $e->getMessage(),
                        'transfer_date' => $transferDate,
                    ]);
                    throw new \Exception('Gagal generate nomor transfer. Silakan coba lagi.');
                }
                
                // Tunggu sebentar sebelum retry
                usleep(100000 * $attempt);
            }
        }
        
        throw new \Exception('Gagal generate nomor transfer setelah ' . $maxRetries . ' percobaan.');
    }

    public function validateSerialForIWT(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'outlet_id' => 'required',
            'warehouse_outlet_from_id' => 'required|integer',
        ]);

        $serialNumber = trim($request->serial_number);
        $outletId = $request->outlet_id;
        $warehouseOutletFromId = $request->warehouse_outlet_from_id;

        $serial = DB::table('inventory_item_serials as s')
            ->join('items as i', 's.item_id', '=', 'i.id')
            ->leftJoin('units as u', 's.repack_unit_id', '=', 'u.id')
            ->where('s.serial_number', $serialNumber)
            ->select(
                's.id', 's.serial_number', 's.item_id', 's.qty_per_pack',
                's.repack_unit_id', 's.repack_qty',
                's.is_out', 's.is_received', 's.is_transferred',
                's.out_outlet_id', 's.out_warehouse_outlet_id',
                'i.name as item_name', 'i.sku',
                'i.small_unit_id', 'i.medium_unit_id', 'i.large_unit_id',
                'i.small_conversion_qty', 'i.medium_conversion_qty',
                'u.name as repack_unit_name'
            )
            ->first();

        if (!$serial) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri tidak ditemukan.']);
        }

        if (!$serial->is_out) {
            return response()->json(['valid' => false, 'message' => 'Serial belum di-dispatch (belum keluar via DO).']);
        }

        if (!$serial->is_received) {
            return response()->json(['valid' => false, 'message' => 'Serial belum diterima (belum di-GR).']);
        }

        if ($serial->is_transferred) {
            return response()->json(['valid' => false, 'message' => 'Serial sudah di-transfer sebelumnya.']);
        }

        if ($serial->out_outlet_id != $outletId) {
            $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $serial->out_outlet_id)->value('nama_outlet') ?? $serial->out_outlet_id;
            return response()->json(['valid' => false, 'message' => "Serial ini milik outlet {$outletName}, bukan outlet yang dipilih."]);
        }

        if ($serial->out_warehouse_outlet_id != $warehouseOutletFromId) {
            $whName = DB::table('warehouse_outlets')->where('id', $serial->out_warehouse_outlet_id)->value('name') ?? $serial->out_warehouse_outlet_id;
            return response()->json(['valid' => false, 'message' => "Serial ini ada di departemen {$whName}, bukan departemen asal yang dipilih."]);
        }

        $qty = 1;
        $unitId = $serial->small_unit_id;
        $unitName = DB::table('units')->where('id', $serial->small_unit_id)->value('name') ?? '';
        if ($serial->repack_qty && $serial->repack_unit_id) {
            $qty = $serial->repack_qty;
            $unitId = $serial->repack_unit_id;
            $unitName = $serial->repack_unit_name ?? '';
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transfer_date' => 'required|date',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_from_id' => 'required|integer|different:warehouse_outlet_to_id',
            'warehouse_outlet_to_id' => 'required|integer|different:warehouse_outlet_from_id',
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
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Minimal harus ada 1 item atau 1 serial.'], 422);
            }
            return redirect()->back()->with('error', 'Minimal harus ada 1 item atau 1 serial.');
        }

        $transferMode = 'normal';
        if ($hasItems && $hasSerials) $transferMode = 'mixed';
        elseif ($hasSerials) $transferMode = 'serial';

        DB::beginTransaction();
        try {
            // Generate transfer number dengan retry mechanism untuk menghindari duplicate
            $transferNumber = $this->generateTransferNumber($validated['transfer_date']);

            // Validasi warehouse outlet belongs to selected outlet
            $warehouseFrom = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_from_id'])->first();
            if (!$warehouseFrom) {
                throw new \Exception('Warehouse outlet asal tidak ditemukan');
            }
            if ($warehouseFrom->outlet_id != $validated['outlet_id']) {
                throw new \Exception('Warehouse outlet asal tidak sesuai dengan outlet yang dipilih');
            }

            $warehouseTo = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_to_id'])->first();
            if (!$warehouseTo) {
                throw new \Exception('Warehouse outlet tujuan tidak ditemukan');
            }
            if ($warehouseTo->outlet_id != $validated['outlet_id']) {
                throw new \Exception('Warehouse outlet tujuan tidak sesuai dengan outlet yang dipilih');
            }

            // Simpan header transfer dengan retry jika terjadi duplicate
            $maxRetries = 3;
            $transfer = null;
            
            for ($retry = 1; $retry <= $maxRetries; $retry++) {
                try {
                    $transfer = InternalWarehouseTransfer::create([
                        'transfer_number' => $transferNumber,
                        'transfer_date' => $validated['transfer_date'],
                        'outlet_id' => $validated['outlet_id'],
                        'warehouse_outlet_from_id' => $validated['warehouse_outlet_from_id'],
                        'warehouse_outlet_to_id' => $validated['warehouse_outlet_to_id'],
                        'notes' => $validated['notes'] ?? null,
                        'transfer_mode' => $transferMode,
                        'created_by' => Auth::id(),
                    ]);
                    break; // Berhasil, keluar dari loop
                } catch (\Illuminate\Database\QueryException $e) {
                    // Jika error karena duplicate entry, generate nomor baru
                    if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                        if ($retry >= $maxRetries) {
                            throw new \Exception('Gagal menyimpan data karena nomor transfer duplicate. Silakan coba lagi.');
                        }
                        
                        // Generate nomor baru
                        $transferNumber = $this->generateTransferNumber($validated['transfer_date']);
                        usleep(100000 * $retry); // Tunggu sebentar sebelum retry
                        continue;
                    }
                    
                    // Jika error lain, throw langsung
                    throw $e;
                }
            }
            
            if (!$transfer) {
                throw new \Exception('Gagal menyimpan header transfer setelah beberapa percobaan.');
            }

            if ($hasItems)
            foreach ($validated['items'] as $item) {
                // Cari inventory_item_id dari outlet_food_inventory_items
                $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item['item_id'])->first();

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
                InternalWarehouseTransferItem::create([
                    'internal_warehouse_transfer_id' => $transfer->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['qty'],
                    'unit_id' => $inventoryItem->small_unit_id, // asumsikan unit small
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'note' => $item['note'] ?? null,
                ]);

                // Update stok di warehouse outlet asal (kurangi)
                $stockFrom = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_from_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stockFrom) {
                    throw new \Exception('Stok tidak ditemukan di warehouse outlet asal');
                }
                [$costSmall, $costMedium, $costLarge] = OutletInventoryCostResolver::transferInboundCostRates(
                    $stockFrom,
                    (int) $validated['outlet_id'],
                    (int) $validated['warehouse_outlet_from_id'],
                    $inventory_item_id
                );
                $qtyFromAfter = $stockFrom->qty_small - $qty_small;
                $fromMac = OutletInventoryCostResolver::resolveMacFromStockRow($stockFrom);
                DB::table('outlet_food_inventory_stocks')
                    ->where('id', $stockFrom->id)
                    ->update([
                        'qty_small' => $qtyFromAfter,
                        'qty_medium' => $stockFrom->qty_medium - $qty_medium,
                        'qty_large' => $stockFrom->qty_large - $qty_large,
                        'value' => OutletInventoryCostResolver::stockTotalValue($qtyFromAfter, $fromMac),
                        'updated_at' => now(),
                    ]);

                // Update stok di warehouse outlet tujuan (tambah)
                $stockTo = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stockTo) {
                    // Buat stok baru jika belum ada
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventory_item_id,
                        'id_outlet' => $validated['outlet_id'],
                        'warehouse_outlet_id' => $validated['warehouse_outlet_to_id'],
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $qty_small * $costSmall,
                        'last_cost_small' => $costSmall,
                        'last_cost_medium' => $costMedium,
                        'last_cost_large' => $costLarge,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stockTo = (object) [
                        'qty_small' => 0,
                        'qty_medium' => 0,
                        'qty_large' => 0,
                        'last_cost_small' => $costSmall,
                        'last_cost_medium' => $costMedium,
                        'last_cost_large' => $costLarge,
                    ];
                } else {
                    // Update stok yang sudah ada
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stockTo->id)
                        ->update([
                            'qty_small' => $stockTo->qty_small + $qty_small,
                            'qty_medium' => $stockTo->qty_medium + $qty_medium,
                            'qty_large' => $stockTo->qty_large + $qty_large,
                            'updated_at' => now(),
                        ]);
                }

                // Hitung MAC (Moving Average Cost) untuk warehouse outlet tujuan
                $qty_lama = $stockTo->qty_small;
                $nilai_lama = $stockTo->qty_small * $stockTo->last_cost_small;
                $qty_baru = $qty_small;
                $nilai_baru = $qty_small * $costSmall;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $costSmall;

                // Update MAC di stok warehouse outlet tujuan
                DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
                    ->update([
                        'last_cost_small' => $mac,
                        'last_cost_medium' => $mac * $smallConv,
                        'last_cost_large' => $mac * $smallConv * $mediumConv,
                        'value' => OutletInventoryCostResolver::stockTotalValue($total_qty, $mac),
                    ]);

                // Insert kartu stok OUT di warehouse outlet asal
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $validated['outlet_id'],
                    'warehouse_outlet_id' => $validated['warehouse_outlet_from_id'],
                    'date' => $validated['transfer_date'],
                    'reference_type' => 'internal_warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'out_qty_small' => $qty_small,
                    'out_qty_medium' => $qty_medium,
                    'out_qty_large' => $qty_large,
                    'cost_per_small' => $costSmall,
                    'cost_per_medium' => $costMedium,
                    'cost_per_large' => $costLarge,
                    'value_out' => $qty_small * $costSmall,
                    'saldo_qty_small' => $stockFrom->qty_small - $qty_small,
                    'saldo_qty_medium' => $stockFrom->qty_medium - $qty_medium,
                    'saldo_qty_large' => $stockFrom->qty_large - $qty_large,
                    'saldo_value' => ($stockFrom->qty_small - $qty_small) * $stockFrom->last_cost_small,
                    'description' => 'Stock Out - Internal Warehouse Transfer',
                    'created_at' => now(),
                ]);

                // Insert kartu stok IN di warehouse outlet tujuan
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $validated['outlet_id'],
                    'warehouse_outlet_id' => $validated['warehouse_outlet_to_id'],
                    'date' => $validated['transfer_date'],
                    'reference_type' => 'internal_warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'cost_per_small' => $costSmall,
                    'cost_per_medium' => $costMedium,
                    'cost_per_large' => $costLarge,
                    'value_in' => $qty_small * $costSmall,
                    'saldo_qty_small' => $stockTo->qty_small + $qty_small,
                    'saldo_qty_medium' => $stockTo->qty_medium + $qty_medium,
                    'saldo_qty_large' => $stockTo->qty_large + $qty_large,
                    'saldo_value' => ($stockTo->qty_small + $qty_small) * $mac,
                    'description' => 'Stock In - Internal Warehouse Transfer',
                    'created_at' => now(),
                ]);

                // Insert cost history untuk warehouse outlet tujuan
                $lastCostHistory = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $stockTo->last_cost_small ?? 0;

                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $validated['outlet_id'],
                    'warehouse_outlet_id' => $validated['warehouse_outlet_to_id'],
                    'date' => $validated['transfer_date'],
                    'old_cost' => $old_cost,
                    'new_cost' => $costSmall,
                    'mac' => $mac,
                    'type' => 'internal_warehouse_transfer',
                    'reference_type' => 'internal_warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'created_at' => now(),
                ]);
            }

            // Process serial items
            if ($hasSerials) {
                $this->processSerialItemsForIWT($transfer, $validated);
            }

            // Insert activity log sebelum commit
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'internal_warehouse_transfer',
                'description' => 'Membuat internal warehouse transfer: ' . $transfer->transfer_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($transfer->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();
            
            // Verifikasi data benar-benar tersimpan
            $savedTransfer = InternalWarehouseTransfer::find($transfer->id);
            if (!$savedTransfer) {
                throw new \Exception('Data transfer tidak ditemukan setelah commit. Kemungkinan ada masalah dengan database transaction.');
            }
            
            // Log untuk debugging
            Log::info('Internal Warehouse Transfer berhasil disimpan', [
                'transfer_id' => $transfer->id,
                'transfer_number' => $transfer->transfer_number,
                'user_id' => Auth::id(),
                'verified' => true,
            ]);
            
            if ($request->expectsJson()) {
                $savedTransfer = InternalWarehouseTransfer::with(['warehouseOutletFrom', 'warehouseOutletTo', 'outlet'])->find($transfer->id);
                return response()->json([
                    'success' => true,
                    'message' => 'Internal Warehouse Transfer berhasil disimpan',
                    'transfer' => $savedTransfer,
                ]);
            }
            return redirect()->route('internal-warehouse-transfer.index')->with('success', 'Internal Warehouse Transfer berhasil disimpan!');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            
            $errMsg = $e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')
                ? 'Nomor transfer sudah digunakan. Silakan refresh halaman dan coba lagi.'
                : 'Gagal menyimpan data: ' . $e->getMessage();
            Log::error('Database error saat menyimpan Internal Warehouse Transfer', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $errMsg], 500);
            }
            return redirect()->back()->withInput()->with('error', $errMsg);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menyimpan Internal Warehouse Transfer', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Check delete permission: only superadmin or warehouse division can delete
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        
        $query = InternalWarehouseTransfer::with(['warehouseOutletFrom', 'warehouseOutletTo', 'creator', 'outlet']);

        // Filter berdasarkan outlet user
        if ($user->id_outlet != 1) {
            $query->where('outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('transfer_number', 'like', "%$search%")
                  ->orWhereHas('warehouseOutletFrom', function($q2) use ($search) {
                      $q2->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('warehouseOutletTo', function($q2) use ($search) {
                      $q2->where('name', 'like', "%$search%");
                  })
                  ->orWhere('notes', 'like', "%$search%")
                  ->orWhereHas('creator', function($q2) use ($search) {
                      $q2->where('nama_lengkap', 'like', "%$search%");
                  });
            });
        }
        if ($request->from) {
            $query->whereDate('transfer_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('transfer_date', '<=', $request->to);
        }
        $transfers = $query->orderByDesc('created_at')->paginate(10)->withQueryString();
        
        // Ambil data outlet untuk mapping
        $outlets = \App\Models\Outlet::where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->get()
            ->keyBy('id_outlet');
        
        return inertia('InternalWarehouseTransfer/Index', [
            'transfers' => $transfers,
            'filters' => $request->only(['search', 'from', 'to']),
            'outlets' => $outlets,
            'canDelete' => $canDelete,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        
        // Ambil data outlet menggunakan Eloquent model
        if ($user->id_outlet == 1) {
            // Admin bisa pilih semua outlet
            $outlets = \App\Models\Outlet::where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->orderBy('nama_outlet')
                ->get();
        } else {
            // User biasa hanya bisa pilih outletnya sendiri
            $outlets = \App\Models\Outlet::where('id_outlet', $user->id_outlet)
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->get();
        }
        
        // Ambil warehouse outlets berdasarkan outlet user
        if ($user->id_outlet == 1) {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('warehouse_outlets.status', 'active')
                ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
                ->orderBy('tbl_data_outlet.nama_outlet')
                ->orderBy('warehouse_outlets.name')
                ->get();
        } else {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('warehouse_outlets.outlet_id', $user->id_outlet)
                ->where('warehouse_outlets.status', 'active')
                ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
                ->orderBy('warehouse_outlets.name')
                ->get();
        }

        return inertia('InternalWarehouseTransfer/Form', [
            'outlets' => $outlets,
            'warehouse_outlets' => $warehouse_outlets,
            'user_outlet_id' => $user->id_outlet,
        ]);
    }

    public function show($id)
    {
        $transfer = InternalWarehouseTransfer::with(['items.item', 'items.unit', 'warehouseOutletFrom', 'warehouseOutletTo', 'creator', 'outlet'])->findOrFail($id);
        
        // Ambil data outlet untuk mapping
        $outlets = \App\Models\Outlet::where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->get()
            ->keyBy('id_outlet');

        $serialItems = DB::table('internal_warehouse_transfer_serial_items as si')
            ->join('items as i', 'si.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
            ->where('si.internal_warehouse_transfer_id', $id)
            ->select('si.*', 'i.name as item_name', 'i.sku', 'u.name as unit_name')
            ->get();
        
        return inertia('InternalWarehouseTransfer/Show', [
            'transfer' => $transfer,
            'outlets' => $outlets,
            'serialItems' => $serialItems,
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        
        // Check permission: only superadmin or warehouse division can delete
        if ($user->id_role !== '5af56935b011a' && $user->division_id != 11) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus data ini'], 403);
        }
        
        DB::beginTransaction();
        try {
            $transfer = InternalWarehouseTransfer::with('items')->findOrFail($id);
            
            // Ambil data warehouse outlet untuk rollback
            $warehouseFrom = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_from_id)->first();
            $warehouseTo = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_to_id)->first();
            
            // Rollback stok dan kartu stok
            foreach ($transfer->items as $item) {
                $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item->item_id)->first();
                if (!$inventoryItem) continue;
                $inventory_item_id = $inventoryItem->id;
                $qty_small = $item->qty_small ?? 0;
                $qty_medium = $item->qty_medium ?? 0;
                $qty_large = $item->qty_large ?? 0;
                
                // Tambah stok kembali ke warehouse outlet asal
                $stockFrom = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $warehouseFrom->outlet_id)
                    ->where('warehouse_outlet_id', $transfer->warehouse_outlet_from_id)
                    ->first();
                if ($stockFrom) {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stockFrom->id)
                        ->update([
                            'qty_small' => $stockFrom->qty_small + $qty_small,
                            'qty_medium' => $stockFrom->qty_medium + $qty_medium,
                            'qty_large' => $stockFrom->qty_large + $qty_large,
                            'updated_at' => now(),
                        ]);
                }
                
                // Kurangi stok dari warehouse outlet tujuan
                $stockTo = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $warehouseTo->outlet_id)
                    ->where('warehouse_outlet_id', $transfer->warehouse_outlet_to_id)
                    ->first();
                if ($stockTo) {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stockTo->id)
                        ->update([
                            'qty_small' => $stockTo->qty_small - $qty_small,
                            'qty_medium' => $stockTo->qty_medium - $qty_medium,
                            'qty_large' => $stockTo->qty_large - $qty_large,
                            'updated_at' => now(),
                        ]);
                }
                
                // Hapus kartu stok terkait
                DB::table('outlet_food_inventory_cards')
                    ->where('reference_type', 'internal_warehouse_transfer')
                    ->where('reference_id', $transfer->id)
                    ->delete();
                
                // Hapus cost history terkait
                DB::table('outlet_food_inventory_cost_histories')
                    ->where('reference_type', 'internal_warehouse_transfer')
                    ->where('reference_id', $transfer->id)
                    ->delete();
            }
            
            // Rollback serial items
            $serialItems = DB::table('internal_warehouse_transfer_serial_items')
                ->where('internal_warehouse_transfer_id', $transfer->id)
                ->get();

            foreach ($serialItems as $si) {
                $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $si->item_id)->first();
                if (!$inventoryItem) continue;
                $inventory_item_id = $inventoryItem->id;
                $qty_small = $si->qty_small ?? 0;
                $qty_medium = $si->qty_medium ?? 0;
                $qty_large = $si->qty_large ?? 0;

                // Add stock back to source
                $stockFrom = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $warehouseFrom->outlet_id)
                    ->where('warehouse_outlet_id', $transfer->warehouse_outlet_from_id)
                    ->first();
                if ($stockFrom) {
                    DB::table('outlet_food_inventory_stocks')->where('id', $stockFrom->id)->update([
                        'qty_small' => $stockFrom->qty_small + $qty_small,
                        'qty_medium' => $stockFrom->qty_medium + $qty_medium,
                        'qty_large' => $stockFrom->qty_large + $qty_large,
                        'updated_at' => now(),
                    ]);
                }

                // Subtract stock from destination
                $stockTo = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $warehouseTo->outlet_id)
                    ->where('warehouse_outlet_id', $transfer->warehouse_outlet_to_id)
                    ->first();
                if ($stockTo) {
                    DB::table('outlet_food_inventory_stocks')->where('id', $stockTo->id)->update([
                        'qty_small' => $stockTo->qty_small - $qty_small,
                        'qty_medium' => $stockTo->qty_medium - $qty_medium,
                        'qty_large' => $stockTo->qty_large - $qty_large,
                        'updated_at' => now(),
                    ]);
                }

                // Reset serial tracking
                DB::table('inventory_item_serials')->where('id', $si->serial_id)->update([
                    'is_transferred' => 0,
                    'transferred_at' => null,
                    'transfer_id' => null,
                    'transfer_from_outlet_id' => null,
                    'transfer_to_outlet_id' => null,
                    'transfer_to_warehouse_outlet_id' => null,
                ]);
            }

            // Delete serial movements & serial items
            DB::table('inventory_serial_movements')
                ->where('internal_warehouse_transfer_id', $transfer->id)
                ->delete();
            DB::table('internal_warehouse_transfer_serial_items')
                ->where('internal_warehouse_transfer_id', $transfer->id)
                ->delete();

            // Hapus detail transfer
            $transfer->items()->delete();
            
            // Simpan data transfer untuk activity log sebelum dihapus
            $transferData = $this->enrichDeleteSnapshot($transfer->toArray());
            
            // Hapus header transfer
            $transfer->delete();
            
            $this->writeActivityLog(
                request(),
                'internal_warehouse_transfer',
                'delete',
                'Menghapus internal warehouse transfer: ' . $transferData['transfer_number'],
                $transferData,
                null
            );
            
            DB::commit();
            return redirect()->route('internal-warehouse-transfer.index')->with('success', 'Data berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'transfer_date' => 'required|date',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_from_id' => 'required|integer|different:warehouse_outlet_to_id',
            'warehouse_outlet_to_id' => 'required|integer|different:warehouse_outlet_from_id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.note' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $transfer = InternalWarehouseTransfer::with('items')->findOrFail($id);
            
            // Ambil data warehouse outlet lama untuk rollback
            $oldWarehouseFrom = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_from_id)->first();
            $oldWarehouseTo = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_to_id)->first();
            
            // Rollback stok & kartu stok lama
            foreach ($transfer->items as $item) {
                $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item->item_id)->first();
                if (!$inventoryItem) continue;
                $inventory_item_id = $inventoryItem->id;
                $qty_small = $item->qty_small ?? 0;
                $qty_medium = $item->qty_medium ?? 0;
                $qty_large = $item->qty_large ?? 0;
                
                // Rollback ke warehouse outlet asal (tambah stok kembali)
                $stockFrom = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $oldWarehouseFrom->outlet_id)
                    ->where('warehouse_outlet_id', $transfer->warehouse_outlet_from_id)
                    ->first();
                if ($stockFrom) {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stockFrom->id)
                        ->update([
                            'qty_small' => $stockFrom->qty_small + $qty_small,
                            'qty_medium' => $stockFrom->qty_medium + $qty_medium,
                            'qty_large' => $stockFrom->qty_large + $qty_large,
                            'updated_at' => now(),
                        ]);
                }
                
                // Rollback dari warehouse outlet tujuan (kurangi stok)
                $stockTo = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $oldWarehouseTo->outlet_id)
                    ->where('warehouse_outlet_id', $transfer->warehouse_outlet_to_id)
                    ->first();
                if ($stockTo) {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stockTo->id)
                        ->update([
                            'qty_small' => $stockTo->qty_small - $qty_small,
                            'qty_medium' => $stockTo->qty_medium - $qty_medium,
                            'qty_large' => $stockTo->qty_large - $qty_large,
                            'updated_at' => now(),
                        ]);
                }
            }
            
            // Hapus kartu stok dan cost history lama
            DB::table('outlet_food_inventory_cards')
                ->where('reference_type', 'internal_warehouse_transfer')
                ->where('reference_id', $transfer->id)
                ->delete();
            DB::table('outlet_food_inventory_cost_histories')
                ->where('reference_type', 'internal_warehouse_transfer')
                ->where('reference_id', $transfer->id)
                ->delete();
            // Hapus detail lama
            $transfer->items()->delete();
            
            // Validasi warehouse outlet belongs to selected outlet
            $warehouseFrom = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_from_id'])->first();
            if (!$warehouseFrom) {
                throw new \Exception('Warehouse outlet asal tidak ditemukan');
            }
            if ($warehouseFrom->outlet_id != $validated['outlet_id']) {
                throw new \Exception('Warehouse outlet asal tidak sesuai dengan outlet yang dipilih');
            }

            $warehouseTo = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_to_id'])->first();
            if (!$warehouseTo) {
                throw new \Exception('Warehouse outlet tujuan tidak ditemukan');
            }
            if ($warehouseTo->outlet_id != $validated['outlet_id']) {
                throw new \Exception('Warehouse outlet tujuan tidak sesuai dengan outlet yang dipilih');
            }

            // Update header transfer
            $transfer->update([
                'transfer_date' => $validated['transfer_date'],
                'outlet_id' => $validated['outlet_id'],
                'warehouse_outlet_from_id' => $validated['warehouse_outlet_from_id'],
                'warehouse_outlet_to_id' => $validated['warehouse_outlet_to_id'],
                'notes' => $validated['notes'] ?? null,
            ]);
            // Insert detail baru
            foreach ($validated['items'] as $item) {
                $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item['item_id'])->first();
                if (!$inventoryItem) throw new \Exception('Inventory item not found for item_id: ' . $item['item_id']);
                $inventory_item_id = $inventoryItem->id;
                $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
                $unit = $item['unit'];
                $qty_input = $item['qty'];
                $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
                $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
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
                
                InternalWarehouseTransferItem::create([
                    'internal_warehouse_transfer_id' => $transfer->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['qty'],
                    'unit_id' => $inventoryItem->small_unit_id,
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'note' => $item['note'] ?? null,
                ]);
                
                // Update stok asal (kurangi)
                $stockFrom = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_from_id'])
                    ->lockForUpdate()
                    ->first();
                [$costSmall, $costMedium, $costLarge] = OutletInventoryCostResolver::transferInboundCostRates(
                    $stockFrom,
                    (int) $validated['outlet_id'],
                    (int) $validated['warehouse_outlet_from_id'],
                    $inventory_item_id
                );
                $qtyFromAfter = $stockFrom->qty_small - $qty_small;
                $fromMac = OutletInventoryCostResolver::resolveMacFromStockRow($stockFrom);
                DB::table('outlet_food_inventory_stocks')
                    ->where('id', $stockFrom->id)
                    ->update([
                        'qty_small' => $qtyFromAfter,
                        'qty_medium' => $stockFrom->qty_medium - $qty_medium,
                        'qty_large' => $stockFrom->qty_large - $qty_large,
                        'value' => OutletInventoryCostResolver::stockTotalValue($qtyFromAfter, $fromMac),
                        'updated_at' => now(),
                    ]);
                
                // Update stok tujuan (tambah)
                $stockTo = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
                    ->lockForUpdate()
                    ->first();
                
                if (!$stockTo) {
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventory_item_id,
                        'id_outlet' => $validated['outlet_id'],
                        'warehouse_outlet_id' => $validated['warehouse_outlet_to_id'],
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $qty_small * $costSmall,
                        'last_cost_small' => $costSmall,
                        'last_cost_medium' => $costMedium,
                        'last_cost_large' => $costLarge,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stockTo = (object) [
                        'qty_small' => 0,
                        'qty_medium' => 0,
                        'qty_large' => 0,
                        'last_cost_small' => $costSmall,
                        'last_cost_medium' => $costMedium,
                        'last_cost_large' => $costLarge,
                    ];
                } else {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stockTo->id)
                        ->update([
                            'qty_small' => $stockTo->qty_small + $qty_small,
                            'qty_medium' => $stockTo->qty_medium + $qty_medium,
                            'qty_large' => $stockTo->qty_large + $qty_large,
                            'updated_at' => now(),
                        ]);
                }
                
                // Hitung MAC untuk warehouse outlet tujuan
                $qty_lama = $stockTo->qty_small;
                $nilai_lama = $stockTo->qty_small * $stockTo->last_cost_small;
                $qty_baru = $qty_small;
                $nilai_baru = $qty_small * $costSmall;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $costSmall;
                
                // Update MAC di stok warehouse outlet tujuan
                DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
                    ->update([
                        'last_cost_small' => $mac,
                        'last_cost_medium' => $mac * $smallConv,
                        'last_cost_large' => $mac * $smallConv * $mediumConv,
                        'value' => OutletInventoryCostResolver::stockTotalValue($total_qty, $mac),
                    ]);
                
                // Insert kartu stok OUT di warehouse outlet asal
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $validated['outlet_id'],
                    'warehouse_outlet_id' => $validated['warehouse_outlet_from_id'],
                    'date' => $validated['transfer_date'],
                    'reference_type' => 'internal_warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'out_qty_small' => $qty_small,
                    'out_qty_medium' => $qty_medium,
                    'out_qty_large' => $qty_large,
                    'cost_per_small' => $costSmall,
                    'cost_per_medium' => $costMedium,
                    'cost_per_large' => $costLarge,
                    'value_out' => $qty_small * $costSmall,
                    'saldo_qty_small' => $stockFrom->qty_small - $qty_small,
                    'saldo_qty_medium' => $stockFrom->qty_medium - $qty_medium,
                    'saldo_qty_large' => $stockFrom->qty_large - $qty_large,
                    'saldo_value' => ($stockFrom->qty_small - $qty_small) * $stockFrom->last_cost_small,
                    'description' => 'Stock Out - Internal Warehouse Transfer',
                    'created_at' => now(),
                ]);
                
                // Insert kartu stok IN di warehouse outlet tujuan
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $validated['outlet_id'],
                    'warehouse_outlet_id' => $validated['warehouse_outlet_to_id'],
                    'date' => $validated['transfer_date'],
                    'reference_type' => 'internal_warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'cost_per_small' => $costSmall,
                    'cost_per_medium' => $costMedium,
                    'cost_per_large' => $costLarge,
                    'value_in' => $qty_small * $costSmall,
                    'saldo_qty_small' => $stockTo->qty_small + $qty_small,
                    'saldo_qty_medium' => $stockTo->qty_medium + $qty_medium,
                    'saldo_qty_large' => $stockTo->qty_large + $qty_large,
                    'saldo_value' => ($stockTo->qty_small + $qty_small) * $mac,
                    'description' => 'Stock In - Internal Warehouse Transfer',
                    'created_at' => now(),
                ]);
                
                // Insert cost history untuk warehouse outlet tujuan
                $lastCostHistory = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $warehouseTo->outlet_id)
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $stockTo->last_cost_small ?? 0;
                
                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $warehouseTo->outlet_id,
                    'warehouse_outlet_id' => $validated['warehouse_outlet_to_id'],
                    'date' => $validated['transfer_date'],
                    'old_cost' => $old_cost,
                    'new_cost' => $costSmall,
                    'mac' => $mac,
                    'type' => 'internal_warehouse_transfer',
                    'reference_type' => 'internal_warehouse_transfer',
                    'reference_id' => $transfer->id,
                    'created_at' => now(),
                ]);
            }
            
            // Insert activity log sebelum commit
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'internal_warehouse_transfer',
                'description' => 'Update internal warehouse transfer: ' . $transfer->transfer_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => json_encode($transfer->toArray()),
                'new_data' => json_encode($validated),
                'created_at' => now(),
            ]);
            
            DB::commit();
            
            // Refresh model untuk memastikan data terbaru
            $transfer->refresh();
            
            // Verifikasi data benar-benar tersimpan
            $savedTransfer = InternalWarehouseTransfer::find($transfer->id);
            if (!$savedTransfer) {
                throw new \Exception('Data transfer tidak ditemukan setelah commit. Kemungkinan ada masalah dengan database transaction.');
            }
            
            // Log untuk debugging
            Log::info('Internal Warehouse Transfer berhasil diupdate', [
                'transfer_id' => $transfer->id,
                'transfer_number' => $transfer->transfer_number,
                'user_id' => Auth::id(),
                'verified' => true,
            ]);
            
            return redirect()->route('internal-warehouse-transfer.index')->with('success', 'Data berhasil diupdate!');
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log error untuk debugging
            Log::error('Gagal mengupdate Internal Warehouse Transfer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'transfer_id' => $id,
                'request_data' => $request->except(['_token']),
            ]);
            
            return redirect()->back()->with('error', 'Gagal mengupdate data: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $user = auth()->user();
        $transfer = OutletTransfer::with(['items', 'warehouseOutletFrom', 'warehouseOutletTo'])->findOrFail($id);
        
        // Ambil data outlet menggunakan Eloquent model
        if ($user->id_outlet == 1) {
            // Admin bisa pilih semua outlet
            $outlets = \App\Models\Outlet::where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->orderBy('nama_outlet')
                ->get();
        } else {
            // User biasa hanya bisa pilih outletnya sendiri
            $outlets = \App\Models\Outlet::where('id_outlet', $user->id_outlet)
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->get();
        }
        
        // Ambil warehouse outlets berdasarkan outlet user
        if ($user->id_outlet == 1) {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('warehouse_outlets.status', 'active')
                ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
                ->orderBy('tbl_data_outlet.nama_outlet')
                ->orderBy('warehouse_outlets.name')
                ->get();
        } else {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('warehouse_outlets.outlet_id', $user->id_outlet)
                ->where('warehouse_outlets.status', 'active')
                ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
                ->orderBy('warehouse_outlets.name')
                ->get();
        }
        
        // Ambil outlet_id dari warehouse outlet
        $outlet_from_id = $transfer->warehouseOutletFrom->outlet_id ?? null;
        $outlet_to_id = $transfer->warehouseOutletTo->outlet_id ?? null;
        
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
        
        return inertia('OutletTransfer/Form', [
            'outlets' => $outlets,
            'warehouse_outlets' => $warehouse_outlets,
            'user_outlet_id' => $user->id_outlet,
            'editData' => [
                'id' => $transfer->id,
                'transfer_date' => $transfer->transfer_date,
                'outlet_from_id' => $outlet_from_id,
                'warehouse_outlet_from_id' => $transfer->warehouse_outlet_from_id,
                'outlet_to_id' => $outlet_to_id,
                'warehouse_outlet_to_id' => $transfer->warehouse_outlet_to_id,
                'notes' => $transfer->notes,
                'items' => $formItems,
            ]
        ]);
    }

    private function processSerialItemsForIWT($transfer, $validated)
    {
        $outletId = $validated['outlet_id'];
        $warehouseFromId = $validated['warehouse_outlet_from_id'];
        $warehouseToId = $validated['warehouse_outlet_to_id'];

        foreach ($validated['serial_items'] as $si) {
            $itemMaster = DB::table('items')->where('id', $si['item_id'])->first();
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
            $qty_small = $si['qty_small'];
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;

            $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $si['item_id'])->first();
            if (!$inventoryItem) continue;
            $inventory_item_id = $inventoryItem->id;

            // Resolve cost from source warehouse
            $stockFrom = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseFromId)
                ->lockForUpdate()
                ->first();

            $costSmall = $stockFrom->last_cost_small ?? 0;
            $costMedium = $costSmall * $smallConv;
            $costLarge = $costSmall * $smallConv * $mediumConv;

            // Save serial item record
            DB::table('internal_warehouse_transfer_serial_items')->insert([
                'internal_warehouse_transfer_id' => $transfer->id,
                'serial_id' => $si['serial_id'],
                'serial_number' => $si['serial_number'],
                'item_id' => $si['item_id'],
                'unit_id' => $si['unit_id'] ?? null,
                'qty' => $si['qty'],
                'qty_small' => $qty_small,
                'qty_medium' => $qty_medium,
                'qty_large' => $qty_large,
                'cost_small' => $costSmall,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Deduct stock from source
            if ($stockFrom) {
                $qtyFromAfter = $stockFrom->qty_small - $qty_small;
                $fromMac = OutletInventoryCostResolver::resolveMacFromStockRow($stockFrom);
                DB::table('outlet_food_inventory_stocks')
                    ->where('id', $stockFrom->id)
                    ->update([
                        'qty_small' => $qtyFromAfter,
                        'qty_medium' => $stockFrom->qty_medium - $qty_medium,
                        'qty_large' => $stockFrom->qty_large - $qty_large,
                        'value' => OutletInventoryCostResolver::stockTotalValue($qtyFromAfter, $fromMac),
                        'updated_at' => now(),
                    ]);
            }

            // Add stock to destination
            $stockTo = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseToId)
                ->lockForUpdate()
                ->first();

            if (!$stockTo) {
                DB::table('outlet_food_inventory_stocks')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $outletId,
                    'warehouse_outlet_id' => $warehouseToId,
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'value' => $qty_small * $costSmall,
                    'last_cost_small' => $costSmall,
                    'last_cost_medium' => $costMedium,
                    'last_cost_large' => $costLarge,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $stockTo = (object) ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0, 'last_cost_small' => $costSmall];
            } else {
                DB::table('outlet_food_inventory_stocks')
                    ->where('id', $stockTo->id)
                    ->update([
                        'qty_small' => $stockTo->qty_small + $qty_small,
                        'qty_medium' => $stockTo->qty_medium + $qty_medium,
                        'qty_large' => $stockTo->qty_large + $qty_large,
                        'updated_at' => now(),
                    ]);
            }

            // MAC calculation for destination
            $qty_lama = $stockTo->qty_small;
            $nilai_lama = $qty_lama * ($stockTo->last_cost_small ?? 0);
            $total_qty = $qty_lama + $qty_small;
            $total_nilai = $nilai_lama + ($qty_small * $costSmall);
            $mac = $total_qty > 0 ? $total_nilai / $total_qty : $costSmall;

            DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseToId)
                ->update([
                    'last_cost_small' => $mac,
                    'last_cost_medium' => $mac * $smallConv,
                    'last_cost_large' => $mac * $smallConv * $mediumConv,
                    'value' => OutletInventoryCostResolver::stockTotalValue($total_qty, $mac),
                ]);

            // Stock card OUT (source)
            DB::table('outlet_food_inventory_cards')->insert([
                'inventory_item_id' => $inventory_item_id,
                'id_outlet' => $outletId,
                'warehouse_outlet_id' => $warehouseFromId,
                'date' => $validated['transfer_date'],
                'reference_type' => 'internal_warehouse_transfer',
                'reference_id' => $transfer->id,
                'out_qty_small' => $qty_small,
                'out_qty_medium' => $qty_medium,
                'out_qty_large' => $qty_large,
                'cost_per_small' => $costSmall,
                'cost_per_medium' => $costMedium,
                'cost_per_large' => $costLarge,
                'value_out' => $qty_small * $costSmall,
                'saldo_qty_small' => ($stockFrom->qty_small ?? 0) - $qty_small,
                'saldo_qty_medium' => ($stockFrom->qty_medium ?? 0) - $qty_medium,
                'saldo_qty_large' => ($stockFrom->qty_large ?? 0) - $qty_large,
                'saldo_value' => (($stockFrom->qty_small ?? 0) - $qty_small) * ($stockFrom->last_cost_small ?? 0),
                'description' => 'Stock Out - IWT Serial: ' . $si['serial_number'],
                'created_at' => now(),
            ]);

            // Stock card IN (destination)
            DB::table('outlet_food_inventory_cards')->insert([
                'inventory_item_id' => $inventory_item_id,
                'id_outlet' => $outletId,
                'warehouse_outlet_id' => $warehouseToId,
                'date' => $validated['transfer_date'],
                'reference_type' => 'internal_warehouse_transfer',
                'reference_id' => $transfer->id,
                'in_qty_small' => $qty_small,
                'in_qty_medium' => $qty_medium,
                'in_qty_large' => $qty_large,
                'cost_per_small' => $costSmall,
                'cost_per_medium' => $costMedium,
                'cost_per_large' => $costLarge,
                'value_in' => $qty_small * $costSmall,
                'saldo_qty_small' => $stockTo->qty_small + $qty_small,
                'saldo_qty_medium' => $stockTo->qty_medium + $qty_medium,
                'saldo_qty_large' => $stockTo->qty_large + $qty_large,
                'saldo_value' => ($stockTo->qty_small + $qty_small) * $mac,
                'description' => 'Stock In - IWT Serial: ' . $si['serial_number'],
                'created_at' => now(),
            ]);

            // Cost history for destination
            DB::table('outlet_food_inventory_cost_histories')->insert([
                'inventory_item_id' => $inventory_item_id,
                'id_outlet' => $outletId,
                'warehouse_outlet_id' => $warehouseToId,
                'date' => $validated['transfer_date'],
                'old_cost' => $stockTo->last_cost_small ?? 0,
                'new_cost' => $costSmall,
                'mac' => $mac,
                'type' => 'internal_warehouse_transfer',
                'reference_type' => 'internal_warehouse_transfer',
                'reference_id' => $transfer->id,
                'created_at' => now(),
            ]);

            // Update serial tracking
            DB::table('inventory_item_serials')->where('id', $si['serial_id'])->update([
                'is_transferred' => 1,
                'transferred_at' => now(),
                'transfer_id' => $transfer->id,
                'transfer_from_outlet_id' => $outletId,
                'transfer_to_outlet_id' => $outletId,
                'transfer_to_warehouse_outlet_id' => $warehouseToId,
            ]);

            // Serial movements
            DB::table('inventory_serial_movements')->insert([
                'serial_id' => $si['serial_id'],
                'movement_type' => 'iwt_out',
                'internal_warehouse_transfer_id' => $transfer->id,
                'from_outlet_id' => $outletId,
                'from_warehouse_outlet_id' => $warehouseFromId,
                'moved_at' => now(),
                'moved_by' => Auth::id(),
                'created_at' => now(),
            ]);
            DB::table('inventory_serial_movements')->insert([
                'serial_id' => $si['serial_id'],
                'movement_type' => 'iwt_in',
                'internal_warehouse_transfer_id' => $transfer->id,
                'to_outlet_id' => $outletId,
                'to_warehouse_outlet_id' => $warehouseToId,
                'moved_at' => now(),
                'moved_by' => Auth::id(),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * API: List internal warehouse transfers (for mobile app)
     */
    public function apiIndex(Request $request)
    {
        $user = auth()->user();
        $query = InternalWarehouseTransfer::with(['warehouseOutletFrom', 'warehouseOutletTo', 'creator', 'outlet']);

        if ($user->id_outlet != 1) {
            $query->where('outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transfer_number', 'like', "%$search%")
                    ->orWhereHas('warehouseOutletFrom', fn($q2) => $q2->where('name', 'like', "%$search%"))
                    ->orWhereHas('warehouseOutletTo', fn($q2) => $q2->where('name', 'like', "%$search%"))
                    ->orWhere('notes', 'like', "%$search%")
                    ->orWhereHas('creator', fn($q2) => $q2->where('nama_lengkap', 'like', "%$search%"));
            });
        }
        if ($request->from) {
            $query->whereDate('transfer_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('transfer_date', '<=', $request->to);
        }

        $perPage = (int) $request->get('per_page', 20);
        $transfers = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $transfers,
        ]);
    }

    /**
     * API: Data for create form (outlets, warehouse outlets)
     */
    public function apiCreateData()
    {
        $user = auth()->user();

        if ($user->id_outlet == 1) {
            $outlets = \App\Models\Outlet::where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->orderBy('nama_outlet')
                ->get();
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('warehouse_outlets.status', 'active')
                ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
                ->orderBy('tbl_data_outlet.nama_outlet')
                ->orderBy('warehouse_outlets.name')
                ->get();
        } else {
            $outlets = \App\Models\Outlet::where('id_outlet', $user->id_outlet)
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->get();
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('warehouse_outlets.outlet_id', $user->id_outlet)
                ->where('warehouse_outlets.status', 'active')
                ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
                ->orderBy('warehouse_outlets.name')
                ->get();
        }

        return response()->json([
            'success' => true,
            'outlets' => $outlets,
            'warehouse_outlets' => $warehouse_outlets,
            'user_outlet_id' => $user->id_outlet,
        ]);
    }

    /**
     * API: Show single internal warehouse transfer (for mobile app)
     */
    public function apiShow($id)
    {
        $transfer = InternalWarehouseTransfer::with([
            'items.item',
            'items.unit',
            'warehouseOutletFrom',
            'warehouseOutletTo',
            'creator',
            'outlet',
        ])->findOrFail($id);

        $user = auth()->user();
        if ($user->id_outlet != 1 && $transfer->outlet_id != $user->id_outlet) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $serialItems = DB::table('internal_warehouse_transfer_serial_items as si')
            ->join('items as i', 'si.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
            ->where('si.internal_warehouse_transfer_id', $id)
            ->select('si.*', 'i.name as item_name', 'i.sku', 'u.name as unit_name')
            ->get();

        return response()->json([
            'success' => true,
            'transfer' => $transfer,
            'serial_items' => $serialItems,
        ]);
    }

    /**
     * API: Store internal warehouse transfer (for mobile app) - delegates to store() which returns JSON when Accept: application/json
     */
    public function apiStore(Request $request)
    {
        return $this->store($request);
    }
} 