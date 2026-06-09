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
     * @return array{user: object, template: KpiTemplate|null, period: array<string, string>}
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
        ];
    }

    public function createDraft(int $userId, string $periodMonth): KpiEvaluation
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
                'user_id' => 'Tidak ada template KPI aktif untuk jabatan karyawan ini. Publish template terlebih dahulu.',
            ]);
        }

        $template->load([
            'strategies.keyStrategy',
            'strategies.items.itemParameters.parameter',
        ]);

        $period = app(OutletAnalyzerService::class)->calendarPeriod($periodMonth);
        $dataCodes = $this->collectDataParameterCodes($template);

        return DB::transaction(function () use ($user, $template, $periodMonth, $period, $dataCodes) {
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
                'period_month' => $periodMonth,
                'period_start' => $period['start_date'],
                'period_end' => $period['end_date'],
                'eval_status' => 'draft',
                'scoring_rules' => $template->scoring_rules ?? $this->templateService->defaultScoringRules(),
                'assessed_by' => Auth::id(),
                'created_by' => Auth::id(),
            ]);

            $this->seedParameterValues($evaluation, $dataCodes);
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

            $this->recalculate($evaluation->fresh());

            return $evaluation->fresh(['template', 'parameterValues', 'items']);
        });
    }

    public function refreshErpValues(KpiEvaluation $evaluation): KpiEvaluation
    {
        if (!$evaluation->isEditable()) {
            throw ValidationException::withMessages(['eval_status' => 'Evaluasi sudah disubmit.']);
        }

        $context = [
            'outlet_id' => $evaluation->id_outlet,
            'user_id' => $evaluation->user_id,
            'period_month' => $evaluation->period_month,
        ];

        $this->resolver->clearCache();

        foreach ($evaluation->parameterValues()->with('parameter.erpMapping')->get() as $pv) {
            if (!in_array($pv->source_type, ['erp', 'hybrid'], true)) {
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
    protected function seedParameterValues(KpiEvaluation $evaluation, array $codes): void
    {
        if (empty($codes)) {
            return;
        }

        $parameters = KpiParameter::with('erpMapping')
            ->whereIn('code', $codes)
            ->where('status', 'A')
            ->get()
            ->keyBy('code');

        $context = [
            'outlet_id' => $evaluation->id_outlet,
            'user_id' => $evaluation->user_id,
            'period_month' => $evaluation->period_month,
        ];

        $this->resolver->clearCache();

        foreach ($codes as $code) {
            $param = $parameters->get($code);
            if (!$param) {
                continue;
            }

            $erpValue = in_array($param->source_type, ['erp', 'hybrid'], true)
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
