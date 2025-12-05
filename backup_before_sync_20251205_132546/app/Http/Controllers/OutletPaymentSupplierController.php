<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class OutletPaymentSupplierController extends Controller
{
    public function index(Request $request)
    {
        // Ambil semua payment supplier, join outlet dan GR supplier
        $payments = \DB::table('outlet_payment_suppliers as pay')
            ->leftJoin('tbl_data_outlet as o', 'pay.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('good_receive_outlet_suppliers as gr', 'pay.gr_id', '=', 'gr.id')
            ->select(
                'pay.id',
                'pay.payment_number',
                'pay.date',
                'pay.total_amount',
                'pay.status',
                'o.nama_outlet as outlet_name',
                'gr.gr_number as gr_supplier_number'
            )
            ->orderByDesc('pay.date')
            ->paginate(10);

        return Inertia::render('OutletPaymentSupplier/Index', [
            'payments' => $payments
        ]);
    }

    public function create(Request $request)
    {
        // Ambil outlet
        $outlets = \DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        // Ambil GR Supplier yang belum ada payment
        $grList = \DB::table('good_receive_outlet_suppliers as gr')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('food_floor_order_supplier_headers as ro', 'gr.ro_supplier_id', '=', 'ro.id')
            ->leftJoin('suppliers as s', 'ro.supplier_id', '=', 's.id')
            ->leftJoin('outlet_payment_suppliers as pay', function($join) {
                $join->on('pay.gr_id', '=', 'gr.id')->whereNull('pay.deleted_at');
            })
            ->where(function($q) {
                $q->whereNull('pay.id')->orWhere('pay.status', 'cancelled');
            })
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.receive_date',
                'gr.outlet_id',
                'o.nama_outlet as outlet_name',
                'gr.status',
                'gr.ro_supplier_id',
                'ro.supplier_fo_number as ro_number',
                's.name as supplier_name',
                'ro.created_at as ro_created_at'
            )
            ->get();

        // Hitung total_amount per GR dan ambil items
        foreach ($grList as $gr) {
            $gr->total_amount = \DB::table('good_receive_outlet_supplier_items')
                ->where('good_receive_id', $gr->id)
                ->sum(\DB::raw('qty_received * price'));
            // Ambil items
            $gr->items = \DB::table('good_receive_outlet_supplier_items as gri')
                ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
                ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
                ->where('gri.good_receive_id', $gr->id)
                ->select(
                    'gri.id',
                    'gri.item_id',
                    'i.name as item_name',
                    'gri.qty_ordered',
                    'gri.qty_received',
                    'gri.unit_id',
                    'u.name as unit_name',
                    'gri.price',
                    'gri.notes'
                )
                ->get();
        }

        return Inertia::render('OutletPaymentSupplier/Form', [
            'mode' => 'create',
            'outlets' => $outlets,
            'grList' => $grList,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'gr_id' => 'required|exists:good_receive_outlet_suppliers,id',
            'date' => 'required|date',
            'total_amount' => 'required|numeric|min:0',
        ]);

        // Cek apakah GR sudah punya payment yang aktif
        $existing = \DB::table('outlet_payment_suppliers')
            ->where('gr_id', $request->gr_id)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->first();
        if ($existing) {
            return back()->with('error', 'GR Supplier ini sudah memiliki payment yang aktif.');
        }

        // Generate payment_number: OPS-YYYYMMDD-xxxx
        $today = date('Ymd');
        $countToday = \DB::table('outlet_payment_suppliers')
            ->whereDate('created_at', date('Y-m-d'))
            ->count();
        $payment_number = 'OPS-' . $today . '-' . str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);

        // Insert payment
        $id = \DB::table('outlet_payment_suppliers')->insertGetId([
            'payment_number' => $payment_number,
            'outlet_id' => $request->outlet_id,
            'gr_id' => $request->gr_id,
            'date' => $request->date,
            'total_amount' => $request->total_amount,
            'status' => 'pending',
            'notes' => $request->notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('outlet-payment-suppliers.index')
            ->with('success', 'Payment supplier berhasil dibuat.');
    }

    public function show($id)
    {
        // Ambil payment supplier
        $payment = \DB::table('outlet_payment_suppliers as pay')
            ->where('pay.id', $id)
            ->first();
        if (!$payment) abort(404);

        // Ambil GR Supplier
        $gr = \DB::table('good_receive_outlet_suppliers as gr')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('food_floor_order_supplier_headers as ro', 'gr.ro_supplier_id', '=', 'ro.id')
            ->where('gr.id', $payment->gr_id)
            ->select(
                'gr.*',
                'o.nama_outlet as outlet_name',
                'ro.supplier_fo_number as ro_number'
            )
            ->first();

        // Ambil items GR Supplier
        $items = \DB::table('good_receive_outlet_supplier_items as gri')
            ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
            ->where('gri.good_receive_id', $gr->id)
            ->select(
                'gri.*',
                'i.name as item_name',
                'u.name as unit_name'
            )
            ->get();

        // Hitung total_amount
        $total_amount = $items->sum(function($item) {
            return $item->qty_received * $item->price;
        });

        $paymentData = [
            'id' => $payment->id,
            'status' => $payment->status,
            'total_amount' => $total_amount,
            'gr_supplier' => $gr,
            'items' => $items,
        ];

        return Inertia::render('OutletPaymentSupplier/Show', [
            'payment' => $paymentData
        ]);
    }

    public function update(Request $request, $id)
    {
        // TODO: Update payment
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,paid,canceled'
        ]);
        $payment = \DB::table('outlet_payment_suppliers')->where('id', $id)->first();
        if (!$payment) {
            return redirect()->back()->with('error', 'Data tidak ditemukan');
        }
        if ($payment->status !== 'pending') {
            return redirect()->back()->with('error', 'Status hanya bisa diubah jika masih pending');
        }
        \DB::table('outlet_payment_suppliers')->where('id', $id)->update([
            'status' => $request->status,
            'updated_at' => now(),
        ]);
        return redirect()->back()->with('success', 'Status berhasil diupdate');
    }

    public function destroy($id)
    {
        // TODO: Delete payment
    }

    public function unpaidGR(Request $request)
    {
        $grSearch = $request->input('gr_search');
        $grFrom = $request->input('gr_from');
        $grTo = $request->input('gr_to');

        // Ambil GR Supplier yang belum ada payment
        $rawGrList = \DB::table('good_receive_outlet_suppliers as gr')
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('food_floor_order_supplier_headers as ro', 'gr.ro_supplier_id', '=', 'ro.id')
            ->leftJoin('suppliers as s', 'ro.supplier_id', '=', 's.id')
            ->leftJoin('outlet_payment_suppliers as pay', function($join) {
                $join->on('pay.gr_id', '=', 'gr.id')->whereNull('pay.deleted_at');
            })
            ->where(function($q) {
                $q->whereNull('pay.id')->orWhere('pay.status', 'cancelled');
            })
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.receive_date',
                'gr.outlet_id',
                'o.nama_outlet as outlet_name',
                'gr.status',
                'gr.ro_supplier_id',
                'ro.supplier_fo_number as ro_number',
                's.name as supplier_name'
            )
            ->get();

        // Hitung total_amount per GR
        foreach ($rawGrList as $gr) {
            $gr->total_amount = \DB::table('good_receive_outlet_supplier_items')
                ->where('good_receive_id', $gr->id)
                ->sum(\DB::raw('qty_received * price'));
        }

        // Filter by search and date
        $filteredGrList = $rawGrList->filter(function($gr) use ($grSearch, $grFrom, $grTo) {
            $match = true;
            if ($grSearch) {
                $search = mb_strtolower($grSearch);
                $match = (
                    ($gr->outlet_name && mb_stripos($gr->outlet_name, $search) !== false) ||
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

        // Grouping per outlet, per tanggal, per supplier
        $grouped = [];
        foreach ($filteredGrList as $gr) {
            $date = date('Y-m-d', strtotime($gr->receive_date));
            $outlet = $gr->outlet_name;
            $supplier = $gr->supplier_name;
            $key = $outlet . '|' . $date . '|' . $supplier;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'outlet_name' => $outlet,
                    'date' => $date,
                    'supplier_name' => $supplier,
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

        return Inertia::render('OutletPaymentSupplier/UnpaidGR', [
            'grGroups' => $grGroupsPaginated,
            'filters' => $request->only(['gr_search', 'gr_from', 'gr_to'])
        ]);
    }
} 