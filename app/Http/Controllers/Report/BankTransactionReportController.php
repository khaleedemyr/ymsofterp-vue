<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BankTransactionExport;

class BankTransactionReportController extends Controller
{
    private function buildQuery(Request $request)
    {
        $query = DB::table('order_payment as op')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->join('tbl_data_outlet as tdo', 'o.kode_outlet', '=', 'tdo.qr_code')
            ->leftJoin('payment_types as pt', 'op.payment_code', '=', 'pt.code')
            ->where('o.status', 'paid')
            ->where(function ($q) {
                $q->where('pt.is_bank', true)
                  ->orWhereNotNull('op.bank_id');
            })
            ->select(
                'o.created_at as tanggal',
                'o.paid_number',
                'op.card_first4',
                'op.card_last4',
                'op.approval_code',
                'o.grand_total',
                DB::raw('COALESCE(o.discount, 0) as discount'),
                DB::raw('COALESCE(o.manual_discount_amount, 0) as manual_discount_amount'),
                DB::raw('(COALESCE(o.discount, 0) + COALESCE(o.manual_discount_amount, 0)) as total_discount'),
                'o.dpp',
                'o.pb1',
                'o.service',
                'op.amount as nilai_gesek',
                'op.payment_type as bank_name',
                'op.payment_code',
                'tdo.nama_outlet',
                'tdo.qr_code as kode_outlet'
            );

        if ($request->filled('kode_outlet')) {
            $query->where('o.kode_outlet', $request->kode_outlet);
        }

        if ($request->filled('payment_code')) {
            $query->where('op.payment_code', $request->payment_code);
        }

        if ($request->filled('payment_type')) {
            $query->where('op.payment_type', $request->payment_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('o.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('o.created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('o.paid_number', 'like', "%{$search}%")
                  ->orWhere('op.approval_code', 'like', "%{$search}%")
                  ->orWhere('op.card_first4', 'like', "%{$search}%")
                  ->orWhere('op.card_last4', 'like', "%{$search}%");
            });
        }

        $sortColumnMap = [
            'tanggal' => 'o.created_at',
            'paid_number' => 'o.paid_number',
            'payment_type' => 'op.payment_type',
            'card_first4' => 'op.card_first4',
            'card_last4' => 'op.card_last4',
            'approval_code' => 'op.approval_code',
            'grand_total' => 'o.grand_total',
            'total_discount' => DB::raw('(COALESCE(o.discount, 0) + COALESCE(o.manual_discount_amount, 0))'),
            'dpp' => 'o.dpp',
            'pb1' => 'o.pb1',
            'service' => 'o.service',
            'nilai_gesek' => 'op.amount',
        ];

        $sortBy = $request->input('sort_by', 'tanggal');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortColumn = $sortColumnMap[$sortBy] ?? 'o.created_at';

        $query->orderBy($sortColumn, $sortDir);

        return $query;
    }

    public function index(Request $request)
    {
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->orderBy('nama_outlet')
            ->get(['qr_code as value', 'nama_outlet as label']);

        $banks = DB::table('payment_types')
            ->where('is_bank', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['code as value', 'name as label']);

        $paymentTypes = DB::table('order_payment')
            ->whereNotNull('payment_type')
            ->where('payment_type', '!=', '')
            ->distinct()
            ->orderBy('payment_type')
            ->pluck('payment_type');

        $loadData = $request->filled('load_data');

        if (!$loadData && !$request->filled('date_from') && !$request->filled('date_to')) {
            return Inertia::render('Report/ReportBankTransaction', [
                'data' => [],
                'outlets' => $outlets,
                'banks' => $banks,
                'paymentTypes' => $paymentTypes,
                'filters' => $request->only(['kode_outlet', 'payment_code', 'payment_type', 'date_from', 'date_to', 'search', 'per_page', 'sort_by', 'sort_dir']),
                'summary' => null,
                'dataLoaded' => false,
                'total' => 0,
                'current_page' => 1,
                'per_page' => 25,
                'last_page' => 1,
            ]);
        }

        $perPage = (int) ($request->per_page ?? 25);
        $page = (int) ($request->page ?? 1);

        $query = $this->buildQuery($request);

        $total = (clone $query)->count();

        $summaryQuery = clone $query;
        $summary = $summaryQuery
            ->select(
                DB::raw('COUNT(*) as total_transaksi'),
                DB::raw('SUM(o.grand_total) as sum_grand_total'),
                DB::raw('SUM(COALESCE(o.discount, 0) + COALESCE(o.manual_discount_amount, 0)) as sum_discount'),
                DB::raw('SUM(o.dpp) as sum_dpp'),
                DB::raw('SUM(o.pb1) as sum_pb1'),
                DB::raw('SUM(o.service) as sum_service'),
                DB::raw('SUM(op.amount) as sum_nilai_gesek')
            )
            ->first();

        $data = $this->buildQuery($request)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return Inertia::render('Report/ReportBankTransaction', [
            'data' => $data,
            'outlets' => $outlets,
            'banks' => $banks,
            'paymentTypes' => $paymentTypes,
            'filters' => $request->only(['kode_outlet', 'payment_code', 'payment_type', 'date_from', 'date_to', 'search', 'per_page', 'sort_by', 'sort_dir']),
            'summary' => $summary,
            'dataLoaded' => true,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ]);
    }

    public function export(Request $request)
    {
        $data = $this->buildQuery($request)->get();

        $outletLabel = 'Semua Outlet';
        if ($request->filled('kode_outlet')) {
            $outlet = DB::table('tbl_data_outlet')->where('qr_code', $request->kode_outlet)->first();
            $outletLabel = $outlet->nama_outlet ?? $request->kode_outlet;
        }

        $bankLabel = 'Semua Bank';
        if ($request->filled('payment_code')) {
            $bank = DB::table('payment_types')->where('code', $request->payment_code)->first();
            $bankLabel = $bank->name ?? $request->payment_code;
        }

        $fileName = 'Rekap_Transaksi_Bank_' . date('Ymd_His') . '.xlsx';

        return Excel::download(
            new BankTransactionExport($data, $outletLabel, $bankLabel, $request->date_from, $request->date_to),
            $fileName
        );
    }
}
