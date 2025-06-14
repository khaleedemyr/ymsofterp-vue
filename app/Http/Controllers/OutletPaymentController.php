<?php

namespace App\Http\Controllers;

use App\Models\OutletPayment;
use App\Models\Outlet;
use App\Models\OutletFoodGoodReceive;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class OutletPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = OutletPayment::with(['outlet', 'goodReceive'])
            ->when($request->outlet, function ($q) use ($request) {
                return $q->where('outlet_id', $request->outlet);
            })
            ->when($request->status, function ($q) use ($request) {
                return $q->where('status', $request->status);
            })
            ->when($request->date, function ($q) use ($request) {
                return $q->whereDate('date', $request->date);
            });

        $payments = $query->latest()->paginate(10)->withQueryString();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        // Ambil filter dari request
        $grSearch = request('gr_search');
        $grFrom = request('gr_from');
        $grTo = request('gr_to');

        // Ambil GR yang belum dibuat payment, join outlet sekalian
        $rawGrList = OutletFoodGoodReceive::whereDoesntHave('outletPayment')
            ->orWhereHas('outletPayment', function ($q) {
                $q->where('status', 'cancelled');
            })
            ->with([
                'outlet',
                'items',
                'creator',
                'deliveryOrder.creator',
                'deliveryOrder.packingList.creator',
                'deliveryOrder.floorOrder.user'
            ])
            ->get();

        // Tambahkan outlet_name dan total_amount ke setiap GR
        foreach ($rawGrList as $gr) {
            $total = \DB::table('outlet_food_good_receive_items as gr_item')
                ->join('outlet_food_good_receives as gr', 'gr_item.outlet_food_good_receive_id', '=', 'gr.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->join('food_floor_order_items as foi', function($join) {
                    $join->on('gr_item.item_id', '=', 'foi.item_id')
                         ->on('do.floor_order_id', '=', 'foi.floor_order_id');
                })
                ->where('gr.id', $gr->id)
                ->sum(\DB::raw('gr_item.received_qty * foi.price'));
            $gr->total_amount = $total;
            $gr->delivery_order = $gr->deliveryOrder;
            $gr->packing_list = $gr->deliveryOrder?->packingList;
            $gr->floor_order = $gr->deliveryOrder?->floorOrder;
            if (!$gr->outlet_name) {
                $gr->outlet_name = $gr->outlet?->name;
                if (!$gr->outlet_name && $gr->outlet_id) {
                    $outlet = \DB::table('tbl_data_outlet')->where('id_outlet', $gr->outlet_id)->first();
                    $gr->outlet_name = $outlet ? $outlet->nama_outlet : '-';
                }
            }
        }

        // Filter by search and date
        $filteredGrList = $rawGrList->filter(function($gr) use ($grSearch, $grFrom, $grTo) {
            $match = true;
            if ($grSearch) {
                $search = mb_strtolower($grSearch);
                $match = (
                    ($gr->outlet_name && mb_stripos($gr->outlet_name, $search) !== false) ||
                    ($gr->number && mb_stripos($gr->number, $search) !== false) ||
                    ($gr->gr_number && mb_stripos($gr->gr_number, $search) !== false)
                );
            }
            if ($match && $grFrom) {
                $match = $match && (date('Y-m-d', strtotime($gr->receive_date)) >= $grFrom);
            }
            if ($match && $grTo) {
                $match = $match && (date('Y-m-d', strtotime($gr->receive_date)) <= $grTo);
            }
            return $match;
        });

        // Grouping per outlet, per tanggal
        $grouped = [];
        foreach ($filteredGrList as $gr) {
            $date = $gr->receive_date instanceof \Carbon\Carbon ? $gr->receive_date->format('Y-m-d') : date('Y-m-d', strtotime($gr->receive_date));
            $outlet = $gr->outlet_name;
            $key = $outlet . '|' . $date;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'outlet_name' => $outlet,
                    'date' => $date,
                    'items' => [],
                    'subtotal' => 0,
                ];
            }
            $grouped[$key]['items'][] = $gr;
            $grouped[$key]['subtotal'] += $gr->total_amount;
        }
        // Convert to array and sort by date desc, outlet asc
        $grGroups = array_values($grouped);
        usort($grGroups, function($a, $b) {
            if ($a['date'] === $b['date']) {
                return strcmp($a['outlet_name'], $b['outlet_name']);
            }
            return strcmp($b['date'], $a['date']); // descending by date
        });
        // Pagination manual (10 group per page)
        $perPage = 10;
        $page = request()->input('gr_page', 1);
        $totalGroups = count($grGroups);
        $paginatedGroups = array_slice($grGroups, ($page-1)*$perPage, $perPage);
        $grGroupsPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedGroups,
            $totalGroups,
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'gr_page']
        );

        return Inertia::render('OutletPayment/Index', [
            'payments' => $payments,
            'outlets' => $outlets,
            'grGroups' => $grGroupsPaginated,
            'filters' => $request->only(['outlet', 'status', 'date'])
        ]);
    }

    public function show(OutletPayment $outletPayment)
    {
        // Load all nested relations needed for the 4 cards
        $outletPayment->load([
            'outlet',
            'goodReceive.items',
            'goodReceive.outlet',
            'goodReceive.creator',
            'goodReceive.deliveryOrder.creator',
            'goodReceive.deliveryOrder.packingList.creator',
            'goodReceive.deliveryOrder.floorOrder.user',
        ]);

        // Attach delivery_order, packing_list, floor_order to goodReceive for easier access in Vue
        $goodReceive = $outletPayment->goodReceive;
        if ($goodReceive) {
            $goodReceive->delivery_order = $goodReceive->deliveryOrder;
            $goodReceive->packing_list = $goodReceive->deliveryOrder?->packingList;
            $goodReceive->floor_order = $goodReceive->deliveryOrder?->floorOrder;

            // Fill item fields (unit, price, item_name) as in Form.vue
            if ($goodReceive->items) {
                foreach ($goodReceive->items as $item) {
                    $foi = \DB::table('delivery_orders as do')
                        ->join('food_floor_order_items as foi', function($join) use ($item) {
                            $join->on('do.floor_order_id', '=', 'foi.floor_order_id')
                                ->where('foi.item_id', '=', $item->item_id);
                        })
                        ->where('do.id', $goodReceive->delivery_order_id)
                        ->select('foi.price')
                        ->first();
                    $item->price = $foi ? floatval($foi->price) : 0;
                    $unit = \DB::table('units')->where('id', $item->unit_id)->first();
                    $item->unit = $unit ? $unit->name : '';
                    $itemMaster = \DB::table('items')->where('id', $item->item_id)->first();
                    $item->item_name = $itemMaster ? $itemMaster->name : '';
                }
            }
        }

        return Inertia::render('OutletPayment/Show', [
            'payment' => $outletPayment
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('DEBUG: OutletPaymentController@store terpanggil', $request->all());
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'gr_id' => 'required|exists:outlet_food_good_receives,id',
            'date' => 'required|date',
            'total_amount' => 'required|numeric|min:0'
        ]);

        $gr = OutletFoodGoodReceive::findOrFail($request->gr_id);
        
        \Log::info('DEBUG: Cek outletPayment', [
            'gr_id' => $gr->id,
            'outletPayment' => $gr->outletPayment ? $gr->outletPayment->toArray() : null
        ]);
        \Log::info('DEBUG: Cek total_amount', [
            'gr_total_amount' => $gr->total_amount,
            'request_total_amount' => $request->total_amount
        ]);
        // Check if GR already has a payment
        if ($gr->outletPayment && $gr->outletPayment->status !== 'cancelled') {
            return back()->with('error', 'GR ini sudah memiliki payment yang aktif.');
        }

        \Log::info('DEBUG OUTLET PAYMENT STORE - REQUEST', $request->all());
        $dataToInsert = [
            'outlet_id' => $request->outlet_id,
            'gr_id' => $request->gr_id,
            'date' => $request->date,
            'total_amount' => $request->total_amount,
            'notes' => $request->notes,
            'status' => 'pending',
        ];
        \Log::info('DEBUG: Sebelum create OutletPayment', $dataToInsert);
        try {
            $created = OutletPayment::create($dataToInsert);
            \Log::info('DEBUG: Setelah create OutletPayment', ['created' => $created]);
        } catch (\Exception $e) {
            \Log::error('DEBUG: Gagal create OutletPayment', ['error' => $e->getMessage()]);
        }

        return redirect()->route('outlet-payments.index')
            ->with('success', 'Payment berhasil dibuat.');
    }

    public function update(Request $request, OutletPayment $outletPayment)
    {
        if ($outletPayment->status !== 'pending') {
            return back()->with('error', 'Hanya payment dengan status pending yang dapat diubah.');
        }

        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'gr_id' => 'required|exists:outlet_food_good_receives,id',
            'date' => 'required|date',
            'total_amount' => 'required|numeric|min:0'
        ]);

        $gr = OutletFoodGoodReceive::findOrFail($request->gr_id);
        
        // Check if GR already has a payment (excluding current payment)
        if ($gr->outletPayment && $gr->outletPayment->id !== $outletPayment->id && $gr->outletPayment->status !== 'cancelled') {
            return back()->with('error', 'GR ini sudah memiliki payment yang aktif.');
        }

        // Check if total amount matches
        if ($gr->total_amount != $request->total_amount) {
            return back()->with('error', 'Total amount tidak sesuai dengan GR.');
        }

        $outletPayment->update([
            'outlet_id' => $request->outlet_id,
            'gr_id' => $request->gr_id,
            'date' => $request->date,
            'total_amount' => $request->total_amount,
            'notes' => $request->notes
        ]);

        return redirect()->route('outlet-payments.index')
            ->with('success', 'Payment berhasil diupdate.');
    }

    public function updateStatus(Request $request, OutletPayment $outletPayment)
    {
        if ($outletPayment->status !== 'pending') {
            return back()->with('error', 'Hanya payment dengan status pending yang dapat diubah statusnya.');
        }

        $request->validate([
            'status' => 'required|in:paid,cancelled'
        ]);

        $outletPayment->update([
            'status' => $request->status
        ]);

        return redirect()->route('outlet-payments.show', $outletPayment)
            ->with('success', 'Status payment berhasil diupdate.');
    }

    public function destroy(OutletPayment $outletPayment)
    {
        if ($outletPayment->status !== 'pending') {
            return back()->with('error', 'Hanya payment dengan status pending yang dapat dihapus.');
        }

        $outletPayment->delete();

        return redirect()->route('outlet-payments.index')
            ->with('success', 'Payment berhasil dihapus.');
    }

    public function create(Request $request)
    {
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        $grList = OutletFoodGoodReceive::whereDoesntHave('outletPayment')
            ->orWhereHas('outletPayment', function ($q) {
                $q->where('status', 'cancelled');
            })
            ->with([
                'outlet',
                'items',
                'creator',
                'deliveryOrder.creator',
                'deliveryOrder.packingList.creator',
                'deliveryOrder.floorOrder.user'
            ])
            ->get();

        foreach ($grList as $gr) {
            $total = \DB::table('outlet_food_good_receive_items as gr_item')
                ->join('outlet_food_good_receives as gr', 'gr_item.outlet_food_good_receive_id', '=', 'gr.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->join('food_floor_order_items as foi', function($join) {
                    $join->on('gr_item.item_id', '=', 'foi.item_id')
                         ->on('do.floor_order_id', '=', 'foi.floor_order_id');
                })
                ->where('gr.id', $gr->id)
                ->sum(\DB::raw('gr_item.received_qty * foi.price'));
            $gr->total_amount = $total;
            $gr->delivery_order = $gr->deliveryOrder;
            $gr->packing_list = $gr->deliveryOrder?->packingList;
            $gr->floor_order = $gr->deliveryOrder?->floorOrder;
            foreach ($gr->items as $item) {
                $foi = DB::table('delivery_orders as do')
                    ->join('food_floor_order_items as foi', function($join) use ($item) {
                        $join->on('do.floor_order_id', '=', 'foi.floor_order_id')
                             ->where('foi.item_id', '=', $item->item_id);
                    })
                    ->where('do.id', $gr->delivery_order_id)
                    ->select('foi.price')
                    ->first();
                $item->price = $foi ? floatval($foi->price) : 0;
                $unit = DB::table('units')->where('id', $item->unit_id)->first();
                $item->unit = $unit ? $unit->name : '';
                $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
                $item->item_name = $itemMaster ? $itemMaster->name : '';
            }
        }

        return Inertia::render('OutletPayment/Form', [
            'mode' => 'create',
            'outlets' => $outlets,
            'grList' => $grList,
        ]);
    }

    public function unpaidGR(Request $request)
    {
        // Ambil filter dari request
        $grSearch = $request->input('gr_search');
        $grFrom = $request->input('gr_from');
        $grTo = $request->input('gr_to');

        $rawGrList = \App\Models\OutletFoodGoodReceive::whereDoesntHave('outletPayment')
            ->orWhereHas('outletPayment', function ($q) {
                $q->where('status', 'cancelled');
            })
            ->with([
                'outlet',
                'items',
                'creator',
                'deliveryOrder.creator',
                'deliveryOrder.packingList.creator',
                'deliveryOrder.floorOrder.user'
            ])
            ->get();

        foreach ($rawGrList as $gr) {
            $total = \DB::table('outlet_food_good_receive_items as gr_item')
                ->join('outlet_food_good_receives as gr', 'gr_item.outlet_food_good_receive_id', '=', 'gr.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->join('food_floor_order_items as foi', function($join) {
                    $join->on('gr_item.item_id', '=', 'foi.item_id')
                         ->on('do.floor_order_id', '=', 'foi.floor_order_id');
                })
                ->where('gr.id', $gr->id)
                ->sum(\DB::raw('gr_item.received_qty * foi.price'));
            $gr->total_amount = $total;
            $gr->delivery_order = $gr->deliveryOrder;
            $gr->packing_list = $gr->deliveryOrder?->packingList;
            $gr->floor_order = $gr->deliveryOrder?->floorOrder;
            if (!$gr->outlet_name) {
                $gr->outlet_name = $gr->outlet?->name;
                if (!$gr->outlet_name && $gr->outlet_id) {
                    $outlet = \DB::table('tbl_data_outlet')->where('id_outlet', $gr->outlet_id)->first();
                    $gr->outlet_name = $outlet ? $outlet->nama_outlet : '-';
                }
            }
        }

        // Filter by search and date
        $filteredGrList = $rawGrList->filter(function($gr) use ($grSearch, $grFrom, $grTo) {
            $match = true;
            if ($grSearch) {
                $search = mb_strtolower($grSearch);
                $match = (
                    ($gr->outlet_name && mb_stripos($gr->outlet_name, $search) !== false) ||
                    ($gr->number && mb_stripos($gr->number, $search) !== false) ||
                    ($gr->gr_number && mb_stripos($gr->gr_number, $search) !== false)
                );
            }
            if ($match && $grFrom) {
                $match = $match && (date('Y-m-d', strtotime($gr->receive_date)) >= $grFrom);
            }
            if ($match && $grTo) {
                $match = $match && (date('Y-m-d', strtotime($gr->receive_date)) <= $grTo);
            }
            return $match;
        });

        // Grouping per outlet, per tanggal
        $grouped = [];
        foreach ($filteredGrList as $gr) {
            $date = $gr->receive_date instanceof \Carbon\Carbon ? $gr->receive_date->format('Y-m-d') : date('Y-m-d', strtotime($gr->receive_date));
            $outlet = $gr->outlet_name;
            $key = $outlet . '|' . $date;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'outlet_name' => $outlet,
                    'date' => $date,
                    'items' => [],
                    'subtotal' => 0,
                ];
            }
            $grouped[$key]['items'][] = $gr;
            $grouped[$key]['subtotal'] += $gr->total_amount;
        }
        // Convert to array and sort by date desc, outlet asc
        $grGroups = array_values($grouped);
        usort($grGroups, function($a, $b) {
            if ($a['date'] === $b['date']) {
                return strcmp($a['outlet_name'], $b['outlet_name']);
            }
            return strcmp($b['date'], $a['date']); // descending by date
        });
        // Pagination manual (10 group per page)
        $perPage = 10;
        $page = $request->input('gr_page', 1);
        $totalGroups = count($grGroups);
        $paginatedGroups = array_slice($grGroups, ($page-1)*$perPage, $perPage);
        $grGroupsPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedGroups,
            $totalGroups,
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => 'gr_page']
        );

        return Inertia::render('OutletPayment/UnpaidGR', [
            'grGroups' => $grGroupsPaginated,
            'filters' => $request->only(['gr_search', 'gr_from', 'gr_to'])
        ]);
    }
} 