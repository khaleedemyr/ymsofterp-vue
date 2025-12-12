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

class FoodInventoryAdjustmentController extends Controller
{
    public function index(Request $request)
    {
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
            // Notifikasi hanya ke SSD Manager dan Cost Control Manager
            $notifUsers = DB::table('users')
                ->whereIn('id_jabatan', [161, 167])
                ->where('status', 'A')
                ->pluck('id');
            foreach ($notifUsers as $uid) {
                DB::table('notifications')->insert([
                    'user_id' => $uid,
                    'type' => 'stock_adjustment_approval',
                    'message' => "Stock Adjustment #$number menunggu approval",
                    'url' => '/food-inventory-adjustment/' . $headerId,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
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
            $update = [];
            $desc = '';
            if ($isSuperadmin) {
                // Superadmin mengikuti flow approval, bukan langsung approved
                if ($adj->status == 'waiting_approval') {
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
            } else if (in_array($user->id_jabatan, [161, 172]) && $adj->status == 'waiting_approval') {
                $update['status'] = 'waiting_cost_control';
                $update['approved_by_ssd_manager'] = $user->id;
                $update['approved_at_ssd_manager'] = now();
                $update['ssd_manager_note'] = $request->note;
                $desc = 'SSD Manager approve stock adjustment ID: ' . $id;
            } else if ($user->id_jabatan == 167 && $adj->status == 'waiting_cost_control') {
                $update['status'] = 'approved';
                $update['approved_by_cost_control_manager'] = $user->id;
                $update['approved_at_cost_control_manager'] = now();
                $update['cost_control_manager_note'] = $request->note;
                $desc = 'Cost Control Manager approve stock adjustment ID: ' . $id;
            } else {
                throw new \Exception('Anda tidak berhak approve pada tahap ini');
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
            if (
                ($isSuperadmin && $adj->status == 'waiting_cost_control') ||
                ($user->id_jabatan == 167 && $adj->status == 'waiting_cost_control')
            ) {
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
            $update = ['status' => 'rejected', 'updated_at' => now()];
            if (in_array($user->id_jabatan, [161, 172])) {
                $update['ssd_manager_note'] = $request->note;
            } else if ($user->id_jabatan == 167) {
                $update['cost_control_manager_note'] = $request->note;
            } else if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
                if ($adj->status == 'waiting_approval') {
                    $update['ssd_manager_note'] = $request->note;
                } else if ($adj->status == 'waiting_cost_control') {
                    $update['cost_control_manager_note'] = $request->note;
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
            $adj = FoodInventoryAdjustment::with(['items'])->findOrFail($id);
            // Jika sudah approved, rollback inventory
            if ($adj->status === 'approved') {
                foreach ($adj->items as $item) {
                    $inventoryItem = \App\Models\FoodInventoryItem::where('item_id', $item->item_id)->first();
                    if (!$inventoryItem) continue;
                    $inventory_item_id = $inventoryItem->id;
                    $itemMaster = \App\Models\Item::find($item->item_id);
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
                    $stock = \App\Models\FoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                        ->where('warehouse_id', $adj->warehouse_id)->first();
                    if ($stock) {
                        if ($adj->type === 'in') {
                            // Rollback stock in: kurangi stok
                            $stock->qty_small -= $qty_small;
                            $stock->qty_medium -= $qty_medium;
                            $stock->qty_large -= $qty_large;
                        } else {
                            // Rollback stock out: tambahkan stok
                            $stock->qty_small += $qty_small;
                            $stock->qty_medium += $qty_medium;
                            $stock->qty_large += $qty_large;
                        }
                        $stock->value = ($stock->qty_small * $stock->last_cost_small)
                            + ($stock->qty_medium * $stock->last_cost_medium)
                            + ($stock->qty_large * $stock->last_cost_large);
                        $stock->save();
                    }
                    // Hapus kartu stok
                    \App\Models\FoodInventoryCard::where('reference_type', 'stock_adjustment')
                        ->where('reference_id', $adj->id)
                        ->where('inventory_item_id', $inventory_item_id)
                        ->delete();
                }
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

    // Proses update inventory setelah approved
    private function processInventory($adjustmentId)
    {
        $adj = FoodInventoryAdjustment::with(['items', 'warehouse'])->find($adjustmentId);
        if (!$adj) return;
        foreach ($adj->items as $item) {
            $inventoryItem = \App\Models\FoodInventoryItem::where('item_id', $item->item_id)->first();
            if (!$inventoryItem) {
                $itemMaster = \App\Models\Item::find($item->item_id);
                $inventoryItem = \App\Models\FoodInventoryItem::create([
                    'item_id' => $item->item_id,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $inventory_item_id = $inventoryItem->id;
            $itemMaster = \App\Models\Item::find($item->item_id);
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
            // Update stok sesuai tipe (in/out)
            $stock = \App\Models\FoodInventoryStock::firstOrCreate(
                [
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $adj->warehouse_id
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
            // Update value dan cost jika stock in
            if ($adj->type === 'in') {
                // Asumsi cost pakai last_cost_small
                $cost = $stock->last_cost_small;
                $stock->value = ($stock->qty_small * $stock->last_cost_small)
                    + ($stock->qty_medium * $stock->last_cost_medium)
                    + ($stock->qty_large * $stock->last_cost_large);
            } else {
                $stock->value = ($stock->qty_small * $stock->last_cost_small)
                    + ($stock->qty_medium * $stock->last_cost_medium)
                    + ($stock->qty_large * $stock->last_cost_large);
            }
            $stock->save();
            // Insert kartu stok
            \App\Models\FoodInventoryCard::create([
                'inventory_item_id' => $inventory_item_id,
                'warehouse_id' => $adj->warehouse_id,
                'date' => $adj->date,
                'reference_type' => 'stock_adjustment',
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
                'description' => 'Stock Adjustment',
            ]);
        }
    }
}

// Model relasi header-detail
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\NotificationService;

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