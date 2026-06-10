<?php

namespace App\Http\Controllers;

use App\Models\KpiParameter;
use App\Models\KpiParameterErpMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KpiParameterController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'A');
        $sourceType = $request->input('source_type');

        $query = KpiParameter::with('erpMapping');

        if ($status === 'A' || $status === 'N') {
            $query->where('status', $status);
        }

        if ($sourceType) {
            $query->where('source_type', $sourceType);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $parameters = $query->orderBy('code')->paginate(15)->withQueryString();

        return Inertia::render('KpiParameters/Index', [
            'parameters' => $parameters,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'source_type' => $sourceType,
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateParameter($request);

        DB::transaction(function () use ($validated) {
            $mapping = $validated['erp_mapping'] ?? null;
            unset($validated['erp_mapping']);

            $parameter = KpiParameter::create($validated);
            $this->syncErpMapping($parameter, $mapping);
        });

        return redirect()->route('kpi-parameters.index')->with('success', 'Parameter KPI berhasil ditambahkan.');
    }

    public function update(Request $request, KpiParameter $kpiParameter)
    {
        $validated = $this->validateParameter($request, $kpiParameter->id);

        DB::transaction(function () use ($validated, $kpiParameter) {
            $mapping = $validated['erp_mapping'] ?? null;
            unset($validated['erp_mapping']);

            $kpiParameter->update($validated);
            $this->syncErpMapping($kpiParameter, $mapping);
        });

        return redirect()->route('kpi-parameters.index')->with('success', 'Parameter KPI berhasil diperbarui.');
    }

    public function show(KpiParameter $kpiParameter)
    {
        $kpiParameter->load('erpMapping');

        return response()->json([
            'id' => $kpiParameter->id,
            'code' => $kpiParameter->code,
            'name' => $kpiParameter->name,
            'source_type' => $kpiParameter->source_type,
            'scope_type' => $kpiParameter->scope_type,
            'data_type' => $kpiParameter->data_type,
            'description' => $kpiParameter->description,
            'target_value' => $kpiParameter->target_value,
            'target_direction' => $kpiParameter->target_direction,
            'frequency' => $kpiParameter->frequency,
            'formula' => $kpiParameter->formula,
            'is_shared' => $kpiParameter->is_shared,
            'status' => $kpiParameter->status,
            'erp_mapping' => $kpiParameter->erpMapping,
        ]);
    }

    public function destroy(KpiParameter $kpiParameter)
    {
        $kpiParameter->update(['status' => 'N']);

        return redirect()->back()->with('success', 'Parameter KPI berhasil dinonaktifkan.');
    }

    public function toggleStatus(KpiParameter $kpiParameter)
    {
        $newStatus = $kpiParameter->status === 'A' ? 'N' : 'A';
        $kpiParameter->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Status parameter berhasil diubah.',
            'new_status' => $newStatus,
        ]);
    }

    protected function validateParameter(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:kpi_parameters,code';
        if ($ignoreId) {
            $uniqueRule .= ',' . $ignoreId;
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', $uniqueRule],
            'name' => 'required|string|max:255',
            'source_type' => 'required|in:erp,manual,hybrid',
            'scope_type' => 'required|in:outlet,employee,division',
            'data_type' => 'required|in:decimal,integer,percent,hours,text',
            'description' => 'nullable|string',
            'target_value' => 'nullable|string|max:100',
            'target_direction' => 'nullable|in:higher_better,lower_better',
            'frequency' => 'nullable|string|max:50',
            'formula' => 'nullable|string',
            'is_shared' => 'boolean',
            'status' => 'required|in:A,N',
            'erp_mapping' => 'nullable|array',
            'erp_mapping.resolver_key' => 'nullable|string|max:100',
            'erp_mapping.static_filters' => 'nullable|array',
            'erp_mapping.dynamic_filter_bindings' => 'nullable|array',
            'erp_mapping.aggregation' => 'nullable|string|max:50',
        ]);

        $validated['is_shared'] = $request->boolean('is_shared', true);
        $validated['target_direction'] = $validated['target_direction'] ?? 'higher_better';
        $validated['frequency'] = $validated['frequency'] ?? 'monthly';

        // Resolver wajib hanya untuk source ERP murni. Hybrid boleh tanpa resolver (input manual).
        if ($validated['source_type'] === 'erp') {
            $request->validate([
                'erp_mapping.resolver_key' => 'required|string|max:100',
            ]);
        }

        return $validated;
    }

    protected function syncErpMapping(KpiParameter $parameter, ?array $mapping): void
    {
        if (!in_array($parameter->source_type, ['erp', 'hybrid'], true)) {
            KpiParameterErpMapping::where('kpi_parameter_id', $parameter->id)->delete();
            return;
        }

        if (!$mapping || empty($mapping['resolver_key'])) {
            return;
        }

        KpiParameterErpMapping::updateOrCreate(
            ['kpi_parameter_id' => $parameter->id],
            [
                'resolver_key' => $mapping['resolver_key'],
                'static_filters' => $mapping['static_filters'] ?? null,
                'dynamic_filter_bindings' => $mapping['dynamic_filter_bindings'] ?? null,
                'aggregation' => $mapping['aggregation'] ?? 'sum',
                'status' => 'A',
            ]
        );
    }

    protected function formOptions(): array
    {
        return [
            'source_types' => [
                ['value' => 'erp', 'label' => 'ERP'],
                ['value' => 'manual', 'label' => 'Manual'],
                ['value' => 'hybrid', 'label' => 'Hybrid'],
            ],
            'scope_types' => [
                ['value' => 'outlet', 'label' => 'Outlet'],
                ['value' => 'employee', 'label' => 'Employee'],
                ['value' => 'division', 'label' => 'Division'],
            ],
            'data_types' => [
                ['value' => 'decimal', 'label' => 'Decimal'],
                ['value' => 'integer', 'label' => 'Integer'],
                ['value' => 'percent', 'label' => 'Percent'],
                ['value' => 'hours', 'label' => 'Hours'],
                ['value' => 'text', 'label' => 'Text'],
            ],
            'target_directions' => [
                ['value' => 'higher_better', 'label' => 'Higher is better'],
                ['value' => 'lower_better', 'label' => 'Lower is better'],
            ],
            'frequencies' => [
                ['value' => 'monthly', 'label' => 'Monthly'],
                ['value' => 'quarterly', 'label' => 'Quarterly'],
            ],
            'resolver_keys' => [
                ['value' => 'daily_revenue_forecast', 'label' => 'POS Orders — Revenue MTD (aggregation: sum)'],
                ['value' => 'pos_order_count', 'label' => 'POS Orders — Jumlah Order MTD (aggregation: count)'],
                ['value' => 'daily_revenue_forecast_budget', 'label' => 'Daily Revenue Forecast (Budget MTD)'],
                ['value' => 'outlet_analyzer_payroll', 'label' => 'Outlet Analyzer - Payroll'],
                ['value' => 'outlet_analyzer_petty_cash', 'label' => 'Outlet Analyzer - Petty Cash'],
                ['value' => 'outlet_internal_use_waste', 'label' => 'Outlet Internal Use & Waste'],
                ['value' => 'cost_report_cogs', 'label' => 'Cost Report - COGS'],
                ['value' => 'training_compliance', 'label' => 'Training Compliance'],
                ['value' => 'ticket_complaint_count', 'label' => 'Ticketing - Complaint Count'],
                ['value' => 'regional_visit_report', 'label' => 'Regional Visit — Kunjungan Karyawan KPI ke Outlet (count)'],
                ['value' => 'regional_target_outlet_visits', 'label' => 'Regional Management — Target Kunjungan Outlet / Bulan'],
            ],
        ];
    }
}
