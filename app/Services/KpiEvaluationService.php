<?php

namespace App\Services;

use App\Models\KpiEvaluation;
use App\Models\KpiEvaluationItem;
use App\Models\KpiEvaluationParameterValue;
use App\Models\KpiParameter;
use App\Models\KpiTemplate;
use App\Models\KpiTemplatePosition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpiEvaluationService
{
    public function __construct(
        private KpiParameterResolverService $resolver,
        private KpiTemplateService $templateService,
    ) {}

    public function loadForEdit(int $id): KpiEvaluation
    {
        return KpiEvaluation::with([
            'template:id,code,name,version',
            'parameterValues',
            'items',
        ])->findOrFail($id);
    }

    /**
     * @return array{user: object, template: KpiTemplate|null, period: array<string, string>, template_hint: string|null}
     */
    public function previewEmployee(int $userId, string $periodMonth): array
    {
        $user = $this->getUserSnapshot($userId);
        $template = $this->resolveTemplate((int) $user->id_jabatan, $periodMonth);
        $period = app(OutletAnalyzerService::class)->calendarPeriod($periodMonth);

        return [
            'user' => $user,
            'template' => $template,
            'period' => $period,
            'template_hint' => $template ? null : $this->explainMissingTemplate((int) $user->id_jabatan, $periodMonth),
        ];
    }

    public function createDraft(int $userId, string $periodMonth, array $scopeInput = []): KpiEvaluation
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            throw ValidationException::withMessages(['period_month' => 'Format periode harus YYYY-MM.']);
        }

        if (KpiEvaluation::where('user_id', $userId)->where('period_month', $periodMonth)->exists()) {
            throw ValidationException::withMessages([
                'period_month' => 'Evaluasi untuk karyawan dan periode ini sudah ada.',
            ]);
        }

        $user = $this->getUserSnapshot($userId);
        if (!$user->id_jabatan) {
            throw ValidationException::withMessages(['user_id' => 'Karyawan belum memiliki jabatan.']);
        }

        $template = $this->resolveTemplate((int) $user->id_jabatan, $periodMonth);
        if (!$template) {
            throw ValidationException::withMessages([
                'user_id' => $this->explainMissingTemplate((int) $user->id_jabatan, $periodMonth)
                    ?? 'Tidak ada template KPI aktif untuk jabatan karyawan ini. Publish template terlebih dahulu.',
            ]);
        }

        $template->load([
            'strategies.keyStrategy',
            'strategies.items.itemParameters.parameter',
        ]);

        [$erpDataScope, $erpScopeOutletIds] = $this->normalizeErpScope(
            $scopeInput['erp_data_scope'] ?? $template->erp_data_scope ?? 'employee_outlet',
            $scopeInput['erp_scope_outlet_ids'] ?? $template->erp_scope_outlet_ids ?? [],
            (int) $user->id_outlet,
        );

        $this->validateErpScope($erpDataScope, $erpScopeOutletIds);

        $period = app(OutletAnalyzerService::class)->calendarPeriod($periodMonth);
        $dataCodes = $this->collectDataParameterCodes($template);

        return DB::transaction(function () use ($user, $template, $periodMonth, $period, $dataCodes, $erpDataScope, $erpScopeOutletIds) {
            $evaluation = KpiEvaluation::create([
                'evaluation_code' => $this->generateCode($periodMonth),
                'user_id' => $user->id,
                'kpi_template_id' => $template->id,
                'template_version' => $template->version,
                'id_jabatan' => $user->id_jabatan,
                'id_outlet' => $user->id_outlet,
                'division_id' => $user->division_id,
                'employee_name' => $user->nama_lengkap,
                'jabatan_name' => $user->nama_jabatan,
                'outlet_name' => $user->nama_outlet,
                'division_name' => $user->nama_divisi,
                'erp_data_scope' => $erpDataScope,
                'erp_scope_outlet_ids' => $erpScopeOutletIds,
                'period_month' => $periodMonth,
                'period_start' => $period['start_date'],
                'period_end' => $period['end_date'],
                'eval_status' => 'draft',
                'scoring_rules' => $template->scoring_rules ?? $this->templateService->defaultScoringRules(),
                'assessed_by' => Auth::id(),
                'created_by' => Auth::id(),
            ]);

            $this->seedParameterValues($evaluation, $dataCodes, fetchErp: false);
            $this->seedItems($evaluation, $template);
            $this->recalculate($evaluation);

            return $evaluation->fresh(['template', 'parameterValues', 'items']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $parameterValues
     * @param  array<int, array<string, mixed>>  $items
     */
    public function saveDraft(KpiEvaluation $evaluation, array $parameterValues, array $items, array $meta = []): KpiEvaluation
    {
        if (!$evaluation->isEditable()) {
            throw ValidationException::withMessages(['eval_status' => 'Evaluasi sudah disubmit dan tidak bisa diubah.']);
        }

        return DB::transaction(function () use ($evaluation, $parameterValues, $items, $meta) {
            foreach ($parameterValues as $row) {
                $pv = KpiEvaluationParameterValue::where('kpi_evaluation_id', $evaluation->id)
                    ->where('id', $row['id'] ?? 0)
                    ->first();

                if (!$pv) {
                    continue;
                }

                $manualValue = isset($row['manual_value']) && $row['manual_value'] !== '' && $row['manual_value'] !== null
                    ? (float) $row['manual_value']
                    : null;
                $isOverridden = (bool) ($row['is_overridden'] ?? false);

                if (in_array($pv->source_type, ['manual', 'hybrid'], true) && $manualValue !== null) {
                    $isOverridden = $pv->source_type === 'hybrid' && $pv->erp_value !== null && $manualValue != $pv->erp_value;
                }

                $finalValue = $this->resolveFinalValue($pv->source_type, $pv->erp_value, $manualValue, $isOverridden);

                $pv->update([
                    'manual_value' => $manualValue,
                    'final_value' => $finalValue,
                    'is_overridden' => $isOverridden,
                    'override_reason' => $row['override_reason'] ?? null,
                ]);
            }

            foreach ($items as $row) {
                KpiEvaluationItem::where('kpi_evaluation_id', $evaluation->id)
                    ->where('id', $row['id'] ?? 0)
                    ->update([
                        'improvement_plan' => $row['improvement_plan'] ?? null,
                    ]);
            }

            $evaluation->update([
                'employee_comments' => $meta['employee_comments'] ?? $evaluation->employee_comments,
                'assessor_comments' => $meta['assessor_comments'] ?? $evaluation->assessor_comments,
            ]);

            if (isset($meta['erp_data_scope'])) {
                [$scope, $outletIds] = $this->normalizeErpScope(
                    (string) $meta['erp_data_scope'],
                    $meta['erp_scope_outlet_ids'] ?? [],
                    (int) $evaluation->id_outlet,
                );
                $this->validateErpScope($scope, $outletIds);

                $scopeChanged = $scope !== ($evaluation->erp_data_scope ?? 'employee_outlet')
                    || $outletIds !== ($evaluation->erp_scope_outlet_ids ?? []);

                $evaluation->update([
                    'erp_data_scope' => $scope,
                    'erp_scope_outlet_ids' => $outletIds,
                ]);

                if ($scopeChanged) {
                    return $this->refreshErp($evaluation->fresh());
                }
            }

            $this->recalculate($evaluation->fresh());

            return $evaluation->fresh(['template', 'parameterValues', 'items']);
        });
    }

    public function refreshErp(KpiEvaluation $evaluation): KpiEvaluation
    {
        if (!$evaluation->isEditable()) {
            throw ValidationException::withMessages(['eval_status' => 'Evaluasi sudah disubmit.']);
        }

        $context = $this->buildErpContext($evaluation);

        $this->resolver->clearCache();

        $parameterRows = $evaluation->parameterValues()->with('parameter.erpMapping')->get();
        $this->resolver->prefetch($context, $parameterRows->pluck('parameter')->filter());

        foreach ($parameterRows as $pv) {
            if (!in_array($pv->source_type, ['erp', 'hybrid'], true)) {
                continue;
            }

            if (!$pv->parameter) {
                continue;
            }

            $erpValue = $this->resolver->resolve($pv->parameter, $context);
            $finalValue = $this->resolveFinalValue(
                $pv->source_type,
                $erpValue,
                $pv->manual_value !== null ? (float) $pv->manual_value : null,
                (bool) $pv->is_overridden,
            );

            $pv->update([
                'erp_value' => $erpValue,
                'final_value' => $finalValue,
                'erp_fetched_at' => now(),
            ]);
        }

        $this->recalculate($evaluation->fresh());

        return $evaluation->fresh(['template', 'parameterValues', 'items']);
    }

    /**
     * @return array<string, mixed>
     */
    public function erpDiagnostics(KpiEvaluation $evaluation, ?string $scopeOverride = null, ?array $outletIdsOverride = null): array
    {
        return $this->erpDiagnosticsFromContext(
            $this->buildErpContext($evaluation, $scopeOverride, $outletIdsOverride),
        );
    }

    public function applyErpScope(KpiEvaluation $evaluation, string $scope, array $outletIds): KpiEvaluation
    {
        [$normalizedScope, $normalizedIds] = $this->normalizeErpScope(
            $scope,
            $outletIds,
            (int) $evaluation->id_outlet,
        );
        $this->validateErpScope($normalizedScope, $normalizedIds);

        $evaluation->update([
            'erp_data_scope' => $normalizedScope,
            'erp_scope_outlet_ids' => $normalizedIds,
        ]);

        return $evaluation->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildErpContext(KpiEvaluation $evaluation, ?string $scopeOverride = null, ?array $outletIdsOverride = null): array
    {
        $scope = $scopeOverride ?? ($evaluation->erp_data_scope ?? 'employee_outlet');
        $storedIds = $outletIdsOverride ?? ($evaluation->erp_scope_outlet_ids ?? []);

        [$normalizedScope, $ids] = $this->normalizeErpScope(
            $scope,
            $storedIds,
            (int) $evaluation->id_outlet,
        );

        $outletIds = $normalizedScope === 'all_outlets'
            ? $this->allActiveOutletIds()
            : $ids;

        return [
            'outlet_ids' => $outletIds,
            'outlet_id' => $outletIds[0] ?? (int) $evaluation->id_outlet,
            'user_id' => $evaluation->user_id,
            'period_month' => $evaluation->period_month,
            'erp_data_scope' => $normalizedScope,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function erpDiagnosticsFromContext(array $context): array
    {
        return $this->resolver->diagnose($context);
    }

    /**
     * @return list<int>
     */
    public function resolveErpOutletIds(KpiEvaluation $evaluation, ?string $scopeOverride = null, ?array $outletIdsOverride = null): array
    {
        return $this->buildErpContext($evaluation, $scopeOverride, $outletIdsOverride)['outlet_ids'];
    }

    /**
     * @param  array<int|string>|null  $outletIds
     * @return array{0: string, 1: list<int>}
     */
    public function normalizeErpScope(string $scope, ?array $outletIds, int $employeeOutletId): array
    {
        $allowed = ['employee_outlet', 'single_outlet', 'multiple_outlets', 'all_outlets'];
        $scope = in_array($scope, $allowed, true) ? $scope : 'employee_outlet';
        $ids = array_values(array_unique(array_filter(array_map('intval', $outletIds ?? []))));

        return match ($scope) {
            'employee_outlet' => ['employee_outlet', $employeeOutletId > 0 ? [$employeeOutletId] : []],
            'single_outlet' => ['single_outlet', $ids !== [] ? [(int) $ids[0]] : ($employeeOutletId > 0 ? [$employeeOutletId] : [])],
            'multiple_outlets' => ['multiple_outlets', $ids],
            'all_outlets' => ['all_outlets', []],
            default => ['employee_outlet', $employeeOutletId > 0 ? [$employeeOutletId] : []],
        };
    }

    /**
     * @param  list<int>  $outletIds
     */
    public function validateErpScope(string $scope, array $outletIds): void
    {
        if ($scope === 'all_outlets') {
            return;
        }

        if ($scope === 'employee_outlet' && empty($outletIds)) {
            throw ValidationException::withMessages([
                'erp_data_scope' => 'Karyawan belum memiliki outlet — pilih scope "1 outlet" atau "beberapa outlet".',
            ]);
        }

        if (in_array($scope, ['single_outlet', 'multiple_outlets'], true) && empty($outletIds)) {
            throw ValidationException::withMessages([
                'erp_scope_outlet_ids' => 'Pilih minimal 1 outlet untuk scope data ERP.',
            ]);
        }
    }

    /**
     * @return list<int>
     */
    public function allActiveOutletIds(): array
    {
        $query = DB::table('tbl_data_outlet')->where('status', 'A');

        if (DB::getSchemaBuilder()->hasColumn('tbl_data_outlet', 'is_outlet')) {
            $query->where('is_outlet', 1);
        }

        return $query->orderBy('nama_outlet')
            ->pluck('id_outlet')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, nama_outlet: string, qr_code: string|null}>
     */
    public function outletOptions(): array
    {
        return DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->when(
                DB::getSchemaBuilder()->hasColumn('tbl_data_outlet', 'is_outlet'),
                fn ($q) => $q->where('is_outlet', 1),
            )
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet', 'qr_code'])
            ->map(fn ($o) => [
                'id' => (int) $o->id_outlet,
                'label' => trim($o->nama_outlet . ($o->qr_code ? " ({$o->qr_code})" : '')),
                'nama_outlet' => $o->nama_outlet,
                'qr_code' => $o->qr_code,
            ])
            ->all();
    }

    public function submit(KpiEvaluation $evaluation): KpiEvaluation
    {
        if (!$evaluation->isEditable()) {
            throw ValidationException::withMessages(['eval_status' => 'Evaluasi sudah disubmit.']);
        }

        $missing = $evaluation->parameterValues()
            ->whereIn('source_type', ['manual', 'hybrid'])
            ->whereNull('final_value')
            ->count();

        if ($missing > 0) {
            throw ValidationException::withMessages([
                'parameter_values' => "Masih ada {$missing} parameter data yang belum diisi.",
            ]);
        }

        $evaluation->update([
            'eval_status' => 'submitted',
            'submitted_at' => now(),
            'assessed_by' => Auth::id(),
        ]);

        return $evaluation->fresh(['template', 'parameterValues', 'items']);
    }

    public function deleteDraft(KpiEvaluation $evaluation): void
    {
        if (!$evaluation->isEditable()) {
            throw ValidationException::withMessages(['eval_status' => 'Hanya evaluasi draft yang bisa dihapus.']);
        }

        $evaluation->delete();
    }

    public function recalculate(KpiEvaluation $evaluation): void
    {
        $valueMap = $evaluation->parameterValues()
            ->get()
            ->mapWithKeys(fn ($pv) => [$pv->parameter_code => $pv->final_value !== null ? (float) $pv->final_value : null])
            ->all();

        $rules = $evaluation->scoring_rules ?? $this->templateService->defaultScoringRules();
        $totalScore = 0.0;

        foreach ($evaluation->items()->get() as $item) {
            $achievement = $this->evaluateFormula($item->formula, $valueMap);
            $scoring = $this->scoreItem($achievement, $item->target_direction, $rules);
            $weighted = round(($scoring['score'] * (float) $item->weight_percent) / 100, 4);

            $item->update([
                'achievement_percent' => $achievement,
                'performance_level' => $scoring['level'],
                'score' => $scoring['score'],
                'weighted_score' => $weighted,
            ]);

            $totalScore += $weighted;
        }

        $evaluation->update(['total_score' => round($totalScore, 2)]);
    }

    protected function resolveTemplate(int $jabatanId, string $periodMonth): ?KpiTemplate
    {
        $periodDate = Carbon::createFromFormat('Y-m', $periodMonth)->startOfMonth();

        $position = KpiTemplatePosition::query()
            ->where('id_jabatan', $jabatanId)
            ->where('status', 'A')
            ->where(function ($q) use ($periodDate) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $periodDate);
            })
            ->where(function ($q) use ($periodDate) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $periodDate);
            })
            ->whereHas('template', function ($q) {
                $q->where('status', 'A')->where('template_status', 'active');
            })
            ->with('template')
            ->orderByDesc('id')
            ->first();

        return $position?->template;
    }

    protected function explainMissingTemplate(int $jabatanId, string $periodMonth): ?string
    {
        $periodDate = Carbon::createFromFormat('Y-m', $periodMonth)->startOfMonth();
        $periodLabel = $periodDate->locale('id')->translatedFormat('F Y');

        $positions = KpiTemplatePosition::query()
            ->where('id_jabatan', $jabatanId)
            ->where('status', 'A')
            ->with('template:id,code,name,template_status,status')
            ->orderByDesc('id')
            ->get();

        if ($positions->isEmpty()) {
            return 'Belum ada template KPI yang di-assign ke jabatan karyawan ini.';
        }

        $activeTemplate = $positions->first(fn ($p) => $p->template
            && $p->template->status === 'A'
            && $p->template->template_status === 'active');

        if (!$activeTemplate) {
            $draft = $positions->first(fn ($p) => $p->template && $p->template->template_status === 'draft');

            return $draft
                ? "Template \"{$draft->template->name}\" masih draft — publish template terlebih dahulu."
                : 'Tidak ada template KPI aktif untuk jabatan karyawan ini.';
        }

        $from = $activeTemplate->effective_from;
        $to = $activeTemplate->effective_to;

        if ($from && $from->gt($periodDate)) {
            return "Template \"{$activeTemplate->template->name}\" baru berlaku dari {$from->locale('id')->translatedFormat('F Y')} — periode {$periodLabel} belum tercakup.";
        }

        if ($to && $to->lt($periodDate)) {
            return "Template \"{$activeTemplate->template->name}\" berakhir {$to->locale('id')->translatedFormat('F Y')} — periode {$periodLabel} sudah di luar masa berlaku.";
        }

        return 'Tidak ada template KPI aktif untuk periode ini.';
    }

    protected function getUserSnapshot(int $userId): object
    {
        $user = User::query()
            ->select(
                'users.id',
                'users.nama_lengkap',
                'users.id_jabatan',
                'users.id_outlet',
                'users.division_id',
                'jabatan.nama_jabatan',
                'divisi.nama_divisi',
                'outlet.nama_outlet',
            )
            ->leftJoin('tbl_data_jabatan as jabatan', 'users.id_jabatan', '=', 'jabatan.id_jabatan')
            ->leftJoin('tbl_data_divisi as divisi', 'users.division_id', '=', 'divisi.id')
            ->leftJoin('tbl_data_outlet as outlet', 'users.id_outlet', '=', 'outlet.id_outlet')
            ->where('users.id', $userId)
            ->where('users.status', 'A')
            ->first();

        if (!$user) {
            throw ValidationException::withMessages(['user_id' => 'Karyawan tidak ditemukan.']);
        }

        return $user;
    }

    protected function generateCode(string $periodMonth): string
    {
        $suffix = str_replace('-', '', $periodMonth);
        $count = KpiEvaluation::where('period_month', $periodMonth)->count() + 1;

        return 'KPI-EVL-' . $suffix . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<string>
     */
    protected function collectDataParameterCodes(KpiTemplate $template): array
    {
        $codes = [];

        foreach ($template->strategies as $strategy) {
            foreach ($strategy->items as $item) {
                $formula = $item->formula;
                if ($item->itemParameters->isNotEmpty()) {
                    $param = $item->itemParameters->first()->parameter;
                    $formula = $param?->formula ?? $formula;
                }
                foreach ($this->extractCodes($formula) as $code) {
                    if (str_starts_with($code, 'D')) {
                        $codes[] = $code;
                    }
                }
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  list<string>  $codes
     */
    protected function seedParameterValues(KpiEvaluation $evaluation, array $codes, bool $fetchErp = true): void
    {
        if (empty($codes)) {
            return;
        }

        $parameters = KpiParameter::with('erpMapping')
            ->whereIn('code', $codes)
            ->where('status', 'A')
            ->get()
            ->keyBy('code');

        $context = $this->buildErpContext($evaluation);

        if ($fetchErp) {
            $this->resolver->clearCache();
            $this->resolver->prefetch($context, $parameters->values());
        }

        foreach ($codes as $code) {
            $param = $parameters->get($code);
            if (!$param) {
                continue;
            }

            $erpValue = $fetchErp && in_array($param->source_type, ['erp', 'hybrid'], true)
                ? $this->resolver->resolve($param, $context)
                : null;

            $finalValue = $this->resolveFinalValue($param->source_type, $erpValue, null, false);

            KpiEvaluationParameterValue::create([
                'kpi_evaluation_id' => $evaluation->id,
                'kpi_parameter_id' => $param->id,
                'parameter_code' => $param->code,
                'parameter_name' => $param->name,
                'source_type' => $param->source_type,
                'scope_type' => $param->scope_type,
                'erp_value' => $erpValue,
                'manual_value' => $param->source_type === 'manual' ? null : null,
                'final_value' => $finalValue,
                'is_overridden' => false,
                'erp_fetched_at' => $erpValue !== null ? now() : null,
            ]);
        }
    }

    protected function seedItems(KpiEvaluation $evaluation, KpiTemplate $template): void
    {
        $sort = 0;

        foreach ($template->strategies as $strategy) {
            foreach ($strategy->items as $item) {
                $param = $item->itemParameters->first()?->parameter;

                KpiEvaluationItem::create([
                    'kpi_evaluation_id' => $evaluation->id,
                    'kpi_template_strategy_id' => $strategy->id,
                    'kpi_template_item_id' => $item->id,
                    'kpi_parameter_id' => $param?->id,
                    'key_strategy_name' => $strategy->keyStrategy?->name,
                    'strategy_weight_percent' => $strategy->weight_percent,
                    'item_name' => $param?->name ?? $item->name,
                    'weight_percent' => $item->weight_percent,
                    'target_value' => $param?->target_value ?? $item->target_value,
                    'target_direction' => $param?->target_direction ?? $item->target_direction ?? 'higher_better',
                    'frequency' => $param?->frequency ?? $item->frequency,
                    'formula' => $param?->formula ?? $item->formula,
                    'sort_order' => $sort++,
                ]);
            }
        }
    }

    protected function resolveFinalValue(string $sourceType, ?float $erpValue, ?float $manualValue, bool $isOverridden): ?float
    {
        return match ($sourceType) {
            'erp' => $erpValue,
            'manual' => $manualValue,
            'hybrid' => $isOverridden && $manualValue !== null ? $manualValue : ($erpValue ?? $manualValue),
            default => $manualValue ?? $erpValue,
        };
    }

    /**
     * @return list<string>
     */
    protected function extractCodes(?string $formula): array
    {
        if (!$formula) {
            return [];
        }

        preg_match_all('/\b(D\d{3}|KPI\d{2})\b/', $formula, $matches);

        return $matches[0] ?? [];
    }

    /**
     * @param  array<string, float|null>  $valueMap
     */
    protected function evaluateFormula(?string $formula, array $valueMap): ?float
    {
        if (!$formula || trim($formula) === '') {
            return null;
        }

        $trimmed = trim($formula);

        if (preg_match('/^\s*(D\d{3}|KPI\d{2})\s*$/', $trimmed, $single)) {
            $code = $single[1];
            return isset($valueMap[$code]) && $valueMap[$code] !== null ? (float) $valueMap[$code] : null;
        }

        $expr = $trimmed;
        uksort($valueMap, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($valueMap as $code => $value) {
            if ($value === null) {
                return null;
            }
            $expr = preg_replace('/\b' . preg_quote($code, '/') . '\b/', (string) ((float) $value), $expr);
        }

        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return null;
        }

        try {
            /** @var float|int $result */
            $result = eval('return ' . $expr . ';');

            return is_numeric($result) ? round((float) $result, 4) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{level: string, score: float}
     */
    protected function scoreItem(?float $achievement, string $direction, array $rules): array
    {
        if ($achievement === null) {
            return ['level' => 'below', 'score' => 0.0];
        }

        $exceedingMin = (float) ($rules['exceeding_min'] ?? 100);
        $meetingMin = (float) ($rules['meeting_min'] ?? 85);

        if ($direction === 'lower_better') {
            if ($achievement <= $meetingMin) {
                return ['level' => 'exceeding', 'score' => 100.0];
            }
            if ($achievement <= $exceedingMin) {
                return ['level' => 'meeting', 'score' => 85.0];
            }

            return ['level' => 'below', 'score' => max(0.0, min(84.0, round(200 - $achievement, 2)))];
        }

        if ($achievement >= $exceedingMin) {
            return ['level' => 'exceeding', 'score' => 100.0];
        }
        if ($achievement >= $meetingMin) {
            return ['level' => 'meeting', 'score' => 85.0];
        }

        return ['level' => 'below', 'score' => max(0.0, min(84.0, round($achievement, 2)))];
    }
}
