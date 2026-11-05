<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Services\JustAcademy\NegativeCommentRadarService;
use App\Services\RoleMenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NegativeCommentRadarController extends Controller
{
    public function __construct(
        protected NegativeCommentRadarService $radar,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $filters = $this->radar->normalizeFilters($request->only(['date_from', 'date_to', 'id_outlet']));
        $runId = $request->filled('run_id') ? (int) $request->input('run_id') : null;
        $activeNiche = $request->filled('niche') ? (string) $request->input('niche') : null;
        if ($activeNiche && ! isset(NegativeCommentRadarService::NICHES[$activeNiche])) {
            $activeNiche = null;
        }

        $run = $this->radar->latestRun($runId);
        $payload = $this->radar->presentRun($run, $activeNiche);

        $outlets = Outlet::query()
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet'])
            ->map(fn ($o) => [
                'id' => (int) $o->id_outlet,
                'name' => (string) $o->nama_outlet,
            ])
            ->values()
            ->all();

        $recentRuns = $this->radar->recentRuns(10)->map(fn ($r) => [
            'id' => (int) $r->id,
            'date_from' => (string) $r->date_from,
            'date_to' => (string) $r->date_to,
            'status' => (string) $r->status,
            'classified_count' => (int) $r->classified_count,
            'created_at' => $r->created_at,
        ])->values()->all();

        $userId = (int) $request->user()->id;
        $allowed = RoleMenuService::allowedMenuCodesForInternalUser($userId);
        // create permission = menu code present + user can create — check via permission table loosely:
        // If they can see the menu they can view; create gated by having *_create if present in role perms.
        $canScan = $this->userCanScan($userId);

        return Inertia::render('JustAcademy/NegativeCommentRadar/Index', [
            'tablesReady' => $this->radar->tablesReady(),
            'filters' => $filters,
            'nicheCatalog' => $this->radar->nicheCatalog(),
            'payload' => $payload,
            'outlets' => $outlets,
            'recentRuns' => $recentRuns,
            'canScan' => $canScan || in_array('just_academy_negative_comment_radar', $allowed, true),
            'maxCases' => 300,
        ]);
    }

    public function analyze(Request $request): RedirectResponse
    {
        $this->authorizeView($request);

        if (! $this->userCanScan((int) $request->user()->id)) {
            // Allow analyze if they have view on the menu (trainers typically have view+create)
            $allowed = RoleMenuService::allowedMenuCodesForInternalUser((int) $request->user()->id);
            if (! in_array('just_academy_negative_comment_radar', $allowed, true)) {
                abort(403);
            }
        }

        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'id_outlet' => 'nullable|integer',
        ]);

        $filters = $this->radar->normalizeFilters($validated);

        try {
            $run = $this->radar->runAnalysis($filters, (int) $request->user()->id);
        } catch (\Throwable $e) {
            return redirect()
                ->route('just-academy.negative-comment-radar.index', $filters)
                ->with('error', 'Gagal menjalankan radar: '.$e->getMessage());
        }

        return redirect()
            ->route('just-academy.negative-comment-radar.index', array_merge($filters, [
                'run_id' => $run->id,
            ]))
            ->with('success', 'Radar selesai: '.(int) $run->classified_count.' komentar negatif dikelompokkan AI.');
    }

    private function authorizeView(Request $request): void
    {
        $allowed = RoleMenuService::allowedMenuCodesForInternalUser((int) $request->user()->id);
        if (! in_array('just_academy_negative_comment_radar', $allowed, true)) {
            // Soft-allow if Just Academy dashboard is available (during rollout before permission grant)
            // Prefer strict: abort 403
            abort(403, 'Tidak ada akses menu Negative Comment Radar.');
        }
    }

    private function userCanScan(int $userId): bool
    {
        return \Illuminate\Support\Facades\DB::table('users as u')
            ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
            ->join('erp_role_permission as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
            ->where('u.id', $userId)
            ->whereIn('p.code', [
                'just_academy_negative_comment_radar_create',
                'just_academy_negative_comment_radar_view',
            ])
            ->exists();
    }
}
