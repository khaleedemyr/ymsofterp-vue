<?php

namespace App\Http\Controllers;

use App\Models\KpiEvaluation;
use App\Models\User;
use App\Services\KpiEvaluationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KpiEvaluationController extends Controller
{
    public function __construct(
        protected KpiEvaluationService $evaluationService,
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $periodMonth = $request->input('period_month');
        $evalStatus = $request->input('eval_status');

        $query = KpiEvaluation::with('template:id,code,name')
            ->orderByDesc('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('evaluation_code', 'like', "%{$search}%")
                    ->orWhere('employee_name', 'like', "%{$search}%")
                    ->orWhere('jabatan_name', 'like', "%{$search}%")
                    ->orWhere('outlet_name', 'like', "%{$search}%");
            });
        }

        if ($periodMonth) {
            $query->where('period_month', $periodMonth);
        }

        if ($evalStatus && in_array($evalStatus, ['draft', 'submitted', 'locked'], true)) {
            $query->where('eval_status', $evalStatus);
        }

        $evaluations = $query->paginate(15)->withQueryString();

        return Inertia::render('KpiEvaluations/Index', [
            'evaluations' => $evaluations,
            'filters' => [
                'search' => $search,
                'period_month' => $periodMonth,
                'eval_status' => $evalStatus,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('KpiEvaluations/Create', [
            'employees' => $this->employeeOptions(),
            'defaultPeriod' => now()->format('Y-m'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'period_month' => 'required|regex:/^\d{4}-\d{2}$/',
        ]);

        $evaluation = $this->evaluationService->createDraft(
            (int) $validated['user_id'],
            $validated['period_month'],
        );

        return redirect()
            ->route('kpi-evaluations.edit', $evaluation->id)
            ->with('success', 'Draft evaluasi KPI berhasil dibuat.');
    }

    public function show(KpiEvaluation $kpiEvaluation)
    {
        $evaluation = $this->evaluationService->loadForEdit($kpiEvaluation->id);

        return Inertia::render('KpiEvaluations/Show', [
            'evaluation' => $this->formatEvaluation($evaluation),
        ]);
    }

    public function edit(KpiEvaluation $kpiEvaluation)
    {
        $evaluation = $this->evaluationService->loadForEdit($kpiEvaluation->id);

        return Inertia::render('KpiEvaluations/Edit', [
            'evaluation' => $this->formatEvaluation($evaluation),
            'erpDiagnostics' => $this->evaluationService->erpDiagnostics($evaluation),
        ]);
    }

    public function update(Request $request, KpiEvaluation $kpiEvaluation)
    {
        $validated = $request->validate([
            'parameter_values' => 'array',
            'parameter_values.*.id' => 'required|integer',
            'parameter_values.*.manual_value' => 'nullable|numeric',
            'parameter_values.*.is_overridden' => 'nullable|boolean',
            'parameter_values.*.override_reason' => 'nullable|string|max:1000',
            'items' => 'array',
            'items.*.id' => 'required|integer',
            'items.*.improvement_plan' => 'nullable|string|max:2000',
            'employee_comments' => 'nullable|string|max:5000',
            'assessor_comments' => 'nullable|string|max:5000',
        ]);

        $this->evaluationService->saveDraft(
            $kpiEvaluation,
            $validated['parameter_values'] ?? [],
            $validated['items'] ?? [],
            [
                'employee_comments' => $validated['employee_comments'] ?? null,
                'assessor_comments' => $validated['assessor_comments'] ?? null,
            ],
        );

        return redirect()
            ->route('kpi-evaluations.edit', $kpiEvaluation->id)
            ->with('success', 'Evaluasi berhasil disimpan.');
    }

    public function destroy(KpiEvaluation $kpiEvaluation)
    {
        $this->evaluationService->deleteDraft($kpiEvaluation);

        return redirect()
            ->route('kpi-evaluations.index')
            ->with('success', 'Draft evaluasi dihapus.');
    }

    public function previewEmployee(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'period_month' => 'required|regex:/^\d{4}-\d{2}$/',
        ]);

        $preview = $this->evaluationService->previewEmployee(
            (int) $validated['user_id'],
            $validated['period_month'],
        );

        return response()->json([
            'user' => $preview['user'],
            'template' => $preview['template'] ? [
                'id' => $preview['template']->id,
                'code' => $preview['template']->code,
                'name' => $preview['template']->name,
                'version' => $preview['template']->version,
            ] : null,
            'period' => $preview['period'],
        ]);
    }

    public function refreshErp(KpiEvaluation $kpiEvaluation)
    {
        $this->evaluationService->refreshErp($kpiEvaluation);

        return redirect()
            ->route('kpi-evaluations.edit', $kpiEvaluation->id)
            ->with('success', 'Data ERP berhasil di-refresh.');
    }

    public function submit(KpiEvaluation $kpiEvaluation)
    {
        $this->evaluationService->submit($kpiEvaluation);

        return redirect()
            ->route('kpi-evaluations.show', $kpiEvaluation->id)
            ->with('success', 'Evaluasi KPI berhasil disubmit.');
    }

    protected function employeeOptions(): array
    {
        return User::query()
            ->select(
                'users.id',
                'users.nama_lengkap',
                'users.id_jabatan',
                'users.id_outlet',
                'jabatan.nama_jabatan',
                'outlet.nama_outlet',
            )
            ->leftJoin('tbl_data_jabatan as jabatan', 'users.id_jabatan', '=', 'jabatan.id_jabatan')
            ->leftJoin('tbl_data_outlet as outlet', 'users.id_outlet', '=', 'outlet.id_outlet')
            ->where('users.status', 'A')
            ->whereNotNull('users.id_jabatan')
            ->orderBy('users.nama_lengkap')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'label' => trim($u->nama_lengkap . ' — ' . ($u->nama_jabatan ?? '-') . ' @ ' . ($u->nama_outlet ?? '-')),
                'nama_lengkap' => $u->nama_lengkap,
                'nama_jabatan' => $u->nama_jabatan,
                'nama_outlet' => $u->nama_outlet,
            ])
            ->all();
    }

    protected function formatEvaluation(KpiEvaluation $evaluation): array
    {
        $strategies = $evaluation->items
            ->groupBy('key_strategy_name')
            ->map(function ($items, $strategyName) {
                $first = $items->first();

                return [
                    'name' => $strategyName,
                    'weight_percent' => $first->strategy_weight_percent,
                    'items' => $items->values()->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $evaluation->id,
            'evaluation_code' => $evaluation->evaluation_code,
            'user_id' => $evaluation->user_id,
            'employee_name' => $evaluation->employee_name,
            'jabatan_name' => $evaluation->jabatan_name,
            'outlet_name' => $evaluation->outlet_name,
            'division_name' => $evaluation->division_name,
            'period_month' => $evaluation->period_month,
            'period_start' => $evaluation->period_start?->toDateString(),
            'period_end' => $evaluation->period_end?->toDateString(),
            'eval_status' => $evaluation->eval_status,
            'total_score' => $evaluation->total_score,
            'scoring_rules' => $evaluation->scoring_rules,
            'employee_comments' => $evaluation->employee_comments,
            'assessor_comments' => $evaluation->assessor_comments,
            'submitted_at' => $evaluation->submitted_at?->toDateTimeString(),
            'is_editable' => $evaluation->isEditable(),
            'template' => $evaluation->template ? [
                'id' => $evaluation->template->id,
                'code' => $evaluation->template->code,
                'name' => $evaluation->template->name,
                'version' => $evaluation->template->version,
            ] : null,
            'parameter_values' => $evaluation->parameterValues,
            'strategies' => $strategies,
            'items' => $evaluation->items,
        ];
    }
}
