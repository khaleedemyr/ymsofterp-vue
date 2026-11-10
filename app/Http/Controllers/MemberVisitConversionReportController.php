<?php

namespace App\Http\Controllers;

use App\Services\RoleMenuService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MemberVisitConversionReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $filters = $this->normalizeFilters($request);
        $summary = $this->buildSummary($filters);
        $daily = $this->buildDailySeries($filters);
        $byOutlet = $this->buildByOutlet($filters);

        return Inertia::render('Crm/MemberVisitConversionReport', [
            'summary' => $summary,
            'daily' => $daily,
            'byOutlet' => $byOutlet,
            'filters' => $filters,
            'outlets' => $this->outletOptions(),
        ]);
    }

    /**
     * @return array{date_from: string, date_to: string, outlet: string}
     */
    private function normalizeFilters(Request $request): array
    {
        return [
            'date_from' => trim((string) $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'))),
            'date_to' => trim((string) $request->get('date_to', Carbon::now()->format('Y-m-d'))),
            'outlet' => trim((string) $request->get('outlet', '')),
        ];
    }

    private function memberOrdersQuery(array $filters)
    {
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $outletCandidates = $this->resolveOutletCandidates($filters['outlet'] ?? '');

        return DB::connection('db_justus')
            ->table('orders as o')
            ->where('o.status', 'paid')
            ->whereNotNull('o.member_id')
            ->where('o.member_id', '!=', '')
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('o.created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('o.created_at', '<=', $dateTo))
            ->when($outletCandidates !== [], fn ($q) => $q->whereIn('o.kode_outlet', $outletCandidates));
    }

    private function allChecksQuery(array $filters)
    {
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $outletCandidates = $this->resolveOutletCandidates($filters['outlet'] ?? '');

        return DB::connection('db_justus')
            ->table('orders as o')
            ->where('o.status', 'paid')
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('o.created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('o.created_at', '<=', $dateTo))
            ->when($outletCandidates !== [], fn ($q) => $q->whereIn('o.kode_outlet', $outletCandidates));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(array $filters): array
    {
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');

        // Total Registered Members (periode)
        $registered = DB::table('member_apps_members')
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->count();

        $memberAgg = (clone $this->memberOrdersQuery($filters))
            ->selectRaw('COUNT(*) as total_member_visits')
            ->selectRaw('COALESCE(SUM(o.grand_total), 0) as total_member_sales')
            ->selectRaw('COUNT(DISTINCT LOWER(o.member_id)) as unique_visiting_members')
            ->first();

        $totalMemberVisits = (int) ($memberAgg->total_member_visits ?? 0);
        $totalMemberSales = (float) ($memberAgg->total_member_sales ?? 0);

        $repeatCustomers = (int) DB::connection('db_justus')
            ->query()
            ->fromSub(
                (clone $this->memberOrdersQuery($filters))
                    ->selectRaw('LOWER(o.member_id) as member_key')
                    ->groupBy(DB::raw('LOWER(o.member_id)'))
                    ->havingRaw('COUNT(*) >= 2'),
                'repeat_customers'
            )
            ->count();

        $totalChecks = (int) (clone $this->allChecksQuery($filters))->count();

        // Mockup: REPEAT CUSTOMER / TOTAL MEMBER VISIT
        $repeatVisitRatio = $totalMemberVisits > 0
            ? round(($repeatCustomers / $totalMemberVisits) * 100, 2)
            : 0;

        // Mockup: TOTAL MEMBER VISIT / CHECK
        $memberVisitPenetration = $totalChecks > 0
            ? round(($totalMemberVisits / $totalChecks) * 100, 2)
            : 0;

        return [
            'total_registered_members' => (int) $registered,
            'total_member_visits' => $totalMemberVisits,
            'total_member_sales' => $totalMemberSales,
            'total_member_sales_formatted' => 'Rp '.number_format($totalMemberSales, 0, ',', '.'),
            'repeat_customers' => $repeatCustomers,
            'repeat_visit_ratio' => $repeatVisitRatio,
            'member_visit_penetration' => $memberVisitPenetration,
            'total_checks' => $totalChecks,
            'unique_visiting_members' => (int) ($memberAgg->unique_visiting_members ?? 0),
        ];
    }

    /**
     * Daily series for the two charts.
     *
     * @return array{labels: list<string>, repeat_visit_ratio: list<float>, member_visit_penetration: list<float>, member_visits: list<int>, checks: list<int>, repeat_customers: list<int>}
     */
    private function buildDailySeries(array $filters): array
    {
        $dateFrom = Carbon::parse($filters['date_from'] ?: Carbon::now()->startOfMonth()->format('Y-m-d'))->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'] ?: Carbon::now()->format('Y-m-d'))->startOfDay();

        $memberDaily = (clone $this->memberOrdersQuery($filters))
            ->selectRaw('DATE(o.created_at) as d')
            ->selectRaw('COUNT(*) as visits')
            ->groupBy(DB::raw('DATE(o.created_at)'))
            ->pluck('visits', 'd');

        $checkDaily = (clone $this->allChecksQuery($filters))
            ->selectRaw('DATE(o.created_at) as d')
            ->selectRaw('COUNT(*) as checks')
            ->groupBy(DB::raw('DATE(o.created_at)'))
            ->pluck('checks', 'd');

        // Member yang repeat di seluruh periode (≥2 visits), dipakai untuk chart harian.
        $repeatMemberKeys = (clone $this->memberOrdersQuery($filters))
            ->selectRaw('LOWER(o.member_id) as member_key')
            ->groupBy(DB::raw('LOWER(o.member_id)'))
            ->havingRaw('COUNT(*) >= 2')
            ->pluck('member_key')
            ->map(fn ($k) => strtolower((string) $k))
            ->all();
        $repeatMemberLookup = array_fill_keys($repeatMemberKeys, true);

        $dailyMemberKeys = (clone $this->memberOrdersQuery($filters))
            ->selectRaw('DATE(o.created_at) as d')
            ->selectRaw('LOWER(o.member_id) as member_key')
            ->groupBy(DB::raw('DATE(o.created_at)'), DB::raw('LOWER(o.member_id)'))
            ->get()
            ->groupBy('d');

        $labels = [];
        $repeatRatios = [];
        $penetrations = [];
        $visitsSeries = [];
        $checksSeries = [];
        $repeatSeries = [];

        foreach (CarbonPeriod::create($dateFrom, $dateTo) as $day) {
            $key = $day->format('Y-m-d');
            $visits = (int) ($memberDaily[$key] ?? 0);
            $checks = (int) ($checkDaily[$key] ?? 0);

            $dayMembers = $dailyMemberKeys->get($key) ?? collect();
            $repeat = $dayMembers->filter(function ($row) use ($repeatMemberLookup) {
                return isset($repeatMemberLookup[strtolower((string) $row->member_key)]);
            })->count();

            $labels[] = $day->format('d M');
            $visitsSeries[] = $visits;
            $checksSeries[] = $checks;
            $repeatSeries[] = $repeat;
            // Sejalan mockup: repeat customers (yang datang hari ini & repeat di periode) / member visits hari ini
            $repeatRatios[] = $visits > 0 ? round(($repeat / $visits) * 100, 2) : 0;
            $penetrations[] = $checks > 0 ? round(($visits / $checks) * 100, 2) : 0;
        }

        return [
            'labels' => $labels,
            'repeat_visit_ratio' => $repeatRatios,
            'member_visit_penetration' => $penetrations,
            'member_visits' => $visitsSeries,
            'checks' => $checksSeries,
            'repeat_customers' => $repeatSeries,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildByOutlet(array $filters): array
    {
        $memberRows = (clone $this->memberOrdersQuery($filters))
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->select([
                'o.kode_outlet',
                DB::raw('MAX(outlet.nama_outlet) as nama_outlet'),
                DB::raw('COUNT(*) as member_visits'),
                DB::raw('COALESCE(SUM(o.grand_total), 0) as member_sales'),
            ])
            ->groupBy('o.kode_outlet')
            ->get()
            ->keyBy('kode_outlet');

        $checkRows = (clone $this->allChecksQuery($filters))
            ->select([
                'o.kode_outlet',
                DB::raw('COUNT(*) as total_checks'),
            ])
            ->groupBy('o.kode_outlet')
            ->pluck('total_checks', 'kode_outlet');

        $repeatByOutlet = (clone $this->memberOrdersQuery($filters))
            ->select([
                'o.kode_outlet',
                DB::raw('LOWER(o.member_id) as member_key'),
            ])
            ->groupBy('o.kode_outlet', DB::raw('LOWER(o.member_id)'))
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->groupBy('kode_outlet')
            ->map(fn ($g) => $g->count());

        $allCodes = collect($memberRows->keys())
            ->merge($checkRows->keys())
            ->unique()
            ->values();

        return $allCodes->map(function ($code) use ($memberRows, $checkRows, $repeatByOutlet) {
            $m = $memberRows->get($code);
            $visits = (int) ($m->member_visits ?? 0);
            $sales = (float) ($m->member_sales ?? 0);
            $checks = (int) ($checkRows[$code] ?? 0);
            $repeat = (int) ($repeatByOutlet[$code] ?? 0);

            return [
                'kode_outlet' => (string) $code,
                'outlet_name' => (string) ($m->nama_outlet ?? $code),
                'member_visits' => $visits,
                'member_sales' => $sales,
                'member_sales_formatted' => 'Rp '.number_format($sales, 0, ',', '.'),
                'total_checks' => $checks,
                'repeat_customers' => $repeat,
                'repeat_visit_ratio' => $visits > 0 ? round(($repeat / $visits) * 100, 2) : 0,
                'member_visit_penetration' => $checks > 0 ? round(($visits / $checks) * 100, 2) : 0,
            ];
        })->sortByDesc('member_visits')->values()->all();
    }

    /**
     * @return list<string>
     */
    private function resolveOutletCandidates($outlet): array
    {
        $outlet = trim((string) $outlet);
        if ($outlet === '') {
            return [];
        }

        $candidates = [$outlet];
        $matched = DB::table('tbl_data_outlet')
            ->select('qr_code', 'nama_outlet', 'id_outlet')
            ->where('qr_code', $outlet)
            ->orWhere('nama_outlet', $outlet)
            ->when(is_numeric($outlet), fn ($q) => $q->orWhere('id_outlet', (int) $outlet))
            ->first();

        if ($matched && ! empty($matched->qr_code)) {
            $candidates[] = $matched->qr_code;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function outletOptions(): array
    {
        return DB::table('tbl_data_outlet')
            ->orderBy('nama_outlet')
            ->get(['qr_code', 'nama_outlet', 'id_outlet'])
            ->map(fn ($o) => [
                'id' => (string) ($o->qr_code ?: $o->id_outlet),
                'name' => (string) $o->nama_outlet,
            ])
            ->values()
            ->all();
    }

    private function authorizeView(Request $request): void
    {
        $userId = (int) ($request->user()->id ?? 0);
        $allowed = RoleMenuService::allowedMenuCodesForInternalUser($userId);
        if (! in_array('member_visit_conversion_report', $allowed, true)) {
            abort(403, 'Anda tidak punya akses ke Member Visit Conversion Report.');
        }
    }
}
