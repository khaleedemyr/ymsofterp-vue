<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\InternalWarehouseTransfer;
use App\Models\InternalWarehouseTransferItem;
use Illuminate\Support\Facades\Log;

class InternalWarehouseTransferController extends Controller
{
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

    public function store(Request $request)
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
                    ->first();

                if (!$stockFrom) {
                    throw new \Exception('Stok tidak ditemukan di warehouse outlet asal');
                }
                DB::table('outlet_food_inventory_stocks')
                    ->where('id', $stockFrom->id)
                    ->update([
                        'qty_small' => $stockFrom->qty_small - $qty_small,
                        'qty_medium' => $stockFrom->qty_medium - $qty_medium,
                        'qty_large' => $stockFrom->qty_large - $qty_large,
                        'updated_at' => now(),
                    ]);

                // Update stok di warehouse outlet tujuan (tambah)
                $stockTo = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
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
                        'value' => $qty_small * $stockFrom->last_cost_small,
                        'last_cost_small' => $stockFrom->last_cost_small,
                        'last_cost_medium' => $stockFrom->last_cost_medium,
                        'last_cost_large' => $stockFrom->last_cost_large,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stockTo = (object) [
                        'qty_small' => 0,
                        'qty_medium' => 0,
                        'qty_large' => 0,
                        'last_cost_small' => $stockFrom->last_cost_small,
                        'last_cost_medium' => $stockFrom->last_cost_medium,
                        'last_cost_large' => $stockFrom->last_cost_large,
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
                $nilai_baru = $qty_small * $stockFrom->last_cost_small;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $stockFrom->last_cost_small;

                // Update MAC di stok warehouse outlet tujuan
                DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
                    ->update([
                        'last_cost_small' => $mac,
                        'last_cost_medium' => $mac * $smallConv,
                        'last_cost_large' => $mac * $smallConv * $mediumConv,
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
                    'cost_per_small' => $stockFrom->last_cost_small,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_out' => $qty_small * $stockFrom->last_cost_small,
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
                    'cost_per_small' => $stockFrom->last_cost_small,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_in' => $qty_small * $stockFrom->last_cost_small,
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
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;

                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $validated['outlet_id'],
                    'warehouse_outlet_id' => $validated['warehouse_outlet_to_id'],
                    'date' => $validated['transfer_date'],
                    'old_cost' => $old_cost,
                    'new_cost' => $stockFrom->last_cost_small,
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
        
        return inertia('InternalWarehouseTransfer/Show', [
            'transfer' => $transfer,
            'outlets' => $outlets,
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
            
            // Hapus detail transfer
            $transfer->items()->delete();
            
            // Simpan data transfer untuk activity log sebelum dihapus
            $transferData = $transfer->toArray();
            
            // Hapus header transfer
            $transfer->delete();
            
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'internal_warehouse_transfer',
                'description' => 'Menghapus internal warehouse transfer: ' . $transferData['transfer_number'],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($transferData),
                'new_data' => null,
                'created_at' => now(),
            ]);
            
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
                    ->first();
                DB::table('outlet_food_inventory_stocks')
                    ->where('id', $stockFrom->id)
                    ->update([
                        'qty_small' => $stockFrom->qty_small - $qty_small,
                        'qty_medium' => $stockFrom->qty_medium - $qty_medium,
                        'qty_large' => $stockFrom->qty_large - $qty_large,
                        'updated_at' => now(),
                    ]);
                
                // Update stok tujuan (tambah)
                $stockTo = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
                    ->first();
                
                if (!$stockTo) {
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventory_item_id,
                        'id_outlet' => $validated['outlet_id'],
                        'warehouse_outlet_id' => $validated['warehouse_outlet_to_id'],
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $qty_small * $stockFrom->last_cost_small,
                        'last_cost_small' => $stockFrom->last_cost_small,
                        'last_cost_medium' => $stockFrom->last_cost_medium,
                        'last_cost_large' => $stockFrom->last_cost_large,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stockTo = (object) [
                        'qty_small' => 0,
                        'qty_medium' => 0,
                        'qty_large' => 0,
                        'last_cost_small' => $stockFrom->last_cost_small,
                        'last_cost_medium' => $stockFrom->last_cost_medium,
                        'last_cost_large' => $stockFrom->last_cost_large,
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
                $nilai_baru = $qty_small * $stockFrom->last_cost_small;
                $total_qty = $qty_lama + $qty_baru;
                $total_nilai = $nilai_lama + $nilai_baru;
                $mac = $total_qty > 0 ? $total_nilai / $total_qty : $stockFrom->last_cost_small;
                
                // Update MAC di stok warehouse outlet tujuan
                DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $validated['outlet_id'])
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
                    ->update([
                        'last_cost_small' => $mac,
                        'last_cost_medium' => $mac * $smallConv,
                        'last_cost_large' => $mac * $smallConv * $mediumConv,
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
                    'cost_per_small' => $stockFrom->last_cost_small,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_out' => $qty_small * $stockFrom->last_cost_small,
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
                    'cost_per_small' => $stockFrom->last_cost_small,
                    'cost_per_medium' => $stockFrom->last_cost_medium,
                    'cost_per_large' => $stockFrom->last_cost_large,
                    'value_in' => $qty_small * $stockFrom->last_cost_small,
                    'saldo_qty_small' => $stockTo->qty_small + $qty_small,
                    'saldo_qty_medium' => $stockTo->qty_medium + $qty_medium,
                    'saldo_qty_large' => $stockTo->qty_large + $qty_large,
                    'saldo_value' => ($stockTo->qty_small + $qty_small) * $mac,
                    'description' => 'Stock In - Outlet Transfer',
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
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;
                
                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $warehouseTo->outlet_id,
                    'warehouse_outlet_id' => $validated['warehouse_outlet_to_id'],
                    'date' => $validated['transfer_date'],
                    'old_cost' => $old_cost,
                    'new_cost' => $stockFrom->last_cost_small,
                    'mac' => $mac,
                    'type' => 'outlet_transfer',
                    'reference_type' => 'outlet_transfer',
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

        return response()->json([
            'success' => true,
            'transfer' => $transfer,
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