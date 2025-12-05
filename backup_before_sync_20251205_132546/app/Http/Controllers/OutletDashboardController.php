<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OutletDashboardController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());
        $id_outlet = $request->input('id_outlet');
        // Ambil qr_code
        $qr_code = DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('qr_code');
        // Summary
        $orders = DB::table('orders')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('kode_outlet', $qr_code)
            ->get();
        $total_orders = $orders->count();
        $total_sales = $orders->sum('grand_total');
        $total_pax = $orders->sum('pax');
        $avg_order = $total_orders ? round($total_sales / $total_orders) : 0;
        $total_discount = $orders->sum('discount');
        $total_cashback = $orders->sum('cashback');
        $total_service = $orders->sum('service');
        $total_tax = $orders->sum('pb1');
        $total_commfee = $orders->sum('commfee');
        // Payment methods
        $payments = DB::table('order_payment')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('kode_outlet', $qr_code)
            ->get();
        $payment_methods = $payments->groupBy('payment_code')->map(function($g) {
            return $g->sum('amount');
        });
        // Active promos
        $active_promos = DB::table('promos')
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->count();
        // Investor count
        $investor_count = DB::table('investors')->count();
        // Sales chart (per hari di range)
        $sales_chart = DB::table('orders')
            ->selectRaw('DATE(created_at) as tgl, SUM(grand_total) as total')
            ->where('kode_outlet', $qr_code)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('tgl')
            ->get();
        // Payment pie
        $payment_pie = $payment_methods;
        // Top items
        $top_items = DB::table('order_items')
            ->select('item_name', DB::raw('SUM(qty) as total_qty'))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('kode_outlet', $qr_code)
            ->groupBy('item_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();
        // Last orders
        $last_orders = DB::table('orders')
            ->select('id', 'paid_number as nomor', 'grand_total')
            ->where('kode_outlet', $qr_code)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
        // Promo usage
        $promo_usage = DB::table('order_promos as op')
            ->join('promos as p', 'op.promo_id', '=', 'p.id')
            ->select('p.name', DB::raw('COUNT(*) as used_count'))
            ->where('op.kode_outlet', $qr_code)
            ->whereDate('op.created_at', '>=', $from)
            ->whereDate('op.created_at', '<=', $to)
            ->groupBy('p.name')
            ->orderByDesc('used_count')
            ->get();
        // Officer checks with transaction value
        $officer_checks = DB::table('officer_checks as oc')
            ->leftJoin('users', 'oc.user_id', '=', 'users.id')
            ->leftJoin('orders', 'oc.id', '=', 'orders.id_oc')
            ->select('oc.id', 'users.nama_lengkap as user_name', 'oc.nilai', DB::raw('SUM(orders.grand_total) as transaksi'))
            ->whereDate('oc.created_at', '>=', $from)
            ->whereDate('oc.created_at', '<=', $to)
            ->groupBy('oc.id', 'users.nama_lengkap', 'oc.nilai')
            ->orderByDesc('oc.created_at')
            ->limit(5)
            ->get();
        // Active promos list
        $active_promos_list = DB::table('promos')
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get();
        // Investors with transaction value
        $investors = DB::table('investors as inv')
            ->leftJoin('orders', 'inv.id', '=', 'orders.id_investor')
            ->select('inv.id', 'inv.name', DB::raw('SUM(orders.grand_total) as transaksi'))
            ->groupBy('inv.id', 'inv.name')
            ->get();
        // Sales per mode
        $sales_per_mode = DB::table('orders')
            ->select('mode', DB::raw('SUM(grand_total) as total'))
            ->where('kode_outlet', $qr_code)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->groupBy('mode')
            ->orderBy('mode')
            ->get();
        // Waiter Leaderboard
        $waiterLeaderboard = DB::table('orders')
            ->select('orders.waiters', DB::raw('SUM(orders.grand_total) as total_sales'), 'users.avatar')
            ->leftJoin('users', 'orders.waiters', '=', 'users.nama_lengkap')
            ->where('orders.kode_outlet', $qr_code)
            ->when($from, function($query) use ($from) {
                return $query->whereDate('orders.created_at', '>=', $from);
            })
            ->when($to, function($query) use ($to) {
                return $query->whereDate('orders.created_at', '<=', $to);
            })
            ->groupBy('orders.waiters', 'users.avatar')
            ->orderBy('total_sales', 'desc')
            ->limit(10)
            ->get();
        return response()->json([
            'summary' => [
                'total_orders' => $total_orders,
                'total_sales' => $total_sales,
                'total_pax' => $total_pax,
                'avg_order' => $avg_order,
                'total_discount' => $total_discount,
                'total_cashback' => $total_cashback,
                'total_service' => $total_service,
                'total_tax' => $total_tax,
                'total_commfee' => $total_commfee,
                'payment_methods' => $payment_methods,
                'active_promos' => $active_promos,
                'investor_count' => $investor_count
            ],
            'sales_chart' => $sales_chart,
            'payment_pie' => $payment_pie,
            'top_items' => $top_items,
            'last_orders' => $last_orders,
            'promo_usage' => $promo_usage,
            'officer_checks' => $officer_checks,
            'active_promos_list' => $active_promos_list,
            'investors' => $investors,
            'sales_per_mode' => $sales_per_mode,
            'waiter_leaderboard' => $waiterLeaderboard
        ]);
    }
} 