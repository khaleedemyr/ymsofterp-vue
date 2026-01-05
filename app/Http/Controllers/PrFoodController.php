<?php

namespace App\Http\Controllers;

use App\Models\PrFood;
use App\Models\PrFoodItem;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\ActivityLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PrFoodController extends Controller
{
    public function index(Request $request)
    {
        $query = PrFood::with([
            'warehouse', 
            'warehouseDivision', 
            'requester',
            'assistantSsdManager:id,nama_lengkap',
            'ssdManager:id,nama_lengkap',
            'viceCoo:id,nama_lengkap'
        ])->orderByDesc('id');
        if ($request->search) {
            $query->where('pr_number', 'like', "%{$request->search}%");
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from) {
            $query->whereDate('tanggal', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('tanggal', '<=', $request->to);
        }
        $perPage = $request->per_page ?? 10;
        $prFoods = $query->paginate($perPage)->withQueryString();
        
        // API response
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($prFoods);
        }
        
        // Web response
        return Inertia::render('PrFoods/Index', [
            'prFoods' => $prFoods,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
        ]);
    }

    public function show($id)
    {
        $prFood = PrFood::with([
            'warehouse',
            'requester',
            'items.item.smallUnit',
            'items.item.mediumUnit',
            'items.item.largeUnit',
            'assistantSsdManager:id,nama_lengkap',
            'ssdManager:id,nama_lengkap',
            'viceCoo:id,nama_lengkap'
        ])->findOrFail($id);

        // Ambil stok untuk setiap item di warehouse PR
        $warehouseId = $prFood->warehouse_id;
        foreach ($prFood->items as $item) {
            $invItem = \App\Models\FoodInventoryItem::where('item_id', $item->item_id)->first();
            if ($invItem) {
                $stock = \App\Models\FoodInventoryStock::where('inventory_item_id', $invItem->id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
                $item->stock_small = $stock ? $stock->qty_small : 0;
                $item->stock_medium = $stock ? $stock->qty_medium : 0;
                $item->stock_large = $stock ? $stock->qty_large : 0;
                $item->unit_small = $invItem->smallUnit ? $invItem->smallUnit->name : null;
                $item->unit_medium = $invItem->mediumUnit ? $invItem->mediumUnit->name : null;
                $item->unit_large = $invItem->largeUnit ? $invItem->largeUnit->name : null;
            } else {
                $item->stock_small = $item->stock_medium = $item->stock_large = 0;
                $item->unit_small = $item->unit_medium = $item->unit_large = null;
            }
        }

        // API response
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json($prFood);
        }
        
        // Web response
        return Inertia::render('PrFoods/Show', [
            'prFood' => $prFood,
        ]);
    }

    public function create()
    {
        // Validasi waktu untuk pembuatan PR Foods
        if (!$this->isWithinPrFoodsSchedule()) {
            return redirect()->route('pr-foods.index')
                ->with('error', 'PR Foods hanya bisa dibuat di luar jam 10:00 - 15:00');
        }
        
        $warehouses = Warehouse::all();
        $items = Item::all();
        return Inertia::render('PrFoods/Form', [
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        // Validasi waktu untuk pembuatan PR Foods
        if (!$this->isWithinPrFoodsSchedule()) {
            return redirect()->route('pr-foods.index')
                ->with('error', 'PR Foods hanya bisa dibuat di luar jam 10:00 - 15:00');
        }
        
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'warehouse_division_id' => 'nullable|exists:warehouse_division,id',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:20',
            'items.*.note' => 'nullable|string',
            'items.*.arrival_date' => 'nullable|date',
        ]);
        $prNumber = 'PRF-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        DB::beginTransaction();
        $prFood = PrFood::create([
            'pr_number' => $prNumber,
            'tanggal' => $validated['tanggal'],
            'status' => 'draft',
            'requested_by' => Auth::id(),
            'warehouse_id' => $validated['warehouse_id'],
            'warehouse_division_id' => $validated['warehouse_division_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);
        foreach ($validated['items'] as $item) {
            PrFoodItem::create([
                'pr_food_id' => $prFood->id,
                'item_id' => $item['item_id'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'note' => $item['note'] ?? null,
                'arrival_date' => $item['arrival_date'] ?? null,
            ]);
        }
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'pr_foods',
            'description' => 'Membuat PR Foods: ' . $prFood->pr_number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $prFood->toArray(),
        ]);
        DB::commit();
        
        // Check if warehouse is MK1 or MK2 to determine approver
        $isMKWarehouse = in_array($prFood->warehouse->name, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
        
        if ($isMKWarehouse) {
            // Notifikasi ke Sous Chef MK (id_jabatan=179)
            $sousChefMK = DB::table('users')->where('id_jabatan', 179)->where('status', 'A')->pluck('id');
            $no_pr = $prFood->pr_number;
            $requester = $prFood->requester->nama_lengkap ?? '-';
            $warehouse = $prFood->warehouse->name ?? '-';
            $this->sendNotification(
                $sousChefMK,
                'pr_approval',
                'Approval PR Foods',
                "PR $no_pr dari $requester ($warehouse) menunggu approval Sous Chef MK.",
                route('pr-foods.show', $prFood->id)
            );
        } else {
            // Notifikasi ke Asisten SSD Manager (id_jabatan=172) terlebih dahulu
            $assistantSsdManagers = DB::table('users')->where('id_jabatan', 172)->where('status', 'A')->pluck('id');
            $no_pr = $prFood->pr_number;
            $requester = $prFood->requester->nama_lengkap ?? '-';
            $warehouse = $prFood->warehouse->name ?? '-';
            $this->sendNotification(
                $assistantSsdManagers,
                'pr_approval',
                'Approval PR Foods',
                "PR $no_pr dari $requester ($warehouse) menunggu approval Asisten SSD Manager.",
                route('pr-foods.show', $prFood->id)
            );
        }
        
        // API response
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'PR Food created successfully',
                'data' => $prFood->load(['warehouse', 'warehouseDivision', 'requester', 'items.item']),
            ], 201);
        }
        
        // Web response
        return redirect()->route('pr-foods.index');
    }

    public function edit($id)
    {
        $prFood = PrFood::with(['items.item', 'warehouse', 'requester'])->findOrFail($id);
        $warehouses = Warehouse::all();
        $items = Item::all();
        return Inertia::render('PrFoods/Form', [
            'prFood' => $prFood,
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    public function update(Request $request, $id)
    {
        $prFood = PrFood::findOrFail($id);
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'warehouse_division_id' => 'nullable|exists:warehouse_division,id',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:20',
            'items.*.note' => 'nullable|string',
            'items.*.arrival_date' => 'nullable|date',
        ]);
        DB::beginTransaction();
        $oldData = $prFood->toArray();
        $prFood->update([
            'tanggal' => $validated['tanggal'],
            'warehouse_id' => $validated['warehouse_id'],
            'warehouse_division_id' => $validated['warehouse_division_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);
        $prFood->items()->delete();
        foreach ($validated['items'] as $item) {
            PrFoodItem::create([
                'pr_food_id' => $prFood->id,
                'item_id' => $item['item_id'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'note' => $item['note'] ?? null,
                'arrival_date' => $item['arrival_date'] ?? null,
            ]);
        }
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'pr_foods',
            'description' => 'Mengupdate PR Foods: ' . $prFood->pr_number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $prFood->fresh()->toArray(),
        ]);
        DB::commit();
        
        // API response
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'PR Food updated successfully',
                'data' => $prFood->fresh()->load(['warehouse', 'warehouseDivision', 'requester', 'items.item']),
            ]);
        }
        
        // Web response
        return redirect()->route('pr-foods.index');
    }

    public function destroy($id)
    {
        $prFood = PrFood::findOrFail($id);
        $oldData = $prFood->toArray();
        $prFood->delete();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'pr_foods',
            'description' => 'Menghapus PR Foods: ' . $prFood->pr_number,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => null,
        ]);
        
        // API response
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'PR Food deleted successfully',
            ]);
        }
        
        // Web response
        return redirect()->route('pr-foods.index');
    }

    // Approval Asisten SSD Manager
    public function approveAssistantSsdManager(Request $request, $id)
    {
        $prFood = PrFood::with(['requester', 'warehouse'])->findOrFail($id);
        
        // Check if warehouse is MK1 or MK2 - jika MK, tidak perlu approval asisten SSD manager
        $isMKWarehouse = in_array($prFood->warehouse->name, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
        if ($isMKWarehouse) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'PR MK tidak memerlukan approval Asisten SSD Manager'
                ], 400);
            }
            return redirect()->route('pr-foods.index')->with('error', 'PR MK tidak memerlukan approval Asisten SSD Manager');
        }
        
        // Support 'note', 'comment', 'notes', and 'assistant_ssd_manager_note' parameters
        $note = $request->input('assistant_ssd_manager_note') ?? $request->input('note') ?? 
                $request->input('comment') ?? $request->input('notes');
        
        // Support both 'approved' boolean and 'reject' parameter
        $isApproved = $request->has('approved') ? $request->approved : 
                     ($request->has('reject') ? !$request->reject : true);
        
        $updateData = [
            'assistant_ssd_manager_approved_at' => now(),
            'assistant_ssd_manager_approved_by' => Auth::id(),
            'assistant_ssd_manager_note' => $note,
        ];
        
        if ($isApproved) {
            // Jika approved, update status tetap draft (belum final approval)
            $updateData['status'] = 'draft';
            $prFood->update($updateData);
            
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'approve',
                'module' => 'pr_foods',
                'description' => 'Approve PR Foods (Asisten SSD Manager): ' . $prFood->pr_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $prFood->fresh()->toArray(),
            ]);
            
            // Notifikasi ke SSD Manager untuk approval selanjutnya
            $ssdManagers = DB::table('users')->where('id_jabatan', 161)->where('status', 'A')->pluck('id');
            $no_pr = $prFood->pr_number;
            $requester = $prFood->requester->nama_lengkap ?? '-';
            $warehouse = $prFood->warehouse->name ?? '-';
            $this->sendNotification(
                $ssdManagers,
                'pr_approval',
                'Approval PR Foods',
                "PR $no_pr dari $requester ($warehouse) sudah di-approve Asisten SSD Manager, menunggu approval SSD Manager.",
                route('pr-foods.show', $prFood->id)
            );
        } else {
            // Jika rejected, update status jadi rejected
            $updateData['status'] = 'rejected';
            $prFood->update($updateData);
            
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'reject',
                'module' => 'pr_foods',
                'description' => 'Reject PR Foods (Asisten SSD Manager): ' . $prFood->pr_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $prFood->fresh()->toArray(),
            ]);
            
            // Notifikasi ke creator PR jika di-reject
            $requestedBy = $prFood->requested_by;
            $no_pr = $prFood->pr_number;
            $requester = $prFood->requester->nama_lengkap ?? '-';
            $warehouse = $prFood->warehouse->name ?? '-';
            $this->sendNotification(
                [$requestedBy],
                'pr_rejected',
                'PR Foods Ditolak',
                "PR $no_pr dari $requester ($warehouse) telah ditolak oleh Asisten SSD Manager.",
                route('pr-foods.show', $prFood->id)
            );
        }
        
        // Return JSON response for axios requests
        // Component will handle the response and reload data
        return response()->json([
            'success' => true,
            'message' => $isApproved ? 'PR Food berhasil disetujui' : 'PR Food berhasil ditolak',
            'pr_food' => $prFood->fresh()
        ], 200, [
            'Content-Type' => 'application/json',
        ], JSON_UNESCAPED_UNICODE);
    }

    // Approval SSD Manager
    public function approveSsdManager(Request $request, $id)
    {
        $prFood = PrFood::with(['requester', 'warehouse'])->findOrFail($id);
        
        // Check if warehouse is MK1 or MK2
        $isMKWarehouse = in_array($prFood->warehouse->name, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
        $approverTitle = $isMKWarehouse ? 'Sous Chef MK' : 'SSD Manager';
        
        // Untuk PR non-MK, pastikan sudah di-approve asisten SSD manager terlebih dahulu
        if (!$isMKWarehouse && !$prFood->assistant_ssd_manager_approved_at) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'PR harus di-approve Asisten SSD Manager terlebih dahulu'
                ], 400);
            }
            return redirect()->route('pr-foods.index')->with('error', 'PR harus di-approve Asisten SSD Manager terlebih dahulu');
        }
        
        // Support 'note', 'comment', 'notes', and 'ssd_manager_note' parameters
        $note = $request->input('ssd_manager_note') ?? $request->input('note') ?? 
                $request->input('comment') ?? $request->input('notes');
        
        // Support both 'approved' boolean and 'reject' parameter
        $isApproved = $request->has('approved') ? $request->approved : 
                     ($request->has('reject') ? !$request->reject : true);
        
        $updateData = [
            'ssd_manager_approved_at' => now(),
            'ssd_manager_approved_by' => Auth::id(),
            'ssd_manager_note' => $note,
        ];
        $updateData['status'] = $isApproved ? 'approved' : 'rejected';
        $prFood->update($updateData);
        
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => $isApproved ? 'approve' : 'reject',
            'module' => 'pr_foods',
            'description' => ($isApproved ? 'Approve' : 'Reject') . " PR Foods ($approverTitle): " . $prFood->pr_number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $prFood->fresh()->toArray(),
        ]);
        
        $no_pr = $prFood->pr_number;
        $requester = $prFood->requester->nama_lengkap ?? '-';
        $warehouse = $prFood->warehouse->name ?? '-';
        
        if ($isApproved) {
            // Jika approved, langsung info Purchasing untuk proses PO
            $adminPurchasing = DB::table('users')->where('id_jabatan', 244)->where('status', 'A')->pluck('id');
            $purchasingManagers = DB::table('users')->where('id_jabatan', 168)->where('status', 'A')->pluck('id');
            $userIds = $adminPurchasing->merge($purchasingManagers);
            $this->sendNotification(
                $userIds,
                'pr_po',
                'Pembuatan PO',
                "PR $no_pr dari $requester ($warehouse) sudah di-approve $approverTitle, silakan buat PO.",
                route('pr-foods.show', $prFood->id)
            );
        } else {
            // Notifikasi ke creator PR jika di-reject
            $requestedBy = $prFood->requested_by;
            \Log::info('Notif reject: requested_by', ['requested_by' => $requestedBy, 'pr_id' => $prFood->id]);
            $this->sendNotification(
                [$requestedBy],
                'pr_rejected',
                'PR Foods Ditolak',
                "PR $no_pr dari $requester ($warehouse) telah ditolak oleh $approverTitle.",
                route('pr-foods.show', $prFood->id)
            );
            \Log::info('Notif reject sent', ['user_id' => $requestedBy, 'pr_id' => $prFood->id]);
        }
        
        // Return JSON response for axios requests
        // Component will handle the response and reload data
        return response()->json([
            'success' => true,
            'message' => $isApproved ? 'PR Food berhasil disetujui' : 'PR Food berhasil ditolak',
            'pr_food' => $prFood->fresh()
        ], 200, [
            'Content-Type' => 'application/json',
        ], JSON_UNESCAPED_UNICODE);
    }

    // Approval Vice COO
    public function approveViceCoo(Request $request, $id)
    {
        $prFood = PrFood::with(['requester', 'warehouse'])->findOrFail($id);
        
        // Support 'note', 'comment', 'notes', and 'vice_coo_note' parameters
        $note = $request->input('vice_coo_note') ?? $request->input('note') ?? 
                $request->input('comment') ?? $request->input('notes');
        
        // Support both 'approved' boolean and 'reject' parameter
        $isApproved = $request->has('approved') ? $request->approved : 
                     ($request->has('reject') ? !$request->reject : true);
        
        $prFood->update([
            'vice_coo_approved_at' => now(),
            'vice_coo_approved_by' => Auth::id(),
            'vice_coo_note' => $note,
            'status' => $isApproved ? 'approved' : 'rejected',
        ]);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => $isApproved ? 'approve' : 'reject',
            'module' => 'pr_foods',
            'description' => ($isApproved ? 'Approve' : 'Reject') . ' PR Foods (Vice COO): ' . $prFood->pr_number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $prFood->fresh()->toArray(),
        ]);
        if ($isApproved) {
            // Notifikasi ke Admin Purchasing & Purchasing Manager
            $adminPurchasing = DB::table('users')->where('id_jabatan', 244)->where('status', 'A')->pluck('id');
            $purchasingManagers = DB::table('users')->where('id_jabatan', 168)->where('status', 'A')->pluck('id');
            $userIds = $adminPurchasing->merge($purchasingManagers);
            $no_pr = $prFood->pr_number;
            $requester = $prFood->requester->nama_lengkap ?? '-';
            $warehouse = $prFood->warehouse->name ?? '-';
            $this->sendNotification(
                $userIds,
                'pr_po',
                'Pembuatan PO',
                "PR $no_pr dari $requester ($warehouse) sudah di-approve Vice COO, silakan buat PO.",
                'PR Foods sudah di-approve Vice COO, silakan buat PO.',
                route('pr-foods.show', $prFood->id)
            );
        }
        return redirect()->route('pr-foods.index');
    }

    // Helper untuk validasi waktu PR Foods
    private function isWithinPrFoodsSchedule()
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        
        $closeStart = $today->copy()->setTime(10, 0, 0); // 10:00 pagi
        $closeEnd = $today->copy()->setTime(15, 0, 0); // 15:00 sore
        
        // Tutup: 10:00 pagi sampai 15:00 sore
        // Buka: 15:00 sore sampai 10:00 pagi besok
        return !($now->gte($closeStart) && $now->lt($closeEnd));
    }

    // Helper untuk insert notifikasi
    private function sendNotification($userIds, $type, $title, $message, $url) {
        $data = [];
        foreach ($userIds as $uid) {
            $data[] = [
                'user_id' => $uid,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
            ];
        }
        NotificationService::createMany($data);
    }

    // API: Get pending PR Foods approvals
    public function getPendingApprovals(Request $request)
    {
        try {
            $user = Auth::user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            $query = PrFood::with(['warehouse', 'requester', 'items'])
                ->where('status', 'draft')
                ->orderByDesc('created_at');
            
            $pendingApprovals = [];
            
            // Convert to int untuk memastikan perbandingan benar
            $userJabatan = (int) $user->id_jabatan;
            
            // Asisten SSD Manager approvals (id_jabatan == 172) - untuk non-MK warehouse yang belum di-approve asisten
            if (($userJabatan == 172 && $user->status == 'A') || $isSuperadmin) {
                $assistantSsdApprovals = (clone $query)
                    ->whereNull('assistant_ssd_manager_approved_at')
                    ->get()
                    ->filter(function($pr) {
                        // Filter non-MK warehouse
                        return !in_array($pr->warehouse->name ?? '', ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
                    });
                
                \Log::info('Asisten SSD Manager approvals check', [
                    'user_id' => $user->id,
                    'user_jabatan' => $userJabatan,
                    'user_status' => $user->status,
                    'count' => $assistantSsdApprovals->count()
                ]);
                
                // Get approver name for this level
                $approver = DB::table('users')
                    ->where('id_jabatan', 172)
                    ->where('status', 'A')
                    ->select('nama_lengkap')
                    ->first();
                
                foreach ($assistantSsdApprovals as $pr) {
                    $pendingApprovals[] = [
                        'id' => $pr->id,
                        'pr_number' => $pr->pr_number,
                        'tanggal' => $pr->tanggal,
                        'warehouse' => $pr->warehouse ? ['name' => $pr->warehouse->name] : null,
                        'requester' => $pr->requester ? ['nama_lengkap' => $pr->requester->nama_lengkap] : null,
                        'items_count' => $pr->items->count(),
                        'description' => $pr->description,
                        'approval_level' => 'assistant_ssd_manager',
                        'approval_level_display' => 'Asisten SSD Manager',
                        'approver_name' => $approver ? $approver->nama_lengkap : 'Asisten SSD Manager',
                        'created_at' => $pr->created_at
                    ];
                }
            }
            
            // SSD Manager approvals
            // Untuk id_jabatan=161: hanya PR yang sudah di-approve asisten
            // Untuk id_jabatan=172: PR yang sudah di-approve asisten tapi belum di-approve SSD Manager
            if ((in_array($userJabatan, [161, 172]) && $user->status == 'A') || $isSuperadmin) {
                $ssdManagerApprovals = (clone $query)
                    ->whereNotNull('assistant_ssd_manager_approved_at')
                    ->whereNull('ssd_manager_approved_at')
                    ->get()
                    ->filter(function($pr) {
                        // Filter non-MK warehouse
                        return !in_array($pr->warehouse->name ?? '', ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
                    });
                
                \Log::info('SSD Manager approvals check', [
                    'user_id' => $user->id,
                    'user_jabatan' => $userJabatan,
                    'user_status' => $user->status,
                    'count' => $ssdManagerApprovals->count()
                ]);
                
                // Get approver name for this level
                $approver = DB::table('users')
                    ->where('id_jabatan', 161)
                    ->where('status', 'A')
                    ->select('nama_lengkap')
                    ->first();
                
                foreach ($ssdManagerApprovals as $pr) {
                    $pendingApprovals[] = [
                        'id' => $pr->id,
                        'pr_number' => $pr->pr_number,
                        'tanggal' => $pr->tanggal,
                        'warehouse' => $pr->warehouse ? ['name' => $pr->warehouse->name] : null,
                        'requester' => $pr->requester ? ['nama_lengkap' => $pr->requester->nama_lengkap] : null,
                        'items_count' => $pr->items->count(),
                        'description' => $pr->description,
                        'approval_level' => 'ssd_manager',
                        'approval_level_display' => 'SSD Manager',
                        'approver_name' => $approver ? $approver->nama_lengkap : 'SSD Manager',
                        'created_at' => $pr->created_at
                    ];
                }
            }
            
            // Sous Chef MK approvals (id_jabatan == 179) - untuk MK warehouse
            if (($userJabatan == 179 && $user->status == 'A') || $isSuperadmin) {
                $sousChefMKApprovals = (clone $query)
                    ->whereNull('ssd_manager_approved_at')
                    ->get()
                    ->filter(function($pr) {
                        // Filter MK warehouse
                        return in_array($pr->warehouse->name ?? '', ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
                    });
                
                // Get approver name for this level
                $approver = DB::table('users')
                    ->where('id_jabatan', 179)
                    ->where('status', 'A')
                    ->select('nama_lengkap')
                    ->first();
                
                foreach ($sousChefMKApprovals as $pr) {
                    $pendingApprovals[] = [
                        'id' => $pr->id,
                        'pr_number' => $pr->pr_number,
                        'tanggal' => $pr->tanggal,
                        'warehouse' => $pr->warehouse ? ['name' => $pr->warehouse->name] : null,
                        'requester' => $pr->requester ? ['nama_lengkap' => $pr->requester->nama_lengkap] : null,
                        'items_count' => $pr->items->count(),
                        'description' => $pr->description,
                        'approval_level' => 'sous_chef_mk',
                        'approval_level_display' => 'Sous Chef MK',
                        'approver_name' => $approver ? $approver->nama_lengkap : 'Sous Chef MK',
                        'created_at' => $pr->created_at
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'pr_foods' => $pendingApprovals
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting pending PR Foods approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get pending approvals'
            ], 500);
        }
    }

    // API: Get PR Food detail for approval modal
    public function getDetail($id)
    {
        try {
            $prFood = PrFood::with([
                'warehouse',
                'warehouseDivision',
                'requester',
                'items.item.smallUnit',
                'items.item.mediumUnit',
                'items.item.largeUnit',
                'assistantSsdManager',
                'ssdManager',
                'viceCoo'
            ])->findOrFail($id);
            
            // Determine current approval level and approver info
            $currentApprovalLevel = null;
            $currentApproverId = null;
            $user = Auth::user();
            $isMKWarehouse = in_array($prFood->warehouse->name ?? '', ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
            
            // Check which approval level is pending and if current user can approve
            if (!$isMKWarehouse && !$prFood->assistant_ssd_manager_approved_at) {
                // Assistant SSD Manager approval pending
                if ($user->id_jabatan == 160) { // Asisten SSD Manager
                    $currentApprovalLevel = 'assistant_ssd_manager';
                    $currentApproverId = $user->id;
                }
            } elseif (!$prFood->ssd_manager_approved_at) {
                // SSD Manager approval pending
                if ($user->id_jabatan == 161 || ($isMKWarehouse && $user->id_jabatan == 162)) { // SSD Manager or Sous Chef MK
                    $currentApprovalLevel = 'ssd_manager';
                    $currentApproverId = $user->id;
                }
            } elseif (!$prFood->vice_coo_approved_at && $prFood->ssd_manager_approved_at) {
                // Vice COO approval pending (only if SSD Manager already approved)
                if ($user->id_jabatan == 163) { // Vice COO
                    $currentApprovalLevel = 'vice_coo';
                    $currentApproverId = $user->id;
                }
            }
            
            return response()->json([
                'success' => true,
                'pr_food' => $prFood,
                'current_approval_level' => $currentApprovalLevel,
                'current_approver_id' => $currentApproverId,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting PR Food detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load PR Food detail'
            ], 500);
        }
    }
} 