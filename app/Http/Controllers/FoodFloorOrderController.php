<?php

namespace App\Http\Controllers;

use App\Models\FoodFloorOrder;
use App\Models\FoodFloorOrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Item;
use Illuminate\Support\Facades\Mail;
use App\Services\FloorOrderService;
use Carbon\Carbon;
use App\Models\WarehouseOutlet;

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
                \Log::info('Mencoba insert ke food_floor_orders', $headerData);
                $inserted = \DB::table('food_floor_orders')->insert($headerData);
                \Log::info('Hasil insert header', ['success' => $inserted]);
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
        $order->delete();
        return redirect()->route('floor-order.index')->with('success', 'Floor Order berhasil dihapus');
    }

    // Submit draft
    public function submit(Request $request, $id)
    {
        \Log::info('SUBMIT FO DIPANGGIL', ['id' => $id]);
        $order = FoodFloorOrder::findOrFail($id);
        $date = now()->format('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $order_number = 'RO-' . $date . '-' . $random;

        $order->update([
            'status' => $order->fo_mode === 'RO Khusus' ? 'submitted' : 'approved',
            'order_number' => $order_number,
        ]);
        \Log::info('FO status & nomor diupdate', ['id' => $order->id, 'status' => $order->status, 'order_number' => $order_number]);

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

        $query = \App\Models\FoodFloorOrder::where('tanggal', $tanggal)
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
        $now = now();
        $data = [];
        foreach ($userIds as $uid) {
            $data[] = [
                'user_id' => $uid,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        \DB::table('notifications')->insert($data);
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

        $order->update([
            'status' => 'approved',
            'approval_by' => $user->id,
            'approval_at' => now(),
            'approval_notes' => $request->notes,
        ]);
        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'approve',
            'module' => 'food_floor_order',
            'description' => 'Approve Floor Order: ' . $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $order->fresh()->toArray(),
        ]);
        return redirect()->back()->with('success', 'Floor Order berhasil di-approve');
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
        switch ($warehouseName) {
            case 'Kitchen':
                return in_array($userJabatan, [174, 180, 345, 346, 347, 348, 349]) && $userStatus === 'A';
            case 'Bar':
                return in_array($userJabatan, [175, 182, 323]) && $userStatus === 'A';
            case 'Service':
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
                $jabatanIds = [174, 180, 345, 346, 347, 348, 349];
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
        $now = now();
        $data = [];
        foreach ($users as $userId) {
            $data[] = [
                'user_id' => $userId,
                'type' => 'floor_order_approval',
                'title' => 'Approval RO Khusus',
                'message' => "RO Khusus {$orderNumber} dari warehouse {$warehouseName} menunggu approval Anda.",
                'url' => route('floor-order.show', $orderId),
                'is_read' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        \DB::table('notifications')->insert($data);
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
            \Log::info('DEBUG WAREHOUSE OUTLET', [
                'order_id' => $order->id,
                'warehouse_outlet_id' => $order->warehouse_outlet_id,
                'warehouseOutlet' => $order->warehouseOutlet
            ]);
          
            $order->loadMissing('items');
            $order->setRelation('outlet', $order->outlet);
            $order->setRelation('requester', $order->requester);
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
            \Log::info('Unique outlet IDs found:', [
                'unique_outlets' => $uniqueOutlets->toArray(),
                'total_ro_suppliers' => $roSuppliers->count()
            ]);

            // Debug: Log RO Supplier yang difilter
            \Log::info('RO Supplier filtering details:', [
                'ro_suppliers_details' => $roSuppliers->map(function($ro) {
                    $totalItems = \DB::table('food_floor_order_items')->where('floor_order_id', $ro->id)->count();
                    $itemsInPO = \App\Models\PurchaseOrderFoodItem::where('ro_id', $ro->id)->count();
                    return [
                        'ro_id' => $ro->id,
                        'ro_number' => $ro->order_number,
                        'status' => $ro->status,
                        'total_items' => $totalItems,
                        'items_in_po' => $itemsInPO,
                        'remaining_items' => $totalItems - $itemsInPO
                    ];
                })
            ]);

            // Debug logging
            \Log::info('RO Supplier Available API called', [
                'user_id' => $user->id,
                'user_outlet_id' => $user->id_outlet,
                'is_superuser' => $user->id_outlet == 1,
                'ro_suppliers_count' => $roSuppliers->count(),
                'ro_suppliers' => $roSuppliers->map(function($ro) {
                    return [
                        'id' => $ro->id,
                        'order_number' => $ro->order_number,
                        'id_outlet' => $ro->id_outlet,
                        'outlet_name' => $ro->outlet ? $ro->outlet->nama_outlet : 'Unknown',
                        'status' => $ro->status,
                        'items_count' => $ro->items->count()
                    ];
                })
            ]);

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
} 