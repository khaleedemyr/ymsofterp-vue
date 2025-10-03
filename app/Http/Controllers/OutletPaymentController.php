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
        // Get filter parameters
        $outlet = $request->input('outlet');
        $status = $request->input('status');
        $date = $request->input('date');
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10);

        // Build query with search and filters
        $query = OutletPayment::query()
            ->leftJoin('tbl_data_outlet as o', 'outlet_payments.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'outlet_payments.created_by', '=', 'u.id')
            ->leftJoin('outlet_food_good_receives as gr', 'outlet_payments.gr_id', '=', 'gr.id')
            ->leftJoin('retail_warehouse_sales as rws', 'outlet_payments.retail_sales_id', '=', 'rws.id')
            ->select(
                'outlet_payments.*',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as creator_name',
                'gr.number as gr_number',
                'rws.number as retail_number'
            );

        // Apply filters
        if ($outlet) {
            $query->where('outlet_payments.outlet_id', $outlet);
        }
        
        if ($status) {
            $query->where('outlet_payments.status', $status);
        }
        
        if ($date) {
            $query->whereDate('outlet_payments.date', $date);
        }

        // Apply search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('outlet_payments.payment_number', 'like', "%{$search}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('gr.number', 'like', "%{$search}%")
                  ->orWhere('rws.number', 'like', "%{$search}%");
            });
        }

        $payments = $query->latest('outlet_payments.date')->paginate($perPage)->withQueryString();
        
        // Add payment type to each payment
        $payments->getCollection()->transform(function($payment) {
            if ($payment->gr_id) {
                $payment->payment_type = 'GR';
            } else {
                $payment->payment_type = 'Retail';
            }
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
            'filters' => $request->only(['outlet', 'status', 'date', 'search', 'per_page'])
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
        
        // Debug retail sales
        $retailSales = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->select('rws.id', 'rws.number', 'rws.status', 'c.name as customer_name', 'c.id_outlet')
            ->limit(5)
            ->get();
        
        $customers = DB::table('customers')
            ->select('id', 'name', 'id_outlet', 'type')
            ->whereNotNull('id_outlet')
            ->limit(5)
            ->get();
        
        // Debug specific outlet
        $outletId = 1; // Test with outlet ID 1
        $customersForOutlet = DB::table('customers')
            ->where('id_outlet', (string)$outletId)
            ->select('id', 'name', 'id_outlet', 'type')
            ->get();
        
        $retailSalesForOutlet = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->where('rws.status', 'completed')
            ->where('c.id_outlet', (string)$outletId)
            ->select('rws.id', 'rws.number', 'rws.status', 'rws.sale_date', 'c.name as customer_name', 'c.id_outlet')
            ->limit(10)
            ->get();
        
        return response()->json([
            'payments' => $payments,
            'outlets' => $outlets,
            'gr' => $gr,
            'retail_sales' => $retailSales,
            'customers' => $customers,
            'customers_for_outlet_1' => $customersForOutlet,
            'retail_sales_for_outlet_1' => $retailSalesForOutlet
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
        $retailIds = $request->input('retail_ids', []);
        
        if (empty($grIds) && $request->has('gr_id')) {
            $grIds = [$request->gr_id];
        }
        
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'gr_ids' => 'nullable|array',
            'gr_ids.*' => 'exists:outlet_food_good_receives,id',
            'retail_ids' => 'nullable|array',
            'retail_ids.*' => 'exists:retail_warehouse_sales,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'total_amount' => 'required|numeric|min:0'
        ]);

        // Validate that at least one transaction is selected
        if (empty($grIds) && empty($retailIds)) {
            return back()->with('error', 'Pilih minimal satu transaksi (GR atau Retail Sales).');
        }

        // Check if any GR already has a payment
        foreach ($grIds as $grId) {
            $gr = OutletFoodGoodReceive::findOrFail($grId);
            if ($gr->outletPayment && $gr->outletPayment->status !== 'cancelled') {
                return back()->with('error', "GR {$gr->number} sudah memiliki payment yang aktif.");
            }
        }

        // Check if any Retail Sales already has a payment (status = paid)
        foreach ($retailIds as $retailId) {
            $retail = DB::table('retail_warehouse_sales')->where('id', $retailId)->first();
            if ($retail && $retail->status === 'paid') {
                return back()->with('error', "Retail Sales {$retail->number} sudah dibayar.");
            }
        }

        \Log::info('DEBUG OUTLET PAYMENT STORE - REQUEST', $request->all());
        
        try {
            DB::beginTransaction();
            
            $createdPayments = [];
            
            // Process GR payments
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
                    'retail_sales_id' => null, // GR payment
                    'date' => $request->date_from,
                    'total_amount' => $grTotalAmount ?: 0,
                    'notes' => $request->notes,
                    'status' => 'pending',
                ];
                
                \Log::info('DEBUG: Creating payment for GR', $dataToInsert);
                $created = OutletPayment::create($dataToInsert);
                $createdPayments[] = $created;
            }
            
            // Process Retail Sales payments
            foreach ($retailIds as $retailId) {
                $retail = DB::table('retail_warehouse_sales')->where('id', $retailId)->first();
                
                $dataToInsert = [
                    'outlet_id' => $request->outlet_id,
                    'gr_id' => null, // Retail sales payment
                    'retail_sales_id' => $retailId,
                    'date' => $request->date_from,
                    'total_amount' => $retail->total_amount,
                    'notes' => $request->notes,
                    'status' => 'pending',
                ];
                
                \Log::info('DEBUG: Creating payment for Retail Sales', $dataToInsert);
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

        try {
            DB::beginTransaction();
            
            $outletPayment->update([
                'status' => $request->status
            ]);

            // If payment is confirmed as paid, update retail sales status
            if ($request->status === 'paid' && $outletPayment->retail_sales_id) {
                DB::table('retail_warehouse_sales')
                    ->where('id', $outletPayment->retail_sales_id)
                    ->update(['status' => 'paid']);
            }
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error updating payment status: ' . $e->getMessage());
            return back()->with('error', 'Gagal mengupdate status payment: ' . $e->getMessage());
        }

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

    public function bulkConfirm(Request $request)
    {
        $request->validate([
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'exists:outlet_payments,id'
        ]);

        try {
            DB::beginTransaction();
            
            $payments = OutletPayment::whereIn('id', $request->payment_ids)
                ->where('status', 'pending')
                ->get();

            if ($payments->isEmpty()) {
                return back()->with('error', 'Tidak ada payment pending yang dipilih.');
            }

            $confirmedCount = 0;
            foreach ($payments as $payment) {
                $payment->update(['status' => 'paid']);
                $confirmedCount++;
            }

            DB::commit();

            $message = $confirmedCount > 1 
                ? "Berhasil mengkonfirmasi {$confirmedCount} payments." 
                : 'Payment berhasil dikonfirmasi.';
                
            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('DEBUG: Gagal bulk confirm OutletPayment', ['error' => $e->getMessage()]);
            return back()->with('error', 'Gagal mengkonfirmasi payments: ' . $e->getMessage());
        }
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
                'items' => [], // Will be loaded via API when needed
                'type' => 'gr' // Add type identifier
            ];
        });

        return response()->json([
            'success' => true,
            'grList' => $groupedGrList
        ]);
    }

    /**
     * Get Retail Sales list for outlet payment
     */
    public function getRetailSalesList(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $perPage = $request->input('per_page', 100); // Default 100, bisa disesuaikan
        \Log::info('DEBUG: getRetailSalesList called', [
            'outlet_id' => $outletId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => $perPage
        ]);

        // Debug: Check customers with id_outlet
        $customersWithOutlet = DB::table('customers')
            ->where('id_outlet', (string)$outletId)
            ->select('id', 'name', 'id_outlet', 'type')
            ->get();
        
        \Log::info('DEBUG: Customers with outlet_id', [
            'outlet_id' => $outletId,
            'customers_count' => $customersWithOutlet->count(),
            'customers' => $customersWithOutlet->toArray()
        ]);

        // Debug: Check ALL retail sales for this outlet and date range
        $allRetailSalesForOutlet = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->where('c.id_outlet', (string)$outletId)
            ->where('rws.status', 'completed')
            ->select('rws.id', 'rws.number', 'rws.customer_id', 'rws.sale_date', 'rws.status', 'c.name as customer_name', 'c.id_outlet')
            ->get();
        
        \Log::info('DEBUG: All retail sales for outlet', [
            'outlet_id' => $outletId,
            'count' => $allRetailSalesForOutlet->count(),
            'data' => $allRetailSalesForOutlet->toArray()
        ]);

        // Debug: Check retail sales with date filter
        if ($dateFrom && $dateTo) {
            $retailSalesWithDateFilter = DB::table('retail_warehouse_sales as rws')
                ->join('customers as c', 'rws.customer_id', '=', 'c.id')
                ->where('c.id_outlet', (string)$outletId)
                ->where('rws.status', 'completed')
                ->whereDate('rws.sale_date', '>=', $dateFrom)
                ->whereDate('rws.sale_date', '<=', $dateTo)
                ->select('rws.id', 'rws.number', 'rws.customer_id', 'rws.sale_date', 'rws.status', 'c.name as customer_name', 'c.id_outlet')
                ->get();
            
            \Log::info('DEBUG: Retail sales with date filter', [
                'outlet_id' => $outletId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'count' => $retailSalesWithDateFilter->count(),
                'data' => $retailSalesWithDateFilter->toArray()
            ]);
        }

        // Debug: Check if outlet exists in tbl_data_outlet
        $outletExists = DB::table('tbl_data_outlet')
            ->where('id_outlet', (string)$outletId)
            ->first();
        
        \Log::info('DEBUG: Outlet exists check', [
            'outlet_id' => $outletId,
            'outlet_exists' => $outletExists ? true : false,
            'outlet_data' => $outletExists
        ]);

        // Debug: Check customers with outlet_id and their outlet mapping
        $customersWithOutletMapping = DB::table('customers as c')
            ->leftJoin('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')
            ->where('c.id_outlet', (string)$outletId)
            ->select('c.id', 'c.name', 'c.id_outlet', 'c.type', 'o.id_outlet as outlet_id', 'o.nama_outlet')
            ->get();
        
        \Log::info('DEBUG: Customers with outlet mapping', [
            'outlet_id' => $outletId,
            'count' => $customersWithOutletMapping->count(),
            'data' => $customersWithOutletMapping->toArray()
        ]);

        // Build query for Retail Sales list
        // NOTE: We only show sales to branch customers (where id_outlet matches)
        // Generic customers (id_outlet = NULL) should not be filtered by outlet
        // because they don't belong to specific outlet
        $retailQuery = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->join('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')  // INNER JOIN to only get branch customers
            ->leftJoin('warehouses as w', 'rws.warehouse_id', '=', 'w.id')
            ->leftJoin('warehouse_division as wd', 'rws.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('outlet_payments as op', function($join) {
                $join->on('rws.id', '=', 'op.retail_sales_id')
                     ->where('op.status', '!=', 'cancelled');
            })
            ->where('rws.status', 'completed') // Only completed sales that haven't been paid
            ->whereNull('op.id') // Exclude retail sales that already have payment
            ->where('c.id_outlet', (string)$outletId) // Only sales to this outlet (cast to string)
            ->where('c.type', 'branch') // Only branch customers, not generic customers
            ->select(
                'rws.id',
                'rws.number',
                'rws.sale_date',
                'rws.total_amount',
                'rws.notes',
                'rws.created_by',
                'c.name as customer_name',
                'c.code as customer_code',
                'c.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
                'w.name as warehouse_name',
                'wd.name as division_name'
            );

        // Apply filters - Use created_at instead of sale_date
        if ($dateFrom) {
            $retailQuery->whereDate('rws.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $retailQuery->whereDate('rws.created_at', '<=', $dateTo);
        }

        // Debug: Get raw SQL query
        $rawSql = $retailQuery->toSql();
        $rawBindings = $retailQuery->getBindings();
        
        \Log::info('DEBUG: Raw SQL Query', [
            'sql' => $rawSql,
            'bindings' => $rawBindings
        ]);

        $retailList = $retailQuery->orderBy('rws.sale_date', 'desc')
            ->paginate($perPage);

        \Log::info('DEBUG: Retail Sales query result', [
            'total' => $retailList->total(),
            'per_page' => $retailList->perPage(),
            'current_page' => $retailList->currentPage(),
            'last_page' => $retailList->lastPage(),
            'data_count' => $retailList->count(),
            'raw_data' => $retailList->getCollection()->toArray()
        ]);

        // Format data
        $formattedRetailList = $retailList->getCollection()->map(function($retail) {
            return (object) [
                'id' => $retail->id,
                'number' => $retail->number,
                'sale_date' => $retail->sale_date,
                'total_amount' => (float) $retail->total_amount,
                'notes' => $retail->notes,
                'created_by' => $retail->created_by,
                'customer_name' => $retail->customer_name,
                'customer_code' => $retail->customer_code,
                'outlet_id' => $retail->outlet_id,
                'outlet_name' => $retail->outlet_name,
                'warehouse_name' => $retail->warehouse_name,
                'division_name' => $retail->division_name,
                'items' => [], // Will be loaded via API when needed
                'type' => 'retail_sales' // Add type identifier
            ];
        });

        \Log::info('DEBUG: Final retail sales response', [
            'success' => true,
            'count' => $formattedRetailList->count(),
            'total' => $retailList->total(),
            'pagination' => [
                'current_page' => $retailList->currentPage(),
                'last_page' => $retailList->lastPage(),
                'per_page' => $retailList->perPage()
            ]
        ]);

        return response()->json([
            'success' => true,
            'retailList' => $formattedRetailList,
            'pagination' => [
                'current_page' => $retailList->currentPage(),
                'last_page' => $retailList->lastPage(),
                'per_page' => $retailList->perPage(),
                'total' => $retailList->total()
            ]
        ]);
    }

    /**
     * Get Retail Sales items for specific Retail Sales ID
     */
    public function getRetailSalesItems($retailId)
    {
        try {
            $items = DB::table('retail_warehouse_sale_items as rwsi')
                ->join('items as i', 'rwsi.item_id', '=', 'i.id')
                ->where('rwsi.retail_warehouse_sale_id', $retailId)
                ->select(
                    'rwsi.id as item_id',
                    'rwsi.item_id as item_master_id',
                    'rwsi.qty as received_qty',
                    'rwsi.price as item_price',
                    'rwsi.unit as unit_name',
                    'i.name as item_name',
                    'rwsi.subtotal'
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
                        'received_qty' => $item->received_qty,
                        'price' => $item->item_price ?: 0,
                        'item_name' => $item->item_name,
                        'unit' => $item->unit_name,
                        'subtotal' => $item->subtotal ?: 0
                    ];
                })
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getRetailSalesItems: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat detail items: ' . $e->getMessage()
            ], 500);
        }
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