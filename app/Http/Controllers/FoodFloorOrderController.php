<?php

namespace App\Http\Controllers;

use App\Models\FoodFloorOrder;
use App\Models\FoodFloorOrderItem;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Item;
use Illuminate\Support\Facades\Mail;
use App\Services\FloorOrderService;
use Carbon\Carbon;
use App\Models\WarehouseOutlet;
use Barryvdh\DomPDF\Facade\Pdf;

class FoodFloorOrderController extends Controller
{
    protected $floorOrderService;

    public function __construct(FloorOrderService $floorOrderService)
    {
        $this->floorOrderService = $floorOrderService;
    }

    // Tampilkan form edit draft
    public function edit($id)
    {
        $order = FoodFloorOrder::with('items')->findOrFail($id);
        return Inertia::render('FloorOrder/Form', [
            'order' => $order,
            'user' => Auth::user()->load('outlet'),
        ]);
    }

    // Method untuk memproses item tanpa validasi supplier
    private function validateAndGroupItemsBySupplier($items, $outletId, $foMode = null)
    {
        $processedItems = [];

        foreach ($items as $item) {
            // Abaikan item kosong
            if (empty($item['item_id']) || empty($item['item_name'])) {
                continue;
            }

            // Cek apakah item ada di supplier (untuk informasi saja, tidak untuk validasi)
            $itemSupplier = \DB::table('item_supplier_outlet')
                ->join('item_supplier', 'item_supplier_outlet.item_supplier_id', '=', 'item_supplier.id')
                ->where('item_supplier_outlet.outlet_id', $outletId)
                ->where('item_supplier.item_id', $item['item_id'])
                ->select('item_supplier.supplier_id', 'item_supplier.id as item_supplier_id')
                ->first();

            $processedItems[] = [
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'supplier_id' => $itemSupplier ? $itemSupplier->supplier_id : null,
                'item_supplier_id' => $itemSupplier ? $itemSupplier->item_supplier_id : null,
                'id_outlet' => $outletId
            ];
        }

        return $processedItems;
    }

    // Method store untuk membuat floor order
    public function store(Request $request)
    {
        // Validasi arrival_date wajib diisi
        if (empty($request->arrival_date)) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal kedatangan wajib diisi'
            ], 422);
        }
        
        try {
            \DB::beginTransaction();

            // Ambil id_outlet dari request atau user login
            $idOutlet = $request->outlet_id ?? (auth()->user()->id_outlet ?? null);
            $userId = auth()->id();
            $tanggal = $request->tanggal ?? now()->toDateString();

            // --- VALIDASI warehouse_outlet_id ---
            $warehouseOutletId = $request->warehouse_outlet_id;
            $warehouseOutlet = \App\Models\WarehouseOutlet::where('id', $warehouseOutletId)
                ->where('outlet_id', $idOutlet)
                ->where('status', 'active')
                ->first();
            if (!$warehouseOutlet) {
                throw new \Exception('Warehouse outlet tidak valid atau tidak aktif untuk outlet ini.');
            }

            // Cek apakah sudah ada draft untuk user, tanggal, outlet, warehouse, status draft
            $existingOrder = \DB::table('food_floor_orders')
                ->where('user_id', $userId)
                ->where('id_outlet', $idOutlet)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->where('tanggal', $tanggal)
                ->where('status', 'draft')
                ->first();

            if ($existingOrder) {
                // Update header FO
                \DB::table('food_floor_orders')->where('id', $existingOrder->id)->update([
                    'description' => $request->description ?? '',
                    'fo_mode' => $request->fo_mode ?? 'RO Utama',
                    'input_mode' => $request->input_mode ?? 'pc',
                    'fo_schedule_id' => $request->fo_schedule_id ?? null,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'arrival_date' => $request->arrival_date ?? null,
                    'updated_at' => now()
                ]);
                $floorOrderId = $existingOrder->id;
            } else {
                // Insert header FO (DRAFT, bukan RO-...)
                $headerData = [
                    'order_number' => 'DRAFT-' . $userId . '-' . time(),
                    'tanggal' => $tanggal,
                    'description' => $request->description ?? '',
                    'fo_mode' => $request->fo_mode ?? 'RO Utama',
                    'input_mode' => $request->input_mode ?? 'pc',
                    'fo_schedule_id' => $request->fo_schedule_id ?? null,
                    'arrival_date' => $request->arrival_date ?? null,
                    'id_outlet' => $idOutlet,
                    'user_id' => $userId,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $inserted = \DB::table('food_floor_orders')->insert($headerData);
                $floorOrderId = \DB::getPdo()->lastInsertId();
            }

            // Ambil data outlet
            $outlet = \DB::table('tbl_data_outlet')->where('id_outlet', $idOutlet)->first();
            $outletName = $outlet ? $outlet->nama_outlet : 'Unknown Outlet';

            $items = $request->items;
            
            // Proses item tanpa validasi supplier
            $processedItems = $this->validateAndGroupItemsBySupplier($items, $idOutlet, $request->fo_mode);

            // Hapus item lama (hanya untuk draft ini)
            \DB::table('food_floor_order_items')->where('floor_order_id', $floorOrderId)->delete();
            \DB::table('food_floor_order_supplier_items')->where('floor_order_id', $floorOrderId)->delete();
            \DB::table('food_floor_order_supplier_headers')->where('floor_order_id', $floorOrderId)->delete();

            // Simpan semua item ke tabel regular items
            foreach ($processedItems as $item) {
                $masterItem = Item::find($item['item_id']);
                \DB::table('food_floor_order_items')->insert([
                    'floor_order_id' => $floorOrderId,
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                    'category_id' => $masterItem ? $masterItem->category_id : null,
                    'warehouse_division_id' => $masterItem ? $masterItem->warehouse_division_id : null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            \DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Floor Order berhasil dibuat',
                'data' => [
                    'floor_order_id' => $floorOrderId,
                    'order_number' => $existingOrder->order_number ?? ('DRAFT-' . $userId . '-' . time())
                ]
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error dalam store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method update untuk mengupdate floor order
    public function update(Request $request, $id)
    {
        // Validasi arrival_date wajib diisi
        if (empty($request->arrival_date)) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal kedatangan wajib diisi'
            ], 422);
        }
        
        $order = FoodFloorOrder::findOrFail($id);
        $oldData = $order->toArray();
        // --- VALIDASI warehouse_outlet_id ---
        $warehouseOutletId = $request->warehouse_outlet_id;
        $warehouseOutlet = \App\Models\WarehouseOutlet::where('id', $warehouseOutletId)
            ->where('outlet_id', $order->id_outlet)
            ->where('status', 'active')
            ->first();
        if (!$warehouseOutlet) {
            return response()->json(['success' => false, 'message' => 'Warehouse outlet tidak valid atau tidak aktif untuk outlet ini.'], 422);
        }
        $order->update(array_merge(
            $request->only(['tanggal', 'description', 'fo_mode', 'input_mode', 'fo_schedule_id', 'arrival_date']),
            ['warehouse_outlet_id' => $warehouseOutletId]
        ));

        // Proses item tanpa validasi supplier
        $processedItems = $this->validateAndGroupItemsBySupplier($request->items, $order->id_outlet, $order->fo_mode);

        // Hapus data item lama
        $order->items()->delete();
        \DB::table('food_floor_order_supplier_items')->where('floor_order_id', $order->id)->delete();
        \DB::table('food_floor_order_supplier_headers')->where('floor_order_id', $order->id)->delete();

        // Simpan semua item ke tabel regular items
        foreach ($processedItems as $item) {
            $masterItem = Item::find($item['item_id']);
            \DB::table('food_floor_order_items')->insert([
                'floor_order_id' => $order->id,
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'category_id' => $masterItem ? $masterItem->category_id : null,
                'warehouse_division_id' => $masterItem ? $masterItem->warehouse_division_id : null,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'activity_type' => 'update',
            'module' => 'food_floor_order',
            'description' => 'Update Floor Order: ' . $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $order->fresh()->toArray(),
        ]);

        return response()->json(['success' => true, 'message' => 'Floor Order berhasil diupdate']);
    }

    // Method destroy untuk menghapus floor order
    public function destroy($id)
    {
        $order = FoodFloorOrder::findOrFail($id);
        
        // Cek apakah RO sudah ada di packing list
        $packingListExists = \DB::table('food_packing_lists')
            ->where('food_floor_order_id', $order->id)
            ->exists();
        
        if ($packingListExists) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Request Order tidak dapat dihapus karena sudah ada di Packing List'
                ], 422);
            }
            return redirect()->route('floor-order.index')
                ->with('error', 'Request Order tidak dapat dihapus karena sudah ada di Packing List');
        }
        
        $order->delete();
        
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Floor Order berhasil dihapus'
            ]);
        }
        
        return redirect()->route('floor-order.index')->with('success', 'Floor Order berhasil dihapus');
    }

    // Submit draft
    public function submit(Request $request, $id)
    {
        $order = FoodFloorOrder::findOrFail($id);
        
        // Budget checking untuk RO yang akan di-approve (RO Utama/Tambahan)
        if ($order->fo_mode !== 'RO Khusus') {
            
            $budgetCheckResult = $this->checkBudgetForFloorOrder($order);
            if (!$budgetCheckResult['success']) {
                \Log::error('FO_SUBMIT: Budget check failed', [
                    'order_id' => $order->id,
                    'message' => $budgetCheckResult['message']
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $budgetCheckResult['message']
                ], 422);
            }
            
        }
        
        $date = now()->format('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $order_number = 'RO-' . $date . '-' . $random;

        $order->update([
            'status' => $order->fo_mode === 'RO Khusus' ? 'submitted' : 'approved',
            'order_number' => $order_number,
        ]);

        // Kirim notifikasi jika RO Khusus
        if ($order->fo_mode === 'RO Khusus' && $order->status === 'submitted') {
            $this->sendNotificationByWarehouse($order->warehouse_outlet_id, $order->id, $order_number);
        }

        return response()->json(['success' => true]);
    }

    // Cek apakah sudah ada FO Utama/Tambahan di hari dan outlet yang sama
    public function checkExists(Request $request)
    {
        $tanggal = $request->tanggal;
        $id_outlet = $request->id_outlet;
        $fo_mode = $request->fo_mode;
        $exclude_id = $request->exclude_id;
        $warehouse_outlet_id = $request->warehouse_outlet_id;
        $currentHour = now('Asia/Jakarta')->hour;

        // Logika untuk mengatasi masalah RO Utama yang dibuat jam 00:01:
        // - RO yang dibuat sebelum jam 12:00 untuk tanggal X, dianggap untuk tanggal X+1 (besok)
        // - RO yang dibuat setelah jam 12:00 untuk tanggal X, dianggap untuk tanggal X yang sama (hari ini)
        // 
        // Contoh:
        // - RO dibuat jam 00:01 tanggal 6 Jan untuk tanggal 7 Jan → dianggap untuk tanggal 7 Jan (besok)
        // - RO dibuat jam 22:00 tanggal 7 Jan untuk tanggal 7 Jan → dianggap untuk tanggal 7 Jan (hari ini)
        // - Jadi kedua RO tersebut bisa ada bersamaan karena berbeda "effective date"

        $query = \App\Models\FoodFloorOrder::where(function($q) use ($tanggal, $currentHour) {
                // Jika user input setelah jam 12:00 untuk tanggal X
                if ($currentHour >= 12) {
                    // Cek RO dengan tanggal X yang dibuat SETELAH jam 12:00
                    // (karena RO yang dibuat sebelum jam 12:00 dianggap untuk hari berikutnya, bukan hari ini)
                    $q->where('tanggal', $tanggal)
                      ->whereRaw('HOUR(created_at) >= 12');
                } else {
                    // Jika user input sebelum jam 12:00 untuk tanggal X
                    // Cek RO dengan tanggal X yang dibuat SEBELUM jam 12:00
                    // (karena RO yang dibuat setelah jam 12:00 dianggap untuk hari yang sama, bukan hari berikutnya)
                    $q->where('tanggal', $tanggal)
                      ->whereRaw('HOUR(created_at) < 12');
                }
            })
            ->where('id_outlet', $id_outlet)
            ->where('fo_mode', $fo_mode)
            ->whereNotIn('status', ['rejected']);

        if ($warehouse_outlet_id) {
            $query->where('warehouse_outlet_id', $warehouse_outlet_id);
        }
        if ($exclude_id) {
            $query->where('id', '!=', $exclude_id);
        }

        $exists = $query->exists();
        return response()->json(['exists' => $exists]);
    }

    public function show($id)
    {
        $order = FoodFloorOrder::with(['outlet', 'requester', 'foSchedule', 'approver', 'warehouseOutlet'])->findOrFail($id);
        
        // Load items dari relasi untuk semua mode
        $order->load('items.category');
        
        return Inertia::render('FloorOrder/Show', [
            'order' => $order,
            'user' => Auth::user()->load('outlet'),
        ]);
    }

    // Tambahkan method sendNotification
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

    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $order = \App\Models\FoodFloorOrder::findOrFail($id);

        // Cek hak akses berdasarkan warehouse outlet
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
        $canApprove = $this->canUserApproveByWarehouse($user, $order->warehouse_outlet_id);
        
        if (!($isSuperadmin || $canApprove)) {
            abort(403, 'Unauthorized - Anda tidak memiliki hak untuk approve RO Khusus untuk warehouse outlet ini');
        }

        if (($order->fo_mode !== 'RO Khusus') || $order->status !== 'submitted') {
            abort(400, 'Tidak bisa approve order ini');
        }

        // Support both 'approved' boolean and 'reject' parameter
        $isReject = ($request->has('approved') && $request->approved === false) || 
                    ($request->has('reject') && $request->reject === true);
        
        // Support 'note', 'comment', 'notes', and 'reason' parameters
        $note = $request->input('note') ?? $request->input('comment') ?? 
                $request->input('notes') ?? $request->input('reason');
        
        // Budget checking hanya untuk approve, bukan reject
        if (!$isReject) {
            $budgetCheckResult = $this->checkBudgetForFloorOrder($order);
            if (!$budgetCheckResult['success']) {
                \Log::error('FO_APPROVE: Budget check failed', [
                    'order_id' => $order->id,
                    'message' => $budgetCheckResult['message']
                ]);
                if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $budgetCheckResult['message'],
                        'violations' => $budgetCheckResult['violations'] ?? null,
                    ], 422);
                }
                return redirect()->back()->withErrors(['budget' => $budgetCheckResult['message']]);
            }
        }
        
        $order->update([
            'status' => $isReject ? 'rejected' : 'approved',
            'approval_by' => $user->id,
            'approval_at' => now(),
            'approval_notes' => $note,
        ]);
        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => $isReject ? 'reject' : 'approve',
            'module' => 'food_floor_order',
            'description' => ($isReject ? 'Reject' : 'Approve') . ' Floor Order: ' . $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $order->fresh()->toArray(),
        ]);
        
        // Send notification to requester if rejected
        if ($isReject && $order->user_id) {
            $this->sendNotification(
                [$order->user_id],
                'floor_order_rejected',
                'RO Khusus Ditolak',
                "RO Khusus {$order->order_number} telah ditolak.",
                route('floor-order.show', $order->id)
            );
        }
        
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $isReject ? 'RO Khusus berhasil ditolak' : 'RO Khusus berhasil di-approve'
            ]);
        }
        
        return redirect()->back()->with('success', $isReject ? 'Floor Order berhasil ditolak' : 'Floor Order berhasil di-approve');
    }

    // Method untuk mengecek apakah user bisa approve berdasarkan warehouse outlet
    private function canUserApproveByWarehouse($user, $warehouseOutletId)
    {
        // Ambil warehouse outlet
        $warehouseOutlet = \DB::table('warehouse_outlets')->where('id', $warehouseOutletId)->first();
        if (!$warehouseOutlet) {
            return false;
        }

        $warehouseName = $warehouseOutlet->name;
        $userJabatan = $user->id_jabatan;
        $userStatus = $user->status;

        // Cek berdasarkan nama warehouse outlet
        // Sekarang semua jabatan yang menerima notifikasi juga bisa approve
        switch ($warehouseName) {
            case 'Kitchen':
                // Jabatan yang bisa approve: semua yang menerima notifikasi
                return in_array($userJabatan, [163, 174, 180, 345, 346, 347, 348, 349]) && $userStatus === 'A';
            case 'Bar':
                // Jabatan yang bisa approve: semua yang menerima notifikasi
                return in_array($userJabatan, [175, 182, 323]) && $userStatus === 'A';
            case 'Service':
                // Jabatan yang bisa approve: semua yang menerima notifikasi
                return in_array($userJabatan, [176, 322, 164, 321]) && $userStatus === 'A';
            default:
                return false;
        }
    }

    // Method untuk mengirim notifikasi berdasarkan warehouse outlet
    private function sendNotificationByWarehouse($warehouseOutletId, $orderId, $orderNumber)
    {
        // Ambil warehouse outlet
        $warehouseOutlet = \DB::table('warehouse_outlets')->where('id', $warehouseOutletId)->first();
        if (!$warehouseOutlet) {
            return;
        }

        $warehouseName = $warehouseOutlet->name;
        $jabatanIds = [];

        // Tentukan jabatan berdasarkan nama warehouse outlet
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
        $users = \DB::table('users')
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
                'type' => 'floor_order_approval',
                'title' => 'Approval RO Khusus',
                'message' => "RO Khusus {$orderNumber} dari warehouse {$warehouseName} menunggu approval Anda.",
                'url' => route('floor-order.show', $orderId),
                'is_read' => 0,
            ];
        }
        NotificationService::createMany($data);
    }

    public function index(Request $request)
    {
        $user = auth()->user()->load('outlet');
        $query = FoodFloorOrder::with(['outlet', 'requester', 'foSchedule', 'warehouseOutlet']);
        if ($user->id_outlet != 1) {
            $query->where('id_outlet', $user->id_outlet);
        }
        if ($request->search) {
            $search = $request->search;
            $query->where('order_number', 'like', "%$search%")
                  ->orWhereHas('outlet', function($q) use ($search) {
                      $q->where('nama_outlet', 'like', "%$search%") ;
                  });
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->start_date) {
            $query->whereDate('tanggal', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('tanggal', '<=', $request->end_date);
        }
        $floorOrders = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        // Load items untuk semua order
        $floorOrders->getCollection()->transform(function($order) {
          
            $order->loadMissing('items');
            $order->setRelation('outlet', $order->outlet);
            $order->setRelation('requester', $order->requester);
            
            // Cek apakah RO sudah ada di packing list
            $order->has_packing_list = \DB::table('food_packing_lists')
                ->where('food_floor_order_id', $order->id)
                ->exists();
            $order->setRelation('foSchedule', $order->foSchedule);
            $order->setRelation('warehouseOutlet', $order->warehouseOutlet);
            return $order;
        });

        return Inertia::render('FloorOrder/Index', [
            'user' => $user,
            'floorOrders' => $floorOrders,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date']),
        ]);
    }

    public function warehouseOutlet() {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_id');
    }

    // API untuk mengambil RO Supplier yang tersedia untuk dibuat PO
    public function supplierAvailable()
    {
        try {
            $user = auth()->user();
            
            // Pastikan user terautentikasi
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }
            
            // Query untuk RO Supplier yang sudah approved dan belum packing
            $query = FoodFloorOrder::with(['items', 'outlet', 'warehouseOutlet'])
                ->where('fo_mode', 'RO Supplier')
                ->whereIn('status', ['approved', 'submitted'])
                ->whereNotNull('id_outlet'); // Pastikan id_outlet tidak null
            
            // Jika user bukan superuser (id_outlet != 1), hanya tampilkan RO dari outlet mereka
            if ($user->id_outlet != 1) {
                $query->where('id_outlet', $user->id_outlet);
            }
            // Jika user adalah superuser (id_outlet = 1), tampilkan semua RO Supplier
            
            $roSuppliers = $query->orderBy('created_at', 'desc')->get();

            // Filter RO Supplier yang belum semua itemnya dibuat PO
            $roSuppliers = $roSuppliers->filter(function($ro) {
                $totalItems = \DB::table('food_floor_order_items')->where('floor_order_id', $ro->id)->count();
                $itemsInPO = \App\Models\PurchaseOrderFoodItem::where('ro_id', $ro->id)->count();
                
                // Hanya tampilkan RO yang belum semua itemnya dibuat PO
                return $totalItems > $itemsInPO;
            });

            // Debug: Cek unique outlet IDs
            $uniqueOutlets = $roSuppliers->pluck('id_outlet')->unique()->values();

            // Debug: Log RO Supplier yang difilter

            // Debug logging

            // Transform data untuk frontend
            $transformedData = $roSuppliers->map(function($ro) {
                return [
                    'id' => $ro->id,
                    'order_number' => $ro->order_number,
                    'tanggal' => $ro->tanggal,
                    'description' => $ro->description,
                    'status' => $ro->status,
                    'id_outlet' => $ro->id_outlet,
                    'outlet_name' => $ro->outlet ? $ro->outlet->nama_outlet : 'Unknown Outlet',
                    'warehouse_outlet_id' => $ro->warehouse_outlet_id,
                    'warehouse_outlet_name' => $ro->warehouseOutlet ? $ro->warehouseOutlet->name : 'Unknown Warehouse',
                    'items' => $ro->items->map(function($item) use ($ro) {
                        return [
                            'id' => $item->id,
                            'item_id' => $item->item_id,
                            'item_name' => $item->item->name ?? $item->item_name ?? 'Unknown Item',
                            'qty' => $item->qty,
                            'unit' => $item->unit,
                            'price' => $item->price,
                            'subtotal' => $item->subtotal,
                            'category_id' => $item->category_id,
                            'warehouse_division_id' => $item->warehouse_division_id,
                            'arrival_date' => $item->arrival_date ?? null,
                            'source' => 'ro_supplier', // Mark as RO Supplier item
                            'ro_id' => $ro->id,
                            'ro_number' => $ro->order_number,
                        ];
                    })
                ];
            });

            // Pastikan mengembalikan array, bukan object
            return response()->json($transformedData->values()->toArray());
        } catch (\Exception $e) {
            \Log::error('Error fetching RO Supplier available:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to fetch RO Supplier data'], 500);
        }
    }

    /**
     * Check budget untuk Food Floor Order
     */
    private function checkBudgetForFloorOrder($order)
    {
        try {

            $budgetViolations = [];
            $budgetInfo = [];

            // Load items dengan relasi
            $order->load('items');
            
            foreach ($order->items as $item) {
                // Cari item master untuk mendapatkan sub_category_id
                $itemMaster = \DB::table('items')->where('id', $item->item_id)->first();
                
                if (!$itemMaster || !$itemMaster->sub_category_id) {
                    continue;
                }

                // Cek apakah ada budget lock untuk sub_category_id ini
                $lockedBudget = \DB::table('locked_budget_food_categories')
                    ->where('sub_category_id', $itemMaster->sub_category_id)
                    ->where('outlet_id', $order->id_outlet)
                    ->first();

                if (!$lockedBudget) {
                    continue;
                }

                // Ambil informasi sub category dan category
                $subCategoryInfo = \DB::table('sub_categories as sc')
                    ->join('categories as c', 'sc.category_id', '=', 'c.id')
                    ->where('sc.id', $itemMaster->sub_category_id)
                    ->select('sc.name as sub_category_name', 'c.name as category_name')
                    ->first();


                // Hitung total transaksi bulan berjalan
                $currentMonth = date('Y-m');
                
                // 1. Total dari retail_food_items
                $retailFoodTotal = \DB::table('retail_food_items as rfi')
                    ->join('retail_food as rf', 'rfi.retail_food_id', '=', 'rf.id')
                    ->join('items as i', \DB::raw('TRIM(i.name)'), '=', \DB::raw('TRIM(rfi.item_name)'))
                    ->where('i.sub_category_id', $itemMaster->sub_category_id)
                    ->where('rf.outlet_id', $order->id_outlet)
                    ->where('rf.status', 'approved')
                    ->whereRaw("DATE_FORMAT(rf.transaction_date, '%Y-%m') = ?", [$currentMonth])
                    ->sum('rfi.subtotal');

                // 2. Total dari food_floor_order_items (exclude current order)
                $foodFloorOrderTotal = \DB::table('food_floor_order_items as ffoi')
                    ->join('food_floor_orders as ffo', 'ffoi.floor_order_id', '=', 'ffo.id')
                    ->join('items as i', 'ffoi.item_id', '=', 'i.id')
                    ->where('i.sub_category_id', $itemMaster->sub_category_id)
                    ->where('ffo.id_outlet', $order->id_outlet)
                    ->whereIn('ffo.status', ['approved', 'received'])
                    ->where('ffo.id', '!=', $order->id) // Exclude current order
                    ->whereRaw("DATE_FORMAT(ffo.tanggal, '%Y-%m') = ?", [$currentMonth])
                    ->sum('ffoi.subtotal');

                // 3. Total gabungan
                $monthlyTotal = $retailFoodTotal + $foodFloorOrderTotal;
                
                // Hitung subtotal item baru
                $newItemSubtotal = $item->qty * $item->price;
                
                // Total setelah ditambah item baru
                $totalAfterNewItem = $monthlyTotal + $newItemSubtotal;


                // Cek apakah melebihi budget
                if ($totalAfterNewItem > $lockedBudget->budget) {
                    $violation = [
                        'item_name' => $item->item_name,
                        'category_name' => $subCategoryInfo->category_name ?? 'N/A',
                        'sub_category_name' => $subCategoryInfo->sub_category_name ?? 'N/A',
                        'budget_amount' => $lockedBudget->budget,
                        'retail_food_total' => $retailFoodTotal,
                        'food_floor_order_total' => $foodFloorOrderTotal,
                        'monthly_total' => $monthlyTotal,
                        'new_item_subtotal' => $newItemSubtotal,
                        'total_after_new_item' => $totalAfterNewItem,
                        'excess_amount' => $totalAfterNewItem - $lockedBudget->budget
                    ];
                    
                    $budgetViolations[] = $violation;
                    
                    \Log::error('FO_BUDGET_CHECK: Budget terlampaui', $violation);
                } else {
                    $budgetInfo[] = [
                        'item_name' => $item->item_name,
                        'category_name' => $subCategoryInfo->category_name ?? 'N/A',
                        'sub_category_name' => $subCategoryInfo->sub_category_name ?? 'N/A',
                        'budget_amount' => $lockedBudget->budget,
                        'retail_food_total' => $retailFoodTotal,
                        'food_floor_order_total' => $foodFloorOrderTotal,
                        'monthly_total' => $monthlyTotal,
                        'new_item_subtotal' => $newItemSubtotal,
                        'total_after_new_item' => $totalAfterNewItem,
                        'remaining_budget' => $lockedBudget->budget - $totalAfterNewItem,
                        'budget_percentage' => $totalAfterNewItem > 0 ? round(($totalAfterNewItem / $lockedBudget->budget) * 100, 2) : 0
                    ];
                }
            }

            // Jika ada pelanggaran budget
            if (!empty($budgetViolations)) {
                $message = "Transaksi ditolak! Budget untuk beberapa kategori telah terlampaui.\n\n";
                
                foreach ($budgetViolations as $violation) {
                    $message .= "📊 {$violation['sub_category_name']} (Kategori: {$violation['category_name']}):\n";
                    $message .= "• Budget yang ditetapkan: Rp " . number_format((float)$violation['budget_amount'], 0, ',', '.') . "\n";
                    $message .= "• Total Retail Food (bulan ini): Rp " . number_format((float)$violation['retail_food_total'], 0, ',', '.') . "\n";
                    $message .= "• Total Food Floor Order (bulan ini): Rp " . number_format((float)$violation['food_floor_order_total'], 0, ',', '.') . "\n";
                    $message .= "• Total Gabungan: Rp " . number_format((float)$violation['monthly_total'], 0, ',', '.') . "\n";
                    $message .= "• Item baru: Rp " . number_format((float)$violation['new_item_subtotal'], 0, ',', '.') . "\n";
                    $message .= "• Total setelah item baru: Rp " . number_format((float)$violation['total_after_new_item'], 0, ',', '.') . "\n";
                    $message .= "• Kelebihan: Rp " . number_format((float)$violation['excess_amount'], 0, ',', '.') . "\n\n";
                }

                return [
                    'success' => false,
                    'message' => $message,
                    'violations' => $budgetViolations
                ];
            }

            return [
                'success' => true,
                'budget_info' => $budgetInfo
            ];

        } catch (\Exception $e) {
            \Log::error('FO_BUDGET_CHECK: Error terjadi', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $order->id
            ]);
            
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengecek budget: ' . $e->getMessage()
            ];
        }
    }

    // API: Get pending RO Khusus approvals
    public function getPendingROKhususApprovals(Request $request)
    {
        try {
            $user = Auth::user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            $query = FoodFloorOrder::with(['outlet', 'requester', 'warehouseOutlet', 'items'])
                ->where('fo_mode', 'RO Khusus')
                ->where('status', 'submitted')
                ->orderByDesc('created_at');
            
            $pendingApprovals = [];
            
            // Filter berdasarkan warehouse outlet dan jabatan user
            if ($isSuperadmin) {
                // Superadmin bisa lihat semua
                $allApprovals = $query->get();
            } else {
                // Filter berdasarkan warehouse outlet yang bisa di-approve user
                $allApprovals = $query->get()->filter(function($order) use ($user) {
                    return $this->canUserApproveByWarehouse($user, $order->warehouse_outlet_id);
                });
            }
            
            foreach ($allApprovals as $order) {
                $warehouseName = $order->warehouseOutlet ? $order->warehouseOutlet->name : 'Unknown';
                $approvalLevelDisplay = $this->getApprovalLevelDisplay($warehouseName);
                
                // Get approver name based on warehouse outlet
                $approverName = null;
                if ($isSuperadmin) {
                    // For superadmin, get approver based on warehouse outlet
                    $approver = $this->getApproverByWarehouse($warehouseName);
                    $approverName = $approver ? $approver->nama_lengkap : $approvalLevelDisplay;
                } else {
                    // For regular users, they are the approver
                    $approverName = $user->nama_lengkap;
                }
                
                $pendingApprovals[] = [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'tanggal' => $order->tanggal,
                    'arrival_date' => $order->arrival_date,
                    'outlet' => $order->outlet ? ['nama_outlet' => $order->outlet->nama_outlet] : null,
                    'warehouse_outlet' => $order->warehouseOutlet ? ['name' => $order->warehouseOutlet->name] : null,
                    'requester' => $order->requester ? ['nama_lengkap' => $order->requester->nama_lengkap] : null,
                    'items_count' => $order->items->count(),
                    'description' => $order->description,
                    'approval_level' => 'ro_khusus',
                    'approval_level_display' => $approvalLevelDisplay,
                    'approver_name' => $approverName,
                    'created_at' => $order->created_at
                ];
            }
            
            return response()->json([
                'success' => true,
                'ro_khusus' => $pendingApprovals
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting pending RO Khusus approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get pending approvals'
            ], 500);
        }
    }

    // Helper untuk mendapatkan approval level display
    private function getApprovalLevelDisplay($warehouseName)
    {
        switch ($warehouseName) {
            case 'Kitchen':
                return 'Kitchen Manager';
            case 'Bar':
                return 'Bar Manager';
            case 'Service':
                return 'Service Manager';
            default:
                return 'Manager';
        }
    }
    
    // Helper untuk mendapatkan approver berdasarkan warehouse outlet
    private function getApproverByWarehouse($warehouseName)
    {
        $jabatanIds = [];
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
                return null;
        }
        
        // Get first active user with matching jabatan
        return \App\Models\User::whereIn('id_jabatan', $jabatanIds)
            ->where('status', 'A')
            ->first();
    }

    // API: Get RO Khusus detail for approval modal
    public function getROKhususDetail($id)
    {
        try {
            $order = FoodFloorOrder::with([
                'outlet',
                'requester',
                'warehouseOutlet',
                'approver',
                'items.item',
                'items.category'
            ])->findOrFail($id);
            
            // Ensure items have proper data
            $order->items->transform(function($item) {
                if (!$item->item && $item->item_id) {
                    $item->item = \App\Models\Item::find($item->item_id);
                }
                // Unit is already in the item as 'unit' column (varchar)
                // No need to transform, just ensure it's accessible
                return $item;
            });
            
            // Calculate total_amount from items (qty * price)
            $totalAmount = $order->items->sum(function($item) {
                return ($item->qty ?? 0) * ($item->price ?? 0);
            });
            
            // Add total_amount to order object
            $order->total_amount = $totalAmount;
            
            return response()->json([
                'success' => true,
                'ro_khusus' => $order,
                'current_approval_flow_id' => null, // RO Khusus doesn't use approval flow system
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting RO Khusus detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load RO Khusus detail'
            ], 500);
        }
    }

    public function exportPdf($id)
    {
        $order = FoodFloorOrder::with([
            'outlet', 
            'requester', 
            'foSchedule', 
            'approver', 
            'warehouseOutlet',
            'items.category'
        ])->findOrFail($id);

        // Group items by category
        $groupedItems = [];
        foreach ($order->items as $item) {
            $categoryName = $item->category ? $item->category->name : 'Lainnya';
            if (!isset($groupedItems[$categoryName])) {
                $groupedItems[$categoryName] = [];
            }
            $groupedItems[$categoryName][] = $item;
        }

        // Calculate subtotals
        $subtotal = $order->items->sum(function($item) {
            return ($item->qty ?? 0) * ($item->price ?? 0);
        });

        $pdf = Pdf::loadView('exports.floor_order_pdf', [
            'order' => $order,
            'groupedItems' => $groupedItems,
            'subtotal' => $subtotal
        ]);

        $filename = 'RO-' . $order->order_number . '.pdf';
        return $pdf->download($filename);
    }
} 