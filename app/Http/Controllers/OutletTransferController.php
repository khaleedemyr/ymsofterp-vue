<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\OutletTransfer;
use App\Models\OutletTransferItem;
use App\Models\OutletTransferApprovalFlow;
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
            // Optional: if provided, create approval flow immediately
            'approvers' => 'nullable|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
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

            // If approvers provided, auto-submit for approval (same as submit() flow)
            if (!empty($validated['approvers']) && is_array($validated['approvers'])) {
                // Reset approval flows (safety)
                $transfer->approvalFlows()->delete();

                foreach ($validated['approvers'] as $index => $approverId) {
                    OutletTransferApprovalFlow::create([
                        'outlet_transfer_id' => $transfer->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1,
                        'status' => 'PENDING',
                    ]);
                }

                $transfer->update([
                    'status' => 'submitted',
                    'approval_by' => null,
                    'approval_at' => null,
                    'approval_notes' => null,
                ]);

                $this->sendNotificationToNextApprover($transfer);

                DB::table('activity_logs')->insert([
                    'user_id' => Auth::id(),
                    'activity_type' => 'submit',
                    'module' => 'outlet_transfer',
                    'description' => 'Submit transfer outlet untuk approval: ' . $transfer->transfer_number,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => null,
                    'new_data' => json_encode($transfer->fresh()->toArray()),
                    'created_at' => now(),
                ]);
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
            $msg = (!empty($validated['approvers']) && is_array($validated['approvers']))
                ? 'Pindah Outlet berhasil disimpan dan di-submit untuk approval!'
                : 'Pindah Outlet berhasil disimpan dalam status draft!';
            return redirect()->route('outlet-transfer.index')->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saat simpan outlet transfer', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    // Submit draft untuk approval
    public function submit(Request $request, $id)
    {
        $transfer = OutletTransfer::with('approvalFlows')->findOrFail($id);
        
        if ($transfer->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Transfer hanya bisa di-submit dari status draft'
            ], 422);
        }

        $validated = $request->validate([
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            $oldData = $transfer->toArray();

            // Reset approval flows (in case of resubmit)
            $transfer->approvalFlows()->delete();

            // Create approval flows (sequential)
            foreach ($validated['approvers'] as $index => $approverId) {
                OutletTransferApprovalFlow::create([
                    'outlet_transfer_id' => $transfer->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1, // Level 1 = terendah, level terakhir = tertinggi
                    'status' => 'PENDING',
                ]);
            }

            // Update status
            $transfer->update([
                'status' => 'submitted',
                'approval_by' => null,
                'approval_at' => null,
                'approval_notes' => null,
            ]);

            // Send notification to first approver
            $this->sendNotificationToNextApprover($transfer);

            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'submit',
                'module' => 'outlet_transfer',
                'description' => 'Submit transfer outlet untuk approval: ' . $transfer->transfer_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => json_encode($oldData),
                'new_data' => json_encode($transfer->fresh()->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saat submit outlet transfer', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal submit transfer: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Approve transfer
    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $transfer = OutletTransfer::with(['approvalFlows', 'items'])->findOrFail($id);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'comments' => 'nullable|string',
        ]);

        if ($validated['action'] === 'reject') {
            $request->validate([
                'comments' => 'required|string',
            ]);
        }

        // Cek hak akses berdasarkan warehouse outlet tujuan
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
        $canApproveLegacy = $this->canUserApproveByWarehouse($user, $transfer->warehouse_outlet_to_id);
        
        if ($transfer->status !== 'submitted') {
            abort(400, 'Tidak bisa approve transfer ini');
        }

        DB::beginTransaction();
        try {
            $oldData = $transfer->toArray();

            // New sequential approval flow
            if ($transfer->approvalFlows && $transfer->approvalFlows->count() > 0) {
                // Only the next approver (lowest pending level) can approve/reject
                $nextFlow = $transfer->approvalFlows()
                    ->where('status', 'PENDING')
                    ->orderBy('approval_level')
                    ->first();

                if (!$nextFlow) {
                    throw new \Exception('Tidak ada approval yang pending.');
                }

                if (!$isSuperadmin && $nextFlow->approver_id != $user->id) {
                    abort(403, 'Unauthorized - Anda bukan approver berikutnya untuk transfer ini');
                }

                if ($validated['action'] === 'approve') {
                    $nextFlow->approve($validated['comments'] ?? null);

                    $hasPending = $transfer->approvalFlows()
                        ->where('status', 'PENDING')
                        ->count() > 0;

                    if (!$hasPending) {
                        // Final approval: execute stock transfer
                        $transfer->update([
                            'status' => 'approved',
                            'approval_by' => $user->id,
                            'approval_at' => now(),
                            'approval_notes' => $validated['comments'] ?? null,
                        ]);

                        $this->processStockTransfer($transfer->fresh()->load('items'));
                    } else {
                        // Notify next approver
                        $this->sendNotificationToNextApprover($transfer);
                    }
                } else {
                    // Reject: stop the chain
                    $nextFlow->reject($validated['comments'] ?? null);
                    $transfer->update([
                        'status' => 'rejected',
                        'approval_by' => $user->id,
                        'approval_at' => now(),
                        'approval_notes' => $validated['comments'] ?? null,
                    ]);
                }
            } else {
                // Legacy (no approval flows): keep old behavior for backward compatibility
                if (!($isSuperadmin || $canApproveLegacy)) {
                    abort(403, 'Unauthorized - Anda tidak memiliki hak untuk approve transfer untuk warehouse outlet ini');
                }

                if ($validated['action'] === 'reject') {
                    $transfer->update([
                        'status' => 'rejected',
                        'approval_by' => $user->id,
                        'approval_at' => now(),
                        'approval_notes' => $validated['comments'] ?? null,
                    ]);
                } else {
                    $transfer->update([
                        'status' => 'approved',
                        'approval_by' => $user->id,
                        'approval_at' => now(),
                        'approval_notes' => $validated['comments'] ?? ($request->notes ?? null),
                    ]);
                    $this->processStockTransfer($transfer->fresh()->load('items'));
                }
            }

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
            $message = $validated['action'] === 'approve'
                ? 'Transfer outlet berhasil diproses approval.'
                : 'Transfer outlet telah di-reject.';
            return redirect()->back()->with('success', $message);
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

    // Send notification to next approver in approval flow
    private function sendNotificationToNextApprover(OutletTransfer $transfer)
    {
        $nextFlow = $transfer->approvalFlows()
            ->where('status', 'PENDING')
            ->orderBy('approval_level')
            ->first();

        if (!$nextFlow) return;

        NotificationService::insert([
            'user_id' => $nextFlow->approver_id,
            'type' => 'outlet_transfer_approval',
            'message' => 'Outlet Transfer ' . $transfer->transfer_number . ' membutuhkan approval Anda.',
            'url' => route('outlet-transfer.show', $transfer->id),
            'is_read' => 0,
        ]);
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
        $query = OutletTransfer::with([
            'warehouseOutletFrom',
            'warehouseOutletTo',
            'creator',
            'outlet',
            'approver',
            'approvalFlows.approver',
            'approvalFlows.approver.jabatan',
        ]);

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

        // User list for selecting approvers (no outlet filter)
        $users = DB::table('users')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where('users.status', 'A')
            ->select('users.id', 'users.nama_lengkap', 'tbl_data_jabatan.nama_jabatan')
            ->orderBy('users.nama_lengkap')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'nama_lengkap' => $u->nama_lengkap,
                    'jabatan' => $u->nama_jabatan ? ['nama_jabatan' => $u->nama_jabatan] : null,
                ];
            });
        
        return inertia('OutletTransfer/Index', [
            'transfers' => $transfers,
            'filters' => $request->only(['search', 'from', 'to']),
            'outlets' => $outlets,
            'user' => $user,
            'users' => $users,
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

    /**
     * Get approvers for search (API for create form)
     */
    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');

        $usersQuery = DB::table('users')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where('users.status', 'A');

        if ($search) {
            $usersQuery->where(function ($query) use ($search) {
                $query->where('users.nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            });
        }

        $users = $usersQuery->select(
                'users.id',
                'users.nama_lengkap as name',
                'users.email',
                'tbl_data_jabatan.nama_jabatan as jabatan'
            )
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }

    public function show($id)
    {
        $transfer = OutletTransfer::with([
            'items.item',
            'items.unit',
            'warehouseOutletFrom',
            'warehouseOutletTo',
            'creator',
            'outlet',
            'approver',
            'approvalFlows.approver',
            'approvalFlows.approver.jabatan',
        ])->findOrFail($id);
        
        // Ambil data outlet untuk mapping
        $outlets = \App\Models\Outlet::where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->get()
            ->keyBy('id_outlet');

        $user = auth()->user();
        $isSuperadmin = $user && $user->id_role === '5af56935b011a' && $user->status === 'A';

        // Determine pending flow + canApprove (new flow), fallback to legacy rules if no flows
        $pendingFlow = null;
        $canApprove = false;
        if ($transfer->approvalFlows && $transfer->approvalFlows->count() > 0) {
            $pendingFlow = $transfer->approvalFlows()
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
            if ($pendingFlow && $pendingFlow->approver_id == $user->id) {
                $canApprove = true;
            }
        } else {
            $canApprove = $this->canUserApproveByWarehouse($user, $transfer->warehouse_outlet_to_id);
        }
        if ($isSuperadmin) {
            $canApprove = true;
        }

        // User list for selecting approvers (no outlet filter)
        $users = DB::table('users')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where('users.status', 'A')
            ->select('users.id', 'users.nama_lengkap', 'tbl_data_jabatan.nama_jabatan')
            ->orderBy('users.nama_lengkap')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'nama_lengkap' => $u->nama_lengkap,
                    'jabatan' => $u->nama_jabatan ? ['nama_jabatan' => $u->nama_jabatan] : null,
                ];
            });
        
        return inertia('OutletTransfer/Show', [
            'transfer' => $transfer,
            'outlets' => $outlets,
            'user' => $user,
            'canApprove' => $canApprove,
            'pendingFlow' => $pendingFlow,
            'users' => $users,
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

    /**
     * Get pending approvals for Outlet Transfer (API for Home dashboard)
     */
    public function getPendingApprovals()
    {
        try {
            $user = auth()->user();
            $userId = $user->id;

            $query = OutletTransfer::with([
                'outlet',
                'creator',
                'warehouseOutletFrom.outlet',
                'warehouseOutletTo.outlet',
                'approvalFlows.approver',
            ])->where('status', 'submitted');

            // Filter by outlet if user is not "all outlets" (same pattern as Stock Opname)
            if (($user->id_outlet ?? null) != 1) {
                $query->where('outlet_id', $user->id_outlet);
            }

            if ($user->id_role === '5af56935b011a') {
                // Superadmin can see all pending approvals (flow-based + legacy without flows)
                $pendingApprovals = $query->where(function ($q) {
                    $q->whereHas('approvalFlows', function ($qq) {
                        $qq->where('status', 'PENDING');
                    })->orDoesntHave('approvalFlows');
                })->get();
            } else {
                // Regular users: approvals assigned via flow OR legacy approvals by warehouse rules
                $pendingApprovals = $query->where(function ($q) use ($userId) {
                    $q->whereHas('approvalFlows', function ($qq) use ($userId) {
                        $qq->where('approver_id', $userId)
                            ->where('status', 'PENDING');
                    })->orDoesntHave('approvalFlows');
                })->get();
            }

            // Only show approvals where user is the next approver and previous levels are approved
            $filteredApprovals = $pendingApprovals->filter(function ($transfer) use ($user) {
                // Flow-based approvals
                if ($transfer->approvalFlows && $transfer->approvalFlows->count() > 0) {
                    $allFlows = $transfer->approvalFlows->sortBy('approval_level');
                    $pendingFlows = $allFlows->where('status', 'PENDING');
                    $nextApprover = $pendingFlows->first();

                    if (!$nextApprover) {
                        return false;
                    }

                    $nextApprovalLevel = $nextApprover->approval_level;
                    $previousFlows = $allFlows->where('approval_level', '<', $nextApprovalLevel);
                    $allPreviousApproved = $previousFlows->every(function ($flow) {
                        return $flow->status === 'APPROVED';
                    });

                    if (!$allPreviousApproved) {
                        return false;
                    }

                    if ($user->id_role === '5af56935b011a') {
                        return true;
                    }

                    return $nextApprover->approver_id == $user->id;
                }

                // Legacy approvals (no flows)
                if ($user->id_role === '5af56935b011a') {
                    return true;
                }

                return $this->canUserApproveByWarehouse($user, $transfer->warehouse_outlet_to_id);
            });

            $mappedApprovals = $filteredApprovals->map(function ($transfer) {
                $nextApprover = null;
                if ($transfer->approvalFlows && $transfer->approvalFlows->count() > 0) {
                    $pendingFlows = $transfer->approvalFlows->where('status', 'PENDING')->sortBy('approval_level');
                    $nextApprover = $pendingFlows->first();
                }

                return [
                    'id' => $transfer->id,
                    'transfer_number' => $transfer->transfer_number,
                    'transfer_date' => $transfer->transfer_date,
                    'notes' => $transfer->notes,
                    'outlet' => $transfer->outlet ? ['nama_outlet' => $transfer->outlet->nama_outlet] : null,
                    'warehouse_outlet_from' => $transfer->warehouseOutletFrom ? [
                        'name' => $transfer->warehouseOutletFrom->name,
                        'outlet' => $transfer->warehouseOutletFrom->outlet ? ['nama_outlet' => $transfer->warehouseOutletFrom->outlet->nama_outlet] : null,
                    ] : null,
                    'warehouse_outlet_to' => $transfer->warehouseOutletTo ? [
                        'name' => $transfer->warehouseOutletTo->name,
                        'outlet' => $transfer->warehouseOutletTo->outlet ? ['nama_outlet' => $transfer->warehouseOutletTo->outlet->nama_outlet] : null,
                    ] : null,
                    'creator' => $transfer->creator ? ['nama_lengkap' => $transfer->creator->nama_lengkap] : null,
                    'approver_name' => ($nextApprover && $nextApprover->approver) ? $nextApprover->approver->nama_lengkap : null,
                    'approval_level' => $nextApprover ? $nextApprover->approval_level : null,
                ];
            });

            return response()->json([
                'success' => true,
                'outlet_transfers' => $mappedApprovals->values(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting pending Outlet Transfer approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get pending approvals',
            ], 500);
        }
    }
} 