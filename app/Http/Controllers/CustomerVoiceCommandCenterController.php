<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\User;
use App\Services\FeedbackCaseIngestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerVoiceCommandCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $query = DB::table('feedback_cases as c')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'c.assigned_to')
            ->select([
                'c.id',
                'c.source_type',
                'c.source_ref',
                'c.id_outlet',
                'o.nama_outlet',
                'c.author_name',
                'c.customer_contact',
                'c.event_at',
                'c.severity',
                'c.summary_id',
                'c.raw_text',
                'c.risk_score',
                'c.status',
                'c.assigned_to',
                'assignee.nama_lengkap as assigned_to_name',
                'c.due_at',
                'c.resolved_at',
                'c.created_at',
            ]);

        if ($request->filled('status')) {
            $query->where('c.status', $request->input('status'));
        }
        if ($request->filled('severity')) {
            $query->where('c.severity', $request->input('severity'));
        }
        if ($request->filled('source_type')) {
            $query->where('c.source_type', $request->input('source_type'));
        }
        if ($request->filled('id_outlet')) {
            $query->where('c.id_outlet', (int) $request->input('id_outlet'));
        }
        if ($request->filled('q')) {
            $keyword = '%'.trim((string) $request->input('q')).'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('c.author_name', 'like', $keyword)
                    ->orWhere('c.summary_id', 'like', $keyword)
                    ->orWhere('c.raw_text', 'like', $keyword)
                    ->orWhere('o.nama_outlet', 'like', $keyword);
            });
        }
        if ($request->boolean('overdue_only')) {
            $query->whereIn('c.status', ['new', 'in_progress'])
                ->whereNotNull('c.due_at')
                ->where('c.due_at', '<', now());
        }

        $cases = $query
            ->orderByDesc('c.risk_score')
            ->orderByDesc('c.event_at')
            ->paginate(20)
            ->withQueryString();

        $caseIds = collect($cases->items())->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $activities = [];
        if ($caseIds !== []) {
            $activityRows = DB::table('feedback_case_activities as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
                ->whereIn('a.case_id', $caseIds)
                ->orderByDesc('a.id')
                ->get([
                    'a.id',
                    'a.case_id',
                    'a.activity_type',
                    'a.from_status',
                    'a.to_status',
                    'a.note',
                    'a.created_at',
                    'u.nama_lengkap as actor_name',
                ]);

            foreach ($activityRows as $row) {
                $caseId = (int) $row->case_id;
                if (! isset($activities[$caseId])) {
                    $activities[$caseId] = [];
                }
                if (count($activities[$caseId]) < 8) {
                    $activities[$caseId][] = $row;
                }
            }
        }

        $summary = [
            'total_cases' => (int) DB::table('feedback_cases')->count(),
            'open_cases' => (int) DB::table('feedback_cases')->whereIn('status', ['new', 'in_progress'])->count(),
            'severe_open' => (int) DB::table('feedback_cases')->whereIn('status', ['new', 'in_progress'])->where('severity', 'severe')->count(),
            'overdue_open' => (int) DB::table('feedback_cases')
                ->whereIn('status', ['new', 'in_progress'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];

        $firstResponseAvgMinutes = DB::table('feedback_cases')
            ->whereNotNull('first_response_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, event_at, first_response_at)) AS avg_minutes')
            ->value('avg_minutes');
        $firstResponseMedianMinutes = $this->medianMinutesBetween('event_at', 'first_response_at');

        $resolutionAvgMinutes = DB::table('feedback_cases')
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, event_at, resolved_at)) AS avg_minutes')
            ->value('avg_minutes');

        $slaResolvedBase = DB::table('feedback_cases')
            ->whereNotNull('due_at')
            ->whereNotNull('resolved_at');
        $slaResolvedTotal = (int) (clone $slaResolvedBase)->count();
        $slaResolvedOnTime = (int) (clone $slaResolvedBase)
            ->whereColumn('resolved_at', '<=', 'due_at')
            ->count();
        $slaCompliancePct = $slaResolvedTotal > 0
            ? round(($slaResolvedOnTime / $slaResolvedTotal) * 100, 2)
            : null;

        $repeatBase = DB::table('feedback_cases')
            ->whereNotNull('summary_id')
            ->where('summary_id', '!=', '')
            ->where('event_at', '>=', now()->subDays(30));
        $repeatTotal = (int) (clone $repeatBase)->count();
        $repeatGrouped = (clone $repeatBase)
            ->select('summary_id')
            ->selectRaw('COUNT(*) AS cnt')
            ->groupBy('summary_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        $repeatCases = (int) $repeatGrouped->sum('cnt');
        $repeatIssueRatePct = $repeatTotal > 0
            ? round(($repeatCases / $repeatTotal) * 100, 2)
            : null;

        $negativeByOutlet = DB::table('feedback_cases as c')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
            ->whereIn('c.severity', ['mild_negative', 'negative', 'severe'])
            ->where('c.event_at', '>=', now()->subDays(30))
            ->groupBy('c.id_outlet', 'o.nama_outlet')
            ->selectRaw('c.id_outlet, o.nama_outlet, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(1)
            ->first();

        $kpis = [
            'first_response_median_minutes' => $firstResponseMedianMinutes,
            'first_response_avg_minutes' => $firstResponseAvgMinutes !== null ? (float) $firstResponseAvgMinutes : null,
            'resolution_avg_minutes' => $resolutionAvgMinutes !== null ? (float) $resolutionAvgMinutes : null,
            'sla_compliance_pct' => $slaCompliancePct,
            'repeat_issue_rate_pct' => $repeatIssueRatePct,
            'repeat_issue_window_days' => 30,
            'negative_top_outlet_30d' => $negativeByOutlet ? [
                'id_outlet' => $negativeByOutlet->id_outlet !== null ? (int) $negativeByOutlet->id_outlet : null,
                'nama_outlet' => (string) ($negativeByOutlet->nama_outlet ?? '-'),
                'total' => (int) ($negativeByOutlet->total ?? 0),
            ] : null,
        ];

        $trendDays = 14;
        $trendStart = now()->subDays($trendDays - 1)->startOfDay();
        $dailyRows = DB::table('feedback_cases')
            ->selectRaw('DATE(event_at) as d')
            ->selectRaw('COUNT(*) as total_cases')
            ->selectRaw("SUM(CASE WHEN severity IN ('mild_negative','negative','severe') THEN 1 ELSE 0 END) as negative_cases")
            ->where('event_at', '>=', $trendStart)
            ->groupBy(DB::raw('DATE(event_at)'))
            ->orderBy('d')
            ->get();

        $dailyMap = [];
        foreach ($dailyRows as $r) {
            $dailyMap[(string) $r->d] = [
                'total_cases' => (int) ($r->total_cases ?? 0),
                'negative_cases' => (int) ($r->negative_cases ?? 0),
            ];
        }

        $trend = [];
        for ($i = $trendDays - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $trend[] = [
                'date' => $d,
                'total_cases' => $dailyMap[$d]['total_cases'] ?? 0,
                'negative_cases' => $dailyMap[$d]['negative_cases'] ?? 0,
            ];
        }

        $perfWindowDays = 30;
        $perfSince = now()->subDays($perfWindowDays)->startOfDay();

        $picRows = DB::table('feedback_cases as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.assigned_to')
            ->whereNotNull('c.assigned_to')
            ->where('c.event_at', '>=', $perfSince)
            ->groupBy('c.assigned_to', 'u.nama_lengkap')
            ->selectRaw('c.assigned_to')
            ->selectRaw('u.nama_lengkap as assignee_name')
            ->selectRaw('COUNT(*) as total_cases')
            ->selectRaw("SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved_cases")
            ->selectRaw("SUM(CASE WHEN c.status IN ('new','in_progress') THEN 1 ELSE 0 END) as open_cases")
            ->selectRaw("AVG(CASE WHEN c.first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, c.event_at, c.first_response_at) END) as avg_first_response_minutes")
            ->selectRaw("SUM(CASE WHEN c.due_at IS NOT NULL AND c.resolved_at IS NOT NULL THEN 1 ELSE 0 END) as sla_total")
            ->selectRaw("SUM(CASE WHEN c.due_at IS NOT NULL AND c.resolved_at IS NOT NULL AND c.resolved_at <= c.due_at THEN 1 ELSE 0 END) as sla_on_time")
            ->orderByDesc('resolved_cases')
            ->orderBy('avg_first_response_minutes')
            ->limit(8)
            ->get();

        $picPerformance = collect($picRows)->map(function ($r) {
            $slaTotal = (int) ($r->sla_total ?? 0);
            $slaOnTime = (int) ($r->sla_on_time ?? 0);

            return [
                'assignee_id' => (int) ($r->assigned_to ?? 0),
                'assignee_name' => (string) ($r->assignee_name ?? '-'),
                'total_cases' => (int) ($r->total_cases ?? 0),
                'resolved_cases' => (int) ($r->resolved_cases ?? 0),
                'open_cases' => (int) ($r->open_cases ?? 0),
                'avg_first_response_minutes' => $r->avg_first_response_minutes !== null ? round((float) $r->avg_first_response_minutes, 2) : null,
                'sla_compliance_pct' => $slaTotal > 0 ? round(($slaOnTime / $slaTotal) * 100, 2) : null,
            ];
        })->values()->all();

        $outletRows = DB::table('feedback_cases as c')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
            ->where('c.event_at', '>=', $perfSince)
            ->groupBy('c.id_outlet', 'o.nama_outlet')
            ->selectRaw('c.id_outlet')
            ->selectRaw('o.nama_outlet as outlet_name')
            ->selectRaw('COUNT(*) as total_cases')
            ->selectRaw("SUM(CASE WHEN c.severity IN ('mild_negative','negative','severe') THEN 1 ELSE 0 END) as negative_cases")
            ->selectRaw("SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved_cases")
            ->selectRaw("SUM(CASE WHEN c.status IN ('new','in_progress') THEN 1 ELSE 0 END) as open_cases")
            ->selectRaw("SUM(CASE WHEN c.due_at IS NOT NULL AND c.resolved_at IS NOT NULL THEN 1 ELSE 0 END) as sla_total")
            ->selectRaw("SUM(CASE WHEN c.due_at IS NOT NULL AND c.resolved_at IS NOT NULL AND c.resolved_at <= c.due_at THEN 1 ELSE 0 END) as sla_on_time")
            ->orderByDesc('negative_cases')
            ->limit(8)
            ->get();

        $outletPerformance = collect($outletRows)->map(function ($r) {
            $totalCases = (int) ($r->total_cases ?? 0);
            $negativeCases = (int) ($r->negative_cases ?? 0);
            $slaTotal = (int) ($r->sla_total ?? 0);
            $slaOnTime = (int) ($r->sla_on_time ?? 0);

            return [
                'id_outlet' => $r->id_outlet !== null ? (int) $r->id_outlet : null,
                'outlet_name' => (string) ($r->outlet_name ?? '-'),
                'total_cases' => $totalCases,
                'negative_cases' => $negativeCases,
                'negative_rate_pct' => $totalCases > 0 ? round(($negativeCases / $totalCases) * 100, 2) : null,
                'resolved_cases' => (int) ($r->resolved_cases ?? 0),
                'open_cases' => (int) ($r->open_cases ?? 0),
                'sla_compliance_pct' => $slaTotal > 0 ? round(($slaOnTime / $slaTotal) * 100, 2) : null,
            ];
        })->values()->all();

        $outlets = Outlet::where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);

        $assignees = User::active()
            ->whereIn('id_jabatan', [155, 173])
            ->orderBy('nama_lengkap')
            ->limit(300)
            ->get(['id', 'nama_lengkap', 'id_outlet', 'id_jabatan']);

        return Inertia::render('CustomerVoiceCommandCenter/Index', [
            'summary' => $summary,
            'kpis' => $kpis,
            'trend' => $trend,
            'picPerformance' => $picPerformance,
            'outletPerformance' => $outletPerformance,
            'perfWindowDays' => $perfWindowDays,
            'cases' => $cases,
            'outlets' => $outlets,
            'assignees' => $assignees,
            'activities' => $activities,
            'filters' => [
                'status' => $request->input('status'),
                'severity' => $request->input('severity'),
                'source_type' => $request->input('source_type'),
                'id_outlet' => $request->input('id_outlet'),
                'q' => $request->input('q'),
                'overdue_only' => $request->boolean('overdue_only'),
            ],
        ]);
    }

    public function sync(Request $request, FeedbackCaseIngestionService $ingestion): RedirectResponse
    {
        $result = $ingestion->ingestAll(2500);

        $message = 'Sync selesai. '
            .'Google/Instagram: '.$result['google_instagram']['upserted'].' baris, '
            .'Guest Comment: '.$result['guest_comment']['upserted'].' baris.';

        return redirect()
            ->route('customer-voice-command-center.index')
            ->with('success', $message);
    }

    public function updateCase(Request $request, int $id): RedirectResponse
    {
        $payload = $request->validate([
            'status' => 'required|string|in:new,in_progress,resolved,ignored',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $row = DB::table('feedback_cases')->where('id', $id)->first();
        if (! $row) {
            return redirect()->route('customer-voice-command-center.index')
                ->with('error', 'Case tidak ditemukan.');
        }

        $now = now();
        $fromStatus = (string) ($row->status ?? 'new');
        $toStatus = (string) $payload['status'];
        $oldAssignee = $row->assigned_to !== null ? (int) $row->assigned_to : null;
        $newAssignee = isset($payload['assigned_to']) && $payload['assigned_to'] !== null
            ? (int) $payload['assigned_to']
            : null;

        $update = [
            'status' => $toStatus,
            'assigned_to' => $newAssignee,
            'updated_at' => $now,
        ];
        if ($toStatus === 'resolved') {
            $update['resolved_at'] = $now;
            if ($row->first_response_at === null) {
                $update['first_response_at'] = $now;
            }
        } elseif ($toStatus === 'in_progress' && $row->first_response_at === null) {
            $update['first_response_at'] = $now;
            $update['resolved_at'] = null;
        } elseif ($toStatus !== 'resolved') {
            $update['resolved_at'] = null;
        }

        DB::transaction(function () use ($id, $update, $request, $fromStatus, $toStatus, $oldAssignee, $newAssignee, $now) {
            DB::table('feedback_cases')->where('id', $id)->update($update);

            if ($fromStatus !== $toStatus) {
                DB::table('feedback_case_activities')->insert([
                    'case_id' => $id,
                    'activity_type' => 'status_changed',
                    'actor_user_id' => $request->user()->id ?? null,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'note' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($oldAssignee !== $newAssignee) {
                DB::table('feedback_case_activities')->insert([
                    'case_id' => $id,
                    'activity_type' => 'assigned',
                    'actor_user_id' => $request->user()->id ?? null,
                    'from_status' => null,
                    'to_status' => null,
                    'note' => 'Assign PIC: '.($newAssignee ?? 'unassigned'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return redirect()
            ->back()
            ->with('success', 'Case diperbarui.');
    }

    public function addNote(Request $request, int $id): RedirectResponse
    {
        $payload = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $exists = DB::table('feedback_cases')->where('id', $id)->exists();
        if (! $exists) {
            return redirect()->route('customer-voice-command-center.index')
                ->with('error', 'Case tidak ditemukan.');
        }

        DB::table('feedback_case_activities')->insert([
            'case_id' => $id,
            'activity_type' => 'note',
            'actor_user_id' => $request->user()->id ?? null,
            'from_status' => null,
            'to_status' => null,
            'note' => trim((string) $payload['note']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Catatan tersimpan.');
    }

    private function medianMinutesBetween(string $startColumn, string $endColumn): ?float
    {
        $rows = DB::table('feedback_cases')
            ->whereNotNull($startColumn)
            ->whereNotNull($endColumn)
            ->selectRaw("TIMESTAMPDIFF(MINUTE, {$startColumn}, {$endColumn}) as v")
            ->whereRaw("TIMESTAMPDIFF(MINUTE, {$startColumn}, {$endColumn}) >= 0")
            ->orderBy('v')
            ->pluck('v')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $count = count($rows);
        if ($count === 0) {
            return null;
        }
        $mid = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $rows[$mid];
        }

        return round((($rows[$mid - 1] + $rows[$mid]) / 2), 2);
    }
}
