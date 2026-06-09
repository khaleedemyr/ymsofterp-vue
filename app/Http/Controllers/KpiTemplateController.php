<?php

namespace App\Http\Controllers;

use App\Models\Jabatan;
use App\Models\KpiKeyStrategy;
use App\Models\KpiParameter;
use App\Models\KpiTemplate;
use App\Services\KpiTemplateService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KpiTemplateController extends Controller
{
    public function __construct(
        protected KpiTemplateService $templateService
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'A');
        $templateStatus = $request->input('template_status');

        $query = KpiTemplate::with(['positions.jabatan:id_jabatan,nama_jabatan'])
            ->withCount('strategies');

        if ($status === 'A' || $status === 'N') {
            $query->where('status', $status);
        }

        if ($templateStatus) {
            $query->where('template_status', $templateStatus);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $templates = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('KpiTemplates/Index', [
            'templates' => $templates,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'template_status' => $templateStatus,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('KpiTemplates/Edit', [
            'template' => null,
            'formData' => $this->builderFormData(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTemplate($request);
        $this->templateService->save($validated);

        return redirect()->route('kpi-templates.index')->with('success', 'Template KPI berhasil dibuat.');
    }

    public function show(KpiTemplate $kpiTemplate)
    {
        $template = $this->templateService->loadForEdit($kpiTemplate->id);

        return Inertia::render('KpiTemplates/Show', [
            'template' => $this->formatTemplate($template),
        ]);
    }

    public function edit(KpiTemplate $kpiTemplate)
    {
        $template = $this->templateService->loadForEdit($kpiTemplate->id);

        return Inertia::render('KpiTemplates/Edit', [
            'template' => $this->formatTemplate($template),
            'formData' => $this->builderFormData(),
        ]);
    }

    public function update(Request $request, KpiTemplate $kpiTemplate)
    {
        $validated = $this->validateTemplate($request, $kpiTemplate->id);
        $this->templateService->save($validated, $kpiTemplate);

        return redirect()->route('kpi-templates.edit', $kpiTemplate->id)->with('success', 'Template KPI berhasil disimpan.');
    }

    public function destroy(KpiTemplate $kpiTemplate)
    {
        $kpiTemplate->update(['status' => 'N', 'template_status' => 'archived']);

        return redirect()->back()->with('success', 'Template KPI berhasil dinonaktifkan.');
    }

    public function publish(KpiTemplate $kpiTemplate)
    {
        $this->templateService->publish($kpiTemplate);

        return redirect()->back()->with('success', 'Template KPI berhasil dipublish.');
    }

    protected function validateTemplate(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:kpi_templates,code';
        if ($ignoreId) {
            $uniqueRule .= ',' . $ignoreId;
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:50', $uniqueRule],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_status' => 'required|in:draft,active,archived',
            'status' => 'required|in:A,N',
            'scoring_rules' => 'nullable|array',
            'jabatan_ids' => 'nullable|array',
            'jabatan_ids.*' => 'integer|exists:tbl_data_jabatan,id_jabatan',
            'strategies' => 'required|array|min:1',
            'strategies.*.id' => 'nullable|integer',
            'strategies.*.kpi_key_strategy_id' => 'required|integer|exists:kpi_key_strategies,id',
            'strategies.*.weight_percent' => 'required|numeric|min:0|max:100',
            'strategies.*.sort_order' => 'nullable|integer|min:0',
            'strategies.*.items' => 'nullable|array',
            'strategies.*.items.*.id' => 'nullable|integer',
            'strategies.*.items.*.name' => 'required|string|max:255',
            'strategies.*.items.*.description' => 'nullable|string',
            'strategies.*.items.*.weight_percent' => 'required|numeric|min:0|max:100',
            'strategies.*.items.*.target_value' => 'nullable|string|max:100',
            'strategies.*.items.*.target_direction' => 'nullable|in:higher_better,lower_better',
            'strategies.*.items.*.frequency' => 'nullable|string|max:50',
            'strategies.*.items.*.formula' => 'nullable|string',
            'strategies.*.items.*.scoring_levels' => 'nullable|array',
            'strategies.*.items.*.sort_order' => 'nullable|integer|min:0',
            'strategies.*.items.*.parameter_ids' => 'nullable|array',
            'strategies.*.items.*.parameter_ids.*' => 'integer|exists:kpi_parameters,id',
        ]);
    }

    protected function builderFormData(): array
    {
        return [
            'jabatans' => Jabatan::active()->orderBy('nama_jabatan')->get(['id_jabatan', 'nama_jabatan']),
            'keyStrategies' => KpiKeyStrategy::active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'parameters' => KpiParameter::active()->orderBy('code')->get(['id', 'code', 'name', 'source_type', 'scope_type']),
            'defaultScoringRules' => $this->templateService->defaultScoringRules(),
            'targetDirections' => [
                ['value' => 'higher_better', 'label' => 'Higher is better'],
                ['value' => 'lower_better', 'label' => 'Lower is better'],
            ],
            'frequencies' => [
                ['value' => 'monthly', 'label' => 'Monthly'],
                ['value' => 'quarterly', 'label' => 'Quarterly'],
            ],
        ];
    }

    protected function formatTemplate(KpiTemplate $template): array
    {
        return [
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'description' => $template->description,
            'version' => $template->version,
            'template_status' => $template->template_status,
            'scoring_rules' => $template->scoring_rules ?? $this->templateService->defaultScoringRules(),
            'status' => $template->status,
            'jabatan_ids' => $template->positions->pluck('id_jabatan')->values()->all(),
            'jabatans' => $template->positions->map(fn ($p) => $p->jabatan)->filter()->values(),
            'strategies' => $template->strategies->map(function ($strategy) {
                return [
                    'id' => $strategy->id,
                    'kpi_key_strategy_id' => $strategy->kpi_key_strategy_id,
                    'key_strategy' => $strategy->keyStrategy,
                    'weight_percent' => (float) $strategy->weight_percent,
                    'sort_order' => $strategy->sort_order,
                    'items' => $strategy->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->name,
                            'description' => $item->description,
                            'weight_percent' => (float) $item->weight_percent,
                            'target_value' => $item->target_value,
                            'target_direction' => $item->target_direction,
                            'frequency' => $item->frequency,
                            'formula' => $item->formula,
                            'scoring_levels' => $item->scoring_levels,
                            'sort_order' => $item->sort_order,
                            'parameter_ids' => $item->itemParameters->pluck('kpi_parameter_id')->values()->all(),
                            'parameters' => $item->itemParameters->map(fn ($ip) => $ip->parameter)->filter()->values(),
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    }
}
