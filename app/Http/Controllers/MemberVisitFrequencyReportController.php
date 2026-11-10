<?php

namespace App\Http\Controllers;

use App\Exports\MemberVisitFrequencyReportExport;
use App\Services\RoleMenuService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MemberVisitFrequencyReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $filters = $this->normalizeFilters($request);
        $summary = $this->buildSummary($filters);
        $byOutlet = $this->buildByOutlet($filters);
        $frequencyBuckets = $this->buildFrequencyBuckets($filters);

        return Inertia::render('Crm/MemberVisitFrequencyReport', [
            'summary' => $summary,
            'byOutlet' => $byOutlet,
            'frequencyBuckets' => $frequencyBuckets,
            'filters' => $filters,
            'outlets' => $this->outletOptions(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorizeView($request);

        $filters = $this->normalizeFilters($request);
        $rows = collect($this->buildByOutlet($filters));
        $from = $filters['date_from'] ?: 'all';
        $to = $filters['date_to'] ?: 'all';
        $filename = 'member-visit-frequency_'.$from.'_'.$to.'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new MemberVisitFrequencyReportExport($rows), $filename);
    }

    /**
     * @return array{
     *   date_from: string,
     *   date_to: string,
     *   outlet: string
     * }
     */
    private function normalizeFilters(Request $request): array
    {
        return [
            'date_from' => trim((string) $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'))),
            'date_to' => trim((string) $request->get('date_to', Carbon::now()->format('Y-m-d'))),
            'outlet' => trim((string) $request->get('outlet', '')),
        ];
    }

    /**
     * Base paid member orders query.
     */
    private function baseOrdersQuery(array $filters)
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

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(array $filters): array
    {
        $agg = (clone $this->baseOrdersQuery($filters))
            ->selectRaw('COUNT(*) as total_visits')
            ->selectRaw('COUNT(DISTINCT LOWER(o.member_id)) as unique_members')
            ->selectRaw('COALESCE(SUM(COALESCE(o.pax, 0)), 0) as total_pax')
            ->selectRaw('COALESCE(SUM(o.grand_total), 0) as revenue')
            ->first();

        $uniqueMembers = (int) ($agg->unique_members ?? 0);
        $totalVisits = (int) ($agg->total_visits ?? 0);
        $totalPax = (int) ($agg->total_pax ?? 0);
        $revenue = (float) ($agg->revenue ?? 0);

        $repeatMembers = (int) DB::connection('db_justus')
            ->query()
            ->fromSub(
                (clone $this->baseOrdersQuery($filters))
                    ->selectRaw('LOWER(o.member_id) as member_key')
                    ->groupBy(DB::raw('LOWER(o.member_id)'))
                    ->havingRaw('COUNT(*) >= 2'),
                'repeat_members'
            )
            ->count();

        // Per request: Member / Pax = total member ÷ total pax
        $memberPerPax = $totalPax > 0 ? round($uniqueMembers / $totalPax, 4) : 0;
        $visitFrequency = $uniqueMembers > 0 ? round($totalVisits / $uniqueMembers, 2) : 0;
        $repeatRatio = $uniqueMembers > 0 ? round(($repeatMembers / $uniqueMembers) * 100, 2) : 0;

        return [
            'unique_members' => $uniqueMembers,
            'total_visits' => $totalVisits,
            'total_pax' => $totalPax,
            'revenue' => $revenue,
            'revenue_formatted' => 'Rp '.number_format($revenue, 0, ',', '.'),
            'member_per_pax' => $memberPerPax,
            'member_per_pax_pct' => round($memberPerPax * 100, 2),
            'visit_frequency' => $visitFrequency,
            'repeat_members' => $repeatMembers,
            'repeat_ratio' => $repeatRatio,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildByOutlet(array $filters): array
    {
        $rows = (clone $this->baseOrdersQuery($filters))
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->select([
                'o.kode_outlet',
                DB::raw('MAX(outlet.nama_outlet) as nama_outlet'),
                DB::raw('COUNT(*) as total_visits'),
                DB::raw('COUNT(DISTINCT LOWER(o.member_id)) as unique_members'),
                DB::raw('COALESCE(SUM(COALESCE(o.pax, 0)), 0) as total_pax'),
                DB::raw('COALESCE(SUM(o.grand_total), 0) as revenue'),
            ])
            ->groupBy('o.kode_outlet')
            ->orderByDesc('total_visits')
            ->get();

        // Repeat members per outlet
        $repeatByOutlet = (clone $this->baseOrdersQuery($filters))
            ->select([
                'o.kode_outlet',
                DB::raw('LOWER(o.member_id) as member_key'),
                DB::raw('COUNT(*) as visit_count'),
            ])
            ->groupBy('o.kode_outlet', DB::raw('LOWER(o.member_id)'))
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->groupBy('kode_outlet')
            ->map(fn (Collection $g) => $g->count());

        return $rows->map(function ($row) use ($repeatByOutlet) {
            $uniqueMembers = (int) ($row->unique_members ?? 0);
            $totalVisits = (int) ($row->total_visits ?? 0);
            $totalPax = (int) ($row->total_pax ?? 0);
            $revenue = (float) ($row->revenue ?? 0);
            $repeatMembers = (int) ($repeatByOutlet[$row->kode_outlet] ?? 0);
            $memberPerPax = $totalPax > 0 ? round($uniqueMembers / $totalPax, 4) : 0;

            return [
                'kode_outlet' => (string) ($row->kode_outlet ?? '-'),
                'outlet_name' => (string) ($row->nama_outlet ?: ($row->kode_outlet ?: '-')),
                'unique_members' => $uniqueMembers,
                'total_visits' => $totalVisits,
                'total_pax' => $totalPax,
                'revenue' => $revenue,
                'revenue_formatted' => 'Rp '.number_format($revenue, 0, ',', '.'),
                'member_per_pax' => $memberPerPax,
                'member_per_pax_pct' => round($memberPerPax * 100, 2),
                'visit_frequency' => $uniqueMembers > 0 ? round($totalVisits / $uniqueMembers, 2) : 0,
                'repeat_members' => $repeatMembers,
                'repeat_ratio' => $uniqueMembers > 0 ? round(($repeatMembers / $uniqueMembers) * 100, 2) : 0,
            ];
        })->values()->all();
    }

    /**
     * Distribution of members by visit count.
     *
     * @return list<array<string, mixed>>
     */
    private function buildFrequencyBuckets(array $filters): array
    {
        $memberVisits = (clone $this->baseOrdersQuery($filters))
            ->select([
                DB::raw('LOWER(o.member_id) as member_key'),
                DB::raw('COUNT(*) as visit_count'),
            ])
            ->groupBy(DB::raw('LOWER(o.member_id)'))
            ->get();

        $buckets = [
            '1' => ['label' => '1 visit', 'members' => 0],
            '2' => ['label' => '2 visits', 'members' => 0],
            '3-5' => ['label' => '3–5 visits', 'members' => 0],
            '6-10' => ['label' => '6–10 visits', 'members' => 0],
            '11+' => ['label' => '11+ visits', 'members' => 0],
        ];

        foreach ($memberVisits as $row) {
            $c = (int) $row->visit_count;
            if ($c <= 1) {
                $buckets['1']['members']++;
            } elseif ($c === 2) {
                $buckets['2']['members']++;
            } elseif ($c <= 5) {
                $buckets['3-5']['members']++;
            } elseif ($c <= 10) {
                $buckets['6-10']['members']++;
            } else {
                $buckets['11+']['members']++;
            }
        }

        $total = max(1, $memberVisits->count());

        return collect($buckets)->map(function ($b, $key) use ($total) {
            return [
                'key' => $key,
                'label' => $b['label'],
                'members' => $b['members'],
                'pct' => round(($b['members'] / $total) * 100, 2),
            ];
        })->values()->all();
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
            ->get(['id_outlet', 'nama_outlet', 'qr_code'])
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
        if (! in_array('member_visit_frequency_report', $allowed, true)) {
            abort(403, 'Anda tidak punya akses ke Member Visit Frequency Report.');
        }
    }
}
