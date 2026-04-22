<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
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

        $cases = $query
            ->orderByDesc('c.risk_score')
            ->orderByDesc('c.event_at')
            ->paginate(20)
            ->withQueryString();

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

        return Inertia::render('CustomerVoiceCommandCenter/Index', [
            'summary' => $summary,
            'cases' => $cases,
            'outlets' => $outlets,
            'filters' => [
                'status' => $request->input('status'),
                'severity' => $request->input('severity'),
                'source_type' => $request->input('source_type'),
                'id_outlet' => $request->input('id_outlet'),
                'q' => $request->input('q'),
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
}
