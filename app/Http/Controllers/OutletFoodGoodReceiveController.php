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
            'good_receive_id' => 'required|exists:outlet_food_good_receives,id',
            'barcode' => 'required|string',
            'qty' => 'nullable|numeric', // hanya diisi jika unit kg
        ]);

        DB::beginTransaction();
        try {
            $gr = DB::table('outlet_food_good_receives')->where('id', $request->good_receive_id)->first();
            if (!$gr) throw new \Exception('Good Receive tidak ditemukan');

            // Cari item di DO berdasarkan barcode
            $item = DB::table('delivery_order_items as doi')
                ->join('items as i', 'doi.item_id', '=', 'i.id')
                ->where('doi.delivery_order_id', $gr->delivery_order_id)
                ->where('i.barcode', $request->barcode)
                ->select('doi.*', 'i.name as item_name', 'doi.unit')
                ->first();
            if (!$item) throw new \Exception('Item tidak ditemukan di DO');

            // Cek satuan
            if ($item->unit == 'pcs') {
                $qtyToAdd = 1;
            } else {
                // kg, gr, liter, dst
                if (!$request->qty || $request->qty <= 0) throw new \Exception('Qty harus diisi untuk item kiloan');
                $qtyToAdd = $request->qty;
            }

            // Update qty_scan
            $newQtyScan = $item->qty_scan + $qtyToAdd;
            if ($newQtyScan > $item->qty_packing_list) {
                throw new \Exception('Qty scan melebihi qty DO');
            }
            DB::table('delivery_order_items')->where('id', $item->id)->update([
                'qty_scan' => $newQtyScan,
                'updated_at' => now(),
            ]);

            // Simpan ke outlet_food_good_receive_items jika belum ada
            $grItem = DB::table('outlet_food_good_receive_items')
                ->where('outlet_food_good_receive_id', $gr->id)
                ->where('item_id', $item->item_id)
                ->first();
            if (!$grItem) {
                DB::table('outlet_food_good_receive_items')->insert([
                    'outlet_food_good_receive_id' => $gr->id,
                    'item_id' => $item->item_id,
                    'unit_id' => null, // bisa diisi jika perlu
                    'qty' => $item->qty_packing_list,
                    'received_qty' => $qtyToAdd,
                    'remaining_qty' => $item->qty_packing_list - $qtyToAdd,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('outlet_food_good_receive_items')
                    ->where('id', $grItem->id)
                    ->update([
                        'received_qty' => $grItem->received_qty + $qtyToAdd,
                        'remaining_qty' => $item->qty_packing_list - ($grItem->received_qty + $qtyToAdd),
                        'updated_at' => now(),
                    ]);
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
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

    public function availableDOs(Request $request)
    {
        $q = $request->input('q');
        $user = auth()->user();
        $idOutlet = $user->id_outlet; // Ambil id_outlet user login

        $query = DB::table('delivery_orders as do')
            ->leftJoin('outlet_food_good_receives as gr', function($join) {
                $join->on('gr.delivery_order_id', '=', 'do.id')->whereNull('gr.deleted_at');
            })
            ->leftJoin('food_floor_orders as fo', 'do.floor_order_id', '=', 'fo.id')
            ->whereNull('gr.id')
            ->where('fo.id_outlet', $idOutlet);

        if ($q) {
            $query->where('do.number', 'like', "%$q%");
        }
        $dos = $query->orderByDesc('do.created_at')
            ->select('do.id', 'do.number')
            ->limit(20)
            ->get();
        return response()->json($dos);
    }

    public function doDetail($do_id)
    {
        // Info DO, Packing List, FO
        $do = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'do.floor_order_id', '=', 'fo.id')
            ->select(
                'do.id as do_id', 'do.number as do_number', 'do.packing_list_id', 'do.floor_order_id',
                'pl.packing_number', 'pl.reason as packing_reason',
                'fo.order_number as floor_order_number', 'fo.tanggal as floor_order_date', 'fo.description as floor_order_desc',
                'do.created_at as do_created_at'
            )
            ->where('do.id', $do_id)
            ->first();

        // List item DO
        $items = DB::table('delivery_order_items as doi')
            ->leftJoin('items as i', 'doi.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'i.small_unit_id', '=', 'u.id')
            ->select(
                'doi.id as delivery_order_item_id',
                'doi.item_id',
                'i.name as item_name',
                'doi.qty_packing_list',
                'doi.qty_scan',
                'doi.unit',
                'i.barcode',
                'u.name as unit_name'
            )
            ->where('doi.delivery_order_id', $do_id)
            ->get();

        return response()->json([
            'do' => $do,
            'items' => $items,
        ]);
    }

    public function submit(Request $request, $id)
    {
        $gr = DB::table('outlet_food_good_receives')->where('id', $id)->first();
        if (!$gr) {
            return response()->json(['success' => false, 'message' => 'Good Receive tidak ditemukan'], 404);
        }
        // Ambil semua item DO
        $items = DB::table('delivery_order_items')
            ->where('delivery_order_id', $gr->delivery_order_id)
            ->get();
        foreach ($items as $item) {
            if (floatval($item->qty_scan) < floatval($item->qty_packing_list)) {
                return response()->json(['success' => false, 'message' => 'Masih ada item yang belum discan sesuai qty DO!'], 400);
            }
        }
        // Update status GR
        DB::table('outlet_food_good_receives')->where('id', $id)->update([
            'status' => 'done',
            'updated_at' => now(),
            'updated_by' => auth()->id(),
        ]);
        return response()->json(['success' => true]);
    }

    public function processStock($id)
    {
        DB::beginTransaction();
        try {
            $gr = DB::table('outlet_food_good_receives')->where('id', $id)->first();
            if (!$gr) throw new \Exception('Good Receive tidak ditemukan');
            if ($gr->status !== 'done') throw new \Exception('GR belum disubmit atau sudah diproses');

            $items = DB::table('outlet_food_good_receive_items')->where('outlet_food_good_receive_id', $id)->get();
            foreach ($items as $item) {
                // Ambil master item
                $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
                if (!$itemMaster) continue;

                // Insert/update outlet_food_inventory_items
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $item->item_id)
                    ->where('outlet_id', $gr->outlet_id)
                    ->first();
                if (!$inventoryItem) {
                    $inventoryItemId = DB::table('outlet_food_inventory_items')->insertGetId([
                        'item_id' => $item->item_id,
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

                // Konversi qty ke small/medium/large
                $unit = DB::table('units')->where('id', $item->unit_id)->value('name');
                $qty_input = $item->received_qty;
                $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
                $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
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

                // Update/insert outlet_food_inventory_stocks
                $stock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $gr->outlet_id)
                    ->first();
                if ($stock) {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty_small' => $stock->qty_small + $qty_small,
                            'qty_medium' => $stock->qty_medium + $qty_medium,
                            'qty_large' => $stock->qty_large + $qty_large,
                            'value' => ($stock->qty_small + $qty_small) * $stock->last_cost_small,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'id_outlet' => $gr->outlet_id,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $qty_small * 0, // cost bisa diisi jika ada
                        'last_cost_small' => 0,
                        'last_cost_medium' => 0,
                        'last_cost_large' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Insert kartu stok (stock in)
                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $gr->outlet_id,
                    'date' => $gr->receive_date,
                    'reference_type' => 'good_receive_outlet',
                    'reference_id' => $gr->id,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'out_qty_small' => 0,
                    'out_qty_medium' => 0,
                    'out_qty_large' => 0,
                    'cost_per_small' => 0,
                    'cost_per_medium' => 0,
                    'cost_per_large' => 0,
                    'value_in' => 0,
                    'value_out' => 0,
                    'saldo_qty_small' => $stock ? $stock->qty_small + $qty_small : $qty_small,
                    'saldo_qty_medium' => $stock ? $stock->qty_medium + $qty_medium : $qty_medium,
                    'saldo_qty_large' => $stock ? $stock->qty_large + $qty_large : $qty_large,
                    'saldo_value' => 0,
                    'description' => 'Good Receive Outlet',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            // Update status GR jadi 'stocked'
            DB::table('outlet_food_good_receives')->where('id', $id)->update([
                'status' => 'stocked',
                'updated_at' => now(),
            ]);
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
} 