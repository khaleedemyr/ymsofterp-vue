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
            'outlets' => $this->evaluationService->outletOptions(),
            'erpScopeOptions' => $this->erpScopeOptions(),
            'defaultPeriod' => now()->format('Y-m'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'period_month' => 'required|regex:/^\d{4}-\d{2}$/',
            'erp_data_scope' => 'required|in:employee_outlet,single_outlet,multiple_outlets,all_outlets',
            'erp_scope_outlet_ids' => 'nullable|array',
            'erp_scope_outlet_ids.*' => 'integer',
        ]);

        $evaluation = $this->evaluationService->createDraft(
            (int) $validated['user_id'],
            $validated['period_month'],
            [
                'erp_data_scope' => $validated['erp_data_scope'],
                'erp_scope_outlet_ids' => $validated['erp_scope_outlet_ids'] ?? [],
            ],
        );

        return redirect()
            ->route('kpi-evaluations.edit', $evaluation->id)
            ->with('success', 'Draft evaluasi KPI berhasil dibuat. Klik Refresh ERP untuk mengambil data.');
    }

    public function show(KpiEvaluation $kpiEvaluation)
    {
        $evaluation = $this->evaluationService->loadForEdit($kpiEvaluation->id);

        return Inertia::render('KpiEvaluations/Show', [
            'evaluation' => $this->formatEvaluation($evaluation),
            'outlets' => $this->evaluationService->outletOptions(),
        ]);
    }

    public function edit(KpiEvaluation $kpiEvaluation)
    {
        $evaluation = $this->evaluationService->loadForEdit($kpiEvaluation->id);

        if ($evaluation->isEditable()) {
            $this->evaluationService->recalculate($evaluation);
            $evaluation = $this->evaluationService->loadForEdit($kpiEvaluation->id);
        }

        return Inertia::render('KpiEvaluations/Edit', [
            'evaluation' => $this->formatEvaluation($evaluation),
            'outlets' => $this->evaluationService->outletOptions(),
            'erpScopeOptions' => $this->erpScopeOptions(),
        ]);
    }

    public function erpDiagnostics(Request $request, KpiEvaluation $kpiEvaluation)
    {
        $evaluation = $this->evaluationService->loadForEdit($kpiEvaluation->id);

        $scope = $request->query('erp_data_scope');
        $outletIds = $request->query('erp_scope_outlet_ids');

        return response()->json(
            $this->evaluationService->erpDiagnostics(
                $evaluation,
                is_string($scope) ? $scope : null,
                is_array($outletIds) ? array_map('intval', $outletIds) : null,
            ),
        );
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
            'items.*.improvement_plan_due_date' => 'nullable|date',
            'employee_comments' => 'nullable|string|max:5000',
            'assessor_comments' => 'nullable|string|max:5000',
            'erp_data_scope' => 'nullable|in:employee_outlet,single_outlet,multiple_outlets,all_outlets',
            'erp_scope_outlet_ids' => 'nullable|array',
            'erp_scope_outlet_ids.*' => 'integer',
        ]);

        $this->evaluationService->saveDraft(
            $kpiEvaluation,
            $validated['parameter_values'] ?? [],
            $validated['items'] ?? [],
            [
                'employee_comments' => $validated['employee_comments'] ?? null,
                'assessor_comments' => $validated['assessor_comments'] ?? null,
                'erp_data_scope' => $validated['erp_data_scope'] ?? null,
                'erp_scope_outlet_ids' => $validated['erp_scope_outlet_ids'] ?? null,
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
                'erp_data_scope' => $preview['template']->erp_data_scope ?? 'employee_outlet',
                'erp_scope_outlet_ids' => $preview['template']->erp_scope_outlet_ids ?? [],
            ] : null,
            'period' => $preview['period'],
            'template_hint' => $preview['template_hint'],
            'erp_scope_suggestion' => $preview['erp_scope_suggestion'],
        ]);
    }

    public function refreshErp(Request $request, KpiEvaluation $kpiEvaluation)
    {
        $validated = $request->validate([
            'erp_data_scope' => 'nullable|in:employee_outlet,single_outlet,multiple_outlets,all_outlets',
            'erp_scope_outlet_ids' => 'nullable|array',
            'erp_scope_outlet_ids.*' => 'integer',
        ]);

        if (isset($validated['erp_data_scope'])) {
            $kpiEvaluation = $this->evaluationService->applyErpScope(
                $kpiEvaluation,
                $validated['erp_data_scope'],
                $validated['erp_scope_outlet_ids'] ?? [],
            );
        }

        $evaluation = $this->evaluationService->refreshErp($kpiEvaluation);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data ERP berhasil di-refresh.',
                'evaluation' => $this->formatEvaluation($evaluation),
            ]);
        }

        return redirect()
            ->route('kpi-evaluations.edit', $kpiEvaluation->id)
            ->with('success', 'Data ERP berhasil di-refresh.');
    }

    public function itemOutletBreakdown(KpiEvaluation $kpiEvaluation, int $item)
    {
        $evaluation = $this->evaluationService->loadForEdit($kpiEvaluation->id);
        $evaluationItem = $evaluation->items->firstWhere('id', $item);

        if (! $evaluationItem) {
            return response()->json(['message' => 'Item KPI tidak ditemukan.'], 404);
        }

        @set_time_limit(180);

        return response()->json(
            $this->evaluationService->getItemOutletBreakdown($evaluation, $evaluationItem),
        );
    }

    public function bulkOutletBreakdowns(KpiEvaluation $kpiEvaluation)
    {
        $evaluation = $this->evaluationService->loadForEdit($kpiEvaluation->id);

        @set_time_limit(300);

        $bulk = $this->evaluationService->getBulkItemOutletBreakdowns($evaluation);

        return response()->json($bulk);
    }

    public function recalculate(Request $request, KpiEvaluation $kpiEvaluation)
    {
        if (!$kpiEvaluation->isEditable()) {
            return response()->json(['message' => 'Evaluasi sudah disubmit.'], 422);
        }

        $validated = $request->validate([
            'parameter_values' => 'nullable|array',
            'parameter_values.*.id' => 'required|integer',
            'parameter_values.*.manual_value' => 'nullable|numeric',
            'parameter_values.*.is_overridden' => 'nullable|boolean',
            'parameter_values.*.override_reason' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*.id' => 'required|integer',
            'items.*.improvement_plan' => 'nullable|string|max:2000',
            'items.*.improvement_plan_due_date' => 'nullable|date',
        ]);

        $evaluation = $this->evaluationService->recalculateFromForm(
            $kpiEvaluation,
            $validated['parameter_values'] ?? [],
            $validated['items'] ?? [],
        );

        return response()->json([
            'success' => true,
            'evaluation' => $this->formatEvaluation($evaluation),
        ]);
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
                'id_outlet' => $u->id_outlet ? (int) $u->id_outlet : null,
                'label' => trim($u->nama_lengkap . ' — ' . ($u->nama_jabatan ?? '-') . ' @ ' . ($u->nama_outlet ?? '-')),
                'nama_lengkap' => $u->nama_lengkap,
                'nama_jabatan' => $u->nama_jabatan,
                'nama_outlet' => $u->nama_outlet,
            ])
            ->all();
    }

    protected function erpScopeOptions(): array
    {
        return [
            ['value' => 'employee_outlet', 'label' => 'Outlet karyawan (default)'],
            ['value' => 'single_outlet', 'label' => '1 outlet (pilih)'],
            ['value' => 'multiple_outlets', 'label' => 'Beberapa outlet (jumlahkan)'],
            ['value' => 'all_outlets', 'label' => 'Semua outlet operasional'],
        ];
    }

    protected function formatEvaluation(KpiEvaluation $evaluation): array
    {
        $periodMonth = (string) $evaluation->period_month;
        $enrichedItems = $this->evaluationService->enrichEvaluationItems($evaluation->items, $periodMonth);

        $strategies = $evaluation->items
            ->groupBy('key_strategy_name')
            ->map(function ($items, $strategyName) use ($periodMonth) {
                $first = $items->first();

                return [
                    'name' => $strategyName,
                    'weight_percent' => $first->strategy_weight_percent,
                    'items' => $this->evaluationService->enrichEvaluationItems($items, $periodMonth),
                ];
            })
            ->values()
            ->all();

        $periodInfo = $this->evaluationService->buildKpiPeriodInfo($periodMonth);

        return [
            'id' => $evaluation->id,
            'evaluation_code' => $evaluation->evaluation_code,
            'user_id' => $evaluation->user_id,
            'employee_name' => $evaluation->employee_name,
            'jabatan_name' => $evaluation->jabatan_name,
            'outlet_name' => $evaluation->outlet_name,
            'division_name' => $evaluation->division_name,
            'erp_data_scope' => $evaluation->erp_data_scope ?? 'employee_outlet',
            'erp_scope_outlet_ids' => $evaluation->erp_scope_outlet_ids ?? [],
            'scope_outlet_count' => count($this->evaluationService->resolveErpOutletIds($evaluation)),
            'period_month' => $evaluation->period_month,
            'period_start' => $periodInfo['start_date'],
            'period_end' => $periodInfo['end_date'],
            'period_info' => $periodInfo,
            'eval_status' => $evaluation->eval_status,
            'total_score' => $evaluation->total_score,
            'scoring_rules' => $evaluation->scoring_rules,
            'employee_comments' => $evaluation->employee_comments,
            'assessor_comments' => $evaluation->assessor_comments,
            'submitted_at' => $evaluation->submitted_at?->toDateTimeString(),
            'updated_at' => $evaluation->updated_at?->toDateTimeString(),
            'is_editable' => $evaluation->isEditable(),
            'template' => $evaluation->template ? [
                'id' => $evaluation->template->id,
                'code' => $evaluation->template->code,
                'name' => $evaluation->template->name,
                'version' => $evaluation->template->version,
            ] : null,
            'parameter_values' => $this->evaluationService->formatParameterValuesForEdit(
                $evaluation->parameterValues,
                $periodMonth,
                $evaluation->items,
            ),
            'strategies' => $strategies,
            'items' => $enrichedItems,
        ];
    }
}
