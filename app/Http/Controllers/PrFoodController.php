<?php

namespace App\Http\Controllers;

use App\Models\PrFood;
use App\Models\PrFoodItem;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PrFoodController extends Controller
{
    public function index(Request $request)
    {
        $query = PrFood::with(['warehouse', 'requester'])
            ->orderByDesc('id');
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
        $prFoods = $query->paginate(10)->withQueryString();
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

        return Inertia::render('PrFoods/Show', [
            'prFood' => $prFood,
        ]);
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $items = Item::all();
        return Inertia::render('PrFoods/Form', [
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
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
        // Notifikasi ke SSD Manager
        $ssdManagers = DB::table('users')->where('id_jabatan', 161)->where('status', 'A')->pluck('id');
        $no_pr = $prFood->pr_number;
        $requester = $prFood->requester->nama_lengkap ?? '-';
        $warehouse = $prFood->warehouse->name ?? '-';
        $this->sendNotification(
            $ssdManagers,
            'pr_approval',
            'Approval PR Foods',
            "PR $no_pr dari $requester ($warehouse) menunggu approval Anda.",
            route('pr-foods.show', $prFood->id)
        );
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
        return redirect()->route('pr-foods.index');
    }

    // Approval SSD Manager
    public function approveSsdManager(Request $request, $id)
    {
        $prFood = PrFood::with(['requester', 'warehouse'])->findOrFail($id);
        $updateData = [
            'ssd_manager_approved_at' => now(),
            'ssd_manager_approved_by' => Auth::id(),
            'ssd_manager_note' => $request->ssd_manager_note,
        ];
        $updateData['status'] = $request->approved ? 'approved' : 'rejected';
        $prFood->update($updateData);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => $request->approved ? 'approve' : 'reject',
            'module' => 'pr_foods',
            'description' => ($request->approved ? 'Approve' : 'Reject') . ' PR Foods (SSD Manager): ' . $prFood->pr_number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $prFood->fresh()->toArray(),
        ]);
        $no_pr = $prFood->pr_number;
        $requester = $prFood->requester->nama_lengkap ?? '-';
        $warehouse = $prFood->warehouse->name ?? '-';
        if ($request->approved) {
            // Jika approved oleh SSD Manager, langsung info Purchasing untuk proses PO
            $adminPurchasing = DB::table('users')->where('id_jabatan', 244)->where('status', 'A')->pluck('id');
            $purchasingManagers = DB::table('users')->where('id_jabatan', 168)->where('status', 'A')->pluck('id');
            $userIds = $adminPurchasing->merge($purchasingManagers);
            $this->sendNotification(
                $userIds,
                'pr_po',
                'Pembuatan PO',
                "PR $no_pr dari $requester ($warehouse) sudah di-approve SSD Manager, silakan buat PO.",
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
                "PR $no_pr dari $requester ($warehouse) telah ditolak oleh SSD Manager.",
                route('pr-foods.show', $prFood->id)
            );
            \Log::info('Notif reject sent', ['user_id' => $requestedBy, 'pr_id' => $prFood->id]);
        }
        return redirect()->route('pr-foods.index');
    }

    // Approval Vice COO
    public function approveViceCoo(Request $request, $id)
    {
        $prFood = PrFood::with(['requester', 'warehouse'])->findOrFail($id);
        $prFood->update([
            'vice_coo_approved_at' => now(),
            'vice_coo_approved_by' => Auth::id(),
            'vice_coo_note' => $request->vice_coo_note,
            'status' => $request->approved ? 'approved' : 'rejected',
        ]);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => $request->approved ? 'approve' : 'reject',
            'module' => 'pr_foods',
            'description' => ($request->approved ? 'Approve' : 'Reject') . ' PR Foods (Vice COO): ' . $prFood->pr_number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $prFood->fresh()->toArray(),
        ]);
        if ($request->approved) {
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

    // Helper untuk insert notifikasi
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
        DB::table('notifications')->insert($data);
    }
} 