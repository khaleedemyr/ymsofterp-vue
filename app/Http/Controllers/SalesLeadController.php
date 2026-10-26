<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\SalesLead;
use App\Models\SalesLeadOutletTarget;
use App\Models\SalesLeadTarget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SalesLeadController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $segment1 = trim((string) $request->get('segment_1', ''));
        $status = trim((string) $request->get('status', ''));
        $outletId = $request->get('outlet_id');
        $result = trim((string) $request->get('result', ''));

        $filtered = $this->visibleQuery()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('sales', fn ($u) => $u->where('nama_lengkap', 'like', "%{$search}%"));
                });
            })
            ->when($segment1 !== '', fn ($q) => $q->where('segment_1', $segment1))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($outletId, fn ($q) => $q->where('outlet_id', (int) $outletId))
            ->when($result === 'gt_15', function ($q) {
                // Result pax: real_pax jika ada, else pax plan
                $q->whereRaw('COALESCE(real_pax, pax) > 15');
            })
            ->when($result === 'lt_15', function ($q) {
                $q->whereRaw('COALESCE(real_pax, pax) < 15')
                    ->whereRaw('COALESCE(real_pax, pax) IS NOT NULL');
            });

        $totals = (clone $filtered)
            ->selectRaw('COUNT(*) as total_leads')
            ->selectRaw('COALESCE(SUM(revenue), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(real_revenue), 0) as total_real_revenue')
            ->selectRaw('COALESCE(SUM(COALESCE(real_pax, pax)), 0) as total_pax')
            ->first();

        $records = (clone $filtered)
            ->with([
                'outlet:id_outlet,nama_outlet',
                'sales:id,nama_lengkap',
                'creator:id,nama_lengkap',
            ])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SalesLead/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'segment_1' => $segment1,
                'status' => $status,
                'outlet_id' => $outletId ? (int) $outletId : '',
                'result' => $result,
            ],
            'totals' => [
                'total_leads' => (int) ($totals->total_leads ?? 0),
                'total_revenue' => (float) ($totals->total_revenue ?? 0),
                'total_real_revenue' => (float) ($totals->total_real_revenue ?? 0),
                'total_pax' => (int) ($totals->total_pax ?? 0),
            ],
            'segment1Options' => SalesLead::SEGMENT_1,
            'statuses' => SalesLead::STATUSES,
            'outlets' => $this->outletOptions(),
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $groupSalesTotal = (float) $request->get('group_sales_total', 0);

        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = (clone $periodStart)->endOfMonth();
        $prevStart = (clone $periodStart)->subMonth()->startOfMonth();
        $prevEnd = (clone $periodStart)->subMonth()->endOfMonth();

        $segments = SalesLead::SEGMENT_1;

        $currentRows = $this->aggregateRevenueBySalesSegment($periodStart, $periodEnd);
        $prevRows = $this->aggregateRevenueBySalesSegment($prevStart, $prevEnd);

        $salesIds = collect($currentRows)->pluck('sales_user_id')
            ->merge(collect($prevRows)->pluck('sales_user_id'))
            ->unique()
            ->filter()
            ->values();

        $salesNames = User::query()
            ->whereIn('id', $salesIds->all())
            ->pluck('nama_lengkap', 'id');

        $targets = SalesLeadTarget::query()
            ->where('year', $year)
            ->where('month', $month)
            ->when($salesIds->isNotEmpty(), fn ($q) => $q->whereIn('sales_user_id', $salesIds->all()))
            ->get()
            ->keyBy(fn ($t) => $t->sales_user_id.'|'.$t->segment_1);

        $buildSalesTable = function (int $salesUserId) use ($segments, $currentRows, $prevRows, $targets, $salesNames) {
            $currMap = collect($currentRows)->where('sales_user_id', $salesUserId)->keyBy('segment_1');
            $prevMap = collect($prevRows)->where('sales_user_id', $salesUserId)->keyBy('segment_1');

            $rows = [];
            $sumTarget = 0;
            $sumGt = 0;
            $sumLt = 0;
            $sumTotal = 0;
            $sumLast = 0;

            foreach ($segments as $segment) {
                $curr = $currMap->get($segment);
                $prev = $prevMap->get($segment);
                $revGt = (float) ($curr->rev_gt_15 ?? 0);
                $revLt = (float) ($curr->rev_lt_15 ?? 0);
                $total = $revGt + $revLt;
                $lastMonth = (float) (($prev->rev_gt_15 ?? 0) + ($prev->rev_lt_15 ?? 0));
                $target = (float) ($targets->get($salesUserId.'|'.$segment)?->target_amount ?? 0);
                $lmVsMtd = $total - $lastMonth;
                $growth = $lastMonth > 0 ? round(($lmVsMtd / $lastMonth) * 100, 2) : ($total > 0 ? 100.0 : 0.0);
                $targetVsMtd = $target > 0 ? round(($total / $target) * 100, 2) : null;

                $rows[] = [
                    'segment_1' => $segment,
                    'target' => $target,
                    'rev_gt_15' => $revGt,
                    'rev_lt_15' => $revLt,
                    'total' => $total,
                    'segment_pct' => 0, // filled after sum
                    'last_month' => $lastMonth,
                    'lm_vs_mtd' => $lmVsMtd,
                    'growth' => $growth,
                    'target_vs_mtd' => $targetVsMtd,
                ];

                $sumTarget += $target;
                $sumGt += $revGt;
                $sumLt += $revLt;
                $sumTotal += $total;
                $sumLast += $lastMonth;
            }

            $rows = array_map(function ($row) use ($sumTotal) {
                $row['segment_pct'] = $sumTotal > 0 ? round(($row['total'] / $sumTotal) * 100, 2) : 0;

                return $row;
            }, $rows);

            $lmVsMtdTotal = $sumTotal - $sumLast;

            return [
                'sales_user_id' => $salesUserId,
                'sales_name' => $salesNames[$salesUserId] ?? 'Unknown',
                'rows' => $rows,
                'total' => [
                    'target' => $sumTarget,
                    'rev_gt_15' => $sumGt,
                    'rev_lt_15' => $sumLt,
                    'total' => $sumTotal,
                    'segment_pct' => 100,
                    'last_month' => $sumLast,
                    'lm_vs_mtd' => $lmVsMtdTotal,
                    'growth' => $sumLast > 0 ? round(($lmVsMtdTotal / $sumLast) * 100, 2) : ($sumTotal > 0 ? 100.0 : 0.0),
                    'target_vs_mtd' => $sumTarget > 0 ? round(($sumTotal / $sumTarget) * 100, 2) : null,
                ],
                'achievement_pct' => $sumTarget > 0 ? round(($sumTotal / $sumTarget) * 100, 2) : null,
                'variance' => $sumTotal - $sumTarget,
                'variance_pct' => $sumTarget > 0 ? round((($sumTotal - $sumTarget) / $sumTarget) * 100, 2) : null,
            ];
        };

        // Include sales who only have targets this month
        $targetSalesIds = SalesLeadTarget::query()
            ->where('year', $year)
            ->where('month', $month)
            ->pluck('sales_user_id');
        $allSalesIds = $salesIds->merge($targetSalesIds)->unique()->filter()->values();

        if ($allSalesIds->isNotEmpty()) {
            $extraNames = User::query()->whereIn('id', $allSalesIds->all())->pluck('nama_lengkap', 'id');
            foreach ($extraNames as $id => $name) {
                $salesNames[$id] = $name;
            }
        }

        $perSales = $allSalesIds
            ->map(fn ($id) => $buildSalesTable((int) $id))
            ->sortBy('sales_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        // Grand total across all sales
        $grandRows = [];
        $gTarget = $gGt = $gLt = $gTotal = $gLast = 0;
        foreach ($segments as $segment) {
            $revGt = 0;
            $revLt = 0;
            $lastMonth = 0;
            $target = 0;
            foreach ($perSales as $salesBlock) {
                $row = collect($salesBlock['rows'])->firstWhere('segment_1', $segment);
                if (! $row) {
                    continue;
                }
                $revGt += $row['rev_gt_15'];
                $revLt += $row['rev_lt_15'];
                $lastMonth += $row['last_month'];
                $target += $row['target'];
            }
            $total = $revGt + $revLt;
            $lmVsMtd = $total - $lastMonth;
            $grandRows[] = [
                'segment_1' => $segment,
                'target' => $target,
                'rev_gt_15' => $revGt,
                'rev_lt_15' => $revLt,
                'total' => $total,
                'segment_pct' => 0,
                'last_month' => $lastMonth,
                'lm_vs_mtd' => $lmVsMtd,
                'growth' => $lastMonth > 0 ? round(($lmVsMtd / $lastMonth) * 100, 2) : ($total > 0 ? 100.0 : 0.0),
                'target_vs_mtd' => $target > 0 ? round(($total / $target) * 100, 2) : null,
            ];
            $gTarget += $target;
            $gGt += $revGt;
            $gLt += $revLt;
            $gTotal += $total;
            $gLast += $lastMonth;
        }
        $grandRows = array_map(function ($row) use ($gTotal) {
            $row['segment_pct'] = $gTotal > 0 ? round(($row['total'] / $gTotal) * 100, 2) : 0;

            return $row;
        }, $grandRows);

        $gLmVs = $gTotal - $gLast;
        $grand = [
            'sales_name' => 'SUMMARY TOTAL',
            'rows' => $grandRows,
            'total' => [
                'target' => $gTarget,
                'rev_gt_15' => $gGt,
                'rev_lt_15' => $gLt,
                'total' => $gTotal,
                'segment_pct' => 100,
                'last_month' => $gLast,
                'lm_vs_mtd' => $gLmVs,
                'growth' => $gLast > 0 ? round(($gLmVs / $gLast) * 100, 2) : ($gTotal > 0 ? 100.0 : 0.0),
                'target_vs_mtd' => $gTarget > 0 ? round(($gTotal / $gTarget) * 100, 2) : null,
            ],
            'achievement_pct' => $gTarget > 0 ? round(($gTotal / $gTarget) * 100, 2) : null,
            'variance' => $gTotal - $gTarget,
            'variance_pct' => $gTarget > 0 ? round((($gTotal - $gTarget) / $gTarget) * 100, 2) : null,
            'contribution_pct' => $groupSalesTotal > 0 ? round(($gTotal / $groupSalesTotal) * 100, 2) : null,
        ];

        [$outletGroups, $outletGrand] = $this->buildOutletContribution(
            $periodStart,
            $periodEnd,
            $prevStart,
            $prevEnd,
            $year,
            $month
        );

        return Inertia::render('SalesLead/Dashboard', [
            'filters' => [
                'year' => $year,
                'month' => $month,
                'group_sales_total' => $groupSalesTotal ?: '',
            ],
            'periodLabel' => $periodStart->translatedFormat('F Y'),
            'prevPeriodLabel' => $prevStart->translatedFormat('F Y'),
            'perSales' => $perSales,
            'grand' => $grand,
            'segments' => $segments,
            'outletGroups' => $outletGroups,
            'outletGrand' => $outletGrand,
        ]);
    }

    public function saveTargets(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'sales_user_id' => 'required|integer|exists:users,id',
            'targets' => 'required|array',
            'targets.*.segment_1' => 'required|string|in:'.implode(',', SalesLead::SEGMENT_1),
            'targets.*.target_amount' => 'nullable|numeric|min:0',
        ]);

        foreach ($validated['targets'] as $row) {
            SalesLeadTarget::updateOrCreate(
                [
                    'sales_user_id' => $validated['sales_user_id'],
                    'segment_1' => $row['segment_1'],
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ],
                [
                    'target_amount' => $row['target_amount'] ?? 0,
                ]
            );
        }

        return back()->with('success', 'Target berhasil disimpan.');
    }

    public function saveOutletTargets(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'budgets' => 'required|array',
            'budgets.*.outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'budgets.*.budget_amount' => 'nullable|numeric|min:0',
        ]);

        foreach ($validated['budgets'] as $row) {
            SalesLeadOutletTarget::updateOrCreate(
                [
                    'outlet_id' => $row['outlet_id'],
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ],
                [
                    'budget_amount' => $row['budget_amount'] ?? 0,
                ]
            );
        }

        return back()->with('success', 'Budget outlet berhasil disimpan.');
    }

    public function create(): Response
    {
        $user = auth()->user();

        return Inertia::render('SalesLead/Create', array_merge($this->formOptions(), [
            'salesUser' => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
            ],
        ]));
    }

    public function show($id): Response
    {
        $record = $this->findVisible($id);
        $user = auth()->user();

        return Inertia::render('SalesLead/Show', [
            'record' => $record,
            'canEdit' => $this->canEdit($record, $user),
            'canDelete' => $this->canDelete($record, $user),
            'statuses' => SalesLead::STATUSES,
        ]);
    }

    public function edit($id): Response
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEdit($record, $user)) {
            abort(403, 'Tidak dapat mengedit Sales Lead ini.');
        }

        return Inertia::render('SalesLead/Edit', array_merge($this->formOptions(), [
            'record' => $record,
            'salesUser' => [
                'id' => $record->sales?->id ?? $record->sales_user_id,
                'nama_lengkap' => $record->sales?->nama_lengkap ?? '-',
            ],
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $user = auth()->user();

        $lead = SalesLead::create([
            'number' => $this->generateNumber(),
            'company_name' => $validated['company_name'],
            'segment_1' => $validated['segment_1'] ?? null,
            'segment_2' => $validated['segment_2'] ?? null,
            'type_of_event' => $validated['type_of_event'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'pax' => $validated['pax'] ?? null,
            'outlet_id' => $validated['outlet_id'] ?? null,
            'status' => $validated['status'],
            'revenue' => $validated['revenue'] ?? null,
            'sales_user_id' => $user->id,
            'remarks' => $validated['remarks'] ?? null,
            'created_by' => $user->id,
        ]);

        return redirect()->route('sales-leads.show', $lead->id)
            ->with('success', 'Sales Lead berhasil disimpan.');
    }

    public function update(Request $request, $id)
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEdit($record, $user)) {
            abort(403, 'Tidak dapat mengedit Sales Lead ini.');
        }

        $validated = $this->validatePayload($request);

        $record->update([
            'company_name' => $validated['company_name'],
            'segment_1' => $validated['segment_1'] ?? null,
            'segment_2' => $validated['segment_2'] ?? null,
            'type_of_event' => $validated['type_of_event'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'pax' => $validated['pax'] ?? null,
            'outlet_id' => $validated['outlet_id'] ?? null,
            'status' => $validated['status'],
            'revenue' => $validated['revenue'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('sales-leads.show', $record->id)
            ->with('success', 'Sales Lead berhasil diupdate.');
    }

    public function destroy($id)
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canDelete($record, $user)) {
            abort(403);
        }

        $record->delete();

        return redirect()->route('sales-leads.index')->with('success', 'Sales Lead dihapus.');
    }

    /**
     * Update actual/closing data from index row (real pax, real revenue, bill photo, remarks).
     */
    public function updateActual(Request $request, $id)
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEdit($record, $user)) {
            return response()->json(['success' => false, 'message' => 'Tidak dapat mengedit Sales Lead ini.'], 403);
        }

        $validated = $request->validate([
            'real_pax' => 'nullable|integer|min:0',
            'real_revenue' => 'nullable|numeric|min:0',
            'closing_remarks' => 'nullable|string|max:5000',
            'bill_photo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_bill_photo' => 'nullable|boolean',
        ]);

        $data = [
            'real_pax' => $validated['real_pax'] ?? null,
            'real_revenue' => $validated['real_revenue'] ?? null,
            'closing_remarks' => $validated['closing_remarks'] ?? null,
            'updated_by' => $user->id,
        ];

        if ($request->boolean('remove_bill_photo') && $record->bill_photo) {
            Storage::disk('public')->delete($record->bill_photo);
            $data['bill_photo'] = null;
        }

        if ($request->hasFile('bill_photo')) {
            if ($record->bill_photo) {
                Storage::disk('public')->delete($record->bill_photo);
            }
            $data['bill_photo'] = $request->file('bill_photo')->store('sales-leads/bills', 'public');
        }

        $record->update($data);
        $record->refresh();

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data real berhasil disimpan.',
                'lead' => [
                    'id' => $record->id,
                    'real_pax' => $record->real_pax,
                    'real_revenue' => $record->real_revenue,
                    'closing_remarks' => $record->closing_remarks,
                    'bill_photo' => $record->bill_photo,
                    'bill_photo_url' => $record->bill_photo_url,
                ],
            ]);
        }

        return back()->with('success', 'Data real berhasil disimpan.');
    }

    /**
     * Aggregate revenue by sales + segment_1 for a period.
     * Amount: COALESCE(real_revenue, revenue)
     * Pax split: COALESCE(real_pax, pax) > 15 / < 15
     */
    private function aggregateRevenueBySalesSegment(Carbon $start, Carbon $end)
    {
        $amountExpr = 'COALESCE(real_revenue, revenue, 0)';
        $paxExpr = 'COALESCE(real_pax, pax)';

        return $this->visibleQuery()
            ->whereNotNull('segment_1')
            ->where('segment_1', '!=', '')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($inner) use ($start, $end) {
                    $inner->whereNotNull('event_date')
                        ->whereDate('event_date', '>=', $start->toDateString())
                        ->whereDate('event_date', '<=', $end->toDateString());
                })->orWhere(function ($inner) use ($start, $end) {
                    $inner->whereNull('event_date')
                        ->whereDate('created_at', '>=', $start->toDateString())
                        ->whereDate('created_at', '<=', $end->toDateString());
                });
            })
            ->select([
                'sales_user_id',
                'segment_1',
                DB::raw("SUM(CASE WHEN {$paxExpr} > 15 THEN {$amountExpr} ELSE 0 END) as rev_gt_15"),
                DB::raw("SUM(CASE WHEN {$paxExpr} < 15 AND {$paxExpr} IS NOT NULL THEN {$amountExpr} ELSE 0 END) as rev_lt_15"),
            ])
            ->groupBy('sales_user_id', 'segment_1')
            ->get();
    }

    private function aggregateRevenueByOutlet(Carbon $start, Carbon $end)
    {
        $amountExpr = 'COALESCE(real_revenue, revenue, 0)';
        $paxExpr = 'COALESCE(real_pax, pax)';

        return $this->visibleQuery()
            ->whereNotNull('outlet_id')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($inner) use ($start, $end) {
                    $inner->whereNotNull('event_date')
                        ->whereDate('event_date', '>=', $start->toDateString())
                        ->whereDate('event_date', '<=', $end->toDateString());
                })->orWhere(function ($inner) use ($start, $end) {
                    $inner->whereNull('event_date')
                        ->whereDate('created_at', '>=', $start->toDateString())
                        ->whereDate('created_at', '<=', $end->toDateString());
                });
            })
            ->select([
                'outlet_id',
                DB::raw("SUM(CASE WHEN {$paxExpr} > 15 THEN {$amountExpr} ELSE 0 END) as rev_gt_15"),
                DB::raw("SUM(CASE WHEN {$paxExpr} IS NOT NULL AND {$paxExpr} < 16 THEN {$amountExpr} ELSE 0 END) as rev_lt_16"),
            ])
            ->groupBy('outlet_id')
            ->get();
    }

    /**
     * @return array{0: array<int, array>, 1: array}
     */
    private function buildOutletContribution(
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $prevStart,
        Carbon $prevEnd,
        int $year,
        int $month
    ): array {
        $currentRows = $this->aggregateRevenueByOutlet($periodStart, $periodEnd);
        $prevRows = $this->aggregateRevenueByOutlet($prevStart, $prevEnd);

        $outletIds = collect($currentRows)->pluck('outlet_id')
            ->merge(collect($prevRows)->pluck('outlet_id'))
            ->merge(
                SalesLeadOutletTarget::query()
                    ->where('year', $year)
                    ->where('month', $month)
                    ->pluck('outlet_id')
            )
            ->unique()
            ->filter()
            ->values();

        if ($outletIds->isEmpty()) {
            return [[], [
                'rows' => [],
                'total' => [
                    'budget' => 0,
                    'rev_gt_15' => 0,
                    'rev_lt_16' => 0,
                    'total' => 0,
                    'performance' => null,
                    'last_month' => 0,
                    'variance' => 0,
                    'growth' => 0.0,
                ],
            ]];
        }

        $outlets = Outlet::query()
            ->with('region:id,name')
            ->whereIn('id_outlet', $outletIds->all())
            ->get(['id_outlet', 'nama_outlet', 'region_id'])
            ->keyBy('id_outlet');

        $budgets = SalesLeadOutletTarget::query()
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('outlet_id', $outletIds->all())
            ->get()
            ->keyBy('outlet_id');

        $currMap = collect($currentRows)->keyBy('outlet_id');
        $prevMap = collect($prevRows)->keyBy('outlet_id');

        $grouped = [];
        foreach ($outletIds as $outletId) {
            $outlet = $outlets->get($outletId);
            if (! $outlet) {
                continue;
            }

            $curr = $currMap->get($outletId);
            $prev = $prevMap->get($outletId);
            $revGt = (float) ($curr->rev_gt_15 ?? 0);
            $revLt = (float) ($curr->rev_lt_16 ?? 0);
            $total = $revGt + $revLt;
            $lastMonth = (float) (($prev->rev_gt_15 ?? 0) + ($prev->rev_lt_16 ?? 0));
            $budget = (float) ($budgets->get($outletId)?->budget_amount ?? 0);
            $variance = $total - $lastMonth;
            $growth = $lastMonth > 0
                ? round(($variance / $lastMonth) * 100, 2)
                : ($total > 0 ? 100.0 : 0.0);
            $performance = $budget > 0 ? round(($total / $budget) * 100, 2) : null;

            $regionName = strtoupper(trim((string) ($outlet->region?->name ?? 'LAINNYA'))) ?: 'LAINNYA';

            $grouped[$regionName][] = [
                'outlet_id' => (int) $outletId,
                'outlet_name' => $outlet->nama_outlet,
                'budget' => $budget,
                'rev_gt_15' => $revGt,
                'rev_lt_16' => $revLt,
                'total' => $total,
                'performance' => $performance,
                'last_month' => $lastMonth,
                'variance' => $variance,
                'growth' => $growth,
            ];
        }

        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);

        $outletGroups = [];
        $gBudget = $gGt = $gLt = $gTotal = $gLast = 0;

        foreach ($grouped as $regionName => $rows) {
            usort($rows, fn ($a, $b) => strnatcasecmp($a['outlet_name'], $b['outlet_name']));

            $sumBudget = 0;
            $sumGt = 0;
            $sumLt = 0;
            $sumTotal = 0;
            $sumLast = 0;

            foreach ($rows as $row) {
                $sumBudget += $row['budget'];
                $sumGt += $row['rev_gt_15'];
                $sumLt += $row['rev_lt_16'];
                $sumTotal += $row['total'];
                $sumLast += $row['last_month'];
            }

            $sumVariance = $sumTotal - $sumLast;
            $regionTotal = [
                'budget' => $sumBudget,
                'rev_gt_15' => $sumGt,
                'rev_lt_16' => $sumLt,
                'total' => $sumTotal,
                'performance' => $sumBudget > 0 ? round(($sumTotal / $sumBudget) * 100, 2) : null,
                'last_month' => $sumLast,
                'variance' => $sumVariance,
                'growth' => $sumLast > 0
                    ? round(($sumVariance / $sumLast) * 100, 2)
                    : ($sumTotal > 0 ? 100.0 : 0.0),
            ];

            $outletGroups[] = [
                'region' => $regionName,
                'rows' => $rows,
                'total' => $regionTotal,
            ];

            $gBudget += $sumBudget;
            $gGt += $sumGt;
            $gLt += $sumLt;
            $gTotal += $sumTotal;
            $gLast += $sumLast;
        }

        $gVariance = $gTotal - $gLast;
        $outletGrand = [
            'rows' => collect($outletGroups)->flatMap(fn ($g) => $g['rows'])->values()->all(),
            'total' => [
                'budget' => $gBudget,
                'rev_gt_15' => $gGt,
                'rev_lt_16' => $gLt,
                'total' => $gTotal,
                'performance' => $gBudget > 0 ? round(($gTotal / $gBudget) * 100, 2) : null,
                'last_month' => $gLast,
                'variance' => $gVariance,
                'growth' => $gLast > 0
                    ? round(($gVariance / $gLast) * 100, 2)
                    : ($gTotal > 0 ? 100.0 : 0.0),
            ],
        ];

        return [$outletGroups, $outletGrand];
    }

    private function formOptions(): array
    {
        return [
            'segment1Options' => SalesLead::SEGMENT_1,
            'segment2Options' => SalesLead::SEGMENT_2,
            'typeOfEvents' => SalesLead::TYPE_OF_EVENTS,
            'statuses' => SalesLead::STATUSES,
            'outlets' => $this->outletOptions(),
        ];
    }

    private function outletOptions()
    {
        return Outlet::query()
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'company_name' => 'required|string|max:255',
            'segment_1' => 'nullable|string|in:'.implode(',', SalesLead::SEGMENT_1),
            'segment_2' => 'nullable|string|in:'.implode(',', SalesLead::SEGMENT_2),
            'type_of_event' => 'nullable|string|in:'.implode(',', SalesLead::TYPE_OF_EVENTS),
            'event_date' => 'nullable|date',
            'pax' => 'nullable|integer|min:0',
            'outlet_id' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'status' => 'required|string|in:'.implode(',', array_keys(SalesLead::STATUSES)),
            'revenue' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:5000',
        ]);
    }

    private function generateNumber(): string
    {
        $prefix = 'SL-'.now()->format('Ym').'-';
        $last = SalesLead::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function visibleQuery()
    {
        $user = auth()->user();
        $isSuperadmin = $this->isSuperadmin($user);

        return SalesLead::query()
            ->when(! $isSuperadmin, function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('created_by', $user->id)
                        ->orWhere('sales_user_id', $user->id);
                });
            });
    }

    private function findVisible($id): SalesLead
    {
        return $this->visibleQuery()
            ->with([
                'outlet:id_outlet,nama_outlet',
                'sales:id,nama_lengkap,email',
                'creator:id,nama_lengkap',
            ])
            ->where('id', $id)
            ->firstOrFail();
    }

    private function canEdit(SalesLead $record, $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return (int) $record->created_by === (int) $user->id
            || (int) $record->sales_user_id === (int) $user->id;
    }

    private function canDelete(SalesLead $record, $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return (int) $record->created_by === (int) $user->id;
    }

    private function isSuperadmin($user): bool
    {
        if (! $user) {
            return false;
        }

        $roleName = strtolower((string) ($user->role_name ?? $user->role ?? ''));
        if (in_array($roleName, ['superadmin', 'super admin', 'administrator'], true)) {
            return true;
        }

        try {
            if (method_exists($user, 'roles')) {
                return $user->roles()->whereIn('name', ['superadmin', 'Superadmin', 'Super Admin'])->exists();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return (int) ($user->id ?? 0) === 1;
    }
}
