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
        // SIMPLE APPROACH: Get payments first, then load related data
        $query = OutletPayment::when($request->outlet, function ($q) use ($request) {
                return $q->where('outlet_id', $request->outlet);
            })
            ->when($request->status, function ($q) use ($request) {
                return $q->where('status', $request->status);
            })
            ->when($request->date, function ($q) use ($request) {
                return $q->whereDate('date', $request->date);
            });

        $payments = $query->latest('date')->paginate(10)->withQueryString();
        
        // Load outlet and GR data separately for each payment
        $payments->getCollection()->transform(function($payment) {
            // Get outlet name
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $payment->outlet_id)
                ->first();
            $payment->outlet_name = $outlet ? $outlet->nama_outlet : 'Outlet Not Found';
            
            // Get GR number
            $gr = DB::table('outlet_food_good_receives')
                ->where('id', $payment->gr_id)
                ->whereNull('deleted_at')
                ->first();
            $payment->gr_number = $gr ? $gr->number : 'GR Not Found';
            
            
            return $payment;
        });

        // Get outlets for filter dropdown
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        // ULTRA OPTIMIZED: Minimal GR list for index (no heavy calculations)
        $grSearch = request('gr_search');
        $grFrom = request('gr_from');
        $grTo = request('gr_to');

        // Simple query without heavy joins - just basic GR info
        $grQuery = DB::table('outlet_food_good_receives as gr')
            ->leftJoin('outlet_payments as op', function($join) {
                $join->on('gr.id', '=', 'op.gr_id')
                     ->where('op.status', '!=', 'cancelled');
            })
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->whereNull('op.id')
            ->select(
                'gr.id',
                'gr.number',
                'gr.receive_date',
                'gr.outlet_id',
                'o.nama_outlet as outlet_name'
            );

        // Apply filters
        if ($grSearch) {
            $grQuery->where(function($q) use ($grSearch) {
                $q->where('gr.number', 'like', "%{$grSearch}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$grSearch}%");
            });
        }
        if ($grFrom) {
            $grQuery->whereDate('gr.receive_date', '>=', $grFrom);
        }
        if ($grTo) {
            $grQuery->whereDate('gr.receive_date', '<=', $grTo);
        }

        $grList = $grQuery->orderBy('gr.receive_date', 'desc')
            ->limit(50) // Limit to 50 for better performance
            ->get();

        // Simple grouping without heavy calculations
        $grouped = [];
        foreach ($grList as $gr) {
            $date = date('Y-m-d', strtotime($gr->receive_date));
            $outlet = $gr->outlet_name ?: '-';
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
        }

        // Convert to array and sort
        $grGroups = array_values($grouped);
        usort($grGroups, function($a, $b) {
            if ($a['date'] === $b['date']) {
                return strcmp($a['outlet_name'], $b['outlet_name']);
            }
            return strcmp($b['date'], $a['date']);
        });

        // Simple pagination
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

    /**
     * Debug method to check database data
     */
    public function debug()
    {
        $payments = DB::table('outlet_payments')->limit(3)->get();
        $outlets = DB::table('tbl_data_outlet')->limit(3)->get();
        $gr = DB::table('outlet_food_good_receives')->limit(3)->get();
        
        return response()->json([
            'payments' => $payments,
            'outlets' => $outlets,
            'gr' => $gr
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
        
        // Handle both single gr_id and multiple gr_ids
        $grIds = $request->input('gr_ids', []);
        if (empty($grIds) && $request->has('gr_id')) {
            $grIds = [$request->gr_id];
        }
        
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'gr_ids' => 'required|array|min:1',
            'gr_ids.*' => 'exists:outlet_food_good_receives,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'total_amount' => 'required|numeric|min:0'
        ]);

        // Check if any GR already has a payment
        foreach ($grIds as $grId) {
            $gr = OutletFoodGoodReceive::findOrFail($grId);
            if ($gr->outletPayment && $gr->outletPayment->status !== 'cancelled') {
                return back()->with('error', "GR {$gr->number} sudah memiliki payment yang aktif.");
            }
        }

        \Log::info('DEBUG OUTLET PAYMENT STORE - REQUEST', $request->all());
        
        try {
            DB::beginTransaction();
            
            $createdPayments = [];
            foreach ($grIds as $grId) {
                $gr = OutletFoodGoodReceive::findOrFail($grId);
                
                // Calculate individual GR total amount
                $grTotalAmount = DB::table('outlet_food_good_receive_items as gri')
                    ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
                    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                    ->leftJoin('food_floor_order_items as foi', function($join) {
                        $join->on('gri.item_id', '=', 'foi.item_id')
                             ->on('do.floor_order_id', '=', 'foi.floor_order_id');
                    })
                    ->where('gri.outlet_food_good_receive_id', $grId)
                    ->sum(DB::raw('COALESCE(gri.received_qty * foi.price, 0)'));
                
                $dataToInsert = [
                    'outlet_id' => $request->outlet_id,
                    'gr_id' => $grId,
                    'date' => $request->date_from, // Use date_from as the payment date
                    'total_amount' => $grTotalAmount ?: 0,
                    'notes' => $request->notes,
                    'status' => 'pending',
                ];
                
                \Log::info('DEBUG: Creating payment for GR', $dataToInsert);
                $created = OutletPayment::create($dataToInsert);
                $createdPayments[] = $created;
            }
            
            DB::commit();
            \Log::info('DEBUG: Successfully created payments', ['count' => count($createdPayments)]);
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('DEBUG: Gagal create OutletPayment', ['error' => $e->getMessage()]);
            return back()->with('error', 'Gagal membuat payment: ' . $e->getMessage());
        }

        $message = count($createdPayments) > 1 
            ? "Berhasil membuat " . count($createdPayments) . " payments." 
            : 'Payment berhasil dibuat.';
            
        return redirect()->route('outlet-payments.index')
            ->with('success', $message);
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
        // ULTRA FAST: Get outlets with minimal query
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        // Get filter parameters
        $outletId = $request->input('outlet_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // ULTRA OPTIMIZED: Super minimal GR list - only essential data
        $grQuery = DB::table('outlet_food_good_receives as gr')
            ->leftJoin('outlet_payments as op', function($join) {
                $join->on('gr.id', '=', 'op.gr_id')
                     ->where('op.status', '!=', 'cancelled');
            })
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            ->whereNull('op.id')
            ->whereNull('gr.deleted_at')
            ->select(
                'gr.id',
                'gr.number',
                'gr.receive_date',
                'gr.outlet_id',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name'
            );

        // Apply filters if provided
        if ($outletId) {
            $grQuery->where('gr.outlet_id', $outletId);
        }
        if ($dateFrom) {
            $grQuery->whereDate('gr.receive_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $grQuery->whereDate('gr.receive_date', '<=', $dateTo);
        }

        $grList = $grQuery->orderBy('gr.receive_date', 'desc')
            ->limit(50) // Reduced to 50 for faster loading
            ->get();

        // Format data with total_amount calculation
        $groupedGrList = $grList->map(function($gr) {
            // Calculate total_amount for each GR
            $totalAmount = DB::table('outlet_food_good_receive_items as gri')
                ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as foi', function($join) {
                    $join->on('gri.item_id', '=', 'foi.item_id')
                         ->on('do.floor_order_id', '=', 'foi.floor_order_id');
                })
                ->where('gri.outlet_food_good_receive_id', $gr->id)
                ->whereNull('gr.deleted_at')
                ->sum(DB::raw('COALESCE(gri.received_qty * foi.price, 0)'));

            return (object) [
                'id' => $gr->id,
                'number' => $gr->number,
                'gr_number' => $gr->number,
                'receive_date' => $gr->receive_date,
                'outlet_id' => $gr->outlet_id,
                'outlet_name' => $gr->outlet_name,
                'warehouse_outlet_name' => $gr->warehouse_outlet_name,
                'total_amount' => (float) ($totalAmount ?: 0), // Ensure it's a float
                'items' => [] // Will be loaded via API when needed
            ];
        });

        return Inertia::render('OutletPayment/Form', [
            'mode' => 'create',
            'outlets' => $outlets,
            'grList' => $groupedGrList,
        ]);
    }

    /**
     * Get GR list with filters for AJAX requests
     */
    public function getGrList(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Build query for GR list
        $grQuery = DB::table('outlet_food_good_receives as gr')
            ->leftJoin('outlet_payments as op', function($join) {
                $join->on('gr.id', '=', 'op.gr_id')
                     ->where('op.status', '!=', 'cancelled');
            })
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            ->whereNull('op.id')
            ->whereNull('gr.deleted_at')
            ->select(
                'gr.id',
                'gr.number',
                'gr.receive_date',
                'gr.outlet_id',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name'
            );

        // Apply filters
        if ($outletId) {
            $grQuery->where('gr.outlet_id', $outletId);
        }
        if ($dateFrom) {
            $grQuery->whereDate('gr.receive_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $grQuery->whereDate('gr.receive_date', '<=', $dateTo);
        }

        $grList = $grQuery->orderBy('gr.receive_date', 'desc')
            ->limit(50)
            ->get();

        // Format data with total_amount calculation
        $groupedGrList = $grList->map(function($gr) {
            // Calculate total_amount for each GR
            $totalAmount = DB::table('outlet_food_good_receive_items as gri')
                ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as foi', function($join) {
                    $join->on('gri.item_id', '=', 'foi.item_id')
                         ->on('do.floor_order_id', '=', 'foi.floor_order_id');
                })
                ->where('gri.outlet_food_good_receive_id', $gr->id)
                ->whereNull('gr.deleted_at')
                ->sum(DB::raw('COALESCE(gri.received_qty * foi.price, 0)'));

            return (object) [
                'id' => $gr->id,
                'number' => $gr->number,
                'gr_number' => $gr->number,
                'receive_date' => $gr->receive_date,
                'outlet_id' => $gr->outlet_id,
                'outlet_name' => $gr->outlet_name,
                'warehouse_outlet_name' => $gr->warehouse_outlet_name,
                'total_amount' => (float) ($totalAmount ?: 0), // Ensure it's a float
                'items' => [] // Will be loaded via API when needed
            ];
        });

        return response()->json([
            'success' => true,
            'grList' => $groupedGrList
        ]);
    }

    /**
     * Get GR items for specific GR ID (for lazy loading) - OPTIMIZED
     */
    public function getGrItems($grId)
    {
        try {
            // ULTRA OPTIMIZED: Single query with proper indexing
            $items = DB::table('outlet_food_good_receive_items as gri')
                ->join('items as i', 'gri.item_id', '=', 'i.id')
                ->join('units as u', 'gri.unit_id', '=', 'u.id')
                ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as foi', function($join) {
                    $join->on('gri.item_id', '=', 'foi.item_id')
                         ->on('do.floor_order_id', '=', 'foi.floor_order_id');
                })
                ->where('gri.outlet_food_good_receive_id', $grId)
                ->whereNull('gr.deleted_at')
                ->select(
                    'gri.id as item_id',
                    'gri.item_id as item_master_id',
                    'gri.unit_id',
                    'gri.received_qty',
                    'foi.price as item_price',
                    'i.name as item_name',
                    'u.name as unit_name',
                    DB::raw('COALESCE(gri.received_qty * foi.price, 0) as subtotal')
                )
                ->get();

            $totalAmount = $items->sum('subtotal');

            return response()->json([
                'success' => true,
                'total_amount' => $totalAmount,
                'items' => $items->map(function($item) {
                    return [
                        'id' => $item->item_id,
                        'item_id' => $item->item_master_id,
                        'unit_id' => $item->unit_id,
                        'received_qty' => $item->received_qty,
                        'price' => $item->item_price ?: 0,
                        'item_name' => $item->item_name,
                        'unit' => $item->unit_name,
                        'subtotal' => $item->subtotal ?: 0
                    ];
                })
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getGrItems: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat detail items: ' . $e->getMessage()
            ], 500);
        }
    }

    public function unpaidGR(Request $request)
    {
        // ULTRA OPTIMIZED: Single query with all data needed
        $grSearch = $request->input('gr_search');
        $grFrom = $request->input('gr_from');
        $grTo = $request->input('gr_to');

        // Single optimized query to get all unpaid GR with total_amount
        $query = DB::table('outlet_food_good_receives as gr')
            ->leftJoin('outlet_payments as op', function($join) {
                $join->on('gr.id', '=', 'op.gr_id')
                     ->where('op.status', '!=', 'cancelled');
            })
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as foi', function($join) {
                $join->on('gri.item_id', '=', 'foi.item_id')
                     ->on('do.floor_order_id', '=', 'foi.floor_order_id');
            })
            ->whereNull('op.id') // GR yang belum ada payment
            ->whereNull('gr.deleted_at') // GR yang belum dihapus
            ->select(
                'gr.id',
                'gr.number',
                'gr.receive_date',
                'gr.outlet_id',
                'gr.delivery_order_id',
                'gr.created_by',
                'gr.notes',
                'o.nama_outlet as outlet_name',
                DB::raw('SUM(COALESCE(gri.received_qty * foi.price, 0)) as total_amount')
            )
            ->groupBy('gr.id', 'gr.number', 'gr.receive_date', 'gr.outlet_id', 'gr.delivery_order_id', 'gr.created_by', 'gr.notes', 'o.nama_outlet');

        // Apply filters
        if ($grSearch) {
            $query->where(function($q) use ($grSearch) {
                $q->where('gr.number', 'like', "%{$grSearch}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$grSearch}%");
            });
        }
        if ($grFrom) {
            $query->whereDate('gr.receive_date', '>=', $grFrom);
        }
        if ($grTo) {
            $query->whereDate('gr.receive_date', '<=', $grTo);
        }

        $rawGrList = $query->orderBy('gr.receive_date', 'desc')
            ->limit(100) // Limit for better performance
            ->get();

        // Convert to collection and format data
        $grCollection = collect($rawGrList)->map(function($gr) {
            return (object) [
                'id' => $gr->id,
                'number' => $gr->number,
                'gr_number' => $gr->number,
                'receive_date' => $gr->receive_date,
                'outlet_id' => $gr->outlet_id,
                'delivery_order_id' => $gr->delivery_order_id,
                'created_by' => $gr->created_by,
                'notes' => $gr->notes,
                'outlet_name' => $gr->outlet_name ?: 'Outlet Not Found',
                'total_amount' => $gr->total_amount ?: 0,
                'items' => [] // Will be loaded via API when needed
            ];
        });

        // Grouping per outlet, per tanggal
        $grouped = [];
        foreach ($grCollection as $gr) {
            $date = date('Y-m-d', strtotime($gr->receive_date));
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

        // Convert to array and sort
        $grGroups = array_values($grouped);
        usort($grGroups, function($a, $b) {
            if ($a['date'] === $b['date']) {
                return strcmp($a['outlet_name'], $b['outlet_name']);
            }
            return strcmp($b['date'], $a['date']);
        });

        // Pagination
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

    public function reportInvoiceOutlet(Request $request)
    {
        $user = auth()->user();
        $query = DB::table('outlet_payments as pay')
            ->join('tbl_data_outlet as o', 'pay.outlet_id', '=', 'o.id_outlet')
            ->join('outlet_food_good_receives as gr', 'pay.gr_id', '=', 'gr.id')
            ->leftJoin('users as u', 'pay.created_by', '=', 'u.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->select(
                'pay.id as payment_id',
                'pay.payment_number as payment_number',
                'pay.date as payment_date',
                'pay.total_amount as payment_total',
                'o.id_outlet',
                'o.nama_outlet as outlet_name',
                'gr.id as gr_id',
                'gr.number as gr_number',
                'gr.receive_date as gr_date',
                'gr.notes as gr_notes',
                'pay.status as payment_status',
                'u.nama_lengkap as created_by_name',
                'w.name as warehouse_name',
                'wd.name as warehouse_division_name'
            );
        // Filter outlet
        if ($user->id_outlet != 1) {
            $query->where('pay.outlet_id', $user->id_outlet);
        } elseif ($request->filled('outlet_id')) {
            $query->where('pay.outlet_id', $request->outlet_id);
        }
        // Filter tanggal
        if ($request->from) {
            $query->whereDate('pay.date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('pay.date', '<=', $request->to);
        }
        // Search
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('pay.payment_number', 'like', "%$search%")
                  ->orWhere('gr.number', 'like', "%$search%")
                  ->orWhere('o.nama_outlet', 'like', "%$search%")
                  ->orWhere('u.nama_lengkap', 'like', "%$search%")
                ;
            });
        }
        $data = $query->orderByDesc('pay.date')->get();
        // Ambil detail item per payment/GR
        $details = [];
        $grIds = $data->pluck('gr_id')->unique()->values();
        if ($grIds->count()) {
            $itemRows = DB::table('outlet_food_good_receive_items as gri')
                ->join('items as i', 'gri.item_id', '=', 'i.id')
                ->join('units as u', 'gri.unit_id', '=', 'u.id')
                ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->join('food_floor_order_items as ffoi', function($join) {
                    $join->on('do.floor_order_id', '=', 'ffoi.floor_order_id')
                         ->on('gri.item_id', '=', 'ffoi.item_id');
                })
                ->whereIn('gri.outlet_food_good_receive_id', $grIds)
                ->select(
                    'gri.outlet_food_good_receive_id as gr_id',
                    'i.name as item_name',
                    'gri.received_qty as qty',
                    'u.name as unit_name',
                    'ffoi.price as price',
                    DB::raw('(gri.received_qty * ffoi.price) as subtotal')
                )
                ->get();
            foreach ($itemRows as $row) {
                $details[$row->gr_id][] = $row;
            }
        }
        // Ambil daftar outlet untuk filter
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
        return Inertia::render('OutletPayment/ReportInvoiceOutlet', [
            'data' => $data,
            'details' => $details,
            'outlets' => $outlets,
            'filters' => $request->only(['search', 'from', 'to', 'outlet_id']),
            'user_id_outlet' => $user->id_outlet,
        ]);
    }
} 