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
}
