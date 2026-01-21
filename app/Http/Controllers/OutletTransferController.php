<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\OutletTransfer;
use App\Models\OutletTransferItem;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class OutletTransferController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'transfer_date' => 'required|date',
            'outlet_from_id' => 'required|integer',
            'warehouse_outlet_from_id' => 'required|integer|different:warehouse_outlet_to_id',
            'outlet_to_id' => 'required|integer',
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
            Log::info('Start simpan outlet transfer', $validated);
            // Generate transfer number
            $dateStr = date('Ymd', strtotime($validated['transfer_date']));
            $countToday = OutletTransfer::whereDate('transfer_date', $validated['transfer_date'])->count() + 1;
            $transferNumber = 'OT-' . $dateStr . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);
            Log::info('Generated transfer number', ['transferNumber' => $transferNumber]);

            // Validasi warehouse outlet belongs to selected outlet
            $warehouseFrom = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_from_id'])->first();
            if (!$warehouseFrom) {
                throw new \Exception('Warehouse outlet asal tidak ditemukan');
            }
            if ($warehouseFrom->outlet_id != $validated['outlet_from_id']) {
                throw new \Exception('Warehouse outlet asal tidak sesuai dengan outlet asal yang dipilih');
            }

            $warehouseTo = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_to_id'])->first();
            if (!$warehouseTo) {
                throw new \Exception('Warehouse outlet tujuan tidak ditemukan');
            }
            if ($warehouseTo->outlet_id != $validated['outlet_to_id']) {
                throw new \Exception('Warehouse outlet tujuan tidak sesuai dengan outlet tujuan yang dipilih');
            }

            // Simpan header transfer dengan status draft
            $transfer = OutletTransfer::create([
                'transfer_number' => $transferNumber,
                'transfer_date' => $validated['transfer_date'],
                'warehouse_outlet_from_id' => $validated['warehouse_outlet_from_id'],
                'warehouse_outlet_to_id' => $validated['warehouse_outlet_to_id'],
                'outlet_id' => $validated['outlet_to_id'], // Gunakan outlet tujuan sebagai outlet_id
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft', // Set status draft untuk approval
                'created_by' => Auth::id(),
            ]);
            Log::info('Header transfer saved', ['transfer' => $transfer]);

            // Simpan detail transfer tanpa memproses stock (draft mode)
            foreach ($validated['items'] as $item) {
                Log::info('Proses item', $item);

                // Cari inventory_item_id dari outlet_food_inventory_items
                $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item['item_id'])->first();
                Log::info('InventoryItem', ['inventoryItem' => $inventoryItem]);

                if (!$inventoryItem) {
                    Log::error('Inventory item not found for item_id: ' . $item['item_id']);
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
                Log::info('Konversi qty', [
                    'input_qty' => $qty_input,
                    'input_unit' => $unit,
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'unitSmall' => $unitSmall,
                    'unitMedium' => $unitMedium,
                    'unitLarge' => $unitLarge,
                    'smallConv' => $smallConv,
                    'mediumConv' => $mediumConv,
                ]);

                // Simpan detail transfer (draft mode - tidak memproses stock)
                OutletTransferItem::create([
                    'outlet_transfer_id' => $transfer->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['qty'],
                    'unit_id' => $inventoryItem->small_unit_id, // asumsikan unit small
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'note' => $item['note'] ?? null,
                ]);
                Log::info('Detail transfer saved (draft mode)', ['item_id' => $item['item_id']]);
            }

            DB::commit();
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'outlet_transfer',
                'description' => 'Membuat transfer outlet: ' . $transfer->transfer_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($transfer->toArray()),
                'created_at' => now(),
            ]);
            Log::info('Selesai proses simpan outlet transfer (draft mode)');
            return redirect()->route('outlet-transfer.index')->with('success', 'Pindah Outlet berhasil disimpan dalam status draft!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saat simpan outlet transfer', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    // Submit draft untuk approval
    public function submit(Request $request, $id)
    {
        $transfer = OutletTransfer::findOrFail($id);
        
        if ($transfer->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Transfer hanya bisa di-submit dari status draft'
            ], 422);
        }

        $transfer->update([
            'status' => 'submitted'
        ]);

        // Kirim notifikasi berdasarkan warehouse outlet tujuan
        $this->sendNotificationByWarehouse($transfer->warehouse_outlet_to_id, $transfer->id, $transfer->transfer_number);

        DB::table('activity_logs')->insert([
            'user_id' => Auth::id(),
            'activity_type' => 'submit',
            'module' => 'outlet_transfer',
            'description' => 'Submit transfer outlet untuk approval: ' . $transfer->transfer_number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode(['status' => 'draft']),
            'new_data' => json_encode(['status' => 'submitted']),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    // Approve transfer
    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $transfer = OutletTransfer::findOrFail($id);

        // Cek hak akses berdasarkan warehouse outlet tujuan
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
        $canApprove = $this->canUserApproveByWarehouse($user, $transfer->warehouse_outlet_to_id);
        
        if (!($isSuperadmin || $canApprove)) {
            abort(403, 'Unauthorized - Anda tidak memiliki hak untuk approve transfer untuk warehouse outlet ini');
        }

        if ($transfer->status !== 'submitted') {
            abort(400, 'Tidak bisa approve transfer ini');
        }

        DB::beginTransaction();
        try {
            $oldData = $transfer->toArray();

            // Update status transfer
            $transfer->update([
                'status' => 'approved',
                'approval_by' => $user->id,
                'approval_at' => now(),
                'approval_notes' => $request->notes,
            ]);

            // Proses stock transfer
            $this->processStockTransfer($transfer);

            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => 'approve',
                'module' => 'outlet_transfer',
                'description' => 'Approve transfer outlet: ' . $transfer->transfer_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => json_encode($oldData),
                'new_data' => json_encode($transfer->fresh()->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Transfer outlet berhasil di-approve');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saat approve transfer outlet', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Gagal approve transfer: ' . $e->getMessage());
        }
    }

    // Method untuk mengecek apakah user bisa approve berdasarkan warehouse outlet
    private function canUserApproveByWarehouse($user, $warehouseOutletId)
    {
        // Ambil warehouse outlet
        $warehouseOutlet = DB::table('warehouse_outlets')->where('id', $warehouseOutletId)->first();
        if (!$warehouseOutlet) {
            return false;
        }

        $warehouseName = $warehouseOutlet->name;
        $userJabatan = $user->id_jabatan;
        $userStatus = $user->status;

        // Cek berdasarkan nama warehouse outlet (sama dengan RO Khusus)
        switch ($warehouseName) {
            case 'Kitchen':
                return in_array($userJabatan, [163, 174, 180, 345, 346, 347, 348, 349]) && $userStatus === 'A';
            case 'Bar':
                return in_array($userJabatan, [175, 182, 323]) && $userStatus === 'A';
            case 'Service':
                return in_array($userJabatan, [176, 322, 164, 321]) && $userStatus === 'A';
            default:
                return false;
        }
    }

    // Method untuk mengirim notifikasi berdasarkan warehouse outlet
    private function sendNotificationByWarehouse($warehouseOutletId, $transferId, $transferNumber)
    {
        // Ambil warehouse outlet
        $warehouseOutlet = DB::table('warehouse_outlets')->where('id', $warehouseOutletId)->first();
        if (!$warehouseOutlet) {
            return;
        }

        $warehouseName = $warehouseOutlet->name;
        $jabatanIds = [];

        // Tentukan jabatan berdasarkan nama warehouse outlet (sama dengan RO Khusus)
        switch ($warehouseName) {
            case 'Kitchen':
                $jabatanIds = [163, 174, 180, 345, 346, 347, 348, 349];
                break;
            case 'Bar':
                $jabatanIds = [175, 182, 323];
                break;
            case 'Service':
                $jabatanIds = [176, 322, 164, 321];
                break;
            default:
                return; // Tidak ada notifikasi untuk warehouse outlet lain
        }

        // Ambil user yang memiliki jabatan tersebut dan status aktif
        $users = DB::table('users')
            ->whereIn('id_jabatan', $jabatanIds)
            ->where('status', 'A')
            ->pluck('id')
            ->toArray();

        if (empty($users)) {
            return;
        }

        // Kirim notifikasi
        $data = [];
        foreach ($users as $userId) {
            $data[] = [
                'user_id' => $userId,
                'type' => 'outlet_transfer_approval',
                'title' => 'Approval Outlet Transfer',
                'message' => "Outlet Transfer {$transferNumber} ke warehouse {$warehouseName} menunggu approval Anda.",
                'url' => route('outlet-transfer.show', $transferId),
                'is_read' => 0,
            ];
        }
        NotificationService::createMany($data);
    }

    // Method untuk memproses stock transfer setelah approval
    private function processStockTransfer($transfer)
    {
        $warehouseFrom = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_from_id)->first();
        $warehouseTo = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_to_id)->first();

        foreach ($transfer->items as $item) {
            $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item->item_id)->first();
            if (!$inventoryItem) continue;
            
            $inventory_item_id = $inventoryItem->id;
            $qty_small = $item->qty_small ?? 0;
            $qty_medium = $item->qty_medium ?? 0;
            $qty_large = $item->qty_large ?? 0;

                // Update stok di warehouse outlet asal (kurangi)
                $stockFrom = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $warehouseFrom->outlet_id)
                ->where('warehouse_outlet_id', $transfer->warehouse_outlet_from_id)
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
                    ->where('id_outlet', $warehouseTo->outlet_id)
                ->where('warehouse_outlet_id', $transfer->warehouse_outlet_to_id)
                    ->first();

                if (!$stockTo) {
                    // Buat stok baru jika belum ada
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventory_item_id,
                        'id_outlet' => $warehouseTo->outlet_id,
                    'warehouse_outlet_id' => $transfer->warehouse_outlet_to_id,
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

            // Ambil data konversi dari tabel items
            $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

                // Update MAC di stok warehouse outlet tujuan
                DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventory_item_id)
                    ->where('id_outlet', $warehouseTo->outlet_id)
                ->where('warehouse_outlet_id', $transfer->warehouse_outlet_to_id)
                    ->update([
                        'last_cost_small' => $mac,
                        'last_cost_medium' => $mac * $smallConv,
                        'last_cost_large' => $mac * $smallConv * $mediumConv,
                    ]);

                // Insert kartu stok OUT di warehouse outlet asal
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $warehouseFrom->outlet_id,
                'warehouse_outlet_id' => $transfer->warehouse_outlet_from_id,
                'date' => $transfer->transfer_date,
                    'reference_type' => 'outlet_transfer',
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
                    'description' => 'Stock Out - Outlet Transfer',
                    'created_at' => now(),
                ]);

                // Insert kartu stok IN di warehouse outlet tujuan
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $warehouseTo->outlet_id,
                'warehouse_outlet_id' => $transfer->warehouse_outlet_to_id,
                'date' => $transfer->transfer_date,
                    'reference_type' => 'outlet_transfer',
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
                ->where('warehouse_outlet_id', $transfer->warehouse_outlet_to_id)
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();
                $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;

                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $warehouseTo->outlet_id,
                'warehouse_outlet_id' => $transfer->warehouse_outlet_to_id,
                'date' => $transfer->transfer_date,
                    'old_cost' => $old_cost,
                    'new_cost' => $stockFrom->last_cost_small,
                    'mac' => $mac,
                    'type' => 'outlet_transfer',
                    'reference_type' => 'outlet_transfer',
                    'reference_id' => $transfer->id,
                    'created_at' => now(),
                ]);
        }
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $query = OutletTransfer::with(['warehouseOutletFrom', 'warehouseOutletTo', 'creator', 'outlet', 'approver']);

        // Visibility rule:
        // - Superadmin: see all
        // - Non-superadmin: only see transfers related to user's outlet (as source OR destination)
        //
        // Note: outlet_transfers.outlet_id is currently used as "outlet tujuan" in store(),
        // so filtering by that column alone would hide transfers created by outlet asal.
        $isSuperadmin = ($user->id_role === '5af56935b011a' && $user->status === 'A');
        $userOutletId = $user->id_outlet ?? null;
        if (!$isSuperadmin && $userOutletId) {
            $query->where(function ($q) use ($userOutletId) {
                $q->whereHas('warehouseOutletFrom', function ($q2) use ($userOutletId) {
                    $q2->where('outlet_id', $userOutletId);
                })->orWhereHas('warehouseOutletTo', function ($q2) use ($userOutletId) {
                    $q2->where('outlet_id', $userOutletId);
                });
            });
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
        
        return inertia('OutletTransfer/Index', [
            'transfers' => $transfers,
            'filters' => $request->only(['search', 'from', 'to']),
            'outlets' => $outlets,
            'user' => $user,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        
        // Ambil data outlet asal berdasarkan id_outlet user
        if ($user->id_outlet == 1) {
            // Admin bisa pilih semua outlet untuk outlet asal
            $outlets_from = \App\Models\Outlet::where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->orderBy('nama_outlet')
                ->get();
        } else {
            // User biasa hanya bisa pilih outletnya sendiri untuk outlet asal
            $outlets_from = \App\Models\Outlet::where('id_outlet', $user->id_outlet)
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->get();
        }
        
        // Ambil data outlet tujuan - semua outlet tanpa filter
        $outlets_to = \App\Models\Outlet::where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();
        
        // Ambil warehouse outlets berdasarkan outlet user untuk outlet asal
        if ($user->id_outlet == 1) {
            $warehouse_outlets_from = DB::table('warehouse_outlets')
                ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('warehouse_outlets.status', 'active')
                ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
                ->orderBy('tbl_data_outlet.nama_outlet')
                ->orderBy('warehouse_outlets.name')
                ->get();
        } else {
            $warehouse_outlets_from = DB::table('warehouse_outlets')
                ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('warehouse_outlets.outlet_id', $user->id_outlet)
                ->where('warehouse_outlets.status', 'active')
                ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
                ->orderBy('warehouse_outlets.name')
                ->get();
        }
        
        // Ambil warehouse outlets untuk outlet tujuan - semua warehouse tanpa filter
        $warehouse_outlets_to = DB::table('warehouse_outlets')
            ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->where('warehouse_outlets.status', 'active')
            ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
            ->orderBy('tbl_data_outlet.nama_outlet')
            ->orderBy('warehouse_outlets.name')
            ->get();

        return inertia('OutletTransfer/Form', [
            'outlets_from' => $outlets_from,
            'outlets_to' => $outlets_to,
            'warehouse_outlets_from' => $warehouse_outlets_from,
            'warehouse_outlets_to' => $warehouse_outlets_to,
            'user_outlet_id' => $user->id_outlet,
        ]);
    }

    public function show($id)
    {
        $transfer = OutletTransfer::with(['items.item', 'items.unit', 'warehouseOutletFrom', 'warehouseOutletTo', 'creator', 'outlet', 'approver'])->findOrFail($id);
        
        // Ambil data outlet untuk mapping
        $outlets = \App\Models\Outlet::where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->get()
            ->keyBy('id_outlet');
        
        return inertia('OutletTransfer/Show', [
            'transfer' => $transfer,
            'outlets' => $outlets,
            'user' => auth()->user(),
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $transfer = OutletTransfer::with('items')->findOrFail($id);
            
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
                    ->where('reference_type', 'outlet_transfer')
                    ->where('reference_id', $transfer->id)
                    ->delete();
                
                // Hapus cost history terkait
                DB::table('outlet_food_inventory_cost_histories')
                    ->where('reference_type', 'outlet_transfer')
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
                'module' => 'outlet_transfer',
                'description' => 'Menghapus transfer outlet: ' . $transferData['transfer_number'],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($transferData),
                'new_data' => null,
                'created_at' => now(),
            ]);
            
            DB::commit();
            return redirect()->route('outlet-transfer.index')->with('success', 'Data berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'transfer_date' => 'required|date',
            'outlet_from_id' => 'required|integer',
            'warehouse_outlet_from_id' => 'required|integer|different:warehouse_outlet_to_id',
            'outlet_to_id' => 'required|integer',
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
            $transfer = OutletTransfer::with('items')->findOrFail($id);
            
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
                ->where('reference_type', 'outlet_transfer')
                ->where('reference_id', $transfer->id)
                ->delete();
            DB::table('outlet_food_inventory_cost_histories')
                ->where('reference_type', 'outlet_transfer')
                ->where('reference_id', $transfer->id)
                ->delete();
            // Hapus detail lama
            $transfer->items()->delete();
            
            // Validasi warehouse outlet belongs to selected outlet
            $warehouseFrom = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_from_id'])->first();
            if (!$warehouseFrom) {
                throw new \Exception('Warehouse outlet asal tidak ditemukan');
            }
            if ($warehouseFrom->outlet_id != $validated['outlet_from_id']) {
                throw new \Exception('Warehouse outlet asal tidak sesuai dengan outlet asal yang dipilih');
            }

            $warehouseTo = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_to_id'])->first();
            if (!$warehouseTo) {
                throw new \Exception('Warehouse outlet tujuan tidak ditemukan');
            }
            if ($warehouseTo->outlet_id != $validated['outlet_to_id']) {
                throw new \Exception('Warehouse outlet tujuan tidak sesuai dengan outlet tujuan yang dipilih');
            }

            // Update header transfer
            $transfer->update([
                'transfer_date' => $validated['transfer_date'],
                'warehouse_outlet_from_id' => $validated['warehouse_outlet_from_id'],
                'warehouse_outlet_to_id' => $validated['warehouse_outlet_to_id'],
                'outlet_id' => $validated['outlet_to_id'], // Gunakan outlet tujuan sebagai outlet_id
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
                
                OutletTransferItem::create([
                    'outlet_transfer_id' => $transfer->id,
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
                    ->where('id_outlet', $warehouseFrom->outlet_id)
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
                    ->where('id_outlet', $warehouseTo->outlet_id)
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
                    ->first();
                
                if (!$stockTo) {
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventory_item_id,
                        'id_outlet' => $warehouseTo->outlet_id,
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
                    ->where('id_outlet', $warehouseTo->outlet_id)
                    ->where('warehouse_outlet_id', $validated['warehouse_outlet_to_id'])
                    ->update([
                        'last_cost_small' => $mac,
                        'last_cost_medium' => $mac * $smallConv,
                        'last_cost_large' => $mac * $smallConv * $mediumConv,
                    ]);
                
                // Insert kartu stok OUT di warehouse outlet asal
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $warehouseFrom->outlet_id,
                    'warehouse_outlet_id' => $validated['warehouse_outlet_from_id'],
                    'date' => $validated['transfer_date'],
                    'reference_type' => 'outlet_transfer',
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
                    'description' => 'Stock Out - Outlet Transfer',
                    'created_at' => now(),
                ]);
                
                // Insert kartu stok IN di warehouse outlet tujuan
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $warehouseTo->outlet_id,
                    'warehouse_outlet_id' => $validated['warehouse_outlet_to_id'],
                    'date' => $validated['transfer_date'],
                    'reference_type' => 'outlet_transfer',
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
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'outlet_transfer',
                'description' => 'Update transfer outlet: ' . $transfer->transfer_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => json_encode($transfer->toArray()),
                'new_data' => json_encode($validated),
                'created_at' => now(),
            ]);
            
            DB::commit();
            return redirect()->route('outlet-transfer.index')->with('success', 'Data berhasil diupdate!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal mengupdate data: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $user = auth()->user();
        $transfer = OutletTransfer::with(['items', 'warehouseOutletFrom', 'warehouseOutletTo'])->findOrFail($id);
        
        // Ambil data outlet asal berdasarkan id_outlet user
        if ($user->id_outlet == 1) {
            // Admin bisa pilih semua outlet untuk outlet asal
            $outlets_from = \App\Models\Outlet::where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->orderBy('nama_outlet')
                ->get();
        } else {
            // User biasa hanya bisa pilih outletnya sendiri untuk outlet asal
            $outlets_from = \App\Models\Outlet::where('id_outlet', $user->id_outlet)
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->get();
        }
        
        // Ambil data outlet tujuan - semua outlet tanpa filter
        $outlets_to = \App\Models\Outlet::where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();
        
        // Ambil warehouse outlets berdasarkan outlet user untuk outlet asal
        if ($user->id_outlet == 1) {
            $warehouse_outlets_from = DB::table('warehouse_outlets')
                ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('warehouse_outlets.status', 'active')
                ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
                ->orderBy('tbl_data_outlet.nama_outlet')
                ->orderBy('warehouse_outlets.name')
                ->get();
        } else {
            $warehouse_outlets_from = DB::table('warehouse_outlets')
                ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('warehouse_outlets.outlet_id', $user->id_outlet)
                ->where('warehouse_outlets.status', 'active')
                ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
                ->orderBy('warehouse_outlets.name')
                ->get();
        }
        
        // Ambil warehouse outlets untuk outlet tujuan - semua warehouse tanpa filter
        $warehouse_outlets_to = DB::table('warehouse_outlets')
            ->join('tbl_data_outlet', 'warehouse_outlets.outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->where('warehouse_outlets.status', 'active')
            ->select('warehouse_outlets.id', 'warehouse_outlets.name', 'warehouse_outlets.outlet_id', 'tbl_data_outlet.nama_outlet')
            ->orderBy('tbl_data_outlet.nama_outlet')
            ->orderBy('warehouse_outlets.name')
            ->get();
        
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
            'outlets_from' => $outlets_from,
            'outlets_to' => $outlets_to,
            'warehouse_outlets_from' => $warehouse_outlets_from,
            'warehouse_outlets_to' => $warehouse_outlets_to,
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
} 