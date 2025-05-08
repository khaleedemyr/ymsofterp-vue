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
        $prFoods = $query->paginate(10)->withQueryString();
        return Inertia::render('PrFoods/Index', [
            'prFoods' => $prFoods,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show($id)
    {
        $prFood = PrFood::with(['warehouse', 'requester', 'items.item'])->findOrFail($id);
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
        return redirect()->route('pr-foods.index');
    }

    public function edit($id)
    {
        $prFood = PrFood::with('items')->findOrFail($id);
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
        $prFood = PrFood::findOrFail($id);
        $prFood->update([
            'ssd_manager_approved_at' => now(),
            'ssd_manager_approved_by' => Auth::id(),
            'ssd_manager_note' => $request->ssd_manager_note,
            'status' => $request->approved ? 'approved' : 'rejected',
        ]);
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
        return redirect()->route('pr-foods.index');
    }

    // Approval Vice COO
    public function approveViceCoo(Request $request, $id)
    {
        $prFood = PrFood::findOrFail($id);
        $prFood->update([
            'vice_coo_approved_at' => now(),
            'vice_coo_approved_by' => Auth::id(),
            'vice_coo_note' => $request->vice_coo_note,
            'status' => $request->approved ? 'po' : 'rejected',
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
        return redirect()->route('pr-foods.index');
    }
} 