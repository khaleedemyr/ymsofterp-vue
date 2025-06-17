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
        return inertia('OutletFoodInventoryAdjustment/Index', [
            'adjustments' => $adjustments,
            'filters' => $request->only(['search', 'outlet_id', 'from', 'to']),
            'user_outlet_id' => $user->id_outlet,
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
        $warehouse_outlets = DB::table('warehouse_outlets')->select('id', 'name')->orderBy('name')->get();
        return inertia('OutletFoodInventoryAdjustment/Form', [
            'outlets' => $outlets,
            'items' => $items,
            'outlet_selectable' => $outlet_selectable,
            'user_outlet_id' => $user->id_outlet,
            'warehouse_outlets' => $warehouse_outlets,
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $number = $this->generateAdjustmentNumber();
            $headerId = DB::table('outlet_food_inventory_adjustments')->insertGetId([
                'number' => $number,
                'date' => $request->date,
                'id_outlet' => $request->outlet_id,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'type' => $request->type,
                'reason' => $request->reason,
                'status' => 'waiting_cost_control',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($request->items as $item) {
                DB::table('outlet_food_inventory_adjustment_items')->insert([
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
                'module' => 'outlet_stock_adjustment',
                'description' => 'Membuat outlet stock adjustment baru: ' . $number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);
            // Notifikasi hanya ke Cost Control Manager
            $notifUsers = DB::table('users')
                ->where('id_jabatan', 167)
                ->where('status', 'A')
                ->pluck('id');
            // (opsional) insert notifikasi ke tabel notifications jika ada
            DB::commit();
            return redirect()->route('outlet-food-inventory-adjustment.show', $headerId)
                ->with('success', 'Outlet stock adjustment berhasil dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal membuat outlet stock adjustment: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
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
        $items = DB::table('outlet_food_inventory_adjustment_items as i')
            ->leftJoin('items as it', 'i.item_id', '=', 'it.id')
            ->select('i.*', 'it.name as item_name')
            ->where('i.adjustment_id', $id)
            ->get();
        $adjustment->items = $items;
        $user = auth()->user();
        return inertia('OutletFoodInventoryAdjustment/Show', [
            'adjustment' => $adjustment,
            'user' => $user,
        ]);
    }

    public function approve(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            $adj = DB::table('outlet_food_inventory_adjustments')->where('id', $id)->first();
            if (!$adj) throw new \Exception('Adjustment not found');
            $update = [];
            $desc = '';
            if ($isSuperadmin) {
                // Superadmin bisa approve di semua tahap
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
            // Update status dan approval field
            DB::table('outlet_food_inventory_adjustments')->where('id', $id)->update($update);
            // Insert activity log
            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => 'approve',
                'module' => 'outlet_stock_adjustment',
                'description' => $desc,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
            // Jika status sudah approved, proses inventory
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
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $adj = DB::table('outlet_food_inventory_adjustments')->where('id', $id)->first();
            if (!$adj) throw new \Exception('Adjustment not found');
            $update = ['status' => 'rejected', 'updated_at' => now()];
            if ($user->id_jabatan == 167) {
                $update['cost_control_manager_note'] = $request->note;
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
            $adj = OutletFoodInventoryAdjustment::with('items')->findOrFail($id);
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
                    $stock = OutletFoodInventoryStock::where('inventory_item_id', $inventory_item_id)
                        ->where('id_outlet', $adj->id_outlet)->first();
                    if ($stock) {
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
                    }
                    OutletFoodInventoryCard::where('reference_type', 'outlet_stock_adjustment')
                        ->where('reference_id', $adj->id)
                        ->where('inventory_item_id', $inventory_item_id)
                        ->delete();
                }
            }
            $adj->items()->delete();
            $adj->delete();
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
} 