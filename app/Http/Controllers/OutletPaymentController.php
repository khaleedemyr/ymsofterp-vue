<?php

namespace App\Http\Controllers;

use App\Models\OutletPayment;
use App\Models\Outlet;
use App\Models\OutletFoodGoodReceive;
use App\Services\BankBookService;
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
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10);
        $loadData = $request->input('load_data', true); // Changed default to true

        // LAZY LOADING: Only load data if load_data is true or if filters/search are present
        if (!$loadData && !$outlet && !$status && !$dateFrom && !$dateTo && !$search) {
            // Return empty paginator
            $payments = new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $perPage,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            
            // Get outlets for filter dropdown
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();

            return Inertia::render('OutletPayment/Index', [
                'payments' => $payments,
                'outlets' => $outlets,
                'grGroups' => new \Illuminate\Pagination\LengthAwarePaginator(
                    collect([]),
                    0,
                    10,
                    1,
                    ['path' => $request->url(), 'pageName' => 'gr_page']
                ),
                'filters' => $request->only(['outlet', 'status', 'date_from', 'date_to', 'search', 'per_page']),
                'dataLoaded' => false
            ]);
        }

        // Build query with search and filters
        $query = DB::table('outlet_payments')
            ->leftJoin('tbl_data_outlet as o', 'outlet_payments.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'outlet_payments.created_by', '=', 'u.id')
            ->leftJoin('outlet_food_good_receives as gr', 'outlet_payments.gr_id', '=', 'gr.id')
            ->leftJoin('retail_warehouse_sales as rws', 'outlet_payments.retail_sales_id', '=', 'rws.id')
            ->select(
                'outlet_payments.*',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as creator_name',
                'gr.number as gr_number',
                'gr.receive_date as gr_date',
                'rws.number as retail_number',
                'rws.sale_date as rws_date',
                'outlet_payments.created_at as payment_created_at'
            );

        // Apply filters
        if ($outlet) {
            $query->where('outlet_payments.outlet_id', $outlet);
        }
        
        if ($status) {
            $query->where('outlet_payments.status', $status);
        }
        
        // Date range filter
        if ($dateFrom) {
            $query->whereDate('outlet_payments.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('outlet_payments.date', '<=', $dateTo);
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

        $payments = $query->orderBy('outlet_payments.date', 'desc')
            ->paginate($perPage)
            ->withQueryString();
        
        // Add payment type to each payment
        // Transform data to add payment_type
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
            'filters' => $request->only(['outlet', 'status', 'date_from', 'date_to', 'search', 'per_page']),
            'dataLoaded' => true
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
            'total_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|in:cash,transfer,check',
            'bank_id' => 'nullable|required_if:payment_method,transfer|required_if:payment_method,check|exists:bank_accounts,id',
            'receiver_bank_ids' => 'nullable|array',
            'receiver_bank_ids.*' => 'exists:bank_accounts,id',
            'coa_id' => 'nullable|exists:chart_of_accounts,id'
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

        
        try {
            DB::beginTransaction();
            
            $createdPayments = [];
            
            // GET WAREHOUSE_ID FOR EACH GR AND RETAIL
            $grWarehouseMap = [];
            foreach ($grIds as $grId) {
                $grData = DB::table('outlet_food_good_receives as gr')
                    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                    ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
                    ->leftJoin('warehouse_division as wd_pl', 'pl.warehouse_division_id', '=', 'wd_pl.id')
                    ->leftJoin('warehouses as w_pl', 'wd_pl.warehouse_id', '=', 'w_pl.id')
                    ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
                    ->where('gr.id', $grId)
                    ->select(
                        'gr.id',
                        DB::raw('COALESCE(
                            w_pl.id,
                            (SELECT w.id 
                             FROM food_floor_order_items ffoi
                             INNER JOIN warehouse_division wd ON ffoi.warehouse_division_id = wd.id
                             INNER JOIN warehouses w ON wd.warehouse_id = w.id
                             WHERE ffoi.floor_order_id = ffo.id
                             LIMIT 1)
                        ) as warehouse_id')
                    )
                    ->first();
                
                $grWarehouseMap[$grId] = $grData->warehouse_id ?? null;
            }
            
            $retailWarehouseMap = [];
            foreach ($retailIds as $retailId) {
                $retailData = DB::table('retail_warehouse_sales')->where('id', $retailId)->first();
                $retailWarehouseMap[$retailId] = $retailData->warehouse_id ?? null;
            }
            
            // Process GR payments
            foreach ($grIds as $grId) {
                $gr = OutletFoodGoodReceive::findOrFail($grId);
                
                // Calculate individual GR total amount (from outlet_food_good_receives only, same as Rekap FJ)
                $totalAmount = DB::table('outlet_food_good_receive_items as gri')
                    ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
                    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                    ->leftJoin('food_floor_order_items as foi', function($join) {
                        $join->on('gri.item_id', '=', 'foi.item_id')
                             ->on('do.floor_order_id', '=', 'foi.floor_order_id');
                    })
                    ->where('gri.outlet_food_good_receive_id', $grId)
                    ->whereNull('gr.deleted_at')
                    ->sum(DB::raw('COALESCE(gri.received_qty * foi.price, 0)'));
                
                // Handle receiver_bank_ids (multiple) - simpan sebagai JSON atau gunakan yang pertama untuk backward compatibility
                $receiverBankId = null;
                $notes = $request->notes ?? '';
                
                if (($request->payment_method === 'transfer' || $request->payment_method === 'check') && !empty($request->receiver_bank_ids)) {
                    // Untuk backward compatibility, simpan yang pertama di receiver_bank_id
                    // Dan simpan semua di notes sebagai JSON
                    $receiverBankId = is_array($request->receiver_bank_ids) ? $request->receiver_bank_ids[0] : $request->receiver_bank_ids;
                    
                    // Simpan semua receiver bank IDs di notes sebagai JSON untuk referensi
                    if (is_array($request->receiver_bank_ids) && count($request->receiver_bank_ids) > 0) {
                        $receiverBanksInfo = json_encode($request->receiver_bank_ids);
                        // Hapus existing receiver banks info jika ada
                        $notes = preg_replace('/\n\[Receiver Banks:.*?\]/', '', $notes);
                        $notes = trim($notes);
                        // Tambahkan receiver banks info
                        $notes = $notes ? $notes . "\n[Receiver Banks: " . $receiverBanksInfo . "]" : "[Receiver Banks: " . $receiverBanksInfo . "]";
                    }
                } elseif (($request->payment_method === 'transfer' || $request->payment_method === 'check') && $request->receiver_bank_id) {
                    // Fallback untuk data lama
                    $receiverBankId = $request->receiver_bank_id;
                } else {
                    // Clear receiver banks info dari notes jika payment method bukan transfer/check atau tidak ada receiver banks
                    $notes = preg_replace('/\n\[Receiver Banks:.*?\]/', '', $notes);
                    $notes = trim($notes);
                }
                
                $dataToInsert = [
                    'outlet_id' => $request->outlet_id,
                    'warehouse_id' => $grWarehouseMap[$grId],
                    'gr_id' => $grId,
                    'retail_sales_id' => null, // GR payment
                    'date' => $request->date_from,
                    'total_amount' => $totalAmount,
                    'notes' => $notes,
                    'payment_method' => $request->payment_method ?? 'cash',
                    'bank_id' => ($request->payment_method === 'transfer' || $request->payment_method === 'check') ? $request->bank_id : null,
                    'receiver_bank_id' => $receiverBankId,
                    'coa_id' => $request->coa_id,
                    'status' => 'pending',
                ];
                
                $created = OutletPayment::create($dataToInsert);
                $createdPayments[] = $created;
            }
            
            // Process Retail Sales payments
            foreach ($retailIds as $retailId) {
                $retail = DB::table('retail_warehouse_sales')->where('id', $retailId)->first();
                
                // Handle receiver_bank_ids (multiple) - simpan sebagai JSON atau gunakan yang pertama untuk backward compatibility
                $receiverBankId = null;
                $notesRetail = $request->notes ?? '';
                
                if (($request->payment_method === 'transfer' || $request->payment_method === 'check') && !empty($request->receiver_bank_ids)) {
                    // Untuk backward compatibility, simpan yang pertama di receiver_bank_id
                    // Dan simpan semua di notes sebagai JSON
                    $receiverBankId = is_array($request->receiver_bank_ids) ? $request->receiver_bank_ids[0] : $request->receiver_bank_ids;
                    
                    // Simpan semua receiver bank IDs di notes sebagai JSON untuk referensi
                    if (is_array($request->receiver_bank_ids) && count($request->receiver_bank_ids) > 0) {
                        $receiverBanksInfo = json_encode($request->receiver_bank_ids);
                        // Hapus existing receiver banks info jika ada
                        $notesRetail = preg_replace('/\n\[Receiver Banks:.*?\]/', '', $notesRetail);
                        $notesRetail = trim($notesRetail);
                        // Tambahkan receiver banks info
                        $notesRetail = $notesRetail ? $notesRetail . "\n[Receiver Banks: " . $receiverBanksInfo . "]" : "[Receiver Banks: " . $receiverBanksInfo . "]";
                    }
                } elseif (($request->payment_method === 'transfer' || $request->payment_method === 'check') && $request->receiver_bank_id) {
                    // Fallback untuk data lama
                    $receiverBankId = $request->receiver_bank_id;
                } else {
                    // Clear receiver banks info dari notes jika payment method bukan transfer/check atau tidak ada receiver banks
                    $notesRetail = preg_replace('/\n\[Receiver Banks:.*?\]/', '', $notesRetail);
                    $notesRetail = trim($notesRetail);
                }
                
                $dataToInsert = [
                    'outlet_id' => $request->outlet_id,
                    'warehouse_id' => $retailWarehouseMap[$retailId],
                    'gr_id' => null, // Retail sales payment
                    'retail_sales_id' => $retailId,
                    'date' => $request->date_from,
                    'total_amount' => $retail->total_amount,
                    'notes' => $notesRetail,
                    'payment_method' => $request->payment_method ?? 'cash',
                    'bank_id' => ($request->payment_method === 'transfer' || $request->payment_method === 'check') ? $request->bank_id : null,
                    'receiver_bank_id' => $receiverBankId,
                    'coa_id' => $request->coa_id,
                    'status' => 'pending',
                ];
                
                $created = OutletPayment::create($dataToInsert);
                $createdPayments[] = $created;
            }
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('DEBUG: Gagal create OutletPayment', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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
            'total_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|in:cash,transfer,check',
            'bank_id' => 'nullable|required_if:payment_method,transfer|required_if:payment_method,check|exists:bank_accounts,id',
            'receiver_bank_ids' => 'nullable|array',
            'receiver_bank_ids.*' => 'exists:bank_accounts,id',
            'coa_id' => 'nullable|exists:chart_of_accounts,id'
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

        // Handle receiver_bank_ids (multiple) - simpan sebagai JSON atau gunakan yang pertama untuk backward compatibility
        $receiverBankId = null;
        $notes = $request->notes ?? '';
        
        if (($request->payment_method === 'transfer' || $request->payment_method === 'check') && !empty($request->receiver_bank_ids)) {
            // Untuk backward compatibility, simpan yang pertama di receiver_bank_id
            // Dan simpan semua di notes sebagai JSON
            $receiverBankId = is_array($request->receiver_bank_ids) ? $request->receiver_bank_ids[0] : $request->receiver_bank_ids;
            
            // Extract existing notes tanpa receiver banks info
            $notes = preg_replace('/\n\[Receiver Banks:.*?\]/', '', $notes);
            $notes = trim($notes);
            
            // Simpan semua receiver bank IDs di notes sebagai JSON untuk referensi
            if (is_array($request->receiver_bank_ids) && count($request->receiver_bank_ids) > 0) {
                $receiverBanksInfo = json_encode($request->receiver_bank_ids);
                $notes = $notes ? $notes . "\n[Receiver Banks: " . $receiverBanksInfo . "]" : "[Receiver Banks: " . $receiverBanksInfo . "]";
            }
        } elseif (($request->payment_method === 'transfer' || $request->payment_method === 'check') && $request->receiver_bank_id) {
            // Fallback untuk data lama
            $receiverBankId = $request->receiver_bank_id;
        } else {
            // Clear receiver banks info dari notes jika payment method bukan transfer/check
            $notes = preg_replace('/\n\[Receiver Banks:.*?\]/', '', $notes);
            $notes = trim($notes);
        }
        
        $outletPayment->update([
            'outlet_id' => $request->outlet_id,
            'gr_id' => $request->gr_id,
            'date' => $request->date,
            'total_amount' => $request->total_amount,
            'notes' => $notes,
            'payment_method' => $request->payment_method ?? 'cash',
            'bank_id' => ($request->payment_method === 'transfer' || $request->payment_method === 'check') ? $request->bank_id : null,
            'receiver_bank_id' => $receiverBankId,
            'coa_id' => $request->coa_id
        ]);

        return redirect()->route('outlet-payments.index')
            ->with('success', 'Payment berhasil diupdate.');
    }

    public function updateStatus(Request $request, OutletPayment $outletPayment, BankBookService $bankBookService)
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

            // Create bank book entry if payment is paid and method is transfer/check
            if ($request->status === 'paid') {
                $bankBookService->createFromOutletPayment($outletPayment);
                
                // CREATE JURNAL when status = paid
                $this->createJurnalForOutletPayment($outletPayment);
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

    public function destroy(OutletPayment $outletPayment, BankBookService $bankBookService)
    {
        if ($outletPayment->status !== 'pending') {
            return back()->with('error', 'Hanya payment dengan status pending yang dapat dihapus.');
        }

        try {
            DB::beginTransaction();

            // Delete bank book entries if exists
            $bankBookService->deleteByReference('outlet_payment', $outletPayment->id);

        $outletPayment->delete();

            DB::commit();

        return redirect()->route('outlet-payments.index')
            ->with('success', 'Payment berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting OutletPayment: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus payment: ' . $e->getMessage());
        }
    }

    public function bulkConfirm(Request $request, BankBookService $bankBookService)
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
                
                // Create bank book entry if payment method is transfer/check
                $bankBookService->createFromOutletPayment($payment);
                
                // CREATE JURNAL when confirmed
                $this->createJurnalForOutletPayment($payment);
                
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
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('warehouse_division as wd_pl', 'pl.warehouse_division_id', '=', 'wd_pl.id')
            ->leftJoin('warehouses as w_pl', 'wd_pl.warehouse_id', '=', 'w_pl.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->whereNull('op.id')
            ->whereNull('gr.deleted_at')
            ->select(
                'gr.id',
                'gr.number',
                'gr.receive_date',
                'gr.outlet_id',
                'gr.delivery_order_id',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                DB::raw('COALESCE(
                    w_pl.id,
                    (SELECT w.id 
                     FROM food_floor_order_items ffoi
                     INNER JOIN warehouse_division wd ON ffoi.warehouse_division_id = wd.id
                     INNER JOIN warehouses w ON wd.warehouse_id = w.id
                     WHERE ffoi.floor_order_id = ffo.id
                     LIMIT 1)
                ) as warehouse_id'),
                DB::raw('COALESCE(
                    w_pl.name,
                    (SELECT w.name 
                     FROM food_floor_order_items ffoi
                     INNER JOIN warehouse_division wd ON ffoi.warehouse_division_id = wd.id
                     INNER JOIN warehouses w ON wd.warehouse_id = w.id
                     WHERE ffoi.floor_order_id = ffo.id
                     LIMIT 1)
                ) as warehouse_name')
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

        // Format data with total_amount calculation (only GR, no GR Supplier, same as Rekap FJ)
        $groupedGrList = $grList->map(function($gr) {
            // Calculate total_amount for each GR (from outlet_food_good_receives only)
            $grTotalAmount = DB::table('outlet_food_good_receive_items as gri')
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
                'warehouse_id' => $gr->warehouse_id,
                'warehouse_name' => $gr->warehouse_name,
                'total_amount' => (float) ($grTotalAmount ?: 0), // Only GR amount (same as Rekap FJ)
                'items' => [] // Will be loaded via API when needed
            ];
        });

        // Get banks for payment method selection
        $banks = \App\Models\BankAccount::where('is_active', 1)
            ->with('outlet')
            ->orderBy('bank_name')
            ->get();
        
        // Transform untuk include outlet name
        $banks = $banks->map(function($bank) {
            return [
                'id' => $bank->id,
                'bank_name' => $bank->bank_name,
                'account_number' => $bank->account_number,
                'account_name' => $bank->account_name,
                'outlet_id' => $bank->outlet_id,
                'outlet' => $bank->outlet ? [
                    'id_outlet' => $bank->outlet->id_outlet,
                    'nama_outlet' => $bank->outlet->nama_outlet,
                ] : null,
                'outlet_name' => $bank->outlet ? $bank->outlet->nama_outlet : 'Head Office',
            ];
        });

        // Get Chart of Accounts for COA selection
        $coas = \App\Models\ChartOfAccount::where('is_active', 1)
            ->orderBy('code')
            ->get()
            ->map(function($coa) {
                return [
                    'id' => $coa->id,
                    'code' => $coa->code,
                    'name' => $coa->name,
                    'display_name' => $coa->code . ' - ' . $coa->name,
                ];
            });

        return Inertia::render('OutletPayment/Form', [
            'mode' => 'create',
            'outlets' => $outlets,
            'grList' => $groupedGrList,
            'banks' => $banks,
            'coas' => $coas,
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
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('warehouse_division as wd_pl', 'pl.warehouse_division_id', '=', 'wd_pl.id')
            ->leftJoin('warehouses as w_pl', 'wd_pl.warehouse_id', '=', 'w_pl.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->whereNull('op.id')
            ->whereNull('gr.deleted_at')
            ->select(
                'gr.id',
                'gr.number',
                'gr.receive_date',
                'gr.outlet_id',
                'gr.delivery_order_id',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                DB::raw('COALESCE(
                    w_pl.id,
                    (SELECT w.id 
                     FROM food_floor_order_items ffoi
                     INNER JOIN warehouse_division wd ON ffoi.warehouse_division_id = wd.id
                     INNER JOIN warehouses w ON wd.warehouse_id = w.id
                     WHERE ffoi.floor_order_id = ffo.id
                     LIMIT 1)
                ) as warehouse_id'),
                DB::raw('COALESCE(
                    w_pl.name,
                    (SELECT w.name 
                     FROM food_floor_order_items ffoi
                     INNER JOIN warehouse_division wd ON ffoi.warehouse_division_id = wd.id
                     INNER JOIN warehouses w ON wd.warehouse_id = w.id
                     WHERE ffoi.floor_order_id = ffo.id
                     LIMIT 1)
                ) as warehouse_name')
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

        // Format data with total_amount calculation (only GR, no GR Supplier, same as Rekap FJ)
        $groupedGrList = $grList->map(function($gr) {
            // Calculate total_amount for each GR (from outlet_food_good_receives only)
            $grTotalAmount = DB::table('outlet_food_good_receive_items as gri')
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
                'warehouse_id' => $gr->warehouse_id,
                'warehouse_name' => $gr->warehouse_name,
                'total_amount' => (float) ($grTotalAmount ?: 0), // Only GR amount (same as Rekap FJ)
                'items' => [], // Will be loaded via API when needed
                'type' => 'gr' // Add type identifier
            ];
        });

        return response()->json([
            'success' => true,
            'grList' => $groupedGrList,
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

        // Debug: Check customers with id_outlet
        $customersWithOutlet = DB::table('customers')
            ->where('id_outlet', (string)$outletId)
            ->select('id', 'name', 'id_outlet', 'type')
            ->get();
        

        // Debug: Check ALL retail sales for this outlet and date range
        $allRetailSalesForOutlet = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->where('c.id_outlet', (string)$outletId)
            ->where('rws.status', 'completed')
            ->select('rws.id', 'rws.number', 'rws.customer_id', 'rws.sale_date', 'rws.status', 'c.name as customer_name', 'c.id_outlet')
            ->get();
        

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
            
        }

        // Debug: Check if outlet exists in tbl_data_outlet
        $outletExists = DB::table('tbl_data_outlet')
            ->where('id_outlet', (string)$outletId)
            ->first();
        

        // Debug: Check customers with outlet_id and their outlet mapping
        $customersWithOutletMapping = DB::table('customers as c')
            ->leftJoin('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')
            ->where('c.id_outlet', (string)$outletId)
            ->select('c.id', 'c.name', 'c.id_outlet', 'c.type', 'o.id_outlet as outlet_id', 'o.nama_outlet')
            ->get();
        

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
                'rws.warehouse_id',
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
        

        $retailList = $retailQuery->orderBy('rws.sale_date', 'desc')
            ->paginate($perPage);


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
                'warehouse_id' => $retail->warehouse_id,
                'warehouse_name' => $retail->warehouse_name,
                'division_name' => $retail->division_name,
                'items' => [], // Will be loaded via API when needed
                'type' => 'retail_sales' // Add type identifier
            ];
        });


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
        
        // Ambil daftar outlet untuk filter
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
        
        // Ambil daftar RO Mode untuk filter (dari food_floor_orders yang memiliki GR)
        $roModes = DB::table('food_floor_orders as ffo')
            ->join('delivery_orders as do', 'ffo.id', '=', 'do.floor_order_id')
            ->join('outlet_food_good_receives as gr', 'do.id', '=', 'gr.delivery_order_id')
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'completed')
            ->whereNotNull('ffo.fo_mode')
            ->distinct()
            ->select('ffo.fo_mode as value')
            ->orderBy('ffo.fo_mode')
            ->get()
            ->map(function($item) {
                return ['id' => $item->value, 'name' => $item->value];
            });
        
        // Cek apakah ada filter yang diterapkan
        $hasFilters = $request->filled('search') || $request->filled('from') || $request->filled('to') || 
                      ($user->id_outlet == 1 && $request->filled('outlet_id')) ||
                      ($user->id_outlet != 1) ||
                      $request->filled('fo_mode') ||
                      $request->filled('transaction_type');
        
        // Jika tidak ada filter, return dengan data kosong
        if (!$hasFilters) {
            return Inertia::render('OutletPayment/ReportInvoiceOutlet', [
                'data' => collect([]),
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) ($request->input('per_page', 15)),
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
                'details' => [],
                'outlets' => $outlets,
                'roModes' => $roModes,
                'filters' => $request->only(['search', 'from', 'to', 'outlet_id', 'fo_mode', 'transaction_type', 'per_page']),
                'user_id_outlet' => $user->id_outlet,
                'hasFilters' => false,
            ]);
        }
        
        // Query untuk GR (Good Receive) - langsung dari tabel GR tanpa outlet_payments
        $grQuery = DB::table('outlet_food_good_receives as gr')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'gr.created_by', '=', 'u.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'completed') // Hanya GR yang sudah completed
            ->select(
                'gr.id as gr_id',
                'gr.number as gr_number',
                'gr.created_at as invoice_date', // Tgl invoice dari created_at GR
                'gr.receive_date as gr_receive_date',
                'gr.notes as gr_notes',
                'o.id_outlet',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as created_by_name',
                'w.name as warehouse_name',
                'wd.name as warehouse_division_name',
                'wo.name as warehouse_outlet_name',
                'ffo.fo_mode',
                'ffo.order_number as ro_number',
                DB::raw("'GR' as transaction_type"),
                DB::raw('NULL as payment_number'),
                DB::raw('NULL as payment_total')
            );
            
        // Query untuk RWS (Retail Warehouse Sales) - langsung dari tabel RWS tanpa outlet_payments
        $rwsQuery = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->join('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'rws.created_by', '=', 'u.id')
            ->leftJoin('warehouse_division as wd', 'rws.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->where('rws.status', 'completed') // Hanya RWS yang sudah completed
            ->where('c.type', 'branch') // Hanya customer branch
            ->select(
                'rws.id as gr_id',
                'rws.number as gr_number',
                'rws.created_at as invoice_date', // Tgl invoice dari created_at RWS
                'rws.sale_date as gr_receive_date',
                'rws.notes as gr_notes',
                'o.id_outlet',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as created_by_name',
                'w.name as warehouse_name',
                'wd.name as warehouse_division_name',
                DB::raw('NULL as warehouse_outlet_name'),
                DB::raw('NULL as fo_mode'),
                DB::raw('NULL as ro_number'),
                DB::raw("'RWS' as transaction_type"),
                DB::raw('NULL as payment_number'),
                'rws.total_amount as payment_total'
            );
            
        // Filter outlet untuk GR query
        if ($user->id_outlet != 1) {
            // Non-superuser: otomatis filter berdasarkan outlet mereka
            $grQuery->where('gr.outlet_id', $user->id_outlet);
        } elseif ($request->filled('outlet_id')) {
            // Superuser: filter berdasarkan outlet yang dipilih
            $grQuery->where('gr.outlet_id', $request->outlet_id);
        }
        
        // Filter outlet untuk RWS query
        if ($user->id_outlet != 1) {
            // Non-superuser: otomatis filter berdasarkan outlet mereka
            $rwsQuery->where('c.id_outlet', $user->id_outlet);
        } elseif ($request->filled('outlet_id')) {
            // Superuser: filter berdasarkan outlet yang dipilih
            $rwsQuery->where('c.id_outlet', $request->outlet_id);
        }
        
        // Filter tanggal untuk GR query
        if ($request->from) {
            $grQuery->whereDate('gr.created_at', '>=', $request->from);
        }
        if ($request->to) {
            $grQuery->whereDate('gr.created_at', '<=', $request->to);
        }
        
        // Filter tanggal untuk RWS query
        if ($request->from) {
            $rwsQuery->whereDate('rws.created_at', '>=', $request->from);
        }
        if ($request->to) {
            $rwsQuery->whereDate('rws.created_at', '<=', $request->to);
        }
        
        // Search untuk GR query
        if ($request->search) {
            $search = $request->search;
            $grQuery->where(function($q) use ($search) {
                $q->where('gr.number', 'like', "%$search%")
                  ->orWhere('o.nama_outlet', 'like', "%$search%")
                  ->orWhere('u.nama_lengkap', 'like', "%$search%");
            });
        }
        
        // Search untuk RWS query
        if ($request->search) {
            $search = $request->search;
            $rwsQuery->where(function($q) use ($search) {
                $q->where('rws.number', 'like', "%$search%")
                  ->orWhere('o.nama_outlet', 'like', "%$search%")
                  ->orWhere('u.nama_lengkap', 'like', "%$search%")
                  ->orWhere('c.name', 'like', "%$search%");
            });
        }
        
        // Filter FO Mode untuk GR query (hanya berlaku untuk transaksi GR)
        if ($request->filled('fo_mode')) {
            $grQuery->where('ffo.fo_mode', $request->fo_mode);
        }
        
        // Filter transaction type
        $transactionType = $request->input('transaction_type');
        
        // Gabungkan kedua query dan order by
        if ($transactionType === 'GR') {
            $data = $grQuery->get()->sortByDesc('invoice_date')->values();
        } elseif ($transactionType === 'RWS') {
            $data = $rwsQuery->get()->sortByDesc('invoice_date')->values();
        } else {
            $data = $grQuery->union($rwsQuery)->get()->sortByDesc('invoice_date')->values();
        }
        
        // Pagination
        $perPageInput = $request->input('per_page', 15);
        $currentPageInput = $request->input('page', 1);
        
        // Handle array input (take first element if array)
        $perPage = is_array($perPageInput) ? (int) ($perPageInput[0] ?? 15) : (int) $perPageInput;
        $currentPage = is_array($currentPageInput) ? (int) ($currentPageInput[0] ?? 1) : (int) $currentPageInput;
        
        // Ensure minimum values
        $perPage = max(1, $perPage);
        $currentPage = max(1, $currentPage);
        
        // Convert collection to paginated collection
        $total = $data->count();
        $items = $data->slice(($currentPage - 1) * $perPage, $perPage)->values();
        
        // Create paginator manually
        $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
        
        // Ambil detail item per GR dan RWS
        $details = [];
        
        // Detail untuk GR items (gunakan paginated data untuk efisiensi)
        $grIds = collect($paginatedData->items())->where('transaction_type', 'GR')->pluck('gr_id')->unique()->values();
        if ($grIds->count()) {
            $grItemRows = DB::table('outlet_food_good_receive_items as gri')
                ->join('items as i', 'gri.item_id', '=', 'i.id')
                ->join('units as u', 'gri.unit_id', '=', 'u.id')
                ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
                ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as ffoi', function($join) {
                    $join->on('do.floor_order_id', '=', 'ffoi.floor_order_id')
                         ->on('gri.item_id', '=', 'ffoi.item_id');
                })
                ->whereIn('gri.outlet_food_good_receive_id', $grIds)
                ->whereNull('gr.deleted_at')
                ->select(
                    'gri.outlet_food_good_receive_id as gr_id',
                    'i.name as item_name',
                    'gri.received_qty as qty',
                    'u.name as unit_name',
                    DB::raw('COALESCE(ffoi.price, 0) as price'),
                    DB::raw('(gri.received_qty * COALESCE(ffoi.price, 0)) as subtotal')
                )
                ->get();
            foreach ($grItemRows as $row) {
                $details[$row->gr_id][] = $row;
            }
        }
        
        // Detail untuk RWS items (gunakan paginated data untuk efisiensi)
        $rwsIds = collect($paginatedData->items())->where('transaction_type', 'RWS')->pluck('gr_id')->unique()->values();
        if ($rwsIds->count()) {
            $rwsItemRows = DB::table('retail_warehouse_sale_items as rwsi')
                ->join('items as i', 'rwsi.item_id', '=', 'i.id')
                ->whereIn('rwsi.retail_warehouse_sale_id', $rwsIds)
                ->select(
                    'rwsi.retail_warehouse_sale_id as gr_id',
                    'i.name as item_name',
                    'rwsi.qty as qty',
                    'rwsi.unit as unit_name',
                    'rwsi.price as price',
                    'rwsi.subtotal as subtotal'
                )
                ->get();
            foreach ($rwsItemRows as $row) {
                $details[$row->gr_id][] = $row;
            }
        }
        
        // Update payment_total untuk GR berdasarkan total items (hanya untuk data yang di-paginate)
        $paginatedItems = collect($paginatedData->items());
        $dataArray = [];
        
        foreach ($paginatedItems as $row) {
            // Convert to array first
            $rowData = (array) $row;
            
            // Update payment_total untuk GR dari detail items
            if ($rowData['transaction_type'] === 'GR') {
                if (isset($details[$rowData['gr_id']]) && count($details[$rowData['gr_id']]) > 0) {
                    $rowData['payment_total'] = collect($details[$rowData['gr_id']])->sum('subtotal');
                } else {
                    // Jika tidak ada detail items, coba hitung langsung dari database
                    $grTotal = DB::table('outlet_food_good_receive_items as gri')
                        ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
                        ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                        ->join('food_floor_order_items as ffoi', function($join) {
                            $join->on('do.floor_order_id', '=', 'ffoi.floor_order_id')
                                 ->on('gri.item_id', '=', 'ffoi.item_id');
                        })
                        ->where('gri.outlet_food_good_receive_id', $rowData['gr_id'])
                        ->whereNull('gr.deleted_at')
                        ->sum(DB::raw('gri.received_qty * ffoi.price'));
                    $rowData['payment_total'] = $grTotal ?: 0;
                }
            }
            
            // Pastikan RWS juga punya payment_total yang valid
            if ($rowData['transaction_type'] === 'RWS') {
                // payment_total sudah diambil dari rws.total_amount di query
                if (!isset($rowData['payment_total']) || $rowData['payment_total'] === null) {
                    // Jika tidak ada, ambil dari total items
                    if (isset($details[$rowData['gr_id']]) && count($details[$rowData['gr_id']]) > 0) {
                        $rowData['payment_total'] = collect($details[$rowData['gr_id']])->sum('subtotal');
                    } else {
                        // Fallback: hitung dari database
                        $rwsTotal = DB::table('retail_warehouse_sale_items')
                            ->where('retail_warehouse_sale_id', $rowData['gr_id'])
                            ->sum('subtotal');
                        $rowData['payment_total'] = $rwsTotal ?: 0;
                    }
                }
            }
            
            $dataArray[] = $rowData;
        }
        
        return Inertia::render('OutletPayment/ReportInvoiceOutlet', [
            'data' => $dataArray,
            'pagination' => [
                'current_page' => $paginatedData->currentPage(),
                'last_page' => $paginatedData->lastPage(),
                'per_page' => $paginatedData->perPage(),
                'total' => $paginatedData->total(),
                'from' => $paginatedData->firstItem(),
                'to' => $paginatedData->lastItem(),
                'first_page_url' => $paginatedData->url(1),
                'last_page_url' => $paginatedData->url($paginatedData->lastPage()),
                'prev_page_url' => $paginatedData->previousPageUrl(),
                'next_page_url' => $paginatedData->nextPageUrl(),
            ],
            'details' => $details,
            'outlets' => $outlets,
            'roModes' => $roModes,
            'filters' => $request->only(['search', 'from', 'to', 'outlet_id', 'fo_mode', 'transaction_type', 'per_page']),
            'user_id_outlet' => $user->id_outlet,
            'hasFilters' => true,
        ]);
    }

    public function exportInvoiceOutlet(Request $request)
    {
        $user = auth()->user();
        
        // Query untuk GR (Good Receive) - sama seperti reportInvoiceOutlet
        $grQuery = DB::table('outlet_food_good_receives as gr')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'gr.created_by', '=', 'u.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouse_outlets as wo', 'gr.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'completed')
            ->select(
                'gr.id as gr_id',
                'gr.number as gr_number',
                'gr.created_at as invoice_date',
                'gr.receive_date as gr_receive_date',
                'gr.notes as gr_notes',
                'o.id_outlet',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as created_by_name',
                'w.name as warehouse_name',
                'wd.name as warehouse_division_name',
                'wo.name as warehouse_outlet_name',
                'ffo.fo_mode',
                'ffo.order_number as ro_number',
                DB::raw("'GR' as transaction_type"),
                DB::raw('NULL as payment_number'),
                DB::raw('NULL as payment_total')
            );
            
        // Query untuk RWS (Retail Warehouse Sales)
        $rwsQuery = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->join('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'rws.created_by', '=', 'u.id')
            ->leftJoin('warehouse_division as wd', 'rws.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->select(
                'rws.id as gr_id',
                'rws.number as gr_number',
                'rws.created_at as invoice_date',
                'rws.sale_date as gr_receive_date',
                'rws.notes as gr_notes',
                'o.id_outlet',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as created_by_name',
                'w.name as warehouse_name',
                'wd.name as warehouse_division_name',
                DB::raw('NULL as warehouse_outlet_name'),
                DB::raw('NULL as fo_mode'),
                DB::raw('NULL as ro_number'),
                DB::raw("'RWS' as transaction_type"),
                DB::raw('NULL as payment_number'),
                'rws.total_amount as payment_total'
            );
            
        // Filter outlet untuk GR query
        if ($user->id_outlet != 1) {
            $grQuery->where('gr.outlet_id', $user->id_outlet);
        } elseif ($request->filled('outlet_id')) {
            $grQuery->where('gr.outlet_id', $request->outlet_id);
        }
        
        // Filter outlet untuk RWS query
        if ($user->id_outlet != 1) {
            $rwsQuery->where('c.id_outlet', $user->id_outlet);
        } elseif ($request->filled('outlet_id')) {
            $rwsQuery->where('c.id_outlet', $request->outlet_id);
        }
        
        // Filter tanggal untuk GR query
        if ($request->from) {
            $grQuery->whereDate('gr.created_at', '>=', $request->from);
        }
        if ($request->to) {
            $grQuery->whereDate('gr.created_at', '<=', $request->to);
        }
        
        // Filter tanggal untuk RWS query
        if ($request->from) {
            $rwsQuery->whereDate('rws.created_at', '>=', $request->from);
        }
        if ($request->to) {
            $rwsQuery->whereDate('rws.created_at', '<=', $request->to);
        }
        
        // Search untuk GR query
        if ($request->search) {
            $search = $request->search;
            $grQuery->where(function($q) use ($search) {
                $q->where('gr.number', 'like', "%$search%")
                  ->orWhere('o.nama_outlet', 'like', "%$search%")
                  ->orWhere('u.nama_lengkap', 'like', "%$search%");
            });
        }
        
        // Search untuk RWS query
        if ($request->search) {
            $search = $request->search;
            $rwsQuery->where(function($q) use ($search) {
                $q->where('rws.number', 'like', "%$search%")
                  ->orWhere('o.nama_outlet', 'like', "%$search%")
                  ->orWhere('u.nama_lengkap', 'like', "%$search%")
                  ->orWhere('c.name', 'like', "%$search%");
            });
        }
        
        // Filter FO Mode untuk GR query
        if ($request->filled('fo_mode')) {
            $grQuery->where('ffo.fo_mode', $request->fo_mode);
        }
        
        // Filter transaction type
        $transactionType = $request->input('transaction_type');
        
        // Gabungkan kedua query dan order by
        if ($transactionType === 'GR') {
            $data = $grQuery->get()->sortByDesc('invoice_date')->values();
        } elseif ($transactionType === 'RWS') {
            $data = $rwsQuery->get()->sortByDesc('invoice_date')->values();
        } else {
            $data = $grQuery->union($rwsQuery)->get()->sortByDesc('invoice_date')->values();
        }
        
        // Ambil detail item per GR dan RWS
        $details = [];
        
        // Detail untuk GR items
        $grIds = $data->where('transaction_type', 'GR')->pluck('gr_id')->unique()->values();
        if ($grIds->count()) {
            $grItemRows = DB::table('outlet_food_good_receive_items as gri')
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
            foreach ($grItemRows as $row) {
                $details[$row->gr_id][] = $row;
            }
        }
        
        // Detail untuk RWS items
        $rwsIds = $data->where('transaction_type', 'RWS')->pluck('gr_id')->unique()->values();
        if ($rwsIds->count()) {
            $rwsItemRows = DB::table('retail_warehouse_sale_items as rwsi')
                ->join('items as i', 'rwsi.item_id', '=', 'i.id')
                ->whereIn('rwsi.retail_warehouse_sale_id', $rwsIds)
                ->select(
                    'rwsi.retail_warehouse_sale_id as gr_id',
                    'i.name as item_name',
                    'rwsi.qty as qty',
                    'rwsi.unit as unit_name',
                    'rwsi.price as price',
                    'rwsi.subtotal as subtotal'
                )
                ->get();
            foreach ($rwsItemRows as $row) {
                $details[$row->gr_id][] = $row;
            }
        }
        
        // Update payment_total untuk GR berdasarkan total items
        foreach ($data as $row) {
            if ($row->transaction_type === 'GR' && isset($details[$row->gr_id])) {
                $row->payment_total = collect($details[$row->gr_id])->sum('subtotal');
            }
        }
        
        // Export to Excel
        $export = new \App\Exports\InvoiceOutletReportExport($data, $details, $request->only(['from', 'to', 'outlet_id', 'transaction_type', 'fo_mode']));
        $export->export();
    }
    
    /**
     * Create jurnal entries for outlet payment (when status = paid)
     */
    private function createJurnalForOutletPayment($payment)
    {
        // Skip if no COA selected
        if (!$payment->coa_id) {
            return;
        }
        
        // Get all payments with same warehouse_id (for grouping)
        $warehouseId = $payment->warehouse_id;
        
        // For simplicity, create jurnal for single payment (not grouped)
        // If you want to group by warehouse, need to collect all payments with same warehouse_id
        
        $totalAmount = $payment->total_amount;
        $paymentNumber = $payment->payment_number ?? $payment->id;
        
        // Generate no jurnal
        $noJurnal = \App\Models\Jurnal::generateNoJurnal();
        $tanggal = $payment->date;
        $keterangan = "Outlet Payment: " . $paymentNumber;
        
        // Determine COA Kredit for OUTLET (uang keluar dari outlet)
        $coaKreditOutlet = null;
        if ($payment->payment_method === 'cash') {
            // Cash: use Kas Outlet (ID 54)
            $coaKreditOutlet = 54;
        } elseif (($payment->payment_method === 'transfer' || $payment->payment_method === 'check') && $payment->bank_id) {
            // Transfer/Check: use bank's COA from bank_id (bank outlet)
            $bank = \App\Models\BankAccount::find($payment->bank_id);
            $coaKreditOutlet = $bank && $bank->coa_id ? $bank->coa_id : 54; // Fallback to Kas Outlet
        } else {
            // Default to Kas Outlet
            $coaKreditOutlet = 54;
        }
        
        // Determine COA Debit for HO (uang masuk ke HO)
        $coaDebitHO = null;
        if ($payment->payment_method === 'cash') {
            // Cash: use Kas HO (ID 60)
            $coaDebitHO = 60;
        } elseif (($payment->payment_method === 'transfer' || $payment->payment_method === 'check') && $payment->receiver_bank_id) {
            // Transfer/Check: use receiver bank's COA
            $receiverBank = \App\Models\BankAccount::find($payment->receiver_bank_id);
            $coaDebitHO = $receiverBank && $receiverBank->coa_id ? $receiverBank->coa_id : 60; // Fallback to Kas HO
        } else {
            // Default to Kas HO
            $coaDebitHO = 60;
        }
        
        // JURNAL 1: OUTLET (Debit Expense, Kredit Kas/Bank Outlet)
        // Note: warehouse_id TIDAK PERLU untuk jurnal outlet
        \App\Models\Jurnal::create([
            'no_jurnal' => $noJurnal,
            'tanggal' => $tanggal,
            'keterangan' => $keterangan . ' - Outlet',
            'coa_debit_id' => $payment->coa_id, // User selected COA (expense)
            'coa_kredit_id' => $coaKreditOutlet,
            'jumlah_debit' => $totalAmount,
            'jumlah_kredit' => $totalAmount,
            'outlet_id' => $payment->outlet_id,
            'reference_type' => 'outlet_payment',
            'reference_id' => $payment->id,
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => auth()->id() ?? 1,
            'created_by' => auth()->id() ?? 1,
        ]);
        
        \App\Models\JurnalGlobal::create([
            'no_jurnal' => $noJurnal,
            'tanggal' => $tanggal,
            'keterangan' => $keterangan . ' - Outlet',
            'coa_debit_id' => $payment->coa_id,
            'coa_kredit_id' => $coaKreditOutlet,
            'jumlah_debit' => $totalAmount,
            'jumlah_kredit' => $totalAmount,
            'outlet_id' => $payment->outlet_id,
            'reference_type' => 'outlet_payment',
            'reference_id' => $payment->id,
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => auth()->id() ?? 1,
            'created_by' => auth()->id() ?? 1,
        ]);
        
        // JURNAL 2: HEAD OFFICE (Debit Kas/Bank HO, Kredit Piutang/Lawan)
        // Generate no jurnal baru untuk HO
        $noJurnalHO = \App\Models\Jurnal::generateNoJurnal();
        
        // Note: outlet_id = 1 untuk HO (bukan null), warehouse_id tetap diisi
        \App\Models\Jurnal::create([
            'no_jurnal' => $noJurnalHO,
            'tanggal' => $tanggal,
            'keterangan' => $keterangan . ' - HO',
            'coa_debit_id' => $coaDebitHO, // Kas/Bank HO
            'coa_kredit_id' => $payment->coa_id, // Same COA (lawan account)
            'jumlah_debit' => $totalAmount,
            'jumlah_kredit' => $totalAmount,
            'outlet_id' => 1, // HO outlet_id = 1
            'warehouse_id' => $warehouseId,
            'reference_type' => 'outlet_payment',
            'reference_id' => $payment->id,
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => auth()->id() ?? 1,
            'created_by' => auth()->id() ?? 1,
        ]);
        
        \App\Models\JurnalGlobal::create([
            'no_jurnal' => $noJurnalHO,
            'tanggal' => $tanggal,
            'keterangan' => $keterangan . ' - HO',
            'coa_debit_id' => $coaDebitHO,
            'coa_kredit_id' => $payment->coa_id,
            'jumlah_debit' => $totalAmount,
            'jumlah_kredit' => $totalAmount,
            'outlet_id' => 1, // HO outlet_id = 1
            'warehouse_id' => $warehouseId,
            'reference_type' => 'outlet_payment',
            'reference_id' => $payment->id,
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => auth()->id() ?? 1,
            'created_by' => auth()->id() ?? 1,
        ]);
    }

} 