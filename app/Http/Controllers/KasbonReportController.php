<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class KasbonReportController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('pr_kasbons')) {
            return Inertia::render('Reports/KasbonReport', [
                'tableMissing' => true,
                'kasbons' => [],
                'summary' => null,
                'divisions' => $this->divisions(),
                'outlets' => $this->outlets(),
                'pagination' => [
                    'total' => 0,
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'from' => null,
                    'to' => null,
                ],
                'filters' => $this->defaultFilters($request),
            ]);
        }

        $perPage = min(100, max(5, (int) $request->input('per_page', 15)));
        $page = max(1, (int) $request->input('page', 1));
        $dateFrom = $request->input('date_from') ?: now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->input('date_to') ?: now()->endOfMonth()->format('Y-m-d');

        $query = $this->buildKasbonReportQuery($request, $dateFrom, $dateTo);

        $summary = (clone $query)->selectRaw(
            "COUNT(*) as total_rows, " .
            "SUM(CASE WHEN k.status = 'active' THEN 1 ELSE 0 END) as active_count, " .
            "SUM(CASE WHEN k.status = 'completed' THEN 1 ELSE 0 END) as completed_count, " .
            "COALESCE(SUM(k.total_amount), 0) as sum_total_amount"
        )->first();

        $paginator = (clone $query)
            ->select([
                'k.id',
                'k.purchase_requisition_id',
                'k.pr_number',
                'k.outlet_id',
                'k.division_id',
                'k.employee_user_id',
                'k.total_amount',
                'k.termin_total',
                'k.installment_amount',
                'k.paid_installments',
                'k.status',
                'k.approved_at',
                'k.last_installment_at',
                'k.created_at',
                'k.updated_at',
                'o.nama_outlet as outlet_name',
                'd.nama_divisi as division_name',
                'emp.nama_lengkap as employee_name',
                'pr.status as pr_status',
            ])
            ->orderByDesc('k.approved_at')
            ->orderByDesc('k.id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        return Inertia::render('Reports/KasbonReport', [
            'tableMissing' => false,
            'kasbons' => $paginator->items(),
            'summary' => [
                'total_rows' => (int) ($summary->total_rows ?? 0),
                'active_count' => (int) ($summary->active_count ?? 0),
                'completed_count' => (int) ($summary->completed_count ?? 0),
                'sum_total_amount' => (float) ($summary->sum_total_amount ?? 0),
            ],
            'divisions' => $this->divisions(),
            'outlets' => $this->outlets(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => array_merge($this->defaultFilters($request), [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
                'page' => $page,
            ]),
        ]);
    }

    private function defaultFilters(Request $request): array
    {
        return [
            'status' => $request->input('status', 'all'),
            'division_id' => $request->input('division_id', ''),
            'outlet_id' => $request->input('outlet_id', ''),
            'search' => $request->input('search', ''),
        ];
    }

    private function buildKasbonReportQuery(Request $request, string $dateFrom, string $dateTo): Builder
    {
        $status = $request->input('status', 'all');
        $divisionId = $request->input('division_id');
        $outletId = $request->input('outlet_id');
        $search = $request->input('search');

        $query = DB::table('pr_kasbons as k')
            ->leftJoin('purchase_requisitions as pr', 'pr.id', '=', 'k.purchase_requisition_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'k.outlet_id')
            ->leftJoin('users as emp', 'emp.id', '=', 'k.employee_user_id')
            ->leftJoin('tbl_data_divisi as d', 'd.id', '=', 'k.division_id');

        if ($status !== 'all') {
            $query->where('k.status', $status);
        }
        if ($divisionId) {
            $query->where('k.division_id', $divisionId);
        }
        if ($outletId) {
            $query->where('k.outlet_id', $outletId);
        }
        if ($dateFrom) {
            $query->whereDate('k.approved_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('k.approved_at', '<=', $dateTo);
        }
        if ($search) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('k.pr_number', 'like', $term)
                    ->orWhere('emp.nama_lengkap', 'like', $term);
            });
        }

        return $query;
    }

    private function divisions()
    {
        return DB::table('tbl_data_divisi')
            ->select('id', 'nama_divisi as name')
            ->orderBy('nama_divisi')
            ->get();
    }

    private function outlets()
    {
        return DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();
    }
}
