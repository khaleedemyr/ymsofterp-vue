<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\OutletFoodInventoryAdjustment;
use App\Models\OutletFoodInventoryAdjustmentItem;
use App\Models\OutletFoodInventoryStock;
use App\Models\OutletFoodInventoryItem;
use App\Models\OutletFoodInventoryCard;
use App\Models\Outlet;
use App\Models\Item;
use App\Models\User;
use App\Exports\OutletStockAdjustmentDetailExport;
use App\Services\NotificationService;
use Maatwebsite\Excel\Facades\Excel;

class OutletFoodInventoryAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = OutletFoodInventoryAdjustment::with(['items', 'outlet', 'creator'])
            ->leftJoin('warehouse_outlets as wo', 'outlet_food_inventory_adjustments.warehouse_outlet_id', '=', 'wo.id')
            ->select('outlet_food_inventory_adjustments.*', 'wo.name as warehouse_outlet_name');
        if ($request->search) {
            $search = $request->search;
            $query->whereHas('items', function($q) use ($search) {
                $q->where('item_id', $search);
            });
        }
        // Validasi outlet: jika user id_outlet==1 bisa lihat semua, selain itu hanya outlet user
        if ($user->id_outlet != 1) {
            $query->where('id_outlet', $user->id_outlet);
        } else if ($request->outlet_id) {
            $query->where('id_outlet', $request->outlet_id);
        }
        if ($request->from) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('date', '<=', $request->to);
        }
        $adjustments = $query->orderByDesc('date')->paginate(10)->withQueryString();
        // Tambahkan created_by ke setiap item
        $adjustments->getCollection()->transform(function($item) {
            $item->created_by = $item->created_by;
            return $item;
        });

        // Check if user can delete
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        return inertia('OutletFoodInventoryAdjustment/Index', [
            'adjustments' => $adjustments,
            'filters' => $request->only(['search', 'outlet_id', 'from', 'to']),
            'user_outlet_id' => $user->id_outlet,
            'canDelete' => $canDelete,
            'auth' => [
                'user' => $user
            ],
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        if ($user->id_outlet == 1) {
            $outlets = \DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();
            $outlet_selectable = true;
        } else {
            $outlet = \DB::table('tbl_data_outlet')
                ->where('id_outlet', $user->id_outlet)
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->first();
            $outlets = $outlet ? collect([$outlet]) : collect();
            $outlet_selectable = false;
        }
        $items = Item::all();
        // Ambil warehouse outlet hanya untuk outlet user dan status aktif
        if ($user->id_outlet == 1) {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        } else {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('outlet_id', $user->id_outlet)
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        }
        return inertia('OutletFoodInventoryAdjustment/Form', [
            'outlets' => $outlets,
            'items' => $items,
            'outlet_selectable' => $outlet_selectable,
            'user_outlet_id' => $user->id_outlet,
            'warehouse_outlets' => $warehouse_outlets,
        ]);
    }

    /**
     * API: Get warehouse outlets by outlet ID
     */
    public function getWarehouseOutlets(Request $request)
    {
        $outlet_id = $request->input('outlet_id');
        
        if (!$outlet_id) {
            return response()->json(['error' => 'Outlet ID is required'], 400);
        }

        $warehouse_outlets = DB::table('warehouse_outlets')
            ->where('outlet_id', $outlet_id)
            ->where('status', 'active')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        return response()->json($warehouse_outlets);
    }

    /**
     * API: List outlet stock adjustments (Approval App)
     */
    public function apiIndex(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $query = DB::table('outlet_food_inventory_adjustments as adj')
            ->leftJoin('tbl_data_outlet as o', 'adj.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'adj.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as creator', 'adj.created_by', '=', 'creator.id')
            ->select(
                'adj.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'creator.nama_lengkap as creator_name',
                'creator.avatar as creator_avatar'
            );

        if ($user->id_outlet != 1) {
            $query->where('adj.id_outlet', $user->id_outlet);
        } elseif ($request->outlet_id) {
            $query->where('adj.id_outlet', $request->outlet_id);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('adj.number', 'like', "%{$search}%")
                    ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                    ->orWhere('creator.nama_lengkap', 'like', "%{$search}%");
            });
        }

        if ($request->from) {
            $query->whereDate('adj.date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('adj.date', '<=', $request->to);
        }

        $perPage = (int) $request->input('per_page', 10);
        $adjustments = $query->orderByDesc('adj.date')->paginate($perPage);

        return response()->json($adjustments);
    }

    /**
     * API: Show outlet stock adjustment detail (Approval App)
     */
    public function apiShow($id)
    {
        $adjustment = DB::table('outlet_food_inventory_adjustments as adj')
            ->leftJoin('tbl_data_outlet as o', 'adj.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'adj.created_by', '=', 'u.id')
            ->leftJoin('warehouse_outlets as wo', 'adj.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'adj.*',
                'o.nama_outlet',
                'u.nama_lengkap as creator_nama_lengkap',
                'u.avatar as creator_avatar',
                'wo.name as warehouse_outlet_name',
                'wo.id as warehouse_outlet_id'
            )
            ->where('adj.id', $id)
            ->first();

        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => 'Adjustment not found'
            ], 404);
        }

        $items = DB::table('outlet_food_inventory_adjustment_items as i')
            ->leftJoin('items as it', 'i.item_id', '=', 'it.id')
            ->select('i.*', 'it.name as item_name')
            ->where('i.adjustment_id', $id)
            ->get();

        $approvalFlows = DB::table('outlet_food_inventory_adjustment_approval_flows as af')
            ->leftJoin('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->select(
                'af.*',
                'u.id as approver_id',
                'u.nik',
                'u.nama_lengkap',
                'u.email',
                'j.id_jabatan',
                'j.nama_jabatan'
            )
            ->where('af.adjustment_id', $id)
            ->orderBy('af.approval_level', 'asc')
            ->get();

        $currentApprover = null;
        $currentUserId = auth()->id();
        if ($currentUserId) {
            $currentApprover = DB::table('outlet_food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->where('approver_id', $currentUserId)
                ->where('status', 'PENDING')
                ->first();
        }

        return response()->json([
            'success' => true,
            'adjustment' => $adjustment,
            'items' => $items,
            'approval_flows' => $approvalFlows,
            'current_approval_flow_id' => $currentApprover ? $currentApprover->id : null,
        ]);
    }

    /**
     * API: Store outlet stock adjustment (Approval App)
     */
    public function apiStore(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'warehouse_outlet_id' => 'required|exists:warehouse_outlets,id',
                'type' => 'required|in:in,out',
                'reason' => 'required|string',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required|exists:items,id',
                'items.*.qty' => 'required|numeric|min:0.0001',
                'items.*.selected_unit' => 'required|string',
                'items.*.note' => 'nullable|string',
                'approvers' => 'required|array|min:1',
                'approvers.*' => 'required|exists:users,id'
            ]);

            $userId = Auth::id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi. Silakan login ulang.'
                ], 401);
            }

            DB::beginTransaction();

            $status = 'waiting_approval';
            $number = $this->generateAdjustmentNumber();

            $headerId = DB::table('outlet_food_inventory_adjustments')->insertGetId([
                'number' => $number,
                'date' => $request->date,
                'id_outlet' => $request->outlet_id,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'type' => $request->type,
                'reason' => $request->reason,
                'status' => $status,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->items as $itemIndex => $item) {
                if (empty($item['item_id'])) {
                    throw new \Exception("Item ID tidak boleh kosong untuk item ke-" . ($itemIndex + 1));
                }
                if (empty($item['qty']) || $item['qty'] <= 0) {
                    throw new \Exception("Quantity harus lebih dari 0 untuk item ke-" . ($itemIndex + 1));
                }
                if (empty($item['selected_unit'])) {
                    throw new \Exception("Unit tidak boleh kosong untuk item ke-" . ($itemIndex + 1));
                }

                $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
                if (!$itemMaster) {
                    throw new \Exception("Item master tidak ditemukan untuk item ke-" . ($itemIndex + 1));
                }

                $itemInserted = DB::table('outlet_food_inventory_adjustment_items')->insert([
                    'adjustment_id' => $headerId,
                    'item_id' => $item['item_id'],
                    'qty' => $item['qty'],
                    'unit' => $item['selected_unit'],
                    'note' => $item['note'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (!$itemInserted) {
                    throw new \Exception("Gagal menyimpan item ke-" . ($itemIndex + 1));
                }
            }

            foreach ($request->approvers as $index => $approverId) {
                if (empty($approverId)) {
                    throw new \Exception("Approver ID tidak boleh kosong untuk approver ke-" . ($index + 1));
                }

                $approverExists = DB::table('users')->where('id', $approverId)->exists();
                if (!$approverExists) {
                    throw new \Exception("Approver dengan ID {$approverId} tidak ditemukan untuk approver ke-" . ($index + 1));
                }

                $flowInserted = DB::table('outlet_food_inventory_adjustment_approval_flows')->insert([
                    'adjustment_id' => $headerId,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (!$flowInserted) {
                    throw new \Exception("Gagal menyimpan approval flow untuk approver ke-" . ($index + 1));
                }
            }

            try {
                $this->sendNotificationToNextApprover($headerId);
            } catch (\Exception $notifError) {
                \Log::warning('OutletFoodInventoryAdjustment apiStore - Notification failed:', [
                    'header_id' => $headerId,
                    'error' => $notifError->getMessage()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Outlet stock adjustment berhasil dibuat',
                'id' => $headerId,
                'number' => $number,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('OutletFoodInventoryAdjustment apiStore - Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat outlet stock adjustment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        \Log::info('OutletFoodInventoryAdjustment store method called with data:', $request->all());
        
        try {
            // Validasi dasar
            $request->validate([
                'date' => 'required|date',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'warehouse_outlet_id' => 'required|exists:warehouse_outlets,id',
                'type' => 'required|in:in,out',
                'reason' => 'required|string',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required|exists:items,id',
                'items.*.qty' => 'required|numeric|min:0.0001',
                'items.*.selected_unit' => 'required|string',
                'items.*.note' => 'nullable|string',
                'approvers' => 'required|array|min:1',
                'approvers.*' => 'required|exists:users,id'
            ]);
            
            // Get user ID
            $userId = Auth::id() ?? auth()->id();
            if (!$userId) {
                \Log::error('OutletFoodInventoryAdjustment store - No user ID found!');
                throw new \Exception('User tidak terautentikasi. Silakan login ulang.');
            }
            
            DB::beginTransaction();
            
            // Approvers is now required, so status will always be waiting_approval
            $status = 'waiting_approval';
            
            $number = $this->generateAdjustmentNumber();
            $headerId = DB::table('outlet_food_inventory_adjustments')->insertGetId([
                'number' => $number,
                'date' => $request->date,
                'id_outlet' => $request->outlet_id,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'type' => $request->type,
                'reason' => $request->reason,
                'status' => $status,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            \Log::info('OutletFoodInventoryAdjustment store - Header created:', [
                'header_id' => $headerId,
                'created_by' => $userId
            ]);
            
            // Process items with validation
            foreach ($request->items as $itemIndex => $item) {
                try {
                    // Validasi item data
                    if (empty($item['item_id'])) {
                        throw new \Exception("Item ID tidak boleh kosong untuk item ke-" . ($itemIndex + 1));
                    }
                    if (empty($item['qty']) || $item['qty'] <= 0) {
                        throw new \Exception("Quantity harus lebih dari 0 untuk item ke-" . ($itemIndex + 1));
                    }
                    if (empty($item['selected_unit'])) {
                        throw new \Exception("Unit tidak boleh kosong untuk item ke-" . ($itemIndex + 1));
                    }
                    
                    // Cek item master
                    $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
                    if (!$itemMaster) {
                        throw new \Exception("Item master tidak ditemukan untuk item ke-" . ($itemIndex + 1));
                    }
                    
                    // Insert item
                    $itemInserted = DB::table('outlet_food_inventory_adjustment_items')->insert([
                        'adjustment_id' => $headerId,
                        'item_id' => $item['item_id'],
                        'qty' => $item['qty'],
                        'unit' => $item['selected_unit'],
                        'note' => $item['note'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    if (!$itemInserted) {
                        throw new \Exception("Gagal menyimpan item ke-" . ($itemIndex + 1));
                    }
                } catch (\Exception $itemError) {
                    // Re-throw dengan informasi item index
                    throw new \Exception("Error pada item ke-" . ($itemIndex + 1) . ": " . $itemError->getMessage());
                }
            }
            
            // Create approval flows (approvers is required)
            if (!is_array($request->approvers)) {
                throw new \Exception("Format approvers tidak valid. Harus berupa array.");
            }
            
            foreach ($request->approvers as $index => $approverId) {
                if (empty($approverId)) {
                    throw new \Exception("Approver ID tidak boleh kosong untuk approver ke-" . ($index + 1));
                }
                
                // Validasi approver exists
                $approverExists = DB::table('users')->where('id', $approverId)->exists();
                if (!$approverExists) {
                    throw new \Exception("Approver dengan ID {$approverId} tidak ditemukan untuk approver ke-" . ($index + 1));
                }
                
                $flowInserted = DB::table('outlet_food_inventory_adjustment_approval_flows')->insert([
                    'adjustment_id' => $headerId,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                if (!$flowInserted) {
                    throw new \Exception("Gagal menyimpan approval flow untuk approver ke-" . ($index + 1));
                }
            }
            
            // Send notification to first approver
            try {
                $this->sendNotificationToNextApprover($headerId);
            } catch (\Exception $notifError) {
                \Log::warning('OutletFoodInventoryAdjustment store - Notification failed:', [
                    'header_id' => $headerId,
                    'error' => $notifError->getMessage()
                ]);
                // Tidak throw error karena notification bukan critical
            }
            
            DB::commit();
            
            // Verifikasi data tersimpan dengan baik
            $headerExists = DB::table('outlet_food_inventory_adjustments')->where('id', $headerId)->exists();
            if (!$headerExists) {
                throw new \Exception("Header tidak ditemukan setelah commit. Kemungkinan terjadi error saat insert.");
            }
            
            $itemsCount = DB::table('outlet_food_inventory_adjustment_items')->where('adjustment_id', $headerId)->count();
            if ($itemsCount !== count($request->items)) {
                throw new \Exception("Jumlah item yang tersimpan ({$itemsCount}) tidak sesuai dengan jumlah item yang dikirim (" . count($request->items) . ").");
            }
            
            \Log::info('OutletFoodInventoryAdjustment store - Successfully saved:', [
                'header_id' => $headerId,
                'type' => $request->type,
                'outlet_id' => $request->outlet_id,
                'date' => $request->date,
                'status' => $status
            ]);
            
            // Activity log CREATE
            try {
                $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $request->outlet_id)->value('nama_outlet') ?? 'Unknown';
                $warehouseName = DB::table('warehouse_outlets')->where('id', $request->warehouse_outlet_id)->value('name') ?? 'Unknown';
                $typeLabel = $request->type === 'in' ? 'Stock In' : 'Stock Out';
                
                DB::table('activity_logs')->insert([
                    'user_id' => $userId,
                    'activity_type' => 'create',
                    'module' => 'outlet_stock_adjustment',
                    'description' => "Membuat Outlet Stock Adjustment: {$typeLabel} - {$outletName} ({$warehouseName})",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => null,
                    'new_data' => json_encode([
                        'header_id' => $headerId,
                        'number' => $number,
                        'type' => $request->type,
                        'outlet_id' => $request->outlet_id,
                        'warehouse_outlet_id' => $request->warehouse_outlet_id,
                        'date' => $request->date,
                        'status' => $status,
                        'item_count' => count($request->items)
                    ]),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $logError) {
                // Tidak throw error karena activity log bukan critical
                \Log::warning('OutletFoodInventoryAdjustment store - Activity log failed (but data saved):', [
                    'header_id' => $headerId,
                    'error' => $logError->getMessage()
                ]);
            }
            
            return redirect()->route('outlet-food-inventory-adjustment.show', $headerId)
                ->with('success', 'Outlet stock adjustment berhasil dibuat!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            \Log::error('OutletFoodInventoryAdjustment store - Validation error:', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return back()->withErrors($e->errors())->withInput()
                ->with('error', 'Validasi gagal: ' . implode(', ', array_map(function($errors) {
                    return implode(', ', $errors);
                }, $e->errors())));
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('OutletFoodInventoryAdjustment store - Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            // Check for specific database errors
            $errorMessage = $e->getMessage();
            if (strpos($e->getMessage(), 'SQLSTATE') !== false) {
                if (strpos($e->getMessage(), 'foreign key') !== false) {
                    $errorMessage = 'Data yang dipilih tidak valid atau tidak ditemukan. Pastikan outlet, warehouse outlet, dan item yang dipilih benar.';
                } elseif (strpos($e->getMessage(), 'duplicate entry') !== false) {
                    $errorMessage = 'Data sudah ada di database. Silakan refresh halaman dan coba lagi.';
                } else {
                    $errorMessage = 'Terjadi kesalahan database. Silakan hubungi administrator jika masalah berlanjut.';
                }
            }
            
            return back()->withInput()->with('error', 'Gagal membuat outlet stock adjustment: ' . $errorMessage);
        }
    }
    
    /**
     * Get approvers for approval flow
     */
    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');
        
        $users = User::where('users.status', 'A')
            ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where(function($query) use ($search) {
                $query->where('users.nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%")
                      ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            })
            ->select('users.id', 'users.nama_lengkap as name', 'users.email', 'tbl_data_jabatan.nama_jabatan as jabatan')
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();
        
        return response()->json(['success' => true, 'users' => $users]);
    }
    
    /**
     * Send notification to the next approver in line
     */
    private function sendNotificationToNextApprover($adjustmentId)
    {
        try {
            // Get the lowest level approver that is still pending
            $nextApprovalFlow = DB::table('outlet_food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $adjustmentId)
                ->where('status', 'PENDING')
                ->orderBy('approval_level', 'asc')
                ->first();
            
            if (!$nextApprovalFlow) {
                return; // No pending approvers
            }
            
            // Get adjustment details
            $adjustment = DB::table('outlet_food_inventory_adjustments as adj')
                ->leftJoin('tbl_data_outlet as o', 'adj.id_outlet', '=', 'o.id_outlet')
                ->leftJoin('users as u', 'adj.created_by', '=', 'u.id')
                ->leftJoin('warehouse_outlets as wo', 'adj.warehouse_outlet_id', '=', 'wo.id')
                ->select(
                    'adj.*',
                    'o.nama_outlet',
                    'u.nama_lengkap as creator_name',
                    'wo.name as warehouse_outlet_name'
                )
                ->where('adj.id', $adjustmentId)
                ->first();
            
            if (!$adjustment) {
                return;
            }
            
            // Get creator details
            $creatorName = $adjustment->creator_name ?? 'Unknown User';
            $outletName = $adjustment->nama_outlet ?? 'Unknown Outlet';
            $warehouseName = $adjustment->warehouse_outlet_name ?? 'Unknown Warehouse';
            $typeLabel = $adjustment->type === 'in' ? 'Stock In' : 'Stock Out';
            
            // Create notification message
            $title = "Outlet Stock Adjustment Approval";
            $message = "Outlet Stock Adjustment baru memerlukan persetujuan Anda:\n\n";
            $message .= "No: {$adjustment->number}\n";
            $message .= "Tanggal: " . date('d/m/Y', strtotime($adjustment->date)) . "\n";
            $message .= "Outlet: {$outletName}\n";
            $message .= "Warehouse: {$warehouseName}\n";
            $message .= "Tipe: {$typeLabel}\n";
            $message .= "Level Approval: {$nextApprovalFlow->approval_level}\n";
            $message .= "Diajukan oleh: {$creatorName}\n\n";
            $message .= "Silakan segera lakukan review dan approval.";
            
            // Create notification using NotificationService (this will trigger NotificationObserver for push notification)
            NotificationService::create([
                'user_id' => $nextApprovalFlow->approver_id,
                'task_id' => $adjustmentId,
                'type' => 'outlet_stock_adjustment_approval',
                'title' => $title,
                'message' => $message,
                'url' => config('app.url') . '/outlet-food-inventory-adjustment/' . $adjustmentId,
                'is_read' => 0,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send notification to next approver', [
                'adjustment_id' => $adjustmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function show($id)
    {
        // Validate that $id is numeric (not a string like 'approvers')
        if (!is_numeric($id)) {
            abort(404, 'Adjustment not found');
        }
        
        $adjustment = DB::table('outlet_food_inventory_adjustments as adj')
            ->leftJoin('tbl_data_outlet as o', 'adj.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'adj.created_by', '=', 'u.id')
            ->leftJoin('warehouse_outlets as wo', 'adj.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'adj.*',
                'o.nama_outlet',
                'u.nama_lengkap as creator_nama_lengkap',
                'wo.name as warehouse_outlet_name',
                'wo.id as warehouse_outlet_id'
            )
            ->where('adj.id', $id)
            ->first();
        
        if (!$adjustment) {
            abort(404, 'Adjustment not found');
        }
        
        $items = DB::table('outlet_food_inventory_adjustment_items as i')
            ->leftJoin('items as it', 'i.item_id', '=', 'it.id')
            ->select('i.*', 'it.name as item_name')
            ->where('i.adjustment_id', $id)
            ->get();
        $adjustment->items = $items;
        
        // Load approval flows if exists
        $approvalFlows = DB::table('outlet_food_inventory_adjustment_approval_flows as af')
            ->leftJoin('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->select(
                'af.*',
                'u.id as approver_id',
                'u.nik',
                'u.nama_lengkap',
                'u.email',
                'j.id_jabatan',
                'j.nama_jabatan'
            )
            ->where('af.adjustment_id', $id)
            ->orderBy('af.approval_level', 'asc')
            ->get();
        
        $adjustment->approval_flows = $approvalFlows;
        
        $user = auth()->user();
        return inertia('OutletFoodInventoryAdjustment/Show', [
            'adjustment' => $adjustment,
            'user' => $user,
        ]);
    }
    
    /**
     * Get pending approvals for current user
     */
    public function getPendingApprovals()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: User not authenticated',
                'adjustments' => []
            ], 401);
        }
        
        // Superadmin: user dengan id_role = '5af56935b011a' bisa melihat semua approval
        $isSuperadmin = $user->id_role === '5af56935b011a';
        
        // Get all adjustments where user is an approver
        $query = DB::table('outlet_food_inventory_adjustment_approval_flows as af')
            ->join('outlet_food_inventory_adjustments as adj', 'af.adjustment_id', '=', 'adj.id')
            ->leftJoin('tbl_data_outlet as o', 'adj.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'adj.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as creator', 'adj.created_by', '=', 'creator.id')
            ->where('af.status', 'PENDING')
            ->where('adj.status', 'waiting_approval');
        
        // Superadmin can see all pending approvals, regular users only their own
        if (!$isSuperadmin) {
            $query->where('af.approver_id', $user->id);
        }
        
        $allPendingApprovals = $query
            ->leftJoin('users as approver', 'af.approver_id', '=', 'approver.id')
            ->select(
                'adj.id',
                'adj.number',
                'adj.date',
                'adj.type',
                'adj.reason',
                'adj.status',
                'adj.created_at',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name',
                'creator.nama_lengkap as creator_name',
                'af.approval_level',
                'approver.nama_lengkap as approver_name'
            )
            ->orderBy('adj.created_at', 'desc')
            ->get()
            ->map(function($adj) {
                // If approver_name is null, get it from the next pending approval flow
                if (!$adj->approver_name) {
                    $nextFlow = DB::table('outlet_food_inventory_adjustment_approval_flows as af')
                        ->leftJoin('users as u', 'af.approver_id', '=', 'u.id')
                        ->where('af.adjustment_id', $adj->id)
                        ->where('af.status', 'PENDING')
                        ->orderBy('af.approval_level', 'asc')
                        ->select('u.nama_lengkap as approver_name')
                        ->first();
                    $adj->approver_name = $nextFlow->approver_name ?? null;
                }
                return $adj;
            });
        
        // Filter to only show adjustments where current user is next in line
        // Skip this filter for superadmin - they can see all pending approvals
        if ($isSuperadmin) {
            $filteredApprovals = $allPendingApprovals;
        } else {
            $filteredApprovals = $allPendingApprovals->filter(function($adj) use ($user) {
                // Get all pending approval flows for this adjustment
                $pendingFlows = DB::table('outlet_food_inventory_adjustment_approval_flows')
                    ->where('adjustment_id', $adj->id)
                    ->where('status', 'PENDING')
                    ->orderBy('approval_level', 'asc')
                    ->get();
                
                if ($pendingFlows->isEmpty()) return false;
                
                // Get the next approver (lowest approval level)
                $nextApprover = $pendingFlows->first();
                return $nextApprover->approver_id === $user->id;
            });
        }
        
        return response()->json([
            'success' => true,
            'adjustments' => $filteredApprovals->values()
        ]);
    }
    
    /**
     * Get adjustment details for approval
     */
    public function getApprovalDetails($id)
    {
        $adjustment = DB::table('outlet_food_inventory_adjustments as adj')
            ->leftJoin('tbl_data_outlet as o', 'adj.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'adj.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as creator', 'adj.created_by', '=', 'creator.id')
            ->select(
                'adj.*',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name',
                'creator.nama_lengkap as creator_name',
                'creator.email as creator_email'
            )
            ->where('adj.id', $id)
            ->first();
        
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => 'Adjustment not found'
            ], 404);
        }
        
        // Get items with quantity before (from stock system) and after (from adjustment qty)
        $items = DB::table('outlet_food_inventory_adjustment_items as i')
            ->leftJoin('items as it', 'i.item_id', '=', 'it.id')
            ->leftJoin('outlet_food_inventory_items as fi', 'it.id', '=', 'fi.item_id')
            ->leftJoin('outlet_food_inventory_stocks as s', function($join) use ($adjustment) {
                $join->on('fi.id', '=', 's.inventory_item_id')
                     ->where('s.id_outlet', '=', $adjustment->id_outlet ?? null)
                     ->where('s.warehouse_outlet_id', '=', $adjustment->warehouse_outlet_id ?? null);
            })
            ->select(
                'i.*',
                'it.name as item_name',
                DB::raw('COALESCE(s.qty_small, 0) as quantity_before'),
                DB::raw('i.qty as quantity_after'),
                DB::raw('(i.qty - COALESCE(s.qty_small, 0)) as difference')
            )
            ->where('i.adjustment_id', $id)
            ->get();
        
        $approvalFlows = DB::table('outlet_food_inventory_adjustment_approval_flows as af')
            ->leftJoin('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->select(
                'af.*',
                'u.id as approver_id',
                'u.nik',
                'u.nama_lengkap',
                'u.email',
                'j.id_jabatan',
                'j.nama_jabatan'
            )
            ->where('af.adjustment_id', $id)
            ->orderBy('af.approval_level', 'asc')
            ->get();
        
        // Get current approver (pending approver for current user)
        $currentApprover = null;
        $currentUserId = auth()->id();
        if ($currentUserId) {
            $currentApprover = DB::table('outlet_food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->where('approver_id', $currentUserId)
                ->where('status', 'PENDING')
                ->first();
        }
        
        return response()->json([
            'success' => true,
            'adjustment' => $adjustment,
            'items' => $items,
            'approval_flows' => $approvalFlows,
            'current_approval_flow_id' => $currentApprover ? $currentApprover->id : null,
        ]);
    }

    public function approve(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $adj = DB::table('outlet_food_inventory_adjustments')->where('id', $id)->first();
            if (!$adj) throw new \Exception('Adjustment not found');
            
            // Support both 'note', 'comment', and 'notes' parameters
            $note = $request->input('note') ?? $request->input('comment') ?? $request->input('notes');
            
            // Check if using new approval flow system
            $hasApprovalFlows = DB::table('outlet_food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->exists();
            
            if ($hasApprovalFlows && $adj->status === 'waiting_approval') {
                // New approval flow system
                // If approval_flow_id is provided, use it; otherwise find by approver_id
                if ($request->has('approval_flow_id')) {
                    $currentApprovalFlow = DB::table('outlet_food_inventory_adjustment_approval_flows')
                        ->where('id', $request->approval_flow_id)
                        ->where('adjustment_id', $id)
                        ->where('status', 'PENDING')
                        ->first();
                } else {
                    $currentApprovalFlow = DB::table('outlet_food_inventory_adjustment_approval_flows')
                        ->where('adjustment_id', $id)
                        ->where('approver_id', $user->id)
                        ->where('status', 'PENDING')
                        ->first();
                }
                
                if (!$currentApprovalFlow) {
                    throw new \Exception('Anda tidak memiliki hak untuk approve data ini');
                }
                
                $currentLevel = $currentApprovalFlow->approval_level;
                
                // Check if lower levels are approved
                $lowerLevelsPending = DB::table('outlet_food_inventory_adjustment_approval_flows')
                    ->where('adjustment_id', $id)
                    ->where('approval_level', '<', $currentLevel)
                    ->where('status', 'PENDING')
                    ->count();
                
                if ($lowerLevelsPending > 0) {
                    throw new \Exception('Tunggu approval dari level yang lebih rendah terlebih dahulu');
                }
                
                // Update approval flow
                DB::table('outlet_food_inventory_adjustment_approval_flows')
                    ->where('id', $currentApprovalFlow->id)
                    ->update([
                        'status' => 'APPROVED',
                        'approved_at' => now(),
                        'comments' => $note,
                        'updated_at' => now(),
                    ]);
                
                // Check if there are more approvers pending
                $pendingApprovers = DB::table('outlet_food_inventory_adjustment_approval_flows')
                    ->where('adjustment_id', $id)
                    ->where('status', 'PENDING')
                    ->count();
                
                if ($pendingApprovers > 0) {
                    // Still have pending approvers
                    DB::commit();
                    $this->sendNotificationToNextApprover($id);
                    
                    DB::table('activity_logs')->insert([
                        'user_id' => $user->id,
                        'activity_type' => 'approve',
                        'module' => 'outlet_stock_adjustment',
                        'description' => 'Approve outlet stock adjustment ID: ' . $id . ' (Partial)',
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'created_at' => now(),
                    ]);
                    
                    if ($request->expectsJson() || $request->wantsJson()) {
                        return response()->json(['success' => true, 'message' => 'Approval berhasil! Notifikasi dikirim ke approver berikutnya.']);
                    } else {
                        return redirect()->route('outlet-food-inventory-adjustment.show', $id)
                            ->with('success', 'Approval berhasil! Notifikasi dikirim ke approver berikutnya.');
                    }
                } else {
                    // All approvers have approved, process inventory
                    DB::table('outlet_food_inventory_adjustments')
                        ->where('id', $id)
                        ->update([
                            'status' => 'approved',
                            'updated_at' => now(),
                        ]);
                    
                    $this->processInventory($id);
                    DB::commit();
                    
                    DB::table('activity_logs')->insert([
                        'user_id' => $user->id,
                        'activity_type' => 'approve',
                        'module' => 'outlet_stock_adjustment',
                        'description' => 'Approve outlet stock adjustment ID: ' . $id . ' (Complete)',
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'created_at' => now(),
                    ]);
                    
                    if ($request->expectsJson() || $request->wantsJson()) {
                        return response()->json(['success' => true, 'message' => 'Semua approval telah selesai! Stock telah diproses.']);
                    } else {
                        return redirect()->route('outlet-food-inventory-adjustment.show', $id)
                            ->with('success', 'Semua approval telah selesai! Stock telah diproses.');
                    }
                }
            } else {
                // Old approval system (backward compatibility)
                $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
                $update = [];
                $desc = '';
                if ($isSuperadmin) {
                    if ($adj->status == 'waiting_cost_control') {
                        $update['status'] = 'approved';
                        $update['approved_by_cost_control_manager'] = $user->id;
                        $update['approved_at_cost_control_manager'] = now();
                        $update['cost_control_manager_note'] = $request->note;
                        $desc = 'Superadmin approve tahap Cost Control Manager outlet stock adjustment ID: ' . $id;
                    } else if ($adj->status == 'waiting_approval') {
                        $update['status'] = 'waiting_cost_control';
                        $update['approved_by_ssd_manager'] = $user->id;
                        $update['approved_at_ssd_manager'] = now();
                        $update['ssd_manager_note'] = $request->note;
                        $desc = 'Superadmin approve tahap SSD Manager outlet stock adjustment ID: ' . $id;
                    } else {
                        throw new \Exception('Status dokumen tidak valid untuk approval');
                    }
                } else if ($user->id_jabatan == 167 && $adj->status == 'waiting_cost_control') {
                    $update['status'] = 'approved';
                    $update['approved_by_cost_control_manager'] = $user->id;
                    $update['approved_at_cost_control_manager'] = now();
                    $update['cost_control_manager_note'] = $request->note;
                    $desc = 'Cost Control Manager approve outlet stock adjustment ID: ' . $id;
                } else {
                    throw new \Exception('Anda tidak berhak approve pada tahap ini');
                }
                DB::table('outlet_food_inventory_adjustments')->where('id', $id)->update($update);
                DB::table('activity_logs')->insert([
                    'user_id' => $user->id,
                    'activity_type' => 'approve',
                    'module' => 'outlet_stock_adjustment',
                    'description' => $desc,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                ]);
                if (
                    ($isSuperadmin && $adj->status == 'waiting_cost_control') ||
                    ($user->id_jabatan == 167 && $adj->status == 'waiting_cost_control')
                ) {
                    $this->processInventory($id);
                }
                DB::commit();
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json(['success' => true]);
                } else {
                    return redirect()->route('outlet-food-inventory-adjustment.show', $id)
                        ->with('success', 'Outlet stock adjustment berhasil di-approve!');
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            } else {
                return back()->with('error', 'Gagal approve outlet stock adjustment: ' . $e->getMessage());
            }
        }
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
            'reason' => 'nullable|string|max:500', // Alias for rejection_reason
            'comment' => 'nullable|string|max:500', // Alias for rejection_reason
        ]);
        
        // Support 'rejection_reason', 'reason', and 'comment' parameters
        $rejectionReason = $request->input('rejection_reason') ?? $request->input('reason') ?? $request->input('comment');
        
        if (!$rejectionReason) {
            return response()->json([
                'success' => false,
                'message' => 'Alasan penolakan harus diisi'
            ], 400);
        }
        
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $adj = DB::table('outlet_food_inventory_adjustments')->where('id', $id)->first();
            if (!$adj) throw new \Exception('Adjustment not found');
            
            // Check if using new approval flow system
            $hasApprovalFlows = DB::table('outlet_food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->exists();
            
            if ($hasApprovalFlows && $adj->status === 'waiting_approval') {
                // New approval flow system
                // If approval_flow_id is provided, use it; otherwise find by approver_id
                if ($request->has('approval_flow_id')) {
                    $currentApprovalFlow = DB::table('outlet_food_inventory_adjustment_approval_flows')
                        ->where('id', $request->approval_flow_id)
                        ->where('adjustment_id', $id)
                        ->where('status', 'PENDING')
                        ->first();
                } else {
                    $currentApprovalFlow = DB::table('outlet_food_inventory_adjustment_approval_flows')
                        ->where('adjustment_id', $id)
                        ->where('approver_id', $user->id)
                        ->where('status', 'PENDING')
                        ->first();
                }
                
                if (!$currentApprovalFlow) {
                    throw new \Exception('Anda tidak memiliki hak untuk menolak data ini');
                }
                
                // Update approval flow
                DB::table('outlet_food_inventory_adjustment_approval_flows')
                    ->where('id', $currentApprovalFlow->id)
                    ->update([
                        'status' => 'REJECTED',
                        'rejected_at' => now(),
                        'comments' => $rejectionReason,
                        'updated_at' => now(),
                    ]);
                
                // Update header status
                DB::table('outlet_food_inventory_adjustments')
                    ->where('id', $id)
                    ->update([
                        'status' => 'rejected',
                        'updated_at' => now(),
                    ]);
                
                DB::commit();
                
                DB::table('activity_logs')->insert([
                    'user_id' => $user->id,
                    'activity_type' => 'reject',
                    'module' => 'outlet_stock_adjustment',
                    'description' => 'Reject outlet stock adjustment ID: ' . $id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                ]);
                
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json(['success' => true, 'message' => 'Data berhasil ditolak']);
                } else {
                    return redirect()->route('outlet-food-inventory-adjustment.show', $id)
                        ->with('success', 'Outlet stock adjustment berhasil direject!');
                }
            } else {
                // Old approval system (backward compatibility)
                $update = ['status' => 'rejected', 'updated_at' => now()];
                if ($user->id_jabatan == 167) {
                    $update['cost_control_manager_note'] = $request->note ?? $validated['rejection_reason'] ?? null;
                }
                DB::table('outlet_food_inventory_adjustments')->where('id', $id)->update($update);
                DB::table('activity_logs')->insert([
                    'user_id' => $user->id,
                    'activity_type' => 'reject',
                    'module' => 'outlet_stock_adjustment',
                    'description' => 'Reject outlet stock adjustment ID: ' . $id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                ]);
                DB::commit();
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json(['success' => true]);
                } else {
                    return redirect()->route('outlet-food-inventory-adjustment.show', $id)
                        ->with('success', 'Outlet stock adjustment berhasil direject!');
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            } else {
                return back()->with('error', 'Gagal reject outlet stock adjustment: ' . $e->getMessage());
            }
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $adj = OutletFoodInventoryAdjustment::with('items')->findOrFail($id);
            
            // Check if user can delete approved status
            if ($adj->status === 'approved') {
                // Only allow delete for approved status if user is superadmin or warehouse division
                $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
                if (!$canDelete) {
                    throw new \Exception('Anda tidak memiliki akses untuk menghapus data ini.');
                }
            }
            
            if ($adj->status === 'approved') {
                foreach ($adj->items as $item) {
                    $inventoryItem = OutletFoodInventoryItem::where('item_id', $item->item_id)->first();
                    if (!$inventoryItem) continue;
                    $inventory_item_id = $inventoryItem->id;
                    $itemMaster = Item::find($item->item_id);
                    $unit = $item->unit;
                    $qty_input = $item->qty;
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
                    // Rollback stock - harus menggunakan warehouse_outlet_id juga untuk konsistensi
                    $stock = OutletFoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                        ->where('id_outlet', $adj->id_outlet)
                        ->where('warehouse_outlet_id', $adj->warehouse_outlet_id)
                        ->first();
                    
                    if ($stock) {
                        // Rollback logic: kebalikan dari processInventory
                        // Jika type 'in' (stock ditambahkan saat approve), saat delete dikurangi
                        // Jika type 'out' (stock dikurangi saat approve), saat delete ditambahkan
                        if ($adj->type === 'in') {
                            $stock->qty_small -= $qty_small;
                            $stock->qty_medium -= $qty_medium;
                            $stock->qty_large -= $qty_large;
                        } else {
                            $stock->qty_small += $qty_small;
                            $stock->qty_medium += $qty_medium;
                            $stock->qty_large += $qty_large;
                        }
                        $stock->value = ($stock->qty_small * $stock->last_cost_small)
                            + ($stock->qty_medium * $stock->last_cost_medium)
                            + ($stock->qty_large * $stock->last_cost_large);
                        $stock->save();
                    } else {
                        \Log::warning('OutletFoodInventoryAdjustment destroy - Stock not found for rollback:', [
                            'adjustment_id' => $id,
                            'inventory_item_id' => $inventory_item_id,
                            'outlet_id' => $adj->id_outlet,
                            'warehouse_outlet_id' => $adj->warehouse_outlet_id
                        ]);
                    }
                    OutletFoodInventoryCard::where('reference_type', 'outlet_stock_adjustment')
                        ->where('reference_id', $adj->id)
                        ->where('inventory_item_id', $inventory_item_id)
                        ->delete();
                }
            }
            
            // Store data for activity log before deletion
            $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $adj->id_outlet)->value('nama_outlet') ?? 'Unknown';
            $warehouseName = DB::table('warehouse_outlets')->where('id', $adj->warehouse_outlet_id)->value('name') ?? 'Unknown';
            $typeLabel = $adj->type === 'in' ? 'Stock In' : 'Stock Out';
            $itemsCount = $adj->items->count();
            $adjustmentNumber = $adj->number;
            $adjustmentStatus = $adj->status;
            $adjustmentDate = $adj->date;
            $adjustmentType = $adj->type;
            $adjustmentOutletId = $adj->id_outlet;
            $adjustmentWarehouseOutletId = $adj->warehouse_outlet_id;
            
            $adj->items()->delete();
            
            // Delete approval flows if exists
            DB::table('outlet_food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->delete();
            
            $adj->delete();
            
            // Activity log DELETE
            try {
                DB::table('activity_logs')->insert([
                    'user_id' => $user->id,
                    'activity_type' => 'delete',
                    'module' => 'outlet_stock_adjustment',
                    'description' => "Menghapus Outlet Stock Adjustment: {$typeLabel} - {$outletName} ({$warehouseName}) - Status: {$adjustmentStatus}",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'old_data' => json_encode([
                        'adjustment_id' => $id,
                        'number' => $adjustmentNumber,
                        'type' => $adjustmentType,
                        'outlet_id' => $adjustmentOutletId,
                        'warehouse_outlet_id' => $adjustmentWarehouseOutletId,
                        'date' => $adjustmentDate,
                        'status' => $adjustmentStatus,
                        'items_count' => $itemsCount
                    ]),
                    'new_data' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $logError) {
                // Tidak throw error karena activity log bukan critical
                \Log::warning('OutletFoodInventoryAdjustment destroy - Activity log failed (but data deleted):', [
                    'adjustment_id' => $id,
                    'error' => $logError->getMessage()
                ]);
            }
            
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function generateAdjustmentNumber()
    {
        $prefix = 'OSA';
        $date = date('Ymd');
        $lastNumber = DB::table('outlet_food_inventory_adjustments')
            ->where('number', 'like', $prefix . $date . '%')
            ->orderBy('number', 'desc')
            ->first();
        
        if ($lastNumber) {
            $lastNumber = intval(substr($lastNumber->number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    private function processInventory($adjustmentId)
    {
        $header = DB::table('outlet_food_inventory_adjustments')->where('id', $adjustmentId)->first();
        $warehouseOutletId = $header ? $header->warehouse_outlet_id : null;
        $adj = OutletFoodInventoryAdjustment::with(['items', 'outlet'])->find($adjustmentId);
        if (!$adj) return;
        foreach ($adj->items as $item) {
            $inventoryItem = OutletFoodInventoryItem::where('item_id', $item->item_id)->first();
            if (!$inventoryItem) {
                $itemMaster = Item::find($item->item_id);
                $inventoryItem = OutletFoodInventoryItem::create([
                    'item_id' => $item->item_id,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $inventory_item_id = $inventoryItem->id;
            $itemMaster = Item::find($item->item_id);
            $unit = $item->unit;
            $qty_input = $item->qty;
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
            $stock = OutletFoodInventoryStock::firstOrCreate(
                [
                    'inventory_item_id' => $inventory_item_id,
                    'id_outlet' => $adj->id_outlet,
                    'warehouse_outlet_id' => $warehouseOutletId,
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
            if ($adj->type === 'in') {
                $stock->qty_small += $qty_small;
                $stock->qty_medium += $qty_medium;
                $stock->qty_large += $qty_large;
            } else {
                $stock->qty_small -= $qty_small;
                $stock->qty_medium -= $qty_medium;
                $stock->qty_large -= $qty_large;
            }
            $stock->value = ($stock->qty_small * $stock->last_cost_small)
                + ($stock->qty_medium * $stock->last_cost_medium)
                + ($stock->qty_large * $stock->last_cost_large);
            $stock->save();
            OutletFoodInventoryCard::create([
                'inventory_item_id' => $inventory_item_id,
                'id_outlet' => $adj->id_outlet,
                'warehouse_outlet_id' => $warehouseOutletId,
                'date' => $adj->date,
                'reference_type' => 'outlet_stock_adjustment',
                'reference_id' => $adj->id,
                'in_qty_small' => $adj->type === 'in' ? $qty_small : 0,
                'in_qty_medium' => $adj->type === 'in' ? $qty_medium : 0,
                'in_qty_large' => $adj->type === 'in' ? $qty_large : 0,
                'out_qty_small' => $adj->type === 'out' ? $qty_small : 0,
                'out_qty_medium' => $adj->type === 'out' ? $qty_medium : 0,
                'out_qty_large' => $adj->type === 'out' ? $qty_large : 0,
                'cost_per_small' => $stock->last_cost_small,
                'cost_per_medium' => $stock->last_cost_medium,
                'cost_per_large' => $stock->last_cost_large,
                'value_in' => $adj->type === 'in' ? $qty_small * $stock->last_cost_small : 0,
                'value_out' => $adj->type === 'out' ? $qty_small * $stock->last_cost_small : 0,
                'saldo_qty_small' => $stock->qty_small,
                'saldo_qty_medium' => $stock->qty_medium,
                'saldo_qty_large' => $stock->qty_large,
                'saldo_value' => $stock->value,
                'description' => 'Outlet Stock Adjustment',
            ]);
        }
    }

    /**
     * Universal report for outlet stock adjustment (with filter type, warehouse, date, outlet)
     */
    public function reportUniversal(Request $request)
    {
        $user = auth()->user();
        $type = $request->input('type'); // 'in' or 'out'
        $warehouse_outlet_id = $request->input('warehouse_outlet_id');
        $from = $request->input('from');
        $to = $request->input('to');
        $selected_outlet_id = $request->input('outlet_id');

        // Jika belum ada filter, return view kosong dengan filter saja
        if (!$from || !$to) {
            $types = [
                ['value' => '', 'label' => 'Semua'],
                ['value' => 'in', 'label' => 'Stock In'],
                ['value' => 'out', 'label' => 'Stock Out'],
            ];
            
            // Filter warehouse outlets based on user outlet
            $warehouse_outlets_query = DB::table('warehouse_outlets')->where('status', 'active');
            if ($user->id_outlet != 1) {
                $warehouse_outlets_query->where('outlet_id', $user->id_outlet);
            }
            $warehouse_outlets = $warehouse_outlets_query->select('id', 'name', 'outlet_id')->orderBy('name')->get();
            
            // Filter outlets: jika user bukan admin, hanya tampilkan outlet mereka sendiri
            $outlets_query = DB::table('tbl_data_outlet');
            if ($user->id_outlet != 1) {
                $outlets_query->where('id_outlet', $user->id_outlet);
            }
            $outlets = $outlets_query->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
            
            // Set default outlet_id untuk non-admin
            $filters = $request->only(['type', 'warehouse_outlet_id', 'from', 'to', 'outlet_id']);
            if ($user->id_outlet != 1) {
                $filters['outlet_id'] = $user->id_outlet;
            }

            return inertia('OutletFoodInventoryAdjustment/ReportUniversal', [
                'data' => [],
                'types' => $types,
                'warehouse_outlets' => $warehouse_outlets,
                'outlets' => $outlets,
                'filters' => $filters,
                'total_per_type' => [],
                'user_outlet_id' => $user->id_outlet,
            ]);
        }

        // Validasi: Maksimal range 3 bulan untuk mencegah timeout
        $fromDate = \Carbon\Carbon::parse($from);
        $toDate = \Carbon\Carbon::parse($to);
        $diffMonths = $fromDate->diffInMonths($toDate);
        
        if ($diffMonths > 3) {
            return redirect()->route('outlet-food-inventory-adjustment.report-universal')
                ->with('error', 'Range tanggal maksimal 3 bulan. Silakan pilih range yang lebih kecil.')
                ->withInput($request->only(['type', 'warehouse_outlet_id', 'from', 'to', 'outlet_id']));
        }

        // Validasi outlet: jika user bukan admin, gunakan outlet user
        if ($user->id_outlet != 1) {
            $selected_outlet_id = $user->id_outlet;
        }

        $query = DB::table('outlet_food_inventory_adjustments as adj')
            ->leftJoin('tbl_data_outlet as o', 'adj.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'adj.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'adj.created_by', '=', 'u.id')
            ->select(
                'adj.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );
            
        if ($user->id_outlet != 1) {
            $query->where('adj.id_outlet', $user->id_outlet);
        } else if ($request->filled('outlet_id')) {
            $query->where('adj.id_outlet', $request->input('outlet_id'));
        }
        
        if ($type) {
            $query->where('adj.type', $type);
        }
        
        if ($warehouse_outlet_id) {
            $query->where('adj.warehouse_outlet_id', $warehouse_outlet_id);
        }
        
        // Filter date wajib
        $query->where('adj.date', '>=', $from);
        $query->where('adj.date', '<=', $to);
        
        // Hanya ambil yang sudah approved (status = 'approved')
        $query->where('adj.status', 'approved');
        
        $data = $query->orderByDesc('adj.date')->orderByDesc('adj.id')->get();

        // Hitung total per type dan hitung MAC untuk setiap item
        $headerIds = $data->pluck('id')->all();
        $totalPerType = [];
        $subtotalPerHeader = [];
        
        if ($headerIds && count($headerIds) > 0) {
            // Batch query untuk details
            $details = DB::table('outlet_food_inventory_adjustment_items as i')
                ->join('outlet_food_inventory_adjustments as adj', 'i.adjustment_id', '=', 'adj.id')
                ->leftJoin('items as it', 'i.item_id', '=', 'it.id')
                ->leftJoin('outlet_food_inventory_items as fi', 'it.id', '=', 'fi.item_id')
                ->leftJoin('units as u_small', 'it.small_unit_id', '=', 'u_small.id')
                ->leftJoin('units as u_medium', 'it.medium_unit_id', '=', 'u_medium.id')
                ->leftJoin('units as u_large', 'it.large_unit_id', '=', 'u_large.id')
                ->select(
                    'i.*',
                    'adj.type as adjustment_type',
                    'adj.date as adjustment_date',
                    'adj.id_outlet',
                    'adj.warehouse_outlet_id',
                    'it.small_unit_id',
                    'it.medium_unit_id',
                    'it.large_unit_id',
                    'it.small_conversion_qty',
                    'it.medium_conversion_qty',
                    'u_small.name as small_unit_name',
                    'u_medium.name as medium_unit_name',
                    'u_large.name as large_unit_name',
                    'fi.id as inventory_item_id'
                )
                ->whereIn('i.adjustment_id', $headerIds)
                ->get();
            
            // Batch query untuk inventory items
            $itemIds = $details->pluck('item_id')->unique()->all();
            $inventoryItems = [];
            if (count($itemIds) > 0) {
                $inventoryItemsData = DB::table('outlet_food_inventory_items')
                    ->whereIn('item_id', $itemIds)
                    ->get()
                    ->keyBy('item_id');
                $inventoryItems = $inventoryItemsData->toArray();
            }
            
            // Batch query untuk MAC histories
            $inventoryItemIds = collect($inventoryItems)->pluck('id')->unique()->all();
            $macHistories = [];
            if (count($inventoryItemIds) > 0 && count($headerIds) > 0) {
                $headerData = $data->keyBy('id');
                $macQueryConditions = [];
                foreach ($details as $detail) {
                    $header = $headerData->get($detail->adjustment_id);
                    if ($header && isset($inventoryItems[$detail->item_id])) {
                        $inventoryItemId = $inventoryItems[$detail->item_id]->id;
                        $key = "{$inventoryItemId}_{$header->id_outlet}_{$header->warehouse_outlet_id}_{$header->date}";
                        if (!isset($macQueryConditions[$key])) {
                            $macQueryConditions[$key] = [
                                'inventory_item_id' => $inventoryItemId,
                                'id_outlet' => $header->id_outlet,
                                'warehouse_outlet_id' => $header->warehouse_outlet_id,
                                'date' => $header->date
                            ];
                        }
                    }
                }
                
                // Batch query MAC histories
                foreach ($macQueryConditions as $condition) {
                    $macRow = DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $condition['inventory_item_id'])
                        ->where('id_outlet', $condition['id_outlet'])
                        ->where('warehouse_outlet_id', $condition['warehouse_outlet_id'])
                        ->where('date', '<=', $condition['date'])
                        ->orderByDesc('date')
                        ->orderByDesc('id')
                        ->first();
                    if ($macRow) {
                        $macKey = "{$condition['inventory_item_id']}_{$condition['id_outlet']}_{$condition['warehouse_outlet_id']}_{$condition['date']}";
                        $macHistories[$macKey] = $macRow->mac;
                    }
                }
            }
            
            // Calculate totals per type
            foreach ($details as $item) {
                $mac = null;
                if (isset($inventoryItems[$item->item_id])) {
                    $inventoryItem = $inventoryItems[$item->item_id];
                    $header = $data->firstWhere('id', $item->adjustment_id);
                    if ($header) {
                        $macKey = "{$inventoryItem->id}_{$header->id_outlet}_{$header->warehouse_outlet_id}_{$header->date}";
                        if (isset($macHistories[$macKey])) {
                            $mac = $macHistories[$macKey];
                        }
                    }
                }
                
                // Convert qty to small unit for MAC calculation
                $qty_small = $item->qty;
                // Compare unit name with item's unit names
                if ($item->unit == $item->medium_unit_name && $item->small_conversion_qty > 0) {
                    $qty_small = $item->qty * $item->small_conversion_qty;
                } elseif ($item->unit == $item->large_unit_name && $item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) {
                    $qty_small = $item->qty * $item->small_conversion_qty * $item->medium_conversion_qty;
                }
                // If unit is small unit, qty_small remains as is
                
                $subtotal_mac = ($mac !== null) ? ($mac * $qty_small) : 0;
                $adjType = $item->adjustment_type;
                if (!isset($totalPerType[$adjType])) $totalPerType[$adjType] = 0;
                $totalPerType[$adjType] += $subtotal_mac;
            }
            
            // Calculate subtotal MAC per header
            foreach ($details as $item) {
                $mac = null;
                if (isset($inventoryItems[$item->item_id])) {
                    $inventoryItem = $inventoryItems[$item->item_id];
                    $header = $data->firstWhere('id', $item->adjustment_id);
                    if ($header) {
                        $macKey = "{$inventoryItem->id}_{$header->id_outlet}_{$header->warehouse_outlet_id}_{$header->date}";
                        if (isset($macHistories[$macKey])) {
                            $mac = $macHistories[$macKey];
                        }
                    }
                }
                
                // Convert qty to small unit for MAC calculation
                $qty_small = $item->qty;
                // Compare unit name with item's unit names
                if ($item->unit == $item->medium_unit_name && $item->small_conversion_qty > 0) {
                    $qty_small = $item->qty * $item->small_conversion_qty;
                } elseif ($item->unit == $item->large_unit_name && $item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) {
                    $qty_small = $item->qty * $item->small_conversion_qty * $item->medium_conversion_qty;
                }
                // If unit is small unit, qty_small remains as is
                
                $subtotal_mac = ($mac !== null) ? ($mac * $qty_small) : 0;
                
                if (!isset($subtotalPerHeader[$item->adjustment_id])) {
                    $subtotalPerHeader[$item->adjustment_id] = 0;
                }
                $subtotalPerHeader[$item->adjustment_id] += $subtotal_mac;
            }
            
            // Add subtotal_mac to each header row
            $data = collect($data)->map(function($row) use ($subtotalPerHeader) {
                $row->subtotal_mac = $subtotalPerHeader[$row->id] ?? 0;
                return $row;
            });
        } else {
            // If no details, set subtotal_mac to 0 for all rows
            $data = collect($data)->map(function($row) {
                $row->subtotal_mac = 0;
                return $row;
            });
        }

        $types = [
            ['value' => '', 'label' => 'Semua'],
            ['value' => 'in', 'label' => 'Stock In'],
            ['value' => 'out', 'label' => 'Stock Out'],
        ];
        
        // Filter warehouse outlets based on selected outlet or user's outlet
        $warehouse_outlets_query = DB::table('warehouse_outlets')->where('status', 'active');
        if ($user->id_outlet == 1) {
            // For superuser, filter by selected outlet if any
            if ($selected_outlet_id) {
                $warehouse_outlets_query->where('outlet_id', $selected_outlet_id);
            }
        } else {
            // For regular user, only show warehouse outlets for their outlet
            $warehouse_outlets_query->where('outlet_id', $user->id_outlet);
        }
        $warehouse_outlets = $warehouse_outlets_query->select('id', 'name', 'outlet_id')->orderBy('name')->get();
        
        // Filter outlets: jika user bukan admin, hanya tampilkan outlet mereka sendiri
        $outlets_query = DB::table('tbl_data_outlet');
        if ($user->id_outlet != 1) {
            $outlets_query->where('id_outlet', $user->id_outlet);
        }
        $outlets = $outlets_query->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

        return inertia('OutletFoodInventoryAdjustment/ReportUniversal', [
            'data' => $data,
            'types' => $types,
            'warehouse_outlets' => $warehouse_outlets,
            'outlets' => $outlets,
            'filters' => $request->only(['type', 'warehouse_outlet_id', 'from', 'to', 'outlet_id']),
            'total_per_type' => $totalPerType,
            'user_outlet_id' => $user->id_outlet,
        ]);
    }

    /**
     * Export Outlet Stock Adjustment Detail to Excel
     */
    public function exportDetail(Request $request)
    {
        $user = auth()->user();

        $search = $request->input('search');
        $from = $request->input('from');
        $to = $request->input('to');

        if (!$from || !$to) {
            return redirect()->route('outlet-food-inventory-adjustment.index', [
                'search' => $search,
                'from' => $from,
                'to' => $to,
            ])->with('error', 'Filter tanggal (From Date dan To Date) wajib diisi untuk export detail.');
        }

        $fromDate = \Carbon\Carbon::parse($from);
        $toDate = \Carbon\Carbon::parse($to);
        $diffMonths = $fromDate->diffInMonths($toDate);

        if ($diffMonths > 3) {
            return redirect()->route('outlet-food-inventory-adjustment.index', [
                'search' => $search,
                'from' => $from,
                'to' => $to,
            ])->with('error', 'Range tanggal maksimal 3 bulan untuk export detail.');
        }

        $export = new OutletStockAdjustmentDetailExport(
            $search,
            $from,
            $to,
            $user->id_outlet
        );

        $dateSuffix = now()->format('Ymd_His');
        $fileName = 'Outlet_Stock_Adjustment_Detail_' . $dateSuffix . '.xlsx';

        return Excel::download($export, $fileName);
    }

    /**
     * Export Outlet Stock Adjustment Report to Excel
     */
    public function exportReportUniversal(Request $request)
    {
        $user = auth()->user();
        $type = $request->input('type');
        $warehouseOutletId = $request->input('warehouse_outlet_id');
        $outletId = $request->input('outlet_id');
        $from = $request->input('from');
        $to = $request->input('to');
        
        // Validasi tanggal wajib
        if (!$from || !$to) {
            return redirect()->route('outlet-food-inventory-adjustment.report-universal')
                ->with('error', 'Filter tanggal (Dari dan Sampai) wajib diisi untuk export.')
                ->withInput($request->only(['type', 'warehouse_outlet_id', 'from', 'to', 'outlet_id']));
        }
        
        // Validasi: Maksimal range 3 bulan
        $fromDate = \Carbon\Carbon::parse($from);
        $toDate = \Carbon\Carbon::parse($to);
        $diffMonths = $fromDate->diffInMonths($toDate);
        
        if ($diffMonths > 3) {
            return redirect()->route('outlet-food-inventory-adjustment.report-universal')
                ->with('error', 'Range tanggal maksimal 3 bulan. Silakan pilih range yang lebih kecil.')
                ->withInput($request->only(['type', 'warehouse_outlet_id', 'from', 'to', 'outlet_id']));
        }
        
        // Validasi outlet: jika user bukan admin, gunakan outlet user
        if ($user->id_outlet != 1) {
            $outletId = $user->id_outlet;
        }
        
        $export = new \App\Exports\OutletStockAdjustmentReportExport(
            $type,
            $warehouseOutletId,
            $outletId,
            $from,
            $to,
            $user->id_outlet
        );
        
        $fileName = 'Outlet_Stock_Adjustment_Report_' . $from . '_' . $to . '.xlsx';
        
        return Excel::download($export, $fileName);
    }

    /**
     * Get adjustment details with MAC calculation for report
     */
    public function getAdjustmentDetailsForReport($id)
    {
        $adjustment = DB::table('outlet_food_inventory_adjustments as adj')
            ->leftJoin('tbl_data_outlet as o', 'adj.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'adj.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'adj.*',
                'o.nama_outlet',
                'wo.name as warehouse_outlet_name'
            )
            ->where('adj.id', $id)
            ->first();
        
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => 'Adjustment not found'
            ], 404);
        }
        
        // Get items with MAC calculation
        $items = DB::table('outlet_food_inventory_adjustment_items as i')
            ->leftJoin('items as it', 'i.item_id', '=', 'it.id')
            ->leftJoin('outlet_food_inventory_items as fi', 'it.id', '=', 'fi.item_id')
            ->leftJoin('units as u_small', 'it.small_unit_id', '=', 'u_small.id')
            ->leftJoin('units as u_medium', 'it.medium_unit_id', '=', 'u_medium.id')
            ->leftJoin('units as u_large', 'it.large_unit_id', '=', 'u_large.id')
            ->select(
                'i.*',
                'it.name as item_name',
                'it.small_unit_id',
                'it.medium_unit_id',
                'it.large_unit_id',
                'it.small_conversion_qty',
                'it.medium_conversion_qty',
                'u_small.name as small_unit_name',
                'u_medium.name as medium_unit_name',
                'u_large.name as large_unit_name',
                'fi.id as inventory_item_id'
            )
            ->where('i.adjustment_id', $id)
            ->get();
        
        // Get MAC for each item
        $itemsWithMac = [];
        foreach ($items as $item) {
            $mac = null;
            if ($item->inventory_item_id) {
                $macRow = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $item->inventory_item_id)
                    ->where('id_outlet', $adjustment->id_outlet)
                    ->where('warehouse_outlet_id', $adjustment->warehouse_outlet_id)
                    ->where('date', '<=', $adjustment->date)
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->first();
                if ($macRow) {
                    $mac = $macRow->mac;
                }
            }
            
            // Convert qty to small unit for MAC calculation
            $qty_small = $item->qty;
            // Compare unit name with item's unit names
            if ($item->unit == $item->medium_unit_name && $item->small_conversion_qty > 0) {
                $qty_small = $item->qty * $item->small_conversion_qty;
            } elseif ($item->unit == $item->large_unit_name && $item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) {
                $qty_small = $item->qty * $item->small_conversion_qty * $item->medium_conversion_qty;
            }
            // If unit is small unit, qty_small remains as is
            
            $subtotal_mac = ($mac !== null) ? ($mac * $qty_small) : 0;
            
            $itemsWithMac[] = [
                'id' => $item->id,
                'item_id' => $item->item_id,
                'item_name' => $item->item_name,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'note' => $item->note,
                'mac' => $mac,
                'subtotal_mac' => $subtotal_mac
            ];
        }
        
        return response()->json([
            'success' => true,
            'details' => $itemsWithMac
        ]);
    }
} 