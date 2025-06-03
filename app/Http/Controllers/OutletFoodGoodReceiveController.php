<?php

namespace App\Http\Controllers;

use App\Models\OutletFoodGoodReceive;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class OutletFoodGoodReceiveController extends Controller
{
    public function index(Request $request)
    {
        $query = OutletFoodGoodReceive::query()
            ->with(['outlet', 'deliveryOrder'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhereHas('deliveryOrder', function ($q) use ($search) {
                            $q->where('number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('outlet', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->outlet_id, function ($q, $outletId) {
                $q->where('outlet_id', $outletId);
            })
            ->when($request->from, function ($q, $from) {
                $q->where('receive_date', '>=', $from);
            })
            ->when($request->to, function ($q, $to) {
                $q->where('receive_date', '<=', $to);
            })
            ->orderBy('created_at', 'desc');

        $goodReceives = $query->get()->map(function ($gr) {
            return [
                'id' => $gr->id,
                'number' => $gr->number,
                'receive_date' => $gr->receive_date,
                'status' => $gr->status,
                'outlet_id' => $gr->outlet_id,
                'outlet_name' => $gr->outlet->name,
                'delivery_order_id' => $gr->delivery_order_id,
                'delivery_order_number' => $gr->deliveryOrder->number,
            ];
        });

        return Inertia::render('OutletFoodGoodReceive/Index', [
            'goodReceives' => $goodReceives,
            'outlets' => Outlet::select('id_outlet as id', 'nama_outlet as name')->get(),
            'filters' => $request->only(['search', 'outlet_id', 'from', 'to'])
        ]);
    }

    public function create()
    {
        return Inertia::render('OutletFoodGoodReceive/Create', [
            'outlets' => Outlet::select('id_outlet as id', 'nama_outlet as name')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'delivery_order_id' => 'required|exists:delivery_orders,id',
            'receive_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);

        $goodReceive = OutletFoodGoodReceive::create([
            'number' => 'GR-' . date('Ymd') . '-' . str_pad(OutletFoodGoodReceive::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT),
            'outlet_id' => $validated['outlet_id'],
            'delivery_order_id' => $validated['delivery_order_id'],
            'receive_date' => $validated['receive_date'],
            'notes' => $validated['notes'],
            'status' => 'draft',
            'created_by' => auth()->id()
        ]);

        return redirect()->route('outlet-food-good-receives.show', $goodReceive->id)
            ->with('success', 'Good Receive berhasil dibuat.');
    }

    public function show(OutletFoodGoodReceive $outletFoodGoodReceive)
    {
        $outletFoodGoodReceive->load(['outlet', 'deliveryOrder', 'items.item', 'items.unit', 'scans']);

        return Inertia::render('OutletFoodGoodReceive/Show', [
            'goodReceive' => $outletFoodGoodReceive
        ]);
    }

    public function destroy(OutletFoodGoodReceive $outletFoodGoodReceive)
    {
        if ($outletFoodGoodReceive->status !== 'draft') {
            return back()->with('error', 'Hanya GR dengan status draft yang dapat dihapus.');
        }

        $outletFoodGoodReceive->delete();

        return back()->with('success', 'Good Receive berhasil dihapus.');
    }

    public function storeScan(Request $request)
    {
        $request->validate([
            'outlet_food_good_receive_id' => 'required|exists:outlet_food_good_receives,id',
            'barcode' => 'required|string',
            'qty' => 'required|numeric|min:1',
            'exp_date' => 'nullable|date',
            'batch_number' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $gr = \App\Models\OutletFoodGoodReceive::with(['items', 'deliveryOrder'])->findOrFail($request->outlet_food_good_receive_id);

            // Cari item_id dari barcode di item_barcodes
            $itemBarcode = \DB::table('item_barcodes')->where('barcode', $request->barcode)->first();
            if (!$itemBarcode) {
                throw new \Exception('Barcode tidak ditemukan pada master item');
            }
            $itemId = $itemBarcode->item_id;

            // Temukan item pada GR
            $grItem = $gr->items()->where('item_id', $itemId)->first();
            if (!$grItem) {
                throw new \Exception('Item tidak terdaftar pada Good Receive ini');
            }

            // Simpan scan
            $scan = $gr->scans()->create([
                'outlet_food_good_receive_item_id' => $grItem->id,
                'barcode' => $request->barcode,
                'qty' => $request->qty,
                'exp_date' => $request->exp_date,
                'batch_number' => $request->batch_number,
                'created_by' => auth()->id(),
            ]);

            // Update received_qty dan remaining_qty
            $grItem->received_qty += $request->qty;
            $grItem->remaining_qty = max(0, $grItem->qty - $grItem->received_qty);
            $grItem->save();

            // Ambil harga dari food_floor_order_items
            $deliveryOrder = $gr->deliveryOrder;
            if (!$deliveryOrder) {
                throw new \Exception('Delivery Order tidak ditemukan pada GR ini');
            }
            $floorOrderId = $deliveryOrder->floor_order_id;
            if (!$floorOrderId) {
                throw new \Exception('floor_order_id tidak ditemukan pada Delivery Order');
            }
            $floorOrderItem = \DB::table('food_floor_order_items')
                ->where('floor_order_id', $floorOrderId)
                ->where('item_id', $itemId)
                ->first();
            if (!$floorOrderItem) {
                throw new \Exception('Item tidak ditemukan pada Food Floor Order');
            }
            $price = $floorOrderItem->price;

            // Ambil master item untuk konversi satuan
            $itemMaster = \DB::table('items')->where('id', $itemId)->first();
            if (!$itemMaster) {
                throw new \Exception('Master item tidak ditemukan');
            }
            $unit_id = $grItem->unit_id;
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
            $qty_small = 0; $qty_medium = 0; $qty_large = 0; $qty_small_for_value = 0;
            if ($unit_id == $itemMaster->large_unit_id) {
                $qty_large = (float) $request->qty;
                $qty_medium = $qty_large * $mediumConv;
                $qty_small = $qty_medium * $smallConv;
                $qty_small_for_value = $qty_small;
            } elseif ($unit_id == $itemMaster->medium_unit_id) {
                $qty_medium = (float) $request->qty;
                $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                $qty_small = $qty_medium * $smallConv;
                $qty_small_for_value = $qty_small;
            } elseif ($unit_id == $itemMaster->small_unit_id) {
                $qty_small = (float) $request->qty;
                $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                $qty_small_for_value = $qty_small;
            }
            // Hitung cost per unit
            $cost_small = $price;
            if ($unit_id == $itemMaster->large_unit_id) {
                $cost_small = $price / (($itemMaster->small_conversion_qty ?: 1) * ($itemMaster->medium_conversion_qty ?: 1));
            } elseif ($unit_id == $itemMaster->medium_unit_id) {
                $cost_small = $price / ($itemMaster->small_conversion_qty ?: 1);
            }
            $cost_medium = $cost_small * ($itemMaster->small_conversion_qty ?: 1);
            $cost_large = $cost_medium * ($itemMaster->medium_conversion_qty ?: 1);

            // Insert/update ke outlet_food_inventory_items
            $inventoryItem = \DB::table('outlet_food_inventory_items')->where('item_id', $itemId)->where('outlet_id', $gr->outlet_id)->first();
            if (!$inventoryItem) {
                $inventoryItemId = \DB::table('outlet_food_inventory_items')->insertGetId([
                    'item_id' => $itemId,
                    'outlet_id' => $gr->outlet_id,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $inventoryItemId = $inventoryItem->id;
            }

            // Insert/update ke outlet_food_inventory_stocks (pakai outlet_id)
            $existingStock = \DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('outlet_id', $gr->outlet_id)
                ->first();
            $qty_lama = $existingStock ? $existingStock->qty_small : 0;
            $nilai_lama = $existingStock ? $existingStock->value : 0;
            $qty_baru = $qty_small;
            $nilai_baru = $qty_small_for_value * $cost_small;
            $total_qty = $qty_lama + $qty_baru;
            $total_nilai = $nilai_lama + $nilai_baru;
            $mac = $total_qty > 0 ? $total_nilai / $total_qty : $cost_small;
            if ($existingStock) {
                \DB::table('outlet_food_inventory_stocks')
                    ->where('id', $existingStock->id)
                    ->update([
                        'qty_small' => $total_qty,
                        'qty_medium' => $existingStock->qty_medium + $qty_medium,
                        'qty_large' => $existingStock->qty_large + $qty_large,
                        'value' => $total_nilai,
                        'last_cost_small' => $mac,
                        'last_cost_medium' => $cost_medium,
                        'last_cost_large' => $cost_large,
                        'updated_at' => now(),
                    ]);
            } else {
                \DB::table('outlet_food_inventory_stocks')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'outlet_id' => $gr->outlet_id,
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'value' => $nilai_baru,
                    'last_cost_small' => $cost_small,
                    'last_cost_medium' => $cost_medium,
                    'last_cost_large' => $cost_large,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert ke outlet_food_inventory_cards (stock card)
            $lastCard = \DB::table('outlet_food_inventory_cards')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('outlet_id', $gr->outlet_id)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first();
            if ($lastCard) {
                $saldo_qty_small = $lastCard->saldo_qty_small + $qty_small;
                $saldo_qty_medium = $lastCard->saldo_qty_medium + $qty_medium;
                $saldo_qty_large = $lastCard->saldo_qty_large + $qty_large;
            } else {
                $saldo_qty_small = $qty_small;
                $saldo_qty_medium = $qty_medium;
                $saldo_qty_large = $qty_large;
            }
            \DB::table('outlet_food_inventory_cards')->insert([
                'inventory_item_id' => $inventoryItemId,
                'outlet_id' => $gr->outlet_id,
                'date' => now(),
                'reference_type' => 'good_receive',
                'reference_id' => $gr->id,
                'in_qty_small' => $qty_small,
                'in_qty_medium' => $qty_medium,
                'in_qty_large' => $qty_large,
                'out_qty_small' => 0,
                'out_qty_medium' => 0,
                'out_qty_large' => 0,
                'cost_per_small' => $cost_small,
                'cost_per_medium' => $cost_medium,
                'cost_per_large' => $cost_large,
                'value_in' => $qty_small_for_value * $cost_small,
                'value_out' => 0,
                'saldo_qty_small' => $saldo_qty_small,
                'saldo_qty_medium' => $saldo_qty_medium,
                'saldo_qty_large' => $saldo_qty_large,
                'saldo_value' => $saldo_qty_small * $cost_small,
                'description' => 'Good Receive',
                'created_at' => now(),
            ]);

            // Insert ke outlet_food_inventory_cost_histories
            \DB::table('outlet_food_inventory_cost_histories')->insert([
                'inventory_item_id' => $inventoryItemId,
                'id_outlet' => $gr->outlet_id,
                'date' => now(),
                'old_cost' => $cost_small,
                'new_cost' => $cost_small,
                'mac' => $mac,
                'type' => 'good_receive',
                'reference_type' => 'good_receive',
                'reference_id' => $gr->id,
                'created_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Scan berhasil disimpan',
                'scan' => $scan,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function scanDO()
    {
        return Inertia::render('OutletFoodGoodReceive/ScanDO');
    }

    public function validateDO(Request $request)
    {
        $number = $request->number;
        \Log::info('validateDO called', ['number' => $number]);
        $do = \DB::table('delivery_orders')->where('number', $number)->first();
        \Log::info('validateDO result', ['do' => $do]);
        if ($do) {
            return response()->json(['success' => true, 'delivery_order_id' => $do->id]);
        } else {
            return response()->json(['success' => false, 'message' => 'Delivery Order tidak ditemukan']);
        }
    }

    public function createFromDO($delivery_order_id)
    {
        // Ambil data DO + outlet
        $do = \DB::table('delivery_orders')
            ->leftJoin('food_floor_orders', 'delivery_orders.floor_order_id', '=', 'food_floor_orders.id')
            ->leftJoin('tbl_data_outlet', 'food_floor_orders.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select(
                'delivery_orders.*',
                'food_floor_orders.id_outlet',
                'tbl_data_outlet.nama_outlet as outlet_name'
            )
            ->where('delivery_orders.id', $delivery_order_id)
            ->first();
        if (!$do) abort(404);

        // Ambil item DO
        $items = \DB::table('delivery_order_items')
            ->where('delivery_order_id', $delivery_order_id)
            ->join('items', 'delivery_order_items.item_id', '=', 'items.id')
            ->select(
                'delivery_order_items.id',
                'items.name',
                'delivery_order_items.barcode',
                'delivery_order_items.qty_packing_list as qty'
            )
            ->get()
            ->map(function ($item) {
                $item->received_qty = 0; // selalu mulai dari 0
                return $item;
            });

        // Kirim data DO dan items dalam satu objek deliveryOrder
        return Inertia::render('OutletFoodGoodReceive/CreateFromDO', [
            'deliveryOrder' => [
                'id' => $do->id,
                'number' => $do->number,
                'date' => $do->created_at,
                'outlet_name' => $do->outlet_name ?? '-',
                'status' => $do->status ?? '-',
                'items' => $items,
            ]
        ]);
    }
} 