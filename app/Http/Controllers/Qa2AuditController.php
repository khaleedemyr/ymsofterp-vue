<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

class Qa2AuditController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;

        $search = $request->input('search');
        $status = $request->input('status');
        $outletId = $request->input('outlet_id');

        $query = DB::table('qa2_audits as a')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'a.outlet_id')
            ->leftJoin('qa2_templates as t', 't.id', '=', 'a.template_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.created_by')
            ->select([
                'a.id',
                'a.audit_number',
                'a.audit_datetime',
                'a.status',
                'a.outlet_id',
                'a.audit_time_start',
                'a.audit_time_end',
                'o.nama_outlet as outlet_name',
                't.name as template_name',
                'u.nama_lengkap as created_by_name',
            ])
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'C') as count_c")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NC') as count_nc")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NA') as count_na")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NC' and not exists (select 1 from qa2_audit_caps c where c.audit_item_id = i.id and c.action_plan is not null and c.action_plan <> '')) as count_nc_pending_cap")
            ->orderByDesc('a.id');

        if (!$isHo) {
            $query->where('a.outlet_id', (int) $user->id_outlet);
        }

        if ($outletId) {
            $query->where('a.outlet_id', (int) $outletId);
        }

        if ($status && in_array($status, ['draft', 'submitted'], true)) {
            $query->where('a.status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('a.audit_number', 'like', "%{$search}%")
                    ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                    ->orWhere('t.name', 'like', "%{$search}%")
                    ->orWhere('u.nama_lengkap', 'like', "%{$search}%")
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->from('qa2_audit_auditors as aa')
                            ->join('users as au', 'au.id', '=', 'aa.user_id')
                            ->leftJoin('tbl_data_jabatan as aj', 'aj.id_jabatan', '=', 'au.id_jabatan')
                            ->whereColumn('aa.audit_id', 'a.id')
                            ->where(function ($people) use ($search) {
                                $people->where('au.nama_lengkap', 'like', "%{$search}%")
                                    ->orWhere('aj.nama_jabatan', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->from('qa2_audit_auditees as ae')
                            ->join('users as au', 'au.id', '=', 'ae.user_id')
                            ->leftJoin('tbl_data_jabatan as aj', 'aj.id_jabatan', '=', 'au.id_jabatan')
                            ->whereColumn('ae.audit_id', 'a.id')
                            ->where(function ($people) use ($search) {
                                $people->where('au.nama_lengkap', 'like', "%{$search}%")
                                    ->orWhere('aj.nama_jabatan', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $audits = $query->paginate(15)->withQueryString();
        $this->attachAuditPeople($audits);

        $statsQuery = DB::table('qa2_audits');
        if (!$isHo) {
            $statsQuery->where('outlet_id', (int) $user->id_outlet);
        }

        $statistics = [
            'total' => (clone $statsQuery)->count(),
            'draft' => (clone $statsQuery)->where('status', 'draft')->count(),
            'submitted' => (clone $statsQuery)->where('status', 'submitted')->count(),
        ];

        $outlets = $this->allowedOutlets($isHo, (int) $user->id_outlet);

        return Inertia::render('Qa2Audits/Index', [
            'audits' => $audits,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'outlet_id' => $outletId,
            ],
            'statistics' => $statistics,
            'outlets' => $outlets,
            'permissions' => [
                'can_manage' => $isHo,
            ],
        ]);
    }

    public function reportSummary(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;

        $outletId = (int) $request->input('outlet_id', 0);
        $fromMonth = (string) $request->input('from_month', now()->format('Y-m'));
        $toMonth = (string) $request->input('to_month', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $fromMonth)) {
            $fromMonth = now()->format('Y-m');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $toMonth)) {
            $toMonth = $fromMonth;
        }

        $fromDate = Carbon::createFromFormat('Y-m', $fromMonth)->startOfMonth();
        $toDate = Carbon::createFromFormat('Y-m', $toMonth)->endOfMonth();
        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfMonth(), $fromDate->copy()->endOfMonth()];
            $fromMonth = $fromDate->format('Y-m');
            $toMonth = $toDate->format('Y-m');
        }

        $rows = $this->buildOutletSummaryRows(
            $isHo,
            (int) $user->id_outlet,
            $outletId,
            $fromDate->toDateTimeString(),
            $toDate->toDateTimeString()
        );

        return Inertia::render('Qa2Audits/SummaryReport', [
            'filters' => [
                'outlet_id' => $outletId > 0 ? (string) $outletId : '',
                'from_month' => $fromMonth,
                'to_month' => $toMonth,
            ],
            'outlets' => $this->allowedOutlets($isHo, (int) $user->id_outlet),
            'rows' => $rows,
            'permissions' => [
                'can_manage' => $isHo,
            ],
        ]);
    }

    public function exportReportSummary(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;

        $outletId = (int) $request->input('outlet_id', 0);
        $fromMonth = (string) $request->input('from_month', now()->format('Y-m'));
        $toMonth = (string) $request->input('to_month', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $fromMonth)) {
            $fromMonth = now()->format('Y-m');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $toMonth)) {
            $toMonth = $fromMonth;
        }
        $fromDate = Carbon::createFromFormat('Y-m', $fromMonth)->startOfMonth();
        $toDate = Carbon::createFromFormat('Y-m', $toMonth)->endOfMonth();
        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfMonth(), $fromDate->copy()->endOfMonth()];
            $fromMonth = $fromDate->format('Y-m');
            $toMonth = $toDate->format('Y-m');
        }

        $rows = $this->buildOutletSummaryRows(
            $isHo,
            (int) $user->id_outlet,
            $outletId,
            $fromDate->toDateTimeString(),
            $toDate->toDateTimeString()
        );

        $exportRows = collect($rows)->map(fn ($row) => [
            'Outlet' => $row['outlet_name'],
            'Jumlah Audit' => $row['audit_count'],
            'Rata-rata Audit Result (%)' => $row['avg_audit_result'],
        ]);

        $fileName = sprintf('qa2_summary_%s_to_%s.xlsx', $fromMonth, $toMonth);

        return Excel::download(new class($exportRows) implements FromCollection, WithHeadings {
            public function __construct(private \Illuminate\Support\Collection $rows) {}
            public function collection()
            {
                return $this->rows;
            }
            public function headings(): array
            {
                return ['Outlet', 'Jumlah Audit', 'Rata-rata Audit Result (%)'];
            }
        }, $fileName);
    }

    public function reportNcDetail(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;

        $outletId = (int) $request->input('outlet_id', 0);
        $fromDateRaw = (string) $request->input('from_date', now()->startOfMonth()->toDateString());
        $toDateRaw = (string) $request->input('to_date', now()->toDateString());

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDateRaw)) {
            $fromDateRaw = now()->startOfMonth()->toDateString();
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDateRaw)) {
            $toDateRaw = now()->toDateString();
        }

        $fromDate = Carbon::createFromFormat('Y-m-d', $fromDateRaw)->startOfDay();
        $toDate = Carbon::createFromFormat('Y-m-d', $toDateRaw)->endOfDay();
        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
            $fromDateRaw = $fromDate->toDateString();
            $toDateRaw = $toDate->toDateString();
        }

        $rows = $this->buildNcDetailRows(
            $isHo,
            (int) $user->id_outlet,
            $outletId,
            $fromDate->toDateTimeString(),
            $toDate->toDateTimeString()
        );

        return Inertia::render('Qa2Audits/NcDetailReport', [
            'filters' => [
                'outlet_id' => $outletId > 0 ? (string) $outletId : '',
                'from_date' => $fromDateRaw,
                'to_date' => $toDateRaw,
            ],
            'outlets' => $this->allowedOutlets($isHo, (int) $user->id_outlet),
            'rows' => $rows,
            'permissions' => [
                'can_manage' => $isHo,
            ],
        ]);
    }

    public function exportReportNcDetail(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;

        $outletId = (int) $request->input('outlet_id', 0);
        $fromDateRaw = (string) $request->input('from_date', now()->startOfMonth()->toDateString());
        $toDateRaw = (string) $request->input('to_date', now()->toDateString());

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDateRaw)) {
            $fromDateRaw = now()->startOfMonth()->toDateString();
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDateRaw)) {
            $toDateRaw = now()->toDateString();
        }

        $fromDate = Carbon::createFromFormat('Y-m-d', $fromDateRaw)->startOfDay();
        $toDate = Carbon::createFromFormat('Y-m-d', $toDateRaw)->endOfDay();
        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
            $fromDateRaw = $fromDate->toDateString();
            $toDateRaw = $toDate->toDateString();
        }

        $rows = collect($this->buildNcDetailRows(
            $isHo,
            (int) $user->id_outlet,
            $outletId,
            $fromDate->toDateTimeString(),
            $toDate->toDateTimeString()
        ))->map(fn ($row) => [
            'Tanggal Audit' => $row['audit_datetime'],
            'Nomor Audit' => $row['audit_number'],
            'Outlet' => $row['outlet_name'],
            'Template' => $row['template_name'],
            'Auditor' => $row['auditors'],
            'Auditee' => $row['auditees'],
            'Kategori' => $row['category_name'],
            'Sub Kategori' => $row['subcategory_name'],
            'Parameter Code' => $row['parameter_code'],
            'Parameter' => $row['parameter_text'],
            'Result' => $row['result'],
            'Komentar' => $row['comment'],
            'Due Date' => $row['due_date'],
            'Attachment Item' => $row['item_attachments'],
            'Action Plan CAP' => $row['cap_action_plan'],
            'Target Date CAP' => $row['cap_target_date'],
            'Status CAP' => $row['cap_status'],
            'Attachment CAP' => $row['cap_attachments'],
        ]);

        $fileName = sprintf('qa2_nc_detail_%s_to_%s.xlsx', $fromDateRaw, $toDateRaw);

        return Excel::download(new class($rows) implements FromCollection, WithHeadings {
            public function __construct(private \Illuminate\Support\Collection $rows) {}
            public function collection()
            {
                return $this->rows;
            }
            public function headings(): array
            {
                return [
                    'Tanggal Audit',
                    'Nomor Audit',
                    'Outlet',
                    'Template',
                    'Auditor',
                    'Auditee',
                    'Kategori',
                    'Sub Kategori',
                    'Parameter Code',
                    'Parameter',
                    'Result',
                    'Komentar',
                    'Due Date',
                    'Attachment Item',
                    'Action Plan CAP',
                    'Target Date CAP',
                    'Status CAP',
                    'Attachment CAP',
                ];
            }
        }, $fileName);
    }

    public function reportNcDashboard(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;
        $userOutletId = (int) ($user->id_outlet ?? 0);
        $outletId = (int) $request->input('outlet_id', 0);

        [$fromDateRaw, $toDateRaw, $fromDateTime, $toDateTime] = $this->normalizeDateRangeForDashboard(
            (string) $request->input('from_date', now()->startOfMonth()->toDateString()),
            (string) $request->input('to_date', now()->toDateString())
        );

        $monthlyOutletRows = $this->buildNcMonthlyOutletAggregates(
            $isHo,
            $userOutletId,
            $outletId,
            $fromDateTime,
            $toDateTime
        );

        $categoryBreakdown = $this->buildNcCategoryBreakdown(
            $isHo,
            $userOutletId,
            $outletId,
            $fromDateTime,
            $toDateTime
        );
        $subcategoryBreakdown = $this->buildNcSubcategoryBreakdown(
            $isHo,
            $userOutletId,
            $outletId,
            $fromDateTime,
            $toDateTime
        );

        $monthlyTrend = $monthlyOutletRows
            ->groupBy('month_key')
            ->map(fn ($rows, $monthKey) => [
                'month_key' => (string) $monthKey,
                'nc_count' => (int) $rows->sum('nc_count'),
                'audit_count' => (int) $rows->sum('audit_count'),
                'nc_per_audit' => $rows->sum('audit_count') > 0
                    ? round($rows->sum('nc_count') / $rows->sum('audit_count'), 2)
                    : 0,
            ])
            ->sortBy('month_key')
            ->values();

        $outletComposition = $monthlyOutletRows
            ->groupBy('outlet_id')
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'outlet_id' => (int) ($first['outlet_id'] ?? 0),
                    'outlet_name' => (string) ($first['outlet_name'] ?? '-'),
                    'nc_count' => (int) $rows->sum('nc_count'),
                ];
            })
            ->sortByDesc('nc_count')
            ->values()
            ->all();

        $totalNc = (int) $monthlyOutletRows->sum('nc_count');
        $totalAudit = (int) $monthlyOutletRows->sum('audit_count');
        $avgNcPerAudit = $totalAudit > 0 ? round($totalNc / $totalAudit, 2) : 0;

        $months = $monthlyTrend->pluck('month_key')->values();
        $latestMonth = $months->last();
        $previousMonth = $months->count() > 1 ? $months->get($months->count() - 2) : null;
        $latestNc = $latestMonth ? (int) ($monthlyTrend->firstWhere('month_key', $latestMonth)['nc_count'] ?? 0) : 0;
        $previousNc = $previousMonth ? (int) ($monthlyTrend->firstWhere('month_key', $previousMonth)['nc_count'] ?? 0) : 0;
        $momDelta = $latestNc - $previousNc;
        $momDeltaPct = $previousNc > 0 ? round(($momDelta / $previousNc) * 100, 2) : ($latestNc > 0 ? 100.0 : 0.0);

        $highestOutlet = $outletComposition[0] ?? null;

        $topOutletIds = collect($outletComposition)->take(5)->pluck('outlet_id')->all();
        $outletTrendSeries = collect($topOutletIds)->map(function ($oid) use ($monthlyOutletRows, $months) {
            $rows = $monthlyOutletRows->where('outlet_id', $oid);
            $name = (string) ($rows->first()['outlet_name'] ?? '-');
            $seriesData = $months->map(function ($month) use ($rows) {
                $matched = $rows->firstWhere('month_key', $month);
                return (int) ($matched['nc_count'] ?? 0);
            })->values()->all();
            $seriesPerAudit = $months->map(function ($month) use ($rows) {
                $matched = $rows->firstWhere('month_key', $month);
                $nc = (int) ($matched['nc_count'] ?? 0);
                $audits = (int) ($matched['audit_count'] ?? 0);
                return $audits > 0 ? round($nc / $audits, 2) : 0;
            })->values()->all();
            return [
                'outlet_id' => (int) $oid,
                'outlet_name' => $name,
                'data' => $seriesData,
                'data_per_audit' => $seriesPerAudit,
            ];
        })->values()->all();

        $movementRows = $monthlyOutletRows
            ->groupBy('outlet_id')
            ->flatMap(function ($rowsByOutlet) {
                $sorted = $rowsByOutlet->sortBy('month_key')->values();
                $prevNc = null;
                return $sorted->map(function ($row) use (&$prevNc) {
                    $nc = (int) ($row['nc_count'] ?? 0);
                    $prev = $prevNc;
                    $delta = $prev === null ? $nc : ($nc - $prev);
                    $deltaPct = $prev && $prev > 0 ? round(($delta / $prev) * 100, 2) : ($prev === 0 && $nc > 0 ? 100.0 : 0.0);
                    $trend = $delta > 0 ? 'naik' : ($delta < 0 ? 'turun' : 'stagnan');
                    $prevNc = $nc;

                    return [
                        'month_key' => (string) ($row['month_key'] ?? ''),
                        'outlet_id' => (int) ($row['outlet_id'] ?? 0),
                        'outlet_name' => (string) ($row['outlet_name'] ?? '-'),
                        'nc_count' => $nc,
                        'audit_count' => (int) ($row['audit_count'] ?? 0),
                        'prev_nc_count' => (int) ($prev ?? 0),
                        'delta' => (int) $delta,
                        'delta_pct' => (float) $deltaPct,
                        'trend' => $trend,
                    ];
                });
            })
            ->sortByDesc(fn ($row) => $row['month_key'] . '|' . str_pad((string) $row['nc_count'], 10, '0', STR_PAD_LEFT))
            ->values()
            ->all();

        $detailRows = $this->buildNcDetailRows(
            $isHo,
            $userOutletId,
            $outletId,
            $fromDateTime,
            $toDateTime
        );

        return Inertia::render('Qa2Audits/NcDashboard', [
            'filters' => [
                'outlet_id' => $outletId > 0 ? (string) $outletId : '',
                'from_date' => $fromDateRaw,
                'to_date' => $toDateRaw,
            ],
            'outlets' => $this->allowedOutlets($isHo, $userOutletId),
            'kpis' => [
                'total_nc' => $totalNc,
                'total_audits' => $totalAudit,
                'avg_nc_per_audit' => $avgNcPerAudit,
                'mom_delta' => $momDelta,
                'mom_delta_pct' => $momDeltaPct,
                'latest_month' => $latestMonth,
                'previous_month' => $previousMonth,
                'highest_outlet_name' => (string) ($highestOutlet['outlet_name'] ?? '-'),
                'highest_outlet_nc' => (int) ($highestOutlet['nc_count'] ?? 0),
            ],
            'trend_months' => $months->all(),
            'monthly_trend' => $monthlyTrend->values()->all(),
            'outlet_trend_series' => $outletTrendSeries,
            'category_breakdown' => $categoryBreakdown,
            'subcategory_breakdown' => $subcategoryBreakdown,
            'outlet_composition' => $outletComposition,
            'movement_rows' => $movementRows,
            'detail_rows' => $detailRows,
            'permissions' => [
                'can_manage' => $isHo,
            ],
        ]);
    }

    public function exportReportNcDashboard(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;
        $userOutletId = (int) ($user->id_outlet ?? 0);
        $outletId = (int) $request->input('outlet_id', 0);

        [$fromDateRaw, $toDateRaw, $fromDateTime, $toDateTime] = $this->normalizeDateRangeForDashboard(
            (string) $request->input('from_date', now()->startOfMonth()->toDateString()),
            (string) $request->input('to_date', now()->toDateString())
        );

        $monthlyOutletRows = $this->buildNcMonthlyOutletAggregates(
            $isHo,
            $userOutletId,
            $outletId,
            $fromDateTime,
            $toDateTime
        );

        $movementRows = $monthlyOutletRows
            ->groupBy('outlet_id')
            ->flatMap(function ($rowsByOutlet) {
                $sorted = $rowsByOutlet->sortBy('month_key')->values();
                $prevNc = null;
                return $sorted->map(function ($row) use (&$prevNc) {
                    $nc = (int) ($row['nc_count'] ?? 0);
                    $prev = $prevNc;
                    $delta = $prev === null ? $nc : ($nc - $prev);
                    $deltaPct = $prev && $prev > 0 ? round(($delta / $prev) * 100, 2) : ($prev === 0 && $nc > 0 ? 100.0 : 0.0);
                    $trend = $delta > 0 ? 'naik' : ($delta < 0 ? 'turun' : 'stagnan');
                    $prevNc = $nc;
                    return [
                        'Bulan' => (string) ($row['month_key'] ?? ''),
                        'Outlet' => (string) ($row['outlet_name'] ?? '-'),
                        'Jumlah NC' => $nc,
                        'Jumlah Audit' => (int) ($row['audit_count'] ?? 0),
                        'NC Bulan Sebelumnya' => (int) ($prev ?? 0),
                        'Delta NC' => (int) $delta,
                        'Delta (%)' => (float) $deltaPct,
                        'Trend' => $trend,
                    ];
                });
            })
            ->values();

        $fileName = sprintf('qa2_nc_dashboard_%s_to_%s.xlsx', $fromDateRaw, $toDateRaw);

        return Excel::download(new class($movementRows) implements FromCollection, WithHeadings {
            public function __construct(private \Illuminate\Support\Collection $rows) {}
            public function collection()
            {
                return $this->rows;
            }
            public function headings(): array
            {
                return ['Bulan', 'Outlet', 'Jumlah NC', 'Jumlah Audit', 'NC Bulan Sebelumnya', 'Delta NC', 'Delta (%)', 'Trend'];
            }
        }, $fileName);
    }

    public function exportReportNcDashboardDetail(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;
        $userOutletId = (int) ($user->id_outlet ?? 0);
        $outletId = (int) $request->input('outlet_id', 0);

        [$fromDateRaw, $toDateRaw, $fromDateTime, $toDateTime] = $this->normalizeDateRangeForDashboard(
            (string) $request->input('from_date', now()->startOfMonth()->toDateString()),
            (string) $request->input('to_date', now()->toDateString())
        );

        $rows = collect($this->buildNcDetailRows(
            $isHo,
            $userOutletId,
            $outletId,
            $fromDateTime,
            $toDateTime
        ))->map(fn ($row) => [
            'Bulan' => $row['month_key'] ?? '',
            'Tanggal Audit' => $row['audit_datetime'],
            'Nomor Audit' => $row['audit_number'],
            'Outlet' => $row['outlet_name'],
            'Template' => $row['template_name'],
            'Auditor' => $row['auditors'],
            'Auditee' => $row['auditees'],
            'Kategori' => $row['category_name'],
            'Sub Kategori' => $row['subcategory_name'],
            'Parameter Code' => $row['parameter_code'],
            'Parameter' => $row['parameter_text'],
            'Komentar' => $row['comment'],
            'Due Date' => $row['due_date'],
            'Action Plan CAP' => $row['cap_action_plan'],
            'Target Date CAP' => $row['cap_target_date'],
            'Status CAP' => $row['cap_status'],
        ]);

        $fileName = sprintf('qa2_nc_dashboard_detail_%s_to_%s.xlsx', $fromDateRaw, $toDateRaw);

        return Excel::download(new class($rows) implements FromCollection, WithHeadings {
            public function __construct(private \Illuminate\Support\Collection $rows) {}
            public function collection()
            {
                return $this->rows;
            }
            public function headings(): array
            {
                return [
                    'Bulan',
                    'Tanggal Audit',
                    'Nomor Audit',
                    'Outlet',
                    'Template',
                    'Auditor',
                    'Auditee',
                    'Kategori',
                    'Sub Kategori',
                    'Parameter Code',
                    'Parameter',
                    'Komentar',
                    'Due Date',
                    'Action Plan CAP',
                    'Target Date CAP',
                    'Status CAP',
                ];
            }
        }, $fileName);
    }

    private function buildOutletSummaryRows(
        bool $isHo,
        int $userOutletId,
        int $outletId,
        string $fromDateTime,
        string $toDateTime
    ): array {
        $query = DB::table('qa2_audits as a')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'a.outlet_id')
            ->leftJoin('qa2_templates as t', 't.id', '=', 'a.template_id')
            ->where('a.status', 'submitted')
            ->whereBetween('a.audit_datetime', [$fromDateTime, $toDateTime])
            ->select([
                'a.id',
                'a.outlet_id',
                'a.template_id',
                'o.nama_outlet as outlet_name',
                't.name as template_name',
            ])
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'C') as count_c")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NC') as count_nc");

        if (!$isHo) {
            $query->where('a.outlet_id', $userOutletId);
        } elseif ($outletId > 0) {
            $query->where('a.outlet_id', $outletId);
        }

        $rows = $query->get()
            ->groupBy('outlet_id')
            ->map(function ($group) {
                $avgScore = $group->avg(function ($r) {
                    $c = (float) ($r->count_c ?? 0);
                    $nc = (float) ($r->count_nc ?? 0);
                    $den = $c + $nc;
                    return $den > 0 ? ($c / $den) * 100 : 0;
                });

                $first = $group->first();
                $perTemplate = $group
                    ->groupBy('template_id')
                    ->map(function ($templateRows) {
                        $avgTemplateScore = $templateRows->avg(function ($r) {
                            $c = (float) ($r->count_c ?? 0);
                            $nc = (float) ($r->count_nc ?? 0);
                            $den = $c + $nc;
                            return $den > 0 ? ($c / $den) * 100 : 0;
                        });

                        $templateFirst = $templateRows->first();
                        return [
                            'template_id' => (int) ($templateFirst->template_id ?? 0),
                            'template_name' => (string) ($templateFirst->template_name ?? '-'),
                            'audit_count' => $templateRows->count(),
                            'avg_audit_result' => round((float) ($avgTemplateScore ?? 0), 2),
                        ];
                    })
                    ->sortBy('template_name')
                    ->values()
                    ->all();

                return [
                    'outlet_id' => (int) ($first->outlet_id ?? 0),
                    'outlet_name' => (string) ($first->outlet_name ?? '-'),
                    'audit_count' => $group->count(),
                    'avg_audit_result' => round((float) ($avgScore ?? 0), 2),
                    'templates' => $perTemplate,
                ];
            })
            ->sortBy('outlet_name')
            ->values()
            ->all();

        return $rows;
    }

    private function normalizeDateRangeForDashboard(string $fromDateRaw, string $toDateRaw): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDateRaw)) {
            $fromDateRaw = now()->startOfMonth()->toDateString();
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDateRaw)) {
            $toDateRaw = now()->toDateString();
        }

        $fromDate = Carbon::createFromFormat('Y-m-d', $fromDateRaw)->startOfDay();
        $toDate = Carbon::createFromFormat('Y-m-d', $toDateRaw)->endOfDay();

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
            $fromDateRaw = $fromDate->toDateString();
            $toDateRaw = $toDate->toDateString();
        }

        return [$fromDateRaw, $toDateRaw, $fromDate->toDateTimeString(), $toDate->toDateTimeString()];
    }

    private function buildNcMonthlyOutletAggregates(
        bool $isHo,
        int $userOutletId,
        int $outletId,
        string $fromDateTime,
        string $toDateTime
    ): \Illuminate\Support\Collection {
        $query = DB::table('qa2_audits as a')
            ->join('qa2_audit_items as i', 'i.audit_id', '=', 'a.id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'a.outlet_id')
            ->where('a.status', 'submitted')
            ->where('i.result', 'NC')
            ->whereBetween('a.audit_datetime', [$fromDateTime, $toDateTime])
            ->selectRaw("DATE_FORMAT(a.audit_datetime, '%Y-%m') as month_key")
            ->selectRaw('a.outlet_id as outlet_id')
            ->selectRaw('COALESCE(o.nama_outlet, "-") as outlet_name')
            ->selectRaw('COUNT(i.id) as nc_count')
            ->selectRaw('COUNT(DISTINCT a.id) as audit_count')
            ->groupBy('month_key', 'a.outlet_id', 'o.nama_outlet');

        if (! $isHo) {
            $query->where('a.outlet_id', $userOutletId);
        } elseif ($outletId > 0) {
            $query->where('a.outlet_id', $outletId);
        }

        return $query->get()
            ->map(fn ($row) => [
                'month_key' => (string) ($row->month_key ?? ''),
                'outlet_id' => (int) ($row->outlet_id ?? 0),
                'outlet_name' => (string) ($row->outlet_name ?? '-'),
                'nc_count' => (int) ($row->nc_count ?? 0),
                'audit_count' => (int) ($row->audit_count ?? 0),
            ])
            ->sortBy(fn ($row) => $row['month_key'] . '|' . str_pad((string) $row['outlet_id'], 10, '0', STR_PAD_LEFT))
            ->values();
    }

    private function buildNcCategoryBreakdown(
        bool $isHo,
        int $userOutletId,
        int $outletId,
        string $fromDateTime,
        string $toDateTime
    ): array {
        $query = DB::table('qa2_audits as a')
            ->join('qa2_audit_items as i', 'i.audit_id', '=', 'a.id')
            ->leftJoin('qa2_categories as c', 'c.id', '=', 'i.category_id')
            ->where('a.status', 'submitted')
            ->where('i.result', 'NC')
            ->whereBetween('a.audit_datetime', [$fromDateTime, $toDateTime])
            ->selectRaw('COALESCE(c.name, "Tanpa Kategori") as label')
            ->selectRaw('COUNT(i.id) as value')
            ->groupBy('label')
            ->orderByDesc('value');

        if (! $isHo) {
            $query->where('a.outlet_id', $userOutletId);
        } elseif ($outletId > 0) {
            $query->where('a.outlet_id', $outletId);
        }

        return $query->get()
            ->map(fn ($row) => ['label' => (string) ($row->label ?? '-'), 'value' => (int) ($row->value ?? 0)])
            ->values()
            ->all();
    }

    private function buildNcSubcategoryBreakdown(
        bool $isHo,
        int $userOutletId,
        int $outletId,
        string $fromDateTime,
        string $toDateTime
    ): array {
        $query = DB::table('qa2_audits as a')
            ->join('qa2_audit_items as i', 'i.audit_id', '=', 'a.id')
            ->leftJoin('qa2_subcategories as s', 's.id', '=', 'i.subcategory_id')
            ->where('a.status', 'submitted')
            ->where('i.result', 'NC')
            ->whereBetween('a.audit_datetime', [$fromDateTime, $toDateTime])
            ->selectRaw('COALESCE(s.name, "Tanpa Sub Kategori") as label')
            ->selectRaw('COUNT(i.id) as value')
            ->groupBy('label')
            ->orderByDesc('value')
            ->limit(15);

        if (! $isHo) {
            $query->where('a.outlet_id', $userOutletId);
        } elseif ($outletId > 0) {
            $query->where('a.outlet_id', $outletId);
        }

        return $query->get()
            ->map(fn ($row) => ['label' => (string) ($row->label ?? '-'), 'value' => (int) ($row->value ?? 0)])
            ->values()
            ->all();
    }

    private function buildNcDetailRows(
        bool $isHo,
        int $userOutletId,
        int $outletId,
        string $fromDateTime,
        string $toDateTime
    ): array {
        $query = DB::table('qa2_audit_items as i')
            ->join('qa2_audits as a', 'a.id', '=', 'i.audit_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'a.outlet_id')
            ->leftJoin('qa2_templates as t', 't.id', '=', 'a.template_id')
            ->leftJoin('qa2_categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('qa2_subcategories as s', 's.id', '=', 'i.subcategory_id')
            ->leftJoin('qa2_audit_caps as cap', 'cap.audit_item_id', '=', 'i.id')
            ->where('a.status', 'submitted')
            ->where('i.result', 'NC')
            ->whereBetween('a.audit_datetime', [$fromDateTime, $toDateTime])
            ->select([
                'a.id as audit_id',
                'a.audit_number',
                'a.audit_datetime',
                'a.outlet_id',
                'i.id as audit_item_id',
                'cap.id as cap_id',
                'o.nama_outlet as outlet_name',
                't.name as template_name',
                'c.name as category_name',
                's.name as subcategory_name',
                'i.parameter_code',
                'i.parameter_text',
                'i.result',
                'i.comment',
                'i.due_date',
                'cap.action_plan as cap_action_plan',
                'cap.target_date as cap_target_date',
                'cap.status as cap_status',
            ])
            ->selectRaw("(select group_concat(distinct u.nama_lengkap order by u.nama_lengkap separator ', ') from qa2_audit_auditors aa join users u on u.id = aa.user_id where aa.audit_id = a.id) as auditors")
            ->selectRaw("(select group_concat(distinct u.nama_lengkap order by u.nama_lengkap separator ', ') from qa2_audit_auditees ad join users u on u.id = ad.user_id where ad.audit_id = a.id) as auditees")
            ->orderByDesc('a.audit_datetime')
            ->orderBy('o.nama_outlet')
            ->orderBy('c.name')
            ->orderBy('s.name');

        if (! $isHo) {
            $query->where('a.outlet_id', $userOutletId);
        } elseif ($outletId > 0) {
            $query->where('a.outlet_id', $outletId);
        }

        $rows = $query->get();

        $itemIds = $rows->pluck('audit_item_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $capIds = $rows->pluck('cap_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $itemMediaMap = DB::table('qa2_audit_item_media')
            ->whereIn('audit_item_id', $itemIds->all())
            ->orderBy('id')
            ->get(['id', 'audit_item_id', 'media_type', 'file_path'])
            ->groupBy('audit_item_id');

        $capMediaMap = DB::table('qa2_audit_cap_media')
            ->whereIn('cap_id', $capIds->all())
            ->orderBy('id')
            ->get(['id', 'cap_id', 'media_type', 'file_path'])
            ->groupBy('cap_id');

        return $rows
            ->map(function ($row) use ($itemMediaMap, $capMediaMap) {
                $itemMedia = collect($itemMediaMap->get((int) ($row->audit_item_id ?? 0), collect()))
                    ->map(function ($media) {
                        $path = (string) ($media->file_path ?? '');
                        return [
                            'id' => (int) ($media->id ?? 0),
                            'media_type' => (string) ($media->media_type ?? ''),
                            'file_path' => $path,
                            'url' => $path !== '' ? Storage::url($path) : null,
                        ];
                    })
                    ->values();

                $capMedia = collect($capMediaMap->get((int) ($row->cap_id ?? 0), collect()))
                    ->map(function ($media) {
                        $path = (string) ($media->file_path ?? '');
                        return [
                            'id' => (int) ($media->id ?? 0),
                            'media_type' => (string) ($media->media_type ?? ''),
                            'file_path' => $path,
                            'url' => $path !== '' ? Storage::url($path) : null,
                        ];
                    })
                    ->values();

                $itemAttachments = $itemMedia
                    ->map(fn ($m) => trim(($m['media_type'] ?? '') . ': ' . ($m['file_path'] ?? '')))
                    ->filter()
                    ->implode("\n");

                $capAttachments = $capMedia
                    ->map(fn ($m) => trim(($m['media_type'] ?? '') . ': ' . ($m['file_path'] ?? '')))
                    ->filter()
                    ->implode("\n");

                return [
                    'audit_id' => (int) ($row->audit_id ?? 0),
                    'audit_number' => (string) ($row->audit_number ?? '-'),
                    'audit_datetime' => (string) ($row->audit_datetime ?? '-'),
                    'month_key' => (string) Carbon::parse((string) ($row->audit_datetime ?? now()))->format('Y-m'),
                    'outlet_id' => (int) ($row->outlet_id ?? 0),
                    'outlet_name' => (string) ($row->outlet_name ?? '-'),
                    'template_name' => (string) ($row->template_name ?? '-'),
                    'auditors' => (string) ($row->auditors ?? '-'),
                    'auditees' => (string) ($row->auditees ?? '-'),
                    'category_name' => (string) ($row->category_name ?? '-'),
                    'subcategory_name' => (string) ($row->subcategory_name ?? '-'),
                    'parameter_code' => (string) ($row->parameter_code ?? '-'),
                    'parameter_text' => (string) ($row->parameter_text ?? '-'),
                    'result' => (string) ($row->result ?? '-'),
                    'comment' => (string) ($row->comment ?? ''),
                    'due_date' => (string) ($row->due_date ?? ''),
                    'item_media' => $itemMedia->all(),
                    'item_attachments' => $itemAttachments,
                    'cap_action_plan' => (string) ($row->cap_action_plan ?? ''),
                    'cap_target_date' => (string) ($row->cap_target_date ?? ''),
                    'cap_status' => (string) ($row->cap_status ?? ''),
                    'cap_media' => $capMedia->all(),
                    'cap_attachments' => $capAttachments,
                ];
            })
            ->values()
            ->all();
    }

    public function create()
    {
        $this->ensureHo();

        $user = auth()->user();

        $templates = DB::table('qa2_templates')
            ->where('status', 'A')
            ->whereExists(function ($q) {
                $q->from('qa2_template_items as ti')
                    ->selectRaw('1')
                    ->whereColumn('ti.template_id', 'qa2_templates.id');
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Qa2Audits/Form', [
            'mode' => 'create',
            'audit' => null,
            'outlets' => $this->allowedOutlets(true, (int) $user->id_outlet),
            'users' => $this->usersForSelector(),
            'templates' => $templates,
            'tree' => [],
            'permissions' => [
                'can_manage' => true,
                'can_fill_cap' => false,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureHo();

        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'template_id' => 'required|exists:qa2_templates,id',
            'auditor_ids' => 'nullable|array',
            'auditor_ids.*' => 'integer|exists:users,id',
            'auditee_ids' => 'nullable|array',
            'auditee_ids.*' => 'integer|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $user = auth()->user();

        // Tarik dulu data template dan pastikan ada item sebelum membuat draft audit.
        $templateRows = $this->getTemplateSeedRows((int) $validated['template_id']);
        if (empty($templateRows)) {
            throw ValidationException::withMessages([
                'template_id' => 'Template tidak memiliki parameter audit. Cek QA2 Template Items.',
            ]);
        }

        $auditId = DB::transaction(function () use ($validated, $user, $templateRows) {
            $auditId = DB::table('qa2_audits')->insertGetId([
                'audit_number' => $this->generateAuditNumber(),
                'audit_datetime' => now(),
                'outlet_id' => (int) $validated['outlet_id'],
                'template_id' => (int) $validated['template_id'],
                'created_by' => (int) $user->id,
                'audit_time_start' => now(),
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncPeople($auditId, $validated['auditor_ids'] ?? [], $validated['auditee_ids'] ?? []);
            $this->seedAuditItemsFromTemplateRows($auditId, $templateRows);

            return $auditId;
        });

        return redirect()->route('qa2-audits.edit', $auditId)->with('success', 'QA Audit draft berhasil dibuat.');
    }

    public function edit(int $id)
    {
        $audit = $this->getAuditRow($id);

        abort_if(!$audit, 404);

        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;
        if (!$isHo && (int) $audit->outlet_id !== (int) $user->id_outlet) {
            abort(403);
        }

        $this->ensureAuditItems((int) $id, (int) $audit->template_id);

        $canFillCap = DB::table('qa2_audit_auditees')
            ->where('audit_id', $id)
            ->where('user_id', (int) $user->id)
            ->exists();

        $canManage = $isHo && $audit->status === 'draft';

        return Inertia::render('Qa2Audits/Form', [
            'mode' => 'edit',
            'audit' => $this->auditPayload($id, !$canManage),
            'outlets' => $this->allowedOutlets($isHo, (int) $user->id_outlet),
            'users' => $this->usersForSelector(),
            'templates' => DB::table('qa2_templates')
                ->where('status', 'A')
                ->whereExists(function ($q) {
                    $q->from('qa2_template_items as ti')
                        ->selectRaw('1')
                        ->whereColumn('ti.template_id', 'qa2_templates.id');
                })
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'tree' => $this->auditTree($id),
            'permissions' => [
                'can_manage' => $canManage,
                'can_fill_cap' => $canFillCap && $audit->status === 'submitted',
                'can_edit_cap' => $canFillCap && $audit->status === 'submitted' && $this->capSubmissionEditable($audit),
                'can_submit_cap' => $canFillCap && $audit->status === 'submitted' && $this->capSubmissionEditable($audit),
            ],
        ]);
    }

    /**
     * Landing page publik — tanpa login, via share token.
     */
    public function publicShow(string $token)
    {
        $auditId = (int) DB::table('qa2_audits')
            ->where('share_token', $token)
            ->value('id');

        abort_if($auditId <= 0, 404);

        return Inertia::render('Qa2Audits/PublicShow', [
            'audit' => $this->auditPayload($auditId, false),
        ]);
    }

    /**
     * Generate / kembalikan link share untuk WhatsApp.
     */
    public function generateShareLink(Request $request, int $id)
    {
        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);

        $user = $request->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;
        if (!$isHo && (int) $audit->outlet_id !== (int) $user->id_outlet) {
            abort(403);
        }

        $shareToken = (string) ($audit->share_token ?? '');
        if ($shareToken === '') {
            do {
                $shareToken = Str::random(48);
                $exists = DB::table('qa2_audits')->where('share_token', $shareToken)->exists();
            } while ($exists);

            DB::table('qa2_audits')
                ->where('id', $id)
                ->update([
                    'share_token' => $shareToken,
                    'updated_at' => now(),
                ]);
        }

        $url = route('qa2-audits.public.show', $shareToken);

        return response()->json([
            'success' => true,
            'url' => $url,
            'message' => $this->buildAuditShareMessage((object) $audit, $url),
        ]);
    }

    public function saveDraft(Request $request, int $id)
    {
        $this->ensureHo();

        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);
        abort_if($audit->status !== 'draft', 422, 'Draft sudah disubmit.');

        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'auditor_ids' => 'nullable|array',
            'auditor_ids.*' => 'integer|exists:users,id',
            'auditee_ids' => 'nullable|array',
            'auditee_ids.*' => 'integer|exists:users,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:qa2_audit_items,id',
            'items.*.result' => 'nullable|in:C,NC,NA',
            'items.*.comment' => 'nullable|string',
            'items.*.due_date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($id, $validated) {
            DB::table('qa2_audits')->where('id', $id)->update([
                'outlet_id' => (int) $validated['outlet_id'],
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);

            $this->syncPeople($id, $validated['auditor_ids'] ?? [], $validated['auditee_ids'] ?? []);

            foreach ($validated['items'] as $item) {
                DB::table('qa2_audit_items')
                    ->where('id', (int) $item['id'])
                    ->where('audit_id', $id)
                    ->update([
                        'result' => $item['result'] ?? null,
                        'comment' => $item['comment'] ?? null,
                        'due_date' => $item['due_date'] ?? null,
                        'updated_at' => now(),
                    ]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function submit(int $id)
    {
        $this->ensureHo();

        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);
        abort_if($audit->status !== 'draft', 422, 'Audit sudah disubmit.');

        $missing = DB::table('qa2_audit_items')
            ->where('audit_id', $id)
            ->whereNull('result')
            ->count();

        if ($missing > 0) {
            return back()->withErrors(['submit' => 'Semua parameter harus diisi C/NC/NA sebelum submit.']);
        }

        DB::table('qa2_audits')->where('id', $id)->update([
            'status' => 'submitted',
            'audit_time_end' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('qa2-audits.index')->with('success', 'QA Audit berhasil disubmit.');
    }

    public function uploadItemMedia(Request $request, int $id, int $itemId)
    {
        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);
        abort_if($audit->status !== 'draft', 422, 'Upload media hanya untuk draft.');
        $this->ensureHo();

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:30720|mimes:jpg,jpeg,png,webp,mp4,mov,avi,webm',
        ]);

        $exists = DB::table('qa2_audit_items')->where('id', $itemId)->where('audit_id', $id)->exists();
        abort_if(!$exists, 404);

        $userId = (int) auth()->id();
        $inserted = [];

        foreach ($request->file('files', []) as $file) {
            $path = $file->store('qa2-audits/items', 'public');
            $mime = (string) $file->getMimeType();
            $mediaType = Str::startsWith($mime, 'video/') ? 'video' : 'photo';

            $mediaId = DB::table('qa2_audit_item_media')->insertGetId([
                'audit_item_id' => $itemId,
                'uploaded_by' => $userId,
                'media_type' => $mediaType,
                'file_path' => $path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted[] = [
                'id' => $mediaId,
                'media_type' => $mediaType,
                'url' => Storage::url($path),
            ];
        }

        return response()->json(['success' => true, 'items' => $inserted]);
    }

    public function deleteItemMedia(int $id, int $itemId, int $mediaId)
    {
        $this->ensureHo();

        $row = DB::table('qa2_audit_item_media as m')
            ->join('qa2_audit_items as i', 'i.id', '=', 'm.audit_item_id')
            ->join('qa2_audits as a', 'a.id', '=', 'i.audit_id')
            ->where('a.id', $id)
            ->where('i.id', $itemId)
            ->where('m.id', $mediaId)
            ->select(['m.id', 'm.file_path'])
            ->first();

        abort_if(!$row, 404);

        DB::table('qa2_audit_item_media')->where('id', $mediaId)->delete();
        Storage::disk('public')->delete($row->file_path);

        return response()->json(['success' => true]);
    }

    public function saveCap(Request $request, int $id)
    {
        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);
        abort_if($audit->status !== 'submitted', 422, 'CAP hanya untuk audit submitted.');

        $user = auth()->user();
        $isAuditee = DB::table('qa2_audit_auditees')
            ->where('audit_id', $id)
            ->where('user_id', (int) $user->id)
            ->exists();

        abort_if(!$isAuditee, 403);
        abort_if(!$this->capSubmissionEditable($audit), 422, 'CAP tidak dapat diubah saat proses approval.');

        $validated = $request->validate([
            'caps' => 'required|array|min:1',
            'caps.*.audit_item_id' => 'required|integer|exists:qa2_audit_items,id',
            'caps.*.action_plan' => 'nullable|string',
            'caps.*.target_date' => 'nullable|date',
            'caps.*.status' => 'nullable|in:open,progress,done',
        ]);

        $savedCaps = [];

        DB::transaction(function () use ($id, $validated, $user, &$savedCaps) {
            foreach ($validated['caps'] as $cap) {
                $item = DB::table('qa2_audit_items')
                    ->where('id', (int) $cap['audit_item_id'])
                    ->where('audit_id', $id)
                    ->where('result', 'NC')
                    ->exists();

                if (!$item) {
                    continue;
                }

                $auditItemId = (int) $cap['audit_item_id'];
                $existingCap = DB::table('qa2_audit_caps')->where('audit_item_id', $auditItemId)->first();

                if ($existingCap) {
                    DB::table('qa2_audit_caps')->where('id', $existingCap->id)->update([
                        'filled_by' => (int) $user->id,
                        'action_plan' => $cap['action_plan'] ?? null,
                        'target_date' => $cap['target_date'] ?? null,
                        'status' => $cap['status'] ?? 'open',
                        'updated_at' => now(),
                    ]);
                    $capId = (int) $existingCap->id;
                } else {
                    $capId = (int) DB::table('qa2_audit_caps')->insertGetId([
                        'audit_item_id' => $auditItemId,
                        'filled_by' => (int) $user->id,
                        'action_plan' => $cap['action_plan'] ?? null,
                        'target_date' => $cap['target_date'] ?? null,
                        'status' => $cap['status'] ?? 'open',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $savedCaps[] = [
                    'audit_item_id' => $auditItemId,
                    'cap_id' => $capId,
                ];
            }
        });

        return response()->json(['success' => true, 'caps' => $savedCaps]);
    }

    public function uploadCapMedia(Request $request, int $id, int $capId)
    {
        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);

        $user = auth()->user();
        $isAuditee = DB::table('qa2_audit_auditees')
            ->where('audit_id', $id)
            ->where('user_id', (int) $user->id)
            ->exists();

        abort_if(!$isAuditee, 403);
        abort_if(!$this->capSubmissionEditable($audit), 422, 'CAP tidak dapat diubah saat proses approval.');

        $cap = DB::table('qa2_audit_caps as c')
            ->join('qa2_audit_items as i', 'i.id', '=', 'c.audit_item_id')
            ->where('c.id', $capId)
            ->where('i.audit_id', $id)
            ->select('c.id')
            ->first();

        abort_if(!$cap, 404);

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:30720|mimes:jpg,jpeg,png,webp,mp4,mov,avi,webm',
        ]);

        $inserted = [];
        foreach ($request->file('files', []) as $file) {
            $path = $file->store('qa2-audits/caps', 'public');
            $mime = (string) $file->getMimeType();
            $mediaType = Str::startsWith($mime, 'video/') ? 'video' : 'photo';

            $mediaId = DB::table('qa2_audit_cap_media')->insertGetId([
                'cap_id' => $capId,
                'uploaded_by' => (int) $user->id,
                'media_type' => $mediaType,
                'file_path' => $path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted[] = [
                'id' => $mediaId,
                'media_type' => $mediaType,
                'url' => Storage::url($path),
            ];
        }

        return response()->json(['success' => true, 'items' => $inserted]);
    }

    public function destroy(int $id)
    {
        $this->ensureHo();

        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);

        DB::transaction(function () use ($id) {
            $itemMedia = DB::table('qa2_audit_item_media as m')
                ->join('qa2_audit_items as i', 'i.id', '=', 'm.audit_item_id')
                ->where('i.audit_id', $id)
                ->pluck('m.file_path');

            $capMedia = DB::table('qa2_audit_cap_media as m')
                ->join('qa2_audit_caps as c', 'c.id', '=', 'm.cap_id')
                ->join('qa2_audit_items as i', 'i.id', '=', 'c.audit_item_id')
                ->where('i.audit_id', $id)
                ->pluck('m.file_path');

            DB::table('qa2_audit_cap_media')->whereIn('cap_id', function ($q) use ($id) {
                $q->from('qa2_audit_caps as c')
                    ->join('qa2_audit_items as i', 'i.id', '=', 'c.audit_item_id')
                    ->select('c.id')
                    ->where('i.audit_id', $id);
            })->delete();

            DB::table('qa2_audit_caps')->whereIn('audit_item_id', function ($q) use ($id) {
                $q->from('qa2_audit_items')->select('id')->where('audit_id', $id);
            })->delete();

            DB::table('qa2_audit_item_media')->whereIn('audit_item_id', function ($q) use ($id) {
                $q->from('qa2_audit_items')->select('id')->where('audit_id', $id);
            })->delete();

            DB::table('qa2_audit_items')->where('audit_id', $id)->delete();
            DB::table('qa2_audit_auditors')->where('audit_id', $id)->delete();
            DB::table('qa2_audit_auditees')->where('audit_id', $id)->delete();
            DB::table('qa2_audits')->where('id', $id)->delete();

            foreach ($itemMedia as $path) {
                Storage::disk('public')->delete((string) $path);
            }
            foreach ($capMedia as $path) {
                Storage::disk('public')->delete((string) $path);
            }
        });

        return back()->with('success', 'QA Audit berhasil dihapus.');
    }

    private function getAuditRow(int $id)
    {
        return DB::table('qa2_audits')->where('id', $id)->first();
    }

    private function buildAuditShareMessage(object $audit, string $url): string
    {
        $outletName = (string) DB::table('tbl_data_outlet')
            ->where('id_outlet', (int) ($audit->outlet_id ?? 0))
            ->value('nama_outlet');

        $templateName = (string) DB::table('qa2_templates')
            ->where('id', (int) ($audit->template_id ?? 0))
            ->value('name');

        $line = 'QA Audit ' . (string) ($audit->audit_number ?? '-');
        if (trim($outletName) !== '') {
            $line .= ' - ' . trim($outletName);
        }
        if (trim($templateName) !== '') {
            $line .= ' (' . trim($templateName) . ')';
        }

        return $line . "\n" . $url;
    }

    private function ensureHo(): void
    {
        $isHo = (int) (auth()->user()->id_outlet ?? 0) === 1;
        abort_if(!$isHo, 403);
    }

    private function generateAuditNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "QAA-{$date}";
        $last = DB::table('qa2_audits')
            ->where('audit_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('audit_number');

        $next = 1;
        if ($last && str_contains($last, '-')) {
            $parts = explode('-', $last);
            $seq = (int) end($parts);
            $next = $seq + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function syncPeople(int $auditId, array $auditorIds, array $auditeeIds): void
    {
        DB::table('qa2_audit_auditors')->where('audit_id', $auditId)->delete();
        DB::table('qa2_audit_auditees')->where('audit_id', $auditId)->delete();

        $auditors = array_values(array_unique(array_map('intval', $auditorIds)));
        $auditees = array_values(array_unique(array_map('intval', $auditeeIds)));

        if (!empty($auditors)) {
            $rows = [];
            foreach ($auditors as $uid) {
                $rows[] = [
                    'audit_id' => $auditId,
                    'user_id' => $uid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('qa2_audit_auditors')->insert($rows);
        }

        if (!empty($auditees)) {
            $rows = [];
            foreach ($auditees as $uid) {
                $rows[] = [
                    'audit_id' => $auditId,
                    'user_id' => $uid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('qa2_audit_auditees')->insert($rows);
        }
    }

    private function getTemplateSeedRows(int $templateId): array
    {
        $items = DB::table('qa2_template_items as ti')
            ->join('qa2_parameters as p', 'p.id', '=', 'ti.parameter_id')
            ->leftJoin('qa2_subcategories as s', 's.id', '=', 'p.subcategory_id')
            ->leftJoin('qa2_categories as c', 'c.id', '=', 's.category_id')
            ->where('ti.template_id', $templateId)
            ->orderBy('ti.sort_order')
            ->select([
                'ti.id as template_item_id',
                'ti.sort_order',
                'p.id as parameter_id',
                'p.code as parameter_code',
                'p.parameter_text',
                's.id as subcategory_id',
                's.name as subcategory_name',
                'c.id as category_id',
                'c.name as category_name',
            ])
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'template_item_id' => (int) $item->template_item_id,
                'category_id' => $item->category_id ? (int) $item->category_id : null,
                'subcategory_id' => $item->subcategory_id ? (int) $item->subcategory_id : null,
                'parameter_id' => (int) $item->parameter_id,
                'parameter_code' => $item->parameter_code,
                'parameter_text' => $item->parameter_text,
                'sort_order' => (int) $item->sort_order,
            ];
        }

        return $rows;
    }

    private function seedAuditItemsFromTemplateRows(int $auditId, array $templateRows): int
    {
        $rows = [];
        foreach ($templateRows as $item) {
            $rows[] = [
                'audit_id' => $auditId,
                'template_item_id' => $item['template_item_id'],
                'category_id' => $item['category_id'],
                'subcategory_id' => $item['subcategory_id'],
                'parameter_id' => $item['parameter_id'],
                'parameter_code' => $item['parameter_code'],
                'parameter_text' => $item['parameter_text'],
                'sort_order' => $item['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('qa2_audit_items')->insert($rows);
        }

        return count($rows);
    }

    private function ensureAuditItems(int $auditId, int $templateId): void
    {
        $exists = DB::table('qa2_audit_items')->where('audit_id', $auditId)->exists();
        if ($exists) {
            return;
        }

        $templateRows = $this->getTemplateSeedRows($templateId);
        if (!empty($templateRows)) {
            $this->seedAuditItemsFromTemplateRows($auditId, $templateRows);
        }
    }

    private function allowedOutlets(bool $isHo, int $userOutletId)
    {
        $query = DB::table('tbl_data_outlet')
            ->select(['id_outlet', 'nama_outlet'])
            ->orderBy('nama_outlet');

        if (!$isHo) {
            $query->where('id_outlet', $userOutletId);
        }

        return $query->get();
    }

    private function usersForSelector()
    {
        return DB::table('users as u')
            ->leftJoin('tbl_data_jabatan as j', 'j.id_jabatan', '=', 'u.id_jabatan')
            ->select([
                'u.id',
                'u.nama_lengkap',
                'u.id_outlet',
                'u.avatar',
                'j.nama_jabatan as jabatan',
            ])
            ->where('u.status', 'A')
            ->whereNotNull('u.id_outlet')
            ->orderBy('u.nama_lengkap')
            ->get();
    }

    private function attachAuditPeople($paginator): void
    {
        $ids = collect($paginator->items())->pluck('id')->map(fn ($x) => (int) $x)->all();
        if (empty($ids)) {
            return;
        }

        $auditors = DB::table('qa2_audit_auditors as aa')
            ->join('users as u', 'u.id', '=', 'aa.user_id')
            ->leftJoin('tbl_data_jabatan as j', 'j.id_jabatan', '=', 'u.id_jabatan')
            ->whereIn('aa.audit_id', $ids)
            ->orderBy('u.nama_lengkap')
            ->get(['aa.audit_id', 'u.id', 'u.nama_lengkap', 'u.avatar', 'j.nama_jabatan as jabatan'])
            ->groupBy('audit_id');

        $auditees = DB::table('qa2_audit_auditees as ae')
            ->join('users as u', 'u.id', '=', 'ae.user_id')
            ->leftJoin('tbl_data_jabatan as j', 'j.id_jabatan', '=', 'u.id_jabatan')
            ->whereIn('ae.audit_id', $ids)
            ->orderBy('u.nama_lengkap')
            ->get(['ae.audit_id', 'u.id', 'u.nama_lengkap', 'u.avatar', 'j.nama_jabatan as jabatan'])
            ->groupBy('audit_id');

        foreach ($paginator->items() as $audit) {
            $auditId = (int) $audit->id;
            $audit->auditors = ($auditors[$auditId] ?? collect())->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->nama_lengkap,
                'jabatan' => $row->jabatan,
                'avatar_url' => $this->resolveUserAvatarUrl($row->avatar ?? null),
            ])->values()->all();
            $audit->auditees = ($auditees[$auditId] ?? collect())->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->nama_lengkap,
                'jabatan' => $row->jabatan,
                'avatar_url' => $this->resolveUserAvatarUrl($row->avatar ?? null),
            ])->values()->all();
        }
    }

    private function auditPayload(int $id, bool $onlyNc = false): array
    {
        $audit = DB::table('qa2_audits as a')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'a.outlet_id')
            ->leftJoin('qa2_templates as t', 't.id', '=', 'a.template_id')
            ->select([
                'a.id',
                'a.audit_number',
                'a.audit_datetime',
                'a.audit_time_start',
                'a.audit_time_end',
                'a.status',
                'a.cap_submission_status',
                'a.cap_submitted_at',
                'a.cap_submitted_by',
                'a.outlet_id',
                'a.template_id',
                'a.notes',
                'o.nama_outlet as outlet_name',
                't.name as template_name',
            ])
            ->where('a.id', $id)
            ->first();

        $auditorIds = DB::table('qa2_audit_auditors')->where('audit_id', $id)->pluck('user_id')->map(fn ($x) => (int) $x)->values()->all();
        $auditeeIds = DB::table('qa2_audit_auditees')->where('audit_id', $id)->pluck('user_id')->map(fn ($x) => (int) $x)->values()->all();

        $auditors = DB::table('qa2_audit_auditors as aa')
            ->join('users as u', 'u.id', '=', 'aa.user_id')
            ->leftJoin('tbl_data_jabatan as j', 'j.id_jabatan', '=', 'u.id_jabatan')
            ->where('aa.audit_id', $id)
            ->orderBy('u.nama_lengkap')
            ->get(['u.id', 'u.nama_lengkap', 'u.avatar', 'j.nama_jabatan as jabatan'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->nama_lengkap,
                'jabatan' => $row->jabatan,
                'avatar_url' => $this->resolveUserAvatarUrl($row->avatar ?? null),
            ])
            ->values()
            ->all();

        $auditees = DB::table('qa2_audit_auditees as ae')
            ->join('users as u', 'u.id', '=', 'ae.user_id')
            ->leftJoin('tbl_data_jabatan as j', 'j.id_jabatan', '=', 'u.id_jabatan')
            ->where('ae.audit_id', $id)
            ->orderBy('u.nama_lengkap')
            ->get(['u.id', 'u.nama_lengkap', 'u.avatar', 'j.nama_jabatan as jabatan'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->nama_lengkap,
                'jabatan' => $row->jabatan,
                'avatar_url' => $this->resolveUserAvatarUrl($row->avatar ?? null),
            ])
            ->values()
            ->all();

        $summaryRows = DB::table('qa2_audit_items as i')
            ->leftJoin('qa2_categories as c', 'c.id', '=', 'i.category_id')
            ->where('i.audit_id', $id)
            ->groupBy('i.category_id', 'c.name')
            ->selectRaw('COALESCE(i.category_id, 0) as id')
            ->selectRaw("COALESCE(c.name, 'Tanpa Kategori') as name")
            ->selectRaw("SUM(CASE WHEN i.result = 'C' THEN 1 ELSE 0 END) as compliant")
            ->selectRaw("SUM(CASE WHEN i.result = 'NC' THEN 1 ELSE 0 END) as non_compliant")
            ->selectRaw("SUM(CASE WHEN i.result = 'NA' THEN 1 ELSE 0 END) as non_applicable")
            ->orderBy('name')
            ->get()
            ->map(function ($row, $index) {
                $compliant = (int) ($row->compliant ?? 0);
                $nonCompliant = (int) ($row->non_compliant ?? 0);
                $denominator = $compliant + $nonCompliant;
                $score = $denominator > 0 ? round(($compliant / $denominator) * 100, 2) : 0;

                return [
                    'id' => (int) ($row->id ?? 0),
                    'name' => (string) ($row->name ?? 'Tanpa Kategori'),
                    'compliant' => $compliant,
                    'non_compliant' => $nonCompliant,
                    'non_applicable' => (int) ($row->non_applicable ?? 0),
                    'score' => $score,
                    'no' => $index + 1,
                ];
            })
            ->values()
            ->all();

        $summaryTotal = [
            'compliant' => array_sum(array_column($summaryRows, 'compliant')),
            'non_compliant' => array_sum(array_column($summaryRows, 'non_compliant')),
            'non_applicable' => array_sum(array_column($summaryRows, 'non_applicable')),
        ];
        $summaryDenominator = $summaryTotal['compliant'] + $summaryTotal['non_compliant'];
        $summaryTotal['score'] = $summaryDenominator > 0
            ? round(($summaryTotal['compliant'] / $summaryDenominator) * 100, 2)
            : 0;

        $itemsQuery = DB::table('qa2_audit_items as i')
            ->leftJoin('qa2_categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('qa2_subcategories as s', 's.id', '=', 'i.subcategory_id')
            ->where('i.audit_id', $id)
            ->select([
                'i.id',
                'i.category_id',
                'i.subcategory_id',
                'i.parameter_id',
                'i.parameter_code',
                'i.parameter_text',
                'i.sort_order',
                'i.result',
                'i.comment',
                'i.due_date',
                'c.name as category_name',
                's.name as subcategory_name',
            ]);

        if ($onlyNc) {
            $itemsQuery->where(function ($q) {
                $q->where('i.result', 'NC')
                    ->orWhere(function ($sub) {
                        $sub->where('i.result', 'C')
                            ->whereNotNull('i.comment')
                            ->whereRaw("TRIM(i.comment) <> ''");
                    });
            });
        }

        $items = $itemsQuery
            ->orderBy('i.sort_order')
            ->get()
            ->map(function ($row) {
                $row->media = DB::table('qa2_audit_item_media')
                    ->where('audit_item_id', $row->id)
                    ->orderBy('id')
                    ->get(['id', 'media_type', 'file_path'])
                    ->map(function ($m) {
                        return [
                            'id' => (int) $m->id,
                            'media_type' => $m->media_type,
                            'url' => Storage::url($m->file_path),
                        ];
                    })
                    ->values()
                    ->all();

                $cap = DB::table('qa2_audit_caps')->where('audit_item_id', $row->id)->first();
                $capMedia = [];
                if ($cap) {
                    $capMedia = DB::table('qa2_audit_cap_media')
                        ->where('cap_id', $cap->id)
                        ->orderBy('id')
                        ->get(['id', 'media_type', 'file_path'])
                        ->map(function ($m) {
                            return [
                                'id' => (int) $m->id,
                                'media_type' => $m->media_type,
                                'url' => Storage::url($m->file_path),
                            ];
                        })
                        ->values()
                        ->all();
                }

                $row->cap = $cap ? [
                    'id' => (int) $cap->id,
                    'action_plan' => $cap->action_plan,
                    'target_date' => $cap->target_date,
                    'status' => $cap->status,
                    'media' => $capMedia,
                ] : null;

                return $row;
            })
            ->values()
            ->all();

        return [
            'id' => (int) $audit->id,
            'audit_number' => $audit->audit_number,
            'audit_datetime' => $audit->audit_datetime,
            'audit_time_start' => $audit->audit_time_start,
            'audit_time_end' => $audit->audit_time_end,
            'status' => $audit->status,
            'cap_submission_status' => $audit->cap_submission_status ?? null,
            'cap_submitted_at' => $audit->cap_submitted_at ?? null,
            'cap_submitted_by' => $audit->cap_submitted_by ? (int) $audit->cap_submitted_by : null,
            'cap_approval_flows' => $this->capApprovalFlowsForAudit($id),
            'outlet_id' => (int) $audit->outlet_id,
            'template_id' => (int) $audit->template_id,
            'notes' => $audit->notes,
            'outlet_name' => $audit->outlet_name,
            'template_name' => $audit->template_name,
            'auditor_ids' => $auditorIds,
            'auditee_ids' => $auditeeIds,
            'auditors' => $auditors,
            'auditees' => $auditees,
            'items' => $items,
            'summary_rows' => $summaryRows,
            'summary_total' => $summaryTotal,
        ];
    }

    private function resolveUserAvatarUrl(?string $avatar): ?string
    {
        $avatar = trim((string) ($avatar ?? ''));
        if ($avatar === '') {
            return null;
        }

        if (Str::startsWith($avatar, ['http://', 'https://', '/'])) {
            return $avatar;
        }

        return Storage::url($avatar);
    }

    private function auditTree(int $id): array
    {
        $items = DB::table('qa2_audit_items as i')
            ->leftJoin('qa2_categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('qa2_subcategories as s', 's.id', '=', 'i.subcategory_id')
            ->where('i.audit_id', $id)
            ->orderBy('i.sort_order')
            ->select([
                'i.id',
                'i.category_id',
                'i.subcategory_id',
                'c.name as category_name',
                's.name as subcategory_name',
            ])
            ->get();

        $grouped = [];
        foreach ($items as $item) {
            $catId = (int) ($item->category_id ?? 0);
            $subId = (int) ($item->subcategory_id ?? 0);
            if (!isset($grouped[$catId])) {
                $grouped[$catId] = [
                    'id' => $catId,
                    'name' => $item->category_name ?: 'Tanpa Kategori',
                    'subcategories' => [],
                ];
            }

            if (!isset($grouped[$catId]['subcategories'][$subId])) {
                $grouped[$catId]['subcategories'][$subId] = [
                    'id' => $subId,
                    'name' => $item->subcategory_name ?: 'Tanpa Subcategory',
                ];
            }
        }

        $result = [];
        foreach ($grouped as $cat) {
            $cat['subcategories'] = array_values($cat['subcategories']);
            $result[] = $cat;
        }

        return $result;
    }

    // ==================== Approval App API (ymsoftapp) ====================

    public function apiIndex(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;

        $search = $request->input('search');
        $status = $request->input('status');
        $outletId = $request->input('outlet_id');

        $query = DB::table('qa2_audits as a')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'a.outlet_id')
            ->leftJoin('qa2_templates as t', 't.id', '=', 'a.template_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.created_by')
            ->select([
                'a.id',
                'a.audit_number',
                'a.audit_datetime',
                'a.status',
                'a.outlet_id',
                'a.audit_time_start',
                'a.audit_time_end',
                'o.nama_outlet as outlet_name',
                't.name as template_name',
                'u.nama_lengkap as created_by_name',
            ])
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'C') as count_c")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NC') as count_nc")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NA') as count_na")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NC' and not exists (select 1 from qa2_audit_caps c where c.audit_item_id = i.id and c.action_plan is not null and c.action_plan <> '')) as count_nc_pending_cap")
            ->orderByDesc('a.id');

        if (!$isHo) {
            $query->where('a.outlet_id', (int) $user->id_outlet);
        }

        if ($outletId) {
            $query->where('a.outlet_id', (int) $outletId);
        }

        if ($status && in_array($status, ['draft', 'submitted'], true)) {
            $query->where('a.status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('a.audit_number', 'like', "%{$search}%")
                    ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                    ->orWhere('t.name', 'like', "%{$search}%")
                    ->orWhere('u.nama_lengkap', 'like', "%{$search}%")
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->from('qa2_audit_auditors as aa')
                            ->join('users as au', 'au.id', '=', 'aa.user_id')
                            ->leftJoin('tbl_data_jabatan as aj', 'aj.id_jabatan', '=', 'au.id_jabatan')
                            ->whereColumn('aa.audit_id', 'a.id')
                            ->where(function ($people) use ($search) {
                                $people->where('au.nama_lengkap', 'like', "%{$search}%")
                                    ->orWhere('aj.nama_jabatan', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->from('qa2_audit_auditees as ae')
                            ->join('users as au', 'au.id', '=', 'ae.user_id')
                            ->leftJoin('tbl_data_jabatan as aj', 'aj.id_jabatan', '=', 'au.id_jabatan')
                            ->whereColumn('ae.audit_id', 'a.id')
                            ->where(function ($people) use ($search) {
                                $people->where('au.nama_lengkap', 'like', "%{$search}%")
                                    ->orWhere('aj.nama_jabatan', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));
        $audits = $query->paginate($perPage);
        $this->attachAuditPeople($audits);

        $statsQuery = DB::table('qa2_audits');
        if (!$isHo) {
            $statsQuery->where('outlet_id', (int) $user->id_outlet);
        }

        return response()->json([
            'success' => true,
            'audits' => collect($audits->items())->map(fn ($row) => (array) $row)->values(),
            'pagination' => [
                'current_page' => $audits->currentPage(),
                'last_page' => $audits->lastPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
            ],
            'statistics' => [
                'total' => (clone $statsQuery)->count(),
                'draft' => (clone $statsQuery)->where('status', 'draft')->count(),
                'submitted' => (clone $statsQuery)->where('status', 'submitted')->count(),
            ],
            'outlets' => $this->allowedOutlets($isHo, (int) $user->id_outlet),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'outlet_id' => $outletId,
            ],
            'permissions' => [
                'can_manage' => $isHo,
            ],
        ]);
    }

    public function apiReportSummary(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;

        $outletId = (int) $request->input('outlet_id', 0);
        $fromMonth = (string) $request->input('from_month', now()->format('Y-m'));
        $toMonth = (string) $request->input('to_month', now()->format('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $fromMonth)) {
            $fromMonth = now()->format('Y-m');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $toMonth)) {
            $toMonth = $fromMonth;
        }

        $fromDate = Carbon::createFromFormat('Y-m', $fromMonth)->startOfMonth();
        $toDate = Carbon::createFromFormat('Y-m', $toMonth)->endOfMonth();
        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfMonth(), $fromDate->copy()->endOfMonth()];
            $fromMonth = $fromDate->format('Y-m');
            $toMonth = $toDate->format('Y-m');
        }

        $rows = $this->buildOutletSummaryRows(
            $isHo,
            (int) $user->id_outlet,
            $outletId,
            $fromDate->toDateTimeString(),
            $toDate->toDateTimeString()
        );

        return response()->json([
            'success' => true,
            'rows' => $rows,
            'outlets' => $this->allowedOutlets($isHo, (int) $user->id_outlet),
            'filters' => [
                'outlet_id' => $outletId > 0 ? (string) $outletId : '',
                'from_month' => $fromMonth,
                'to_month' => $toMonth,
            ],
            'permissions' => [
                'can_manage' => $isHo,
            ],
        ]);
    }

    public function apiCreateData()
    {
        $this->ensureHo();

        $user = auth()->user();

        return response()->json([
            'success' => true,
            'outlets' => $this->allowedOutlets(true, (int) $user->id_outlet),
            'users' => $this->usersForSelector(),
            'templates' => DB::table('qa2_templates')
                ->where('status', 'A')
                ->whereExists(function ($q) {
                    $q->from('qa2_template_items as ti')
                        ->selectRaw('1')
                        ->whereColumn('ti.template_id', 'qa2_templates.id');
                })
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function apiStore(Request $request)
    {
        $this->ensureHo();

        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'template_id' => 'required|exists:qa2_templates,id',
            'auditor_ids' => 'nullable|array',
            'auditor_ids.*' => 'integer|exists:users,id',
            'auditee_ids' => 'nullable|array',
            'auditee_ids.*' => 'integer|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $user = auth()->user();
        $templateRows = $this->getTemplateSeedRows((int) $validated['template_id']);
        if (empty($templateRows)) {
            return response()->json([
                'success' => false,
                'message' => 'Template tidak memiliki parameter audit.',
            ], 422);
        }

        $auditId = DB::transaction(function () use ($validated, $user, $templateRows) {
            $auditId = DB::table('qa2_audits')->insertGetId([
                'audit_number' => $this->generateAuditNumber(),
                'audit_datetime' => now(),
                'outlet_id' => (int) $validated['outlet_id'],
                'template_id' => (int) $validated['template_id'],
                'created_by' => (int) $user->id,
                'audit_time_start' => now(),
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncPeople($auditId, $validated['auditor_ids'] ?? [], $validated['auditee_ids'] ?? []);
            $this->seedAuditItemsFromTemplateRows($auditId, $templateRows);

            return $auditId;
        });

        return response()->json([
            'success' => true,
            'audit_id' => (int) $auditId,
            'message' => 'QA Audit draft berhasil dibuat.',
        ]);
    }

    public function apiShow(int $id)
    {
        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);

        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;
        if (!$isHo && (int) $audit->outlet_id !== (int) $user->id_outlet) {
            abort(403);
        }

        $this->ensureAuditItems((int) $id, (int) $audit->template_id);

        $canFillCap = DB::table('qa2_audit_auditees')
            ->where('audit_id', $id)
            ->where('user_id', (int) $user->id)
            ->exists();
        $canManage = $isHo && $audit->status === 'draft';

        return response()->json([
            'success' => true,
            'mode' => 'edit',
            'audit' => $this->auditPayload($id, !$canManage),
            'outlets' => $this->allowedOutlets($isHo, (int) $user->id_outlet),
            'users' => $this->usersForSelector(),
            'templates' => DB::table('qa2_templates')
                ->where('status', 'A')
                ->whereExists(function ($q) {
                    $q->from('qa2_template_items as ti')
                        ->selectRaw('1')
                        ->whereColumn('ti.template_id', 'qa2_templates.id');
                })
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'tree' => $this->auditTree($id),
            'permissions' => [
                'can_manage' => $canManage,
                'can_fill_cap' => $canFillCap && $audit->status === 'submitted',
                'can_edit_cap' => $canFillCap && $audit->status === 'submitted' && $this->capSubmissionEditable($audit),
                'can_submit_cap' => $canFillCap && $audit->status === 'submitted' && $this->capSubmissionEditable($audit),
            ],
        ]);
    }

    public function apiSubmit(int $id)
    {
        $this->ensureHo();

        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);
        abort_if($audit->status !== 'draft', 422, 'Audit sudah disubmit.');

        $missing = DB::table('qa2_audit_items')
            ->where('audit_id', $id)
            ->whereNull('result')
            ->count();

        if ($missing > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Semua parameter harus diisi C/NC/NA sebelum submit.',
            ], 422);
        }

        DB::table('qa2_audits')->where('id', $id)->update([
            'status' => 'submitted',
            'audit_time_end' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QA Audit berhasil disubmit.',
        ]);
    }

    public function apiDestroy(int $id)
    {
        $this->ensureHo();

        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);

        DB::transaction(function () use ($id) {
            $itemMedia = DB::table('qa2_audit_item_media as m')
                ->join('qa2_audit_items as i', 'i.id', '=', 'm.audit_item_id')
                ->where('i.audit_id', $id)
                ->pluck('m.file_path');

            $capMedia = DB::table('qa2_audit_cap_media as m')
                ->join('qa2_audit_caps as c', 'c.id', '=', 'm.cap_id')
                ->join('qa2_audit_items as i', 'i.id', '=', 'c.audit_item_id')
                ->where('i.audit_id', $id)
                ->pluck('m.file_path');

            DB::table('qa2_audit_cap_media')->whereIn('cap_id', function ($q) use ($id) {
                $q->from('qa2_audit_caps as c')
                    ->join('qa2_audit_items as i', 'i.id', '=', 'c.audit_item_id')
                    ->select('c.id')
                    ->where('i.audit_id', $id);
            })->delete();

            DB::table('qa2_audit_caps')->whereIn('audit_item_id', function ($q) use ($id) {
                $q->from('qa2_audit_items')->select('id')->where('audit_id', $id);
            })->delete();

            DB::table('qa2_audit_item_media')->whereIn('audit_item_id', function ($q) use ($id) {
                $q->from('qa2_audit_items')->select('id')->where('audit_id', $id);
            })->delete();

            DB::table('qa2_audit_items')->where('audit_id', $id)->delete();
            DB::table('qa2_audit_auditors')->where('audit_id', $id)->delete();
            DB::table('qa2_audit_auditees')->where('audit_id', $id)->delete();
            if (Schema::hasTable('qa2_audit_cap_approval_flows')) {
                DB::table('qa2_audit_cap_approval_flows')->where('audit_id', $id)->delete();
            }
            DB::table('qa2_audits')->where('id', $id)->delete();

            foreach ($itemMedia as $path) {
                Storage::disk('public')->delete((string) $path);
            }
            foreach ($capMedia as $path) {
                Storage::disk('public')->delete((string) $path);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'QA Audit berhasil dihapus.',
        ]);
    }

    public function submitCapForApproval(Request $request, int $id)
    {
        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);
        abort_if($audit->status !== 'submitted', 422, 'CAP hanya untuk audit submitted.');

        $user = auth()->user();
        $isAuditee = DB::table('qa2_audit_auditees')
            ->where('audit_id', $id)
            ->where('user_id', (int) $user->id)
            ->exists();
        abort_if(!$isAuditee, 403);
        abort_if(!$this->capSubmissionEditable($audit), 422, 'CAP sudah dalam proses approval atau telah disetujui.');

        $validated = $request->validate([
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
            'caps' => 'nullable|array',
            'caps.*.audit_item_id' => 'required|integer|exists:qa2_audit_items,id',
            'caps.*.action_plan' => 'nullable|string',
            'caps.*.target_date' => 'nullable|date',
            'caps.*.status' => 'nullable|in:open,progress,done',
        ]);

        $ncItems = DB::table('qa2_audit_items')
            ->where('audit_id', $id)
            ->where('result', 'NC')
            ->get(['id']);

        if ($ncItems->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tidak ada parameter NC untuk CAP.'], 422);
        }

        if (!empty($validated['caps'])) {
            $this->persistCaps($id, $validated['caps'], (int) $user->id);
        }

        foreach ($ncItems as $ncItem) {
            $cap = DB::table('qa2_audit_caps')->where('audit_item_id', $ncItem->id)->first();
            $plan = trim((string) ($cap->action_plan ?? ''));
            if ($plan === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Semua parameter NC wajib memiliki action plan sebelum submit CAP.',
                ], 422);
            }
        }

        DB::transaction(function () use ($id, $validated, $user) {
            DB::table('qa2_audit_cap_approval_flows')->where('audit_id', $id)->delete();

            foreach ($validated['approvers'] as $index => $approverId) {
                DB::table('qa2_audit_cap_approval_flows')->insert([
                    'audit_id' => $id,
                    'approver_id' => (int) $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('qa2_audits')->where('id', $id)->update([
                'cap_submission_status' => 'pending_approval',
                'cap_submitted_at' => now(),
                'cap_submitted_by' => (int) $user->id,
                'updated_at' => now(),
            ]);
        });

        $this->notifyNextCapApprover($id);

        return response()->json([
            'success' => true,
            'message' => 'CAP berhasil disubmit untuk approval.',
        ]);
    }

    public function getPendingCapApprovals(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized', 'audits' => []], 401);
        }

        $isSuperadmin = $currentUser->id_role === '5af56935b011a';

        $query = DB::table('qa2_audits as a')
            ->join('qa2_audit_cap_approval_flows as af', 'a.id', '=', 'af.audit_id')
            ->join('tbl_data_outlet as o', 'a.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('qa2_templates as t', 't.id', '=', 'a.template_id')
            ->leftJoin('users as submitter', 'a.cap_submitted_by', '=', 'submitter.id')
            ->where('af.status', 'PENDING')
            ->where('a.cap_submission_status', 'pending_approval');

        if (!$isSuperadmin) {
            $query->where('af.approver_id', $currentUser->id);
        }

        $rows = $query
            ->leftJoin('users as approver', 'af.approver_id', '=', 'approver.id')
            ->select(
                'a.id',
                'a.audit_number',
                'a.audit_datetime',
                'a.cap_submitted_at',
                'o.nama_outlet as outlet_name',
                't.name as template_name',
                'submitter.nama_lengkap as submitter_name',
                'af.approval_level',
                'approver.nama_lengkap as approver_name'
            )
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NC') as nc_count")
            ->get()
            ->filter(function ($row) use ($currentUser, $isSuperadmin) {
                if ($isSuperadmin) {
                    return true;
                }
                $lowerPending = DB::table('qa2_audit_cap_approval_flows')
                    ->where('audit_id', $row->id)
                    ->where('approval_level', '<', $row->approval_level)
                    ->where('status', 'PENDING')
                    ->count();

                return $lowerPending === 0;
            })
            ->unique('id')
            ->values();

        return response()->json(['success' => true, 'audits' => $rows]);
    }

    public function getCapApprovalDetails(int $id)
    {
        $audit = DB::table('qa2_audits as a')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'a.outlet_id')
            ->leftJoin('qa2_templates as t', 't.id', '=', 'a.template_id')
            ->leftJoin('users as submitter', 'a.cap_submitted_by', '=', 'submitter.id')
            ->where('a.id', $id)
            ->select(
                'a.*',
                'o.nama_outlet as outlet_name',
                't.name as template_name',
                'submitter.nama_lengkap as submitter_name'
            )
            ->first();

        if (!$audit) {
            return response()->json(['success' => false, 'message' => 'Audit tidak ditemukan'], 404);
        }

        $payload = $this->auditPayload($id);
        $ncItems = collect($payload['items'] ?? [])
            ->filter(fn ($item) => ($item->result ?? null) === 'NC' || (is_array($item) ? ($item['result'] ?? null) === 'NC' : false))
            ->values();

        if ($ncItems->isEmpty()) {
            $ncItems = collect($payload['items'])->filter(function ($item) {
                $row = is_array($item) ? $item : (array) $item;
                return ($row['result'] ?? null) === 'NC';
            })->values();
        }

        $capItems = collect($payload['items'])
            ->filter(function ($item) {
                $row = is_object($item) ? (array) $item : $item;
                return ($row['result'] ?? null) === 'NC';
            })
            ->map(function ($item) {
                $row = is_object($item) ? json_decode(json_encode($item), true) : $item;
                return [
                    'id' => $row['id'],
                    'parameter_code' => $row['parameter_code'] ?? null,
                    'parameter_text' => $row['parameter_text'] ?? null,
                    'category_name' => $row['category_name'] ?? null,
                    'subcategory_name' => $row['subcategory_name'] ?? null,
                    'comment' => $row['comment'] ?? null,
                    'due_date' => $row['due_date'] ?? null,
                    'auditor_media' => $row['media'] ?? [],
                    'cap' => $row['cap'] ?? null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'audit' => [
                'id' => (int) $audit->id,
                'audit_number' => $audit->audit_number,
                'audit_datetime' => $audit->audit_datetime,
                'outlet_name' => $audit->outlet_name,
                'template_name' => $audit->template_name,
                'submitter_name' => $audit->submitter_name,
                'cap_submission_status' => $audit->cap_submission_status,
                'cap_submitted_at' => $audit->cap_submitted_at,
            ],
            'cap_items' => $capItems,
            'approval_flows' => $this->capApprovalFlowsForAudit($id),
        ]);
    }

    public function approveCap(Request $request, int $id)
    {
        $audit = $this->getAuditRow($id);
        if (!$audit) {
            return response()->json(['success' => false, 'message' => 'Audit tidak ditemukan'], 404);
        }
        if (($audit->cap_submission_status ?? '') !== 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'CAP tidak dalam status pending approval'], 400);
        }

        $currentUser = auth()->user();
        $isSuperadmin = $currentUser->id_role === '5af56935b011a';
        $note = $request->input('note') ?? $request->input('comments');

        $flow = DB::table('qa2_audit_cap_approval_flows')
            ->where('audit_id', $id)
            ->where('approver_id', $currentUser->id)
            ->where('status', 'PENDING')
            ->first();

        if (!$flow && $isSuperadmin) {
            $flow = DB::table('qa2_audit_cap_approval_flows')
                ->where('audit_id', $id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
        }

        if (!$flow) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak approve CAP ini'], 403);
        }

        $lowerPending = DB::table('qa2_audit_cap_approval_flows')
            ->where('audit_id', $id)
            ->where('approval_level', '<', $flow->approval_level)
            ->where('status', 'PENDING')
            ->count();
        if ($lowerPending > 0 && !$isSuperadmin) {
            return response()->json(['success' => false, 'message' => 'Tunggu approval level sebelumnya'], 400);
        }

        DB::table('qa2_audit_cap_approval_flows')->where('id', $flow->id)->update([
            'status' => 'APPROVED',
            'approved_at' => now(),
            'comments' => $note,
            'updated_at' => now(),
        ]);

        $pendingCount = DB::table('qa2_audit_cap_approval_flows')
            ->where('audit_id', $id)
            ->where('status', 'PENDING')
            ->count();

        $message = 'CAP berhasil di-approve.';
        if ($pendingCount === 0) {
            DB::table('qa2_audits')->where('id', $id)->update([
                'cap_submission_status' => 'approved',
                'updated_at' => now(),
            ]);
            $message = 'Semua approval selesai. CAP telah disetujui.';
        } else {
            $this->notifyNextCapApprover($id);
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    public function rejectCap(Request $request, int $id)
    {
        $audit = $this->getAuditRow($id);
        if (!$audit) {
            return response()->json(['success' => false, 'message' => 'Audit tidak ditemukan'], 404);
        }
        if (($audit->cap_submission_status ?? '') !== 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'CAP tidak dalam status pending approval'], 400);
        }

        $currentUser = auth()->user();
        $isSuperadmin = $currentUser->id_role === '5af56935b011a';
        $note = $request->input('note') ?? $request->input('comments');

        $flow = DB::table('qa2_audit_cap_approval_flows')
            ->where('audit_id', $id)
            ->where('approver_id', $currentUser->id)
            ->where('status', 'PENDING')
            ->first();

        if (!$flow && $isSuperadmin) {
            $flow = DB::table('qa2_audit_cap_approval_flows')
                ->where('audit_id', $id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
        }

        if (!$flow) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak reject CAP ini'], 403);
        }

        DB::table('qa2_audit_cap_approval_flows')
            ->where('audit_id', $id)
            ->where('status', 'PENDING')
            ->update([
                'status' => 'REJECTED',
                'rejected_at' => now(),
                'comments' => $note,
                'updated_at' => now(),
            ]);

        DB::table('qa2_audits')->where('id', $id)->update([
            'cap_submission_status' => 'rejected',
            'updated_at' => now(),
        ]);

        if ($audit->cap_submitted_by) {
            NotificationService::insert([
                'user_id' => (int) $audit->cap_submitted_by,
                'type' => 'qa2_cap_rejected',
                'title' => 'CAP QA2 Ditolak',
                'message' => "CAP audit {$audit->audit_number} ditolak. Silakan perbaiki dan submit ulang.",
                'is_read' => 0,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'CAP ditolak. Auditee dapat memperbaiki dan submit ulang.']);
    }

    private function capSubmissionEditable(object $audit): bool
    {
        $status = $audit->cap_submission_status ?? null;
        return !in_array($status, ['pending_approval', 'approved'], true);
    }

    private function persistCaps(int $auditId, array $caps, int $userId): void
    {
        foreach ($caps as $cap) {
            $itemExists = DB::table('qa2_audit_items')
                ->where('id', (int) $cap['audit_item_id'])
                ->where('audit_id', $auditId)
                ->where('result', 'NC')
                ->exists();
            if (!$itemExists) {
                continue;
            }

            $auditItemId = (int) $cap['audit_item_id'];
            $existingCap = DB::table('qa2_audit_caps')->where('audit_item_id', $auditItemId)->first();

            if ($existingCap) {
                DB::table('qa2_audit_caps')->where('id', $existingCap->id)->update([
                    'filled_by' => $userId,
                    'action_plan' => $cap['action_plan'] ?? null,
                    'target_date' => $cap['target_date'] ?? null,
                    'status' => $cap['status'] ?? 'open',
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('qa2_audit_caps')->insert([
                    'audit_item_id' => $auditItemId,
                    'filled_by' => $userId,
                    'action_plan' => $cap['action_plan'] ?? null,
                    'target_date' => $cap['target_date'] ?? null,
                    'status' => $cap['status'] ?? 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function capApprovalFlowsForAudit(int $auditId): array
    {
        if (!Schema::hasTable('qa2_audit_cap_approval_flows')) {
            return [];
        }

        return DB::table('qa2_audit_cap_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.audit_id', $auditId)
            ->orderBy('af.approval_level')
            ->get([
                'af.id',
                'af.approver_id',
                'af.approval_level',
                'af.status',
                'af.comments',
                'af.approved_at',
                'af.rejected_at',
                'u.nama_lengkap as approver_name',
                'u.email as approver_email',
                'j.nama_jabatan as approver_jabatan',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'approver_id' => (int) $row->approver_id,
                'approval_level' => (int) $row->approval_level,
                'status' => $row->status,
                'comments' => $row->comments,
                'approved_at' => $row->approved_at,
                'rejected_at' => $row->rejected_at,
                'approver_name' => $row->approver_name,
                'approver_email' => $row->approver_email,
                'approver_jabatan' => $row->approver_jabatan,
            ])
            ->values()
            ->all();
    }

    private function notifyNextCapApprover(int $auditId): void
    {
        try {
            $next = DB::table('qa2_audit_cap_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->where('af.audit_id', $auditId)
                ->where('af.status', 'PENDING')
                ->orderBy('af.approval_level')
                ->select('u.id', 'u.nama_lengkap')
                ->first();

            if (!$next) {
                return;
            }

            $audit = DB::table('qa2_audits as a')
                ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'a.outlet_id')
                ->where('a.id', $auditId)
                ->select('a.audit_number', 'o.nama_outlet')
                ->first();

            if (!$audit) {
                return;
            }

            NotificationService::insert([
                'user_id' => (int) $next->id,
                'type' => 'qa2_cap_approval',
                'title' => 'Approval CAP QA2',
                'message' => "CAP audit {$audit->audit_number} ({$audit->nama_outlet}) menunggu approval Anda.",
                'is_read' => 0,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('QA2 CAP approval notification failed: '.$e->getMessage());
        }
    }
}
