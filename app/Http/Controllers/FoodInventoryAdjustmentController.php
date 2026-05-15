<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\FoodInventoryAdjustment;
use App\Models\FoodInventoryAdjustmentItem;
use App\Models\FoodInventoryStock;
use App\Models\FoodInventoryItem;
use App\Models\FoodInventoryCard;
use App\Models\Warehouse;
use App\Models\Item;
use App\Services\NotificationService;
use App\Support\InventorySerialInUse;
use Illuminate\Support\Str;

class FoodInventoryAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Check if user can delete food inventory adjustment
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        
        $query = FoodInventoryAdjustment::with(['items', 'warehouse', 'creator']);
        if ($request->search) {
            $search = $request->search;
            $query->whereHas('items', function($q) use ($search) {
                $q->where('item_id', $search);
            });
        }
        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->from) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('date', '<=', $request->to);
        }
        $adjustments = $query->orderByDesc('date')->paginate(10)->withQueryString();
        return inertia('FoodInventoryAdjustment/Index', [
            'adjustments' => $adjustments,
            'filters' => $request->only(['search', 'warehouse_id', 'from', 'to']),
            'canDelete' => $canDelete,
        ]);
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $items = Item::all();
        return inertia('FoodInventoryAdjustment/Form', [
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $number = $this->generateAdjustmentNumber();
            $headerId = DB::table('food_inventory_adjustments')->insertGetId([
                'number' => $number,
                'date' => $request->date,
                'warehouse_id' => $request->warehouse_id,
                'type' => $request->type,
                'reason' => $request->reason,
                'status' => 'waiting_approval',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($request->items as $item) {
                DB::table('food_inventory_adjustment_items')->insert([
                    'adjustment_id' => $headerId,
                    'item_id' => $item['item_id'],
                    'qty' => $item['qty'],
                    'unit' => $item['selected_unit'],
                    'note' => $item['note'] ?? null,
                ]);
            }
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'create',
                'module' => 'stock_adjustment',
                'description' => 'Membuat stock adjustment baru: ' . $number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);
            
            // Cek warehouse untuk menentukan approver
            $warehouse = DB::table('warehouses')->where('id', $request->warehouse_id)->first();
            $isMKWarehouse = $warehouse && in_array($warehouse->name, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
            
            // Notifikasi berdasarkan warehouse
            if ($isMKWarehouse) {
                // Untuk MK warehouse: langsung ke Sous Chef MK (179) saja
                $notifUsers = DB::table('users')
                    ->where('id_jabatan', 179)
                    ->where('status', 'A')
                    ->pluck('id');
            } else {
                // Untuk non-MK warehouse: ke Asisten SSD Manager (172) dulu
                $notifUsers = DB::table('users')
                    ->where('id_jabatan', 172)
                    ->where('status', 'A')
                    ->pluck('id');
            }
            
            foreach ($notifUsers as $uid) {
                NotificationService::insert([
                    'user_id' => $uid,
                    'type' => 'stock_adjustment_approval',
                    'message' => "Stock Adjustment #$number menunggu approval",
                    'url' => '/food-inventory-adjustment/' . $headerId,
                    'is_read' => 0,
                ]);
            }
            DB::commit();
            // Redirect ke halaman detail dengan flash message
            return redirect()->route('food-inventory-adjustment.show', $headerId)
                ->with('success', 'Stock adjustment berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    public function approve(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            $adj = DB::table('food_inventory_adjustments')->where('id', $id)->first();
            if (!$adj) throw new \Exception('Adjustment not found');
            
            // Cek warehouse untuk menentukan flow approval
            $warehouse = DB::table('warehouses')->where('id', $adj->warehouse_id)->first();
            $isMKWarehouse = $warehouse && in_array($warehouse->name, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
            
            $update = [];
            $desc = '';
            
            if ($isSuperadmin) {
                // Superadmin mengikuti flow approval berdasarkan warehouse
                if ($adj->status == 'waiting_approval') {
                    if ($isMKWarehouse) {
                        // MK: langsung ke waiting_cost_control (Sous Chef MK)
                        $update['status'] = 'waiting_cost_control';
                        $update['approved_by_ssd_manager'] = $user->id;
                        $update['approved_at_ssd_manager'] = now();
                        $update['ssd_manager_note'] = $request->note;
                        $desc = 'Superadmin approve tahap Sous Chef MK stock adjustment ID: ' . $id;
                    } else {
                        // Non-MK: approve sebagai Asisten SSD Manager
                        $update['status'] = 'waiting_ssd_manager';
                        $update['approved_by_assistant_ssd_manager'] = $user->id;
                        $update['approved_at_assistant_ssd_manager'] = now();
                        $update['assistant_ssd_manager_note'] = $request->note;
                        $desc = 'Superadmin approve tahap Asisten SSD Manager stock adjustment ID: ' . $id;
                    }
                } else if ($adj->status == 'waiting_ssd_manager') {
                    // Non-MK: dari Asisten SSD Manager ke Cost Control
                    $update['status'] = 'waiting_cost_control';
                    $update['approved_by_ssd_manager'] = $user->id;
                    $update['approved_at_ssd_manager'] = now();
                    $update['ssd_manager_note'] = $request->note;
                    $desc = 'Superadmin approve tahap SSD Manager stock adjustment ID: ' . $id;
                } else if ($adj->status == 'waiting_cost_control') {
                    $update['status'] = 'approved';
                    $update['approved_by_cost_control_manager'] = $user->id;
                    $update['approved_at_cost_control_manager'] = now();
                    $update['cost_control_manager_note'] = $request->note;
                    $desc = 'Superadmin approve tahap Cost Control Manager stock adjustment ID: ' . $id;
                } else {
                    throw new \Exception('Status dokumen tidak valid untuk approval');
                }
            } else if ($isMKWarehouse) {
                // MK Warehouse: hanya Sous Chef MK (179) yang bisa approve
                if ($user->id_jabatan == 179 && $adj->status == 'waiting_approval') {
                    $update['status'] = 'waiting_cost_control';
                    $update['approved_by_ssd_manager'] = $user->id;
                    $update['approved_at_ssd_manager'] = now();
                    $update['ssd_manager_note'] = $request->note;
                    $desc = 'Sous Chef MK approve stock adjustment ID: ' . $id;
                } else if ($user->id_jabatan == 167 && $adj->status == 'waiting_cost_control') {
                    $update['status'] = 'approved';
                    $update['approved_by_cost_control_manager'] = $user->id;
                    $update['approved_at_cost_control_manager'] = now();
                    $update['cost_control_manager_note'] = $request->note;
                    $desc = 'Cost Control Manager approve stock adjustment ID: ' . $id;
                } else {
                    throw new \Exception('Anda tidak berhak approve pada tahap ini');
                }
            } else {
                // Non-MK Warehouse: Asisten SSD Manager (172) dulu, baru SSD Manager (161)
                if ($user->id_jabatan == 172 && $adj->status == 'waiting_approval') {
                    $update['status'] = 'waiting_ssd_manager';
                    $update['approved_by_assistant_ssd_manager'] = $user->id;
                    $update['approved_at_assistant_ssd_manager'] = now();
                    $update['assistant_ssd_manager_note'] = $request->note;
                    $desc = 'Asisten SSD Manager approve stock adjustment ID: ' . $id;
                    
                    // Notifikasi ke SSD Manager untuk approval selanjutnya
                    $ssdManagers = DB::table('users')->where('id_jabatan', 161)->where('status', 'A')->pluck('id');
                    $adjNumber = $adj->number;
                    $warehouseName = $warehouse->name ?? '-';
                    foreach ($ssdManagers as $uid) {
                        NotificationService::insert([
                            'user_id' => $uid,
                            'type' => 'stock_adjustment_approval',
                            'message' => "Stock Adjustment #$adjNumber dari $warehouseName sudah di-approve Asisten SSD Manager, menunggu approval SSD Manager.",
                            'url' => '/food-inventory-adjustment/' . $id,
                            'is_read' => 0,
                        ]);
                    }
                } else if (in_array($user->id_jabatan, [161, 172]) && $adj->status == 'waiting_ssd_manager') {
                    // SSD Manager atau Asisten SSD Manager bisa approve level 2 (jika sudah ada approval level 1)
                    // Asisten SSD Manager bisa approve level 2 juga (skip level 1 jika dia yang approve)
                    $isAssistantSSDManager = $user->id_jabatan == 172;
                    if (!$adj->approved_at_assistant_ssd_manager && $user->id_jabatan == 161) {
                        throw new \Exception('Stock Adjustment harus di-approve Asisten SSD Manager terlebih dahulu');
                    }
                    $update['status'] = 'waiting_cost_control';
                    $update['approved_by_ssd_manager'] = $user->id;
                    $update['approved_at_ssd_manager'] = now();
                    $update['ssd_manager_note'] = $request->note;
                    $desc = ($user->id_jabatan == 172 ? 'Asisten SSD Manager' : 'SSD Manager') . ' approve stock adjustment ID: ' . $id;
                    
                    // Notifikasi ke Cost Control Manager
                    $costControlManagers = DB::table('users')->where('id_jabatan', 167)->where('status', 'A')->pluck('id');
                    $adjNumber = $adj->number;
                    $warehouseName = $warehouse->name ?? '-';
                    foreach ($costControlManagers as $uid) {
                        NotificationService::insert([
                            'user_id' => $uid,
                            'type' => 'stock_adjustment_approval',
                            'message' => "Stock Adjustment #$adjNumber dari $warehouseName sudah di-approve SSD Manager, menunggu approval Cost Control Manager.",
                            'url' => '/food-inventory-adjustment/' . $id,
                            'is_read' => 0,
                        ]);
                    }
                } else if ($user->id_jabatan == 167 && $adj->status == 'waiting_cost_control') {
                    $update['status'] = 'approved';
                    $update['approved_by_cost_control_manager'] = $user->id;
                    $update['approved_at_cost_control_manager'] = now();
                    $update['cost_control_manager_note'] = $request->note;
                    $desc = 'Cost Control Manager approve stock adjustment ID: ' . $id;
                } else {
                    throw new \Exception('Anda tidak berhak approve pada tahap ini');
                }
            }
            DB::table('food_inventory_adjustments')->where('id', $id)->update($update);
            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => 'approve',
                'module' => 'stock_adjustment',
                'description' => $desc,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
            // Jika status sudah approved, lakukan update ke inventory
            if ($update['status'] == 'approved') {
                $this->processInventory($id);
            }
            DB::commit();
            // Response inertia/redirect jika bukan AJAX/JSON
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            } else {
                return redirect()->route('food-inventory-adjustment.show', $id)
                    ->with('success', 'Stock adjustment berhasil di-approve!');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            } else {
                return redirect()->back()->with('error', 'Gagal approve: ' . $e->getMessage());
            }
        }
    }

    public function reject(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $adj = DB::table('food_inventory_adjustments')->where('id', $id)->first();
            if (!$adj) throw new \Exception('Adjustment not found');
            
            // Cek warehouse untuk menentukan flow approval
            $warehouse = DB::table('warehouses')->where('id', $adj->warehouse_id)->first();
            $isMKWarehouse = $warehouse && in_array($warehouse->name, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
            
            $update = ['status' => 'rejected', 'updated_at' => now()];
            
            if ($isMKWarehouse) {
                // MK Warehouse: hanya Sous Chef MK (179)
                if ($user->id_jabatan == 179) {
                    $update['ssd_manager_note'] = $request->note;
                } else if ($user->id_jabatan == 167) {
                    $update['cost_control_manager_note'] = $request->note;
                } else if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
                    if ($adj->status == 'waiting_approval' || $adj->status == 'waiting_cost_control') {
                        $update['ssd_manager_note'] = $request->note;
                    } else if ($adj->status == 'waiting_cost_control') {
                        $update['cost_control_manager_note'] = $request->note;
                    }
                }
            } else {
                // Non-MK Warehouse
                if ($user->id_jabatan == 172) {
                    $update['assistant_ssd_manager_note'] = $request->note;
                } else if (in_array($user->id_jabatan, [161, 172])) {
                    $update['ssd_manager_note'] = $request->note;
                } else if ($user->id_jabatan == 167) {
                    $update['cost_control_manager_note'] = $request->note;
                } else if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
                    if ($adj->status == 'waiting_approval') {
                        $update['assistant_ssd_manager_note'] = $request->note;
                    } else if ($adj->status == 'waiting_ssd_manager') {
                        $update['ssd_manager_note'] = $request->note;
                    } else if ($adj->status == 'waiting_cost_control') {
                        $update['cost_control_manager_note'] = $request->note;
                    }
                }
            }
            DB::table('food_inventory_adjustments')->where('id', $id)->update($update);
            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => 'reject',
                'module' => 'stock_adjustment',
                'description' => 'Reject stock adjustment ID: ' . $id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
            DB::commit();
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            } else {
                return redirect()->route('food-inventory-adjustment.show', $id)
                    ->with('success', 'Stock adjustment berhasil direject!');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            } else {
                return redirect()->back()->with('error', 'Gagal reject: ' . $e->getMessage());
            }
        }
    }

    public function show($id)
    {
        $adjustment = FoodInventoryAdjustment::with([
            'items.item',
            'warehouse',
            'creator',
        ])->findOrFail($id);
        $user = auth()->user();
        return inertia('FoodInventoryAdjustment/Show', [
            'adjustment' => $adjustment,
            'user' => $user,
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
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk menghapus Stock Adjustment. Hanya superadmin atau user dengan division warehouse yang dapat menghapus.'
                ], 403);
            }
            
            $adj = FoodInventoryAdjustment::with(['items'])->findOrFail($id);
            // Jika sudah approved, rollback inventory (kebalikan dari approve)
            if ($adj->status === 'approved') {
                foreach ($adj->items as $item) {
                    $inventoryItem = \App\Models\FoodInventoryItem::where('item_id', $item->item_id)->first();
                    if (!$inventoryItem) {
                        continue;
                    }
                    $itemMaster = \App\Models\Item::find($item->item_id);
                    if (!$itemMaster) {
                        continue;
                    }
                    ['qty_small' => $qty_small, 'qty_medium' => $qty_medium, 'qty_large' => $qty_large] =
                        $this->convertAdjustmentQty($itemMaster, $item->unit, (float) $item->qty);
                    $this->applyWarehouseAdjustmentToStock($adj, $inventoryItem->id, $itemMaster, $qty_small, $qty_medium, $qty_large, true);
                }
                $itemIds = $adj->items->pluck('id')->filter()->values()->all();
                if (!empty($itemIds)) {
                    DB::table('inventory_item_serials')
                        ->where('source_type', 'stock_adjustment')
                        ->whereIn('source_item_id', $itemIds)
                        ->delete();
                }
                DB::table('food_inventory_cost_histories')
                    ->where('reference_type', 'stock_adjustment')
                    ->where('reference_id', $adj->id)
                    ->delete();
                \App\Models\FoodInventoryCard::where('reference_type', 'stock_adjustment')
                    ->where('reference_id', $adj->id)
                    ->delete();
            }
            // Hapus detail dan header
            $adj->items()->delete();
            $adj->delete();
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'delete',
                'module' => 'stock_adjustment',
                'description' => 'Menghapus stock adjustment: ' . $adj->number,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($adj->toArray()),
                'new_data' => null,
                'created_at' => now(),
            ]);
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get pending approvals for current user
     */
    public function getPendingApprovals()
    {
        try {
            $user = Auth::user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            $jabatan = $user->id_jabatan;
            
            \Log::info('Warehouse Stock Adjustment approvals check', [
                'user_id' => $user->id,
                'user_jabatan' => $jabatan,
                'user_status' => $user->status,
                'id_role' => $user->id_role,
                'isSuperadmin' => $isSuperadmin
            ]);
            
            // Query adjustments based on status
            $query = FoodInventoryAdjustment::with(['warehouse', 'creator', 'items'])
                ->whereIn('status', ['waiting_approval', 'waiting_ssd_manager', 'waiting_cost_control']);
            
            // Filter based on user role and warehouse - sesuai tahapan approval
            if (!$isSuperadmin) {
                $query->where(function($q) use ($jabatan) {
                    // Asisten SSD Manager (172): non-MK warehouse, hanya tahap pertama (waiting_approval)
                    if ($jabatan === 172) {
                        $q->where('status', 'waiting_approval')
                            ->whereHas('warehouse', function($wh) {
                                $wh->whereNotIn('name', ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
                            });
                    }
                    // SSD Manager (161): non-MK warehouse, hanya tahap kedua (waiting_ssd_manager)
                    elseif ($jabatan === 161) {
                        $q->where('status', 'waiting_ssd_manager')
                            ->whereHas('warehouse', function($wh) {
                                $wh->whereNotIn('name', ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
                            });
                    }
                    // Sous Chef MK (179): MK warehouse, hanya tahap pertama (waiting_approval)
                    elseif ($jabatan === 179) {
                        $q->where('status', 'waiting_approval')
                            ->whereHas('warehouse', function($wh) {
                                $wh->whereIn('name', ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
                            });
                    }
                    // Cost Control Manager (167): semua warehouse, tahap akhir (waiting_cost_control)
                    elseif ($jabatan === 167) {
                        $q->where('status', 'waiting_cost_control');
                    }
                });
            }
            
            $adjustments = $query->orderBy('created_at', 'desc')->get();
            
            \Log::info('Warehouse Stock Adjustment query result', [
                'count' => $adjustments->count()
            ]);
            
            // Transform data untuk response
            $transformedAdjustments = $adjustments->map(function($adj) {
                // Tentukan approver berdasarkan status dan warehouse
                $approverName = 'Approval';
                $warehouseName = $adj->warehouse->name ?? '';
                $isMKWarehouse = in_array($warehouseName, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
                
                if ($adj->status === 'waiting_approval') {
                    if ($isMKWarehouse) {
                        $approverName = 'Sous Chef MK';
                    } else {
                        $approverName = 'Asisten SSD Manager';
                    }
                } elseif ($adj->status === 'waiting_ssd_manager') {
                    $approverName = 'SSD Manager';
                } elseif ($adj->status === 'waiting_cost_control') {
                    $approverName = 'Cost Control Manager';
                }
                
                return [
                    'id' => $adj->id,
                    'number' => $adj->number,
                    'date' => $adj->date,
                    'type' => $adj->type,
                    'reason' => $adj->reason,
                    'status' => $adj->status,
                    'warehouse' => [
                        'id' => $adj->warehouse->id ?? null,
                        'name' => $adj->warehouse->name ?? null,
                    ],
                    'creator' => [
                        'id' => $adj->creator->id ?? null,
                        'nama_lengkap' => $adj->creator->nama_lengkap ?? null,
                    ],
                    'created_at' => $adj->created_at,
                    'items_count' => $adj->items->count(),
                    'approver_name' => $approverName,
                ];
            });
            
            return response()->json([
                'success' => true,
                'adjustments' => $transformedAdjustments
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting pending warehouse stock adjustment approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get pending approvals'
            ], 500);
        }
    }
    
    /**
     * Get approval details for specific adjustment
     */
    public function getApprovalDetails($id)
    {
        try {
            $adjustment = FoodInventoryAdjustment::with([
                'warehouse',
                'creator',
                'items.item.category',
                'items.item.smallUnit',
                'items.item.mediumUnit',
                'items.item.largeUnit'
            ])->findOrFail($id);
            
            // Warehouse stock adjustment tidak pakai approval_flows table
            // Approval data tersimpan di field langsung di tabel food_inventory_adjustments
            // Field: approved_by_assistant_ssd_manager, approved_by_ssd_manager, approved_by_cost_control_manager, etc.
            
            // Get approver details if exists
            $approvers = [];
            
            if ($adjustment->approved_by_assistant_ssd_manager) {
                $approver = DB::table('users')
                    ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
                    ->where('users.id', $adjustment->approved_by_assistant_ssd_manager)
                    ->select('users.*', 'j.nama_jabatan')
                    ->first();
                if ($approver) {
                    $approvers[] = [
                        'level' => 1,
                        'role' => 'Asisten SSD Manager',
                        'approver' => $approver,
                        'approved_at' => $adjustment->approved_at_assistant_ssd_manager,
                        'note' => $adjustment->assistant_ssd_manager_note
                    ];
                }
            }
            
            if ($adjustment->approved_by_ssd_manager) {
                $approver = DB::table('users')
                    ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
                    ->where('users.id', $adjustment->approved_by_ssd_manager)
                    ->select('users.*', 'j.nama_jabatan')
                    ->first();
                if ($approver) {
                    $approvers[] = [
                        'level' => 2,
                        'role' => 'SSD Manager / Sous Chef MK',
                        'approver' => $approver,
                        'approved_at' => $adjustment->approved_at_ssd_manager,
                        'note' => $adjustment->ssd_manager_note
                    ];
                }
            }
            
            if ($adjustment->approved_by_cost_control_manager) {
                $approver = DB::table('users')
                    ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
                    ->where('users.id', $adjustment->approved_by_cost_control_manager)
                    ->select('users.*', 'j.nama_jabatan')
                    ->first();
                if ($approver) {
                    $approvers[] = [
                        'level' => 3,
                        'role' => 'Cost Control Manager',
                        'approver' => $approver,
                        'approved_at' => $adjustment->approved_at_cost_control_manager,
                        'note' => $adjustment->cost_control_manager_note
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'adjustment' => $adjustment,
                'items' => $adjustment->items,
                'approvers' => $approvers
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting approval details', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get adjustment details'
            ], 500);
        }
    }

    /**
     * API: List all adjustments (for mobile - same as web index, JSON)
     */
    public function apiIndex(Request $request)
    {
        $query = FoodInventoryAdjustment::with(['items', 'warehouse', 'creator']);
        if ($request->search) {
            $search = $request->search;
            $query->whereHas('items', function ($q) use ($search) {
                $q->where('item_id', $search);
            });
        }
        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->from) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('date', '<=', $request->to);
        }
        $perPage = (int) $request->get('per_page', 20);
        $adjustments = $query->orderByDesc('date')->paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => $adjustments->items(),
            'current_page' => $adjustments->currentPage(),
            'last_page' => $adjustments->lastPage(),
            'per_page' => $adjustments->perPage(),
            'total' => $adjustments->total(),
        ]);
    }

    /**
     * API: List warehouses (for mobile create form)
     */
    public function apiWarehouses()
    {
        $warehouses = Warehouse::select('id', 'name')->orderBy('name')->get();
        return response()->json(['success' => true, 'warehouses' => $warehouses]);
    }

    /**
     * API: Search items for warehouse (for mobile create form)
     */
    public function apiSearchItems(Request $request)
    {
        return app(\App\Http\Controllers\ItemController::class)->searchForWarehouseTransfer($request);
    }

    /**
     * API: Store new adjustment (for mobile - same as web store, return JSON)
     */
    public function apiStore(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'type' => 'required|in:in,out',
            'reason' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.selected_unit' => 'required|string',
        ]);
        DB::beginTransaction();
        try {
            $number = $this->generateAdjustmentNumber();
            $headerId = DB::table('food_inventory_adjustments')->insertGetId([
                'number' => $number,
                'date' => $request->date,
                'warehouse_id' => $request->warehouse_id,
                'type' => $request->type,
                'reason' => $request->reason,
                'status' => 'waiting_approval',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($request->items as $item) {
                DB::table('food_inventory_adjustment_items')->insert([
                    'adjustment_id' => $headerId,
                    'item_id' => $item['item_id'],
                    'qty' => $item['qty'],
                    'unit' => $item['selected_unit'],
                    'note' => $item['note'] ?? null,
                ]);
            }
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'create',
                'module' => 'stock_adjustment',
                'description' => 'Membuat stock adjustment baru: ' . $number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);
            $warehouse = DB::table('warehouses')->where('id', $request->warehouse_id)->first();
            $isMKWarehouse = $warehouse && in_array($warehouse->name, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
            if ($isMKWarehouse) {
                $notifUsers = DB::table('users')->where('id_jabatan', 179)->where('status', 'A')->pluck('id');
            } else {
                $notifUsers = DB::table('users')->where('id_jabatan', 172)->where('status', 'A')->pluck('id');
            }
            foreach ($notifUsers as $uid) {
                NotificationService::insert([
                    'user_id' => $uid,
                    'type' => 'stock_adjustment_approval',
                    'message' => "Stock Adjustment #$number menunggu approval",
                    'url' => '/food-inventory-adjustment/' . $headerId,
                    'is_read' => 0,
                ]);
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment berhasil disimpan!',
                'id' => $headerId,
                'number' => $number,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Food inventory adjustment apiStore error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage(),
            ], 422);
        }
    }

    // Helper untuk generate nomor adjustment
    private function generateAdjustmentNumber()
    {
        $prefix = 'SA-' . date('Ym') . '-';
        $last = DB::table('food_inventory_adjustments')
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');
        $next = $last ? (int)substr($last, -4) + 1 : 1;
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // Proses update inventory setelah approved (MAC selaras Good Receive)
    private function processInventory($adjustmentId)
    {
        $adj = FoodInventoryAdjustment::with(['items', 'warehouse'])->find($adjustmentId);
        if (!$adj) {
            return;
        }
        foreach ($adj->items as $item) {
            $inventoryItem = \App\Models\FoodInventoryItem::where('item_id', $item->item_id)->first();
            if (!$inventoryItem) {
                $itemMaster = \App\Models\Item::find($item->item_id);
                if (!$itemMaster) {
                    continue;
                }
                $inventoryItem = \App\Models\FoodInventoryItem::create([
                    'item_id' => $item->item_id,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $itemMaster = \App\Models\Item::find($item->item_id);
            if (!$itemMaster) {
                continue;
            }
            ['qty_small' => $qty_small, 'qty_medium' => $qty_medium, 'qty_large' => $qty_large] =
                $this->convertAdjustmentQty($itemMaster, $item->unit, (float) $item->qty);
            $this->applyWarehouseAdjustmentToStock($adj, $inventoryItem->id, $itemMaster, $qty_small, $qty_medium, $qty_large, false);
        }
    }

    private function convertAdjustmentQty($itemMaster, string $unit, float $qty_input): array
    {
        $qty_small = 0;
        $qty_medium = 0;
        $qty_large = 0;
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

        return compact('qty_small', 'qty_medium', 'qty_large', 'smallConv', 'mediumConv');
    }

    private function resolveMacFromStock($stock): float
    {
        $qty = (float) ($stock->qty_small ?? 0);
        $value = (float) ($stock->value ?? 0);
        if ($qty > 0) {
            return $value / $qty;
        }

        return (float) ($stock->last_cost_small ?? 0);
    }

    private function resolveWarehouseDivisionId(int $inventoryItemId, int $warehouseId): ?int
    {
        $last = DB::table('food_inventory_cost_histories')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->first();

        return $last->warehouse_division_id ?? null;
    }

    private function insertWarehouseCostHistory(
        int $inventoryItemId,
        int $warehouseId,
        string $date,
        float $oldCost,
        float $newCost,
        float $mac,
        int $adjustmentId,
        ?int $warehouseDivisionId = null
    ): void {
        $row = [
            'inventory_item_id' => $inventoryItemId,
            'warehouse_id' => $warehouseId,
            'date' => $date,
            'old_cost' => $oldCost,
            'new_cost' => $newCost,
            'mac' => $mac,
            'type' => 'stock_adjustment',
            'reference_type' => 'stock_adjustment',
            'reference_id' => $adjustmentId,
            'created_at' => now(),
        ];
        if ($warehouseDivisionId) {
            $row['warehouse_division_id'] = $warehouseDivisionId;
        }
        DB::table('food_inventory_cost_histories')->insert($row);
    }

    /**
     * @param  bool  $reverse  true saat rollback delete (kebalikan tipe adjustment)
     */
    private function applyWarehouseAdjustmentToStock(
        $adj,
        int $inventoryItemId,
        $itemMaster,
        float $qty_small,
        float $qty_medium,
        float $qty_large,
        bool $reverse = false
    ): void {
        $isIn = $reverse ? ($adj->type === 'out') : ($adj->type === 'in');
        $smallConv = $itemMaster->small_conversion_qty ?: 1;
        $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

        $stock = \App\Models\FoodInventoryStock::firstOrCreate(
            [
                'inventory_item_id' => $inventoryItemId,
                'warehouse_id' => $adj->warehouse_id,
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

        $qty_lama = (float) $stock->qty_small;
        $nilai_lama = (float) $stock->value;
        $mac_lama = $this->resolveMacFromStock($stock);

        if ($isIn) {
            $nilai_baru = $qty_small * $mac_lama;
            $total_qty = $qty_lama + $qty_small;
            $total_nilai = $nilai_lama + $nilai_baru;
            $mac = $total_qty > 0 ? $total_nilai / $total_qty : $mac_lama;
            $stock->qty_small = $total_qty;
            $stock->qty_medium += $qty_medium;
            $stock->qty_large += $qty_large;
            $stock->value = $total_nilai;
            $stock->last_cost_small = $mac;
            $stock->last_cost_medium = $mac * $smallConv;
            $stock->last_cost_large = $stock->last_cost_medium * $mediumConv;
            $txnCost = $mac_lama;
        } else {
            $mac = $mac_lama;
            $nilai_keluar = $qty_small * $mac;
            $total_qty = max(0, $qty_lama - $qty_small);
            $total_nilai = max(0, $nilai_lama - $nilai_keluar);
            $stock->qty_small = $total_qty;
            $stock->qty_medium = max(0, (float) $stock->qty_medium - $qty_medium);
            $stock->qty_large = max(0, (float) $stock->qty_large - $qty_large);
            $stock->value = $total_nilai;
            $txnCost = $mac;
        }
        $stock->save();

        if (!$reverse) {
            $lastCostHistory = DB::table('food_inventory_cost_histories')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('warehouse_id', $adj->warehouse_id)
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->first();
            $old_cost = $lastCostHistory ? (float) $lastCostHistory->new_cost : 0;
            $warehouseDivisionId = $this->resolveWarehouseDivisionId($inventoryItemId, (int) $adj->warehouse_id);
            $this->insertWarehouseCostHistory(
                $inventoryItemId,
                (int) $adj->warehouse_id,
                $adj->date,
                $old_cost,
                $txnCost,
                $mac,
                (int) $adj->id,
                $warehouseDivisionId
            );
        }

        \App\Models\FoodInventoryCard::create([
            'inventory_item_id' => $inventoryItemId,
            'warehouse_id' => $adj->warehouse_id,
            'date' => $adj->date,
            'reference_type' => 'stock_adjustment',
            'reference_id' => $adj->id,
            'in_qty_small' => $isIn ? $qty_small : 0,
            'in_qty_medium' => $isIn ? $qty_medium : 0,
            'in_qty_large' => $isIn ? $qty_large : 0,
            'out_qty_small' => $isIn ? 0 : $qty_small,
            'out_qty_medium' => $isIn ? 0 : $qty_medium,
            'out_qty_large' => $isIn ? 0 : $qty_large,
            'cost_per_small' => $txnCost,
            'cost_per_medium' => $txnCost * $smallConv,
            'cost_per_large' => $txnCost * $smallConv * $mediumConv,
            'value_in' => $isIn ? $qty_small * $mac_lama : 0,
            'value_out' => $isIn ? 0 : $qty_small * $mac_lama,
            'saldo_qty_small' => $stock->qty_small,
            'saldo_qty_medium' => $stock->qty_medium,
            'saldo_qty_large' => $stock->qty_large,
            'saldo_value' => $stock->value,
            'description' => $reverse ? 'Rollback Stock Adjustment' : 'Stock Adjustment',
        ]);
    }

    public function adjustmentSerialSummary($adjustmentId)
    {
        $adj = DB::table('food_inventory_adjustments')->where('id', $adjustmentId)->first();
        if (!$adj || $adj->type !== 'in') {
            return response()->json([]);
        }

        $case = InventorySerialInUse::mysqlSumInUseCase('s');
        $summary = DB::table('inventory_item_serials as s')
            ->select(
                's.source_item_id as adjustment_item_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("{$case} as in_use")
            )
            ->where('s.source_type', 'stock_adjustment')
            ->where('s.source_id', $adjustmentId)
            ->groupBy('s.source_item_id')
            ->get();

        return response()->json($summary);
    }

    public function serialUnits($adjustmentItemId)
    {
        $line = $this->loadAdjustmentItemForSerials((int) $adjustmentItemId);
        if (!$line) {
            return response()->json(['message' => 'Baris adjustment tidak ditemukan'], 404);
        }
        if ($line->adjustment_type !== 'in') {
            return response()->json(['message' => 'Generate serial hanya untuk Stock In'], 422);
        }

        $receivedUnitId = $this->resolveAdjustmentItemUnitId($line);
        if (!$receivedUnitId) {
            return response()->json(['message' => 'Unit baris tidak bisa dipetakan ke master item.'], 422);
        }

        $smallConv = (float) ($line->small_conversion_qty ?: 1);
        $mediumConv = (float) ($line->medium_conversion_qty ?: 1);
        $qtyReceived = (float) ($line->qty ?: 0);

        $qtySmall = $qtyReceived;
        if ($receivedUnitId === (int) $line->medium_unit_id) {
            $qtySmall = $qtyReceived * $smallConv;
        } elseif ($receivedUnitId === (int) $line->large_unit_id) {
            $qtySmall = $qtyReceived * $smallConv * $mediumConv;
        }

        $unitIds = collect([
            $line->small_unit_id,
            $line->medium_unit_id,
            $line->large_unit_id,
        ])->filter()->unique()->values();

        $unitsMaster = DB::table('units')->whereIn('id', $unitIds)->pluck('name', 'id');

        $units = [];
        foreach ($unitIds as $unitId) {
            $unitIdInt = (int) $unitId;
            $convertedQty = $qtySmall;
            if ($unitIdInt === (int) $line->medium_unit_id) {
                $convertedQty = $smallConv > 0 ? ($qtySmall / $smallConv) : 0;
            } elseif ($unitIdInt === (int) $line->large_unit_id) {
                $divider = $smallConv * $mediumConv;
                $convertedQty = $divider > 0 ? ($qtySmall / $divider) : 0;
            }
            $units[] = [
                'unit_id' => $unitIdInt,
                'unit_name' => $unitsMaster[$unitIdInt] ?? "Unit {$unitIdInt}",
                'converted_qty' => round($convertedQty, 4),
            ];
        }

        return response()->json([
            'adjustment_item_id' => (int) $line->id,
            'item_name' => $line->item_name,
            'qty_received' => round($qtyReceived, 4),
            'received_unit_name' => $line->unit_name,
            'units' => $units,
        ]);
    }

    public function generateSerials(Request $request, $adjustmentItemId)
    {
        $validated = $request->validate([
            'unit_id' => 'required|integer|exists:units,id',
            'repack_unit_id' => 'nullable|integer|exists:units,id',
            'repack_qty' => 'nullable|numeric|min:0.01',
        ]);

        $line = $this->loadAdjustmentItemForSerials((int) $adjustmentItemId);
        if (!$line) {
            return response()->json(['message' => 'Baris adjustment tidak ditemukan'], 404);
        }
        if ($line->adjustment_type !== 'in') {
            return response()->json(['message' => 'Generate serial hanya untuk Stock In'], 422);
        }

        $targetUnitId = (int) $validated['unit_id'];
        $validUnitIds = collect([$line->small_unit_id, $line->medium_unit_id, $line->large_unit_id])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (!in_array($targetUnitId, $validUnitIds, true)) {
            return response()->json(['message' => 'Unit tidak sesuai konversi item'], 422);
        }

        $receivedUnitId = $this->resolveAdjustmentItemUnitId($line);
        $smallConv = (float) ($line->small_conversion_qty ?: 1);
        $mediumConv = (float) ($line->medium_conversion_qty ?: 1);
        $qtyReceived = (float) ($line->qty ?: 0);

        $qtySmall = $qtyReceived;
        if ($receivedUnitId === (int) $line->medium_unit_id) {
            $qtySmall = $qtyReceived * $smallConv;
        } elseif ($receivedUnitId === (int) $line->large_unit_id) {
            $qtySmall = $qtyReceived * $smallConv * $mediumConv;
        }

        $convertedQty = $qtySmall;
        if ($targetUnitId === (int) $line->medium_unit_id) {
            $convertedQty = $smallConv > 0 ? ($qtySmall / $smallConv) : 0;
        } elseif ($targetUnitId === (int) $line->large_unit_id) {
            $divider = $smallConv * $mediumConv;
            $convertedQty = $divider > 0 ? ($qtySmall / $divider) : 0;
        }

        $repackUnitId = $request->input('repack_unit_id');
        $repackQty = (float) $request->input('repack_qty', 0);

        if ($repackUnitId && $repackQty > 0) {
            $serialCount = (int) ceil($convertedQty / $repackQty);
        } else {
            $repackUnitId = null;
            $repackQty = null;
            $serialCount = (int) round($convertedQty);
            if ($serialCount <= 0 || abs($convertedQty - $serialCount) > 0.00001) {
                return response()->json([
                    'message' => 'Qty hasil konversi harus bilangan bulat positif agar bisa generate serial.',
                    'converted_qty' => round($convertedQty, 4),
                ], 422);
            }
        }

        if ($serialCount <= 0) {
            return response()->json(['message' => 'Jumlah serial yang akan digenerate harus lebih dari 0.'], 422);
        }

        $warehouseId = (int) $line->warehouse_id;
        $inventoryItemId = DB::table('food_inventory_items')->where('item_id', $line->item_id)->value('id');
        if (!$inventoryItemId) {
            return response()->json(['message' => 'Master food_inventory_items belum ada untuk item ini.'], 422);
        }

        $stock = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('warehouse_id', $warehouseId)
            ->first();
        $costSmall = 0.0;
        if ($stock && (float) $stock->qty_small > 0) {
            $costSmall = (float) $stock->value / (float) $stock->qty_small;
        } elseif ($stock) {
            $costSmall = (float) ($stock->last_cost_small ?? 0);
        }
        $costMedium = $costSmall * $smallConv;
        $costLarge = $costMedium * $mediumConv;

        DB::beginTransaction();
        try {
            if (InventorySerialInUse::existsInUseFor(function ($q) use ($line, $targetUnitId) {
                $q->where('source_type', 'stock_adjustment')
                    ->where('source_item_id', $line->id)
                    ->where('unit_id', $targetUnitId);
            })) {
                DB::rollBack();

                return response()->json(['message' => InventorySerialInUse::failureMessage()], 422);
            }

            DB::table('inventory_item_serials')
                ->where('source_type', 'stock_adjustment')
                ->where('source_item_id', $line->id)
                ->where('unit_id', $targetUnitId)
                ->delete();

            $now = now();
            $rows = [];
            for ($i = 0; $i < $serialCount; $i++) {
                $rows[] = [
                    'source_type' => 'stock_adjustment',
                    'source_id' => $line->adjustment_id,
                    'source_item_id' => $line->id,
                    'warehouse_id' => $warehouseId,
                    'inventory_item_id' => $inventoryItemId,
                    'item_id' => $line->item_id,
                    'unit_id' => $targetUnitId,
                    'serial_number' => $this->generateUniqueAdjustmentSerialNumber(),
                    'source_qty' => $qtyReceived,
                    'source_unit_id' => $receivedUnitId,
                    'generated_qty_unit' => $convertedQty,
                    'cost_small' => $costSmall,
                    'cost_medium' => $costMedium,
                    'cost_large' => $costLarge,
                    'ref_gr_number' => $line->adjustment_number,
                    'ref_po_number' => null,
                    'ref_pr_number' => null,
                    'repack_unit_id' => $repackUnitId,
                    'repack_qty' => $repackQty,
                    'generated_by' => Auth::id(),
                    'generated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('inventory_item_serials')->insert($rows);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil generate {$serialCount} serial.",
                'total' => $serialCount,
                'converted_qty' => round($convertedQty, 4),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function serialList($adjustmentItemId)
    {
        $line = $this->loadAdjustmentItemForSerials((int) $adjustmentItemId);
        if (!$line) {
            return response()->json(['message' => 'Baris adjustment tidak ditemukan'], 404);
        }

        $rows = DB::table('inventory_item_serials as s')
            ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
            ->leftJoin('units as ru', 'ru.id', '=', 's.repack_unit_id')
            ->select(
                's.id',
                's.serial_number',
                's.ref_gr_number as adjustment_number',
                's.generated_at',
                's.repack_unit_id',
                's.repack_qty',
                'u.name as unit_name',
                'ru.name as repack_unit_name'
            )
            ->where('s.source_type', 'stock_adjustment')
            ->where('s.source_item_id', $adjustmentItemId)
            ->orderBy('s.id')
            ->get();

        return response()->json($rows);
    }

    public function rollbackSerials(Request $request, $adjustmentItemId)
    {
        $validated = $request->validate([
            'unit_id' => 'nullable|integer|exists:units,id',
        ]);

        $line = $this->loadAdjustmentItemForSerials((int) $adjustmentItemId);
        if (!$line) {
            return response()->json(['message' => 'Baris adjustment tidak ditemukan'], 404);
        }
        if ($line->adjustment_type !== 'in') {
            return response()->json(['message' => 'Rollback serial hanya untuk Stock In'], 422);
        }

        $query = DB::table('inventory_item_serials')
            ->where('source_type', 'stock_adjustment')
            ->where('source_item_id', $adjustmentItemId);

        if (!empty($validated['unit_id'])) {
            $query->where('unit_id', (int) $validated['unit_id']);
        }

        if (InventorySerialInUse::existsInUseFor(function ($q) use ($adjustmentItemId, $validated) {
            $q->where('source_type', 'stock_adjustment')->where('source_item_id', $adjustmentItemId);
            if (!empty($validated['unit_id'])) {
                $q->where('unit_id', (int) $validated['unit_id']);
            }
        })) {
            return response()->json([
                'success' => false,
                'message' => InventorySerialInUse::failureMessage(),
            ], 422);
        }

        $deleted = $query->delete();

        return response()->json([
            'success' => true,
            'message' => "Rollback serial berhasil. Terhapus: {$deleted}",
            'deleted' => $deleted,
        ]);
    }

    private function loadAdjustmentItemForSerials(int $adjustmentItemId): ?object
    {
        return DB::table('food_inventory_adjustment_items as ai')
            ->join('food_inventory_adjustments as adj', 'adj.id', '=', 'ai.adjustment_id')
            ->join('items as i', 'i.id', '=', 'ai.item_id')
            ->leftJoin('units as u_small', 'u_small.id', '=', 'i.small_unit_id')
            ->leftJoin('units as u_medium', 'u_medium.id', '=', 'i.medium_unit_id')
            ->leftJoin('units as u_large', 'u_large.id', '=', 'i.large_unit_id')
            ->select(
                'ai.id',
                'ai.adjustment_id',
                'ai.item_id',
                'ai.qty',
                'ai.unit as unit_name',
                'adj.type as adjustment_type',
                'adj.warehouse_id',
                'adj.number as adjustment_number',
                'adj.status as adjustment_status',
                'i.name as item_name',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'u_small.name as small_unit_name',
                'u_medium.name as medium_unit_name',
                'u_large.name as large_unit_name'
            )
            ->where('ai.id', $adjustmentItemId)
            ->first();
    }

    private function resolveAdjustmentItemUnitId(object $line): ?int
    {
        $unitName = trim((string) $line->unit_name);
        if ($unitName !== '' && $unitName === (string) $line->small_unit_name) {
            return (int) $line->small_unit_id;
        }
        if ($unitName !== '' && $unitName === (string) $line->medium_unit_name) {
            return (int) $line->medium_unit_id;
        }
        if ($unitName !== '' && $unitName === (string) $line->large_unit_name) {
            return (int) $line->large_unit_id;
        }

        return $line->small_unit_id ? (int) $line->small_unit_id : null;
    }

    private function generateUniqueAdjustmentSerialNumber(): string
    {
        $prefix = 'A' . now()->format('ymdHi');

        for ($i = 0; $i < 10; $i++) {
            $serial = $prefix . strtoupper(Str::random(4));
            if (!DB::table('inventory_item_serials')->where('serial_number', $serial)->exists()) {
                return $serial;
            }
        }

        return $prefix . strtoupper(Str::random(6));
    }
}

// Model relasi header-detail
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodInventoryAdjustment extends Model
{
    protected $table = 'food_inventory_adjustments';
    protected $guarded = [];
    public function items()
    {
        return $this->hasMany(FoodInventoryAdjustmentItem::class, 'adjustment_id');
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

class FoodInventoryAdjustmentItem extends Model
{
    protected $table = 'food_inventory_adjustment_items';
    protected $guarded = [];
    public function adjustment()
    {
        return $this->belongsTo(FoodInventoryAdjustment::class, 'adjustment_id');
    }
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
} 