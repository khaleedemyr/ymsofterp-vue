<?php

namespace App\Services;

use App\Models\KpiEvaluation;
use App\Models\KpiEvaluationItem;
use App\Models\KpiEvaluationParameterValue;
use App\Models\KpiParameter;
use App\Models\KpiTemplate;
use App\Models\KpiTemplatePosition;
use App\Models\User;
use App\Models\UserRegional;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpiEvaluationService
{
    public function __construct(
        private KpiParameterResolverService $resolver,
        private KpiTemplateService $templateService,
        private RegionalVisitAnalyticsService $regionalVisits,
    ) {}

    public function loadForEdit(int $id): KpiEvaluation
    {
        $evaluation = KpiEvaluation::with([
            'template:id,code,name,version',
            'template.strategies.items.itemParameters.parameter',
            'parameterValues.parameter',
            'items',
        ])->findOrFail($id);

        if ($evaluation->isEditable()) {
            $this->syncEvaluationDataPeriod($evaluation);
            $this->syncParameterValuesFromTemplate($evaluation);
            $this->syncItemFormulasFromTemplate($evaluation);
            $evaluation->load(['parameterValues', 'items']);
        }

        return $evaluation;
    }

    /**
     * Bulan data KPI = 1 bulan sebelum periode evaluasi yang dipilih user.
     * Contoh: evaluasi Juli 2026 → data Juni 2026.
     */
    public function resolveDataPeriodMonth(string $evaluationPeriodMonth): string
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $evaluationPeriodMonth)) {
            return $evaluationPeriodMonth;
        }

        return Carbon::createFromFormat('Y-m', $evaluationPeriodMonth)
            ->subMonth()
            ->format('Y-m');
    }

    /**
     * @return array{
     *     evaluation_period_month: string,
     *     data_period_month: string,
     *     evaluation_label: string,
     *     data_label: string,
     *     start_date: string,
     *     end_date: string,
     *     attendance_start: string,
     *     attendance_end: string,
     *     attendance_label: string
     * }
     */
    public function buildKpiPeriodInfo(string $evaluationPeriodMonth): array
    {
        $outletAnalyzer = app(OutletAnalyzerService::class);
        $dataMonth = $this->resolveDataPeriodMonth($evaluationPeriodMonth);
        $dataCalendar = $outletAnalyzer->calendarPeriod($dataMonth);
        $attendance = $outletAnalyzer->payrollPeriod($dataMonth);
        $evaluationCalendar = $outletAnalyzer->calendarPeriod($evaluationPeriodMonth);

        return [
            'evaluation_period_month' => $evaluationPeriodMonth,
            'data_period_month' => $dataMonth,
            'evaluation_label' => $evaluationCalendar['label'],
            'data_label' => $dataCalendar['label'],
            'start_date' => $dataCalendar['start_date'],
            'end_date' => $dataCalendar['end_date'],
            'attendance_start' => $attendance['start_date'],
            'attendance_end' => $attendance['end_date'],
            'attendance_label' => $attendance['label'],
        ];
    }

    public function labelFrequency(?string $frequency): string
    {
        return match (strtolower(trim((string) ($frequency ?: 'monthly')))) {
            'quarterly' => 'Quarterly',
            'monthly' => 'Monthly',
            default => ucfirst(trim((string) $frequency)),
        };
    }

    public function maxFrequency(string $a, string $b): string
    {
        $rank = ['monthly' => 1, 'quarterly' => 2];

        return ($rank[strtolower(trim($b))] ?? 0) > ($rank[strtolower(trim($a))] ?? 0) ? strtolower(trim($b)) : strtolower(trim($a));
    }

    /**
     * @return list<string>
     */
    public function resolveFrequencyDataMonths(string $frequency, string $evaluationPeriodMonth): array
    {
        $end = Carbon::createFromFormat('Y-m', $this->resolveDataPeriodMonth($evaluationPeriodMonth));

        $monthCount = match (strtolower(trim($frequency ?: 'monthly'))) {
            'quarterly' => 3,
            default => 1,
        };

        $months = [];
        for ($offset = $monthCount - 1; $offset >= 0; $offset--) {
            $months[] = $end->copy()->subMonths($offset)->format('Y-m');
        }

        return $months;
    }

    /**
     * @return array{
     *     frequency: string,
     *     frequency_label: string,
     *     data_period_months: list<string>,
     *     data_window_label: string,
     *     data_window_month_count: int
     * }
     */
    public function buildFrequencyWindowInfo(string $frequency, string $evaluationPeriodMonth): array
    {
        $frequency = strtolower(trim($frequency ?: 'monthly'));
        $months = $this->resolveFrequencyDataMonths($frequency, $evaluationPeriodMonth);
        $outletAnalyzer = app(OutletAnalyzerService::class);

        if (count($months) === 1) {
            $windowLabel = $outletAnalyzer->calendarPeriod($months[0])['label'];
        } else {
            $first = Carbon::createFromFormat('Y-m', $months[0]);
            $last = Carbon::createFromFormat('Y-m', $months[array_key_last($months)]);
            $windowLabel = $first->locale('id')->translatedFormat('F').' – '.$last->locale('id')->translatedFormat('F Y');
        }

        return [
            'frequency' => $frequency,
            'frequency_label' => $this->labelFrequency($frequency),
            'data_period_months' => $months,
            'data_window_label' => $windowLabel,
            'data_window_month_count' => count($months),
        ];
    }

    /**
     * Skor key strategy = rata-rata tertimbang skor KPI dalam strategy (skala 0–100).
     */
    public function calculateStrategyScore(Collection $items): ?float
    {
        $weighted = 0.0;
        $totalWeight = 0.0;

        foreach ($items as $item) {
            if ($item->score === null) {
                continue;
            }

            $weight = (float) $item->weight_percent;
            if ($weight <= 0) {
                continue;
            }

            $weighted += (float) $item->score * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return null;
        }

        return round($weighted / $totalWeight, 2);
    }

    /**
     * Kontribusi strategy terhadap total skor evaluasi.
     */
    public function calculateStrategyContribution(Collection $items): ?float
    {
        $hasValue = false;
        $total = 0.0;

        foreach ($items as $item) {
            if ($item->weighted_score === null) {
                continue;
            }

            $hasValue = true;
            $total += (float) $item->weighted_score;
        }

        if (! $hasValue) {
            return null;
        }

        return round($total, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function enrichEvaluationItems(Collection $items, string $evaluationPeriodMonth): array
    {
        return $items->map(function (KpiEvaluationItem $item) use ($evaluationPeriodMonth) {
            $row = $item->toArray();
            $window = $this->buildFrequencyWindowInfo((string) ($row['frequency'] ?? 'monthly'), $evaluationPeriodMonth);

            return array_merge($row, $window, $this->resolveAchievementDisplayMeta($item));
        })->values()->all();
    }

    /**
     * Satuan tampilan achievement KPI (bukan selalu persen).
     *
     * @return array{value_type: string, data_type: string, unit_suffix: string, unit_label: string}
     */
    public function resolveAchievementDisplayMeta(KpiEvaluationItem $item): array
    {
        $target = trim((string) ($item->target_value ?? ''));
        $formula = trim((string) ($item->formula ?? ''));

        if (preg_match('/\*?\s*100\s*$/', $formula) || preg_match('/\/\s*D\d{3}.*\*\s*100/i', $formula)) {
            return $this->achievementDisplayFromDataType('percent');
        }

        if (preg_match('/Person/i', $target)) {
            return [
                'value_type' => 'count',
                'data_type' => 'integer',
                'unit_suffix' => '',
                'unit_label' => 'orang',
            ];
        }

        if (preg_match('/minutes?/i', $target)) {
            return [
                'value_type' => 'duration',
                'data_type' => 'decimal',
                'unit_suffix' => '',
                'unit_label' => 'menit',
            ];
        }

        if (preg_match('/hours?/i', $target)) {
            return [
                'value_type' => 'duration',
                'data_type' => 'hours',
                'unit_suffix' => '',
                'unit_label' => 'jam',
            ];
        }

        if (str_contains($target, '%')) {
            return $this->achievementDisplayFromDataType('percent');
        }

        $codes = $this->extractCodes($formula);
        $dataCodes = array_values(array_filter($codes, fn (string $c) => preg_match('/^D\d{3}$/', $c)));

        if (count($dataCodes) === 1 && count($codes) === 1) {
            $param = KpiParameter::query()->where('code', $dataCodes[0])->first();
            if ($param) {
                $dataType = $this->resolveEffectiveParameterDataType($param, (string) $param->name);

                return $this->achievementDisplayFromDataType($dataType, (string) $param->code, (string) $param->name);
            }
        }

        return [
            'value_type' => 'decimal',
            'data_type' => 'decimal',
            'unit_suffix' => '',
            'unit_label' => '',
        ];
    }

    /**
     * @return array{value_type: string, data_type: string, unit_suffix: string, unit_label: string}
     */
    private function achievementDisplayFromDataType(string $dataType, string $code = '', string $name = ''): array
    {
        $lowerName = strtolower($name);

        if ($dataType === 'percent') {
            return [
                'value_type' => 'percent',
                'data_type' => 'percent',
                'unit_suffix' => '%',
                'unit_label' => '',
            ];
        }

        if ($dataType === 'integer') {
            $label = match (true) {
                $code === 'D032' || str_contains($lowerName, 'coaching') || str_contains($lowerName, 'person') => 'orang',
                str_contains($lowerName, 'product') || str_contains($lowerName, 'npd') || str_contains($lowerName, 'benchmark') => 'produk',
                str_contains($lowerName, 'visit') && str_contains($lowerName, 'count') => 'hari',
                default => '',
            };

            return [
                'value_type' => 'count',
                'data_type' => 'integer',
                'unit_suffix' => '',
                'unit_label' => $label,
            ];
        }

        if ($dataType === 'hours') {
            return [
                'value_type' => 'duration',
                'data_type' => 'hours',
                'unit_suffix' => '',
                'unit_label' => 'jam',
            ];
        }

        return [
            'value_type' => 'decimal',
            'data_type' => $dataType,
            'unit_suffix' => '',
            'unit_label' => '',
        ];
    }

    public function syncEvaluationDataPeriod(KpiEvaluation $evaluation): KpiEvaluation
    {
        $period = $this->buildKpiPeriodInfo((string) $evaluation->period_month);

        $currentStart = $evaluation->period_start?->toDateString();
        $currentEnd = $evaluation->period_end?->toDateString();

        if ($currentStart !== $period['start_date'] || $currentEnd !== $period['end_date']) {
            $evaluation->update([
                'period_start' => $period['start_date'],
                'period_end' => $period['end_date'],
            ]);
            $evaluation->refresh();
        }

        return $evaluation;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function formatParameterValuesForEdit(
        Collection $parameterValues,
        ?string $evaluationPeriodMonth = null,
        ?Collection $evaluationItems = null,
    ): array {
        if ($parameterValues instanceof \Illuminate\Database\Eloquent\Collection) {
            $parameterValues->loadMissing(['parameter.erpMapping']);
        }

        $codeFrequencyBoost = $this->buildCodeFrequencyBoostFromItems($evaluationItems);

        return $parameterValues->map(function (KpiEvaluationParameterValue $pv) use ($evaluationPeriodMonth, $codeFrequencyBoost) {
            $row = $pv->toArray();
            $param = $pv->relationLoaded('parameter') ? $pv->parameter : null;
            $row['data_type'] = $param?->data_type;
            $row['parameter_description'] = $param?->description;
            $row['manual_input_hint'] = $this->buildManualInputHint($pv, $param);
            $row['input_unit'] = $this->resolveParameterInputUnit($param, (string) $pv->parameter_code, (string) ($pv->parameter_name ?: $param?->name ?: ''));

            $frequency = $this->maxFrequency(
                (string) ($param?->frequency ?? 'monthly'),
                $codeFrequencyBoost[$pv->parameter_code] ?? 'monthly',
            );
            $row['frequency'] = $frequency;
            if ($evaluationPeriodMonth !== null) {
                $row = array_merge($row, $this->buildFrequencyWindowInfo($frequency, $evaluationPeriodMonth));
            }

            return $row;
        })->values()->all();
    }

    public function buildManualInputHint(KpiEvaluationParameterValue $pv, ?KpiParameter $param = null): string
    {
        $param ??= $pv->relationLoaded('parameter') ? $pv->parameter : null;

        $customHint = trim((string) ($param?->manual_input_hint ?? ''));
        if ($customHint !== '') {
            return $this->appendManualInputSourceSuffix($customHint, (string) $pv->source_type);
        }

        $code = (string) $pv->parameter_code;
        $name = trim((string) ($pv->parameter_name ?: $param?->name ?: $code));
        $dataType = $this->resolveEffectiveParameterDataType($param, $name);
        $description = trim((string) ($param?->description ?? ''));

        $instruction = $this->buildParameterSpecificInputInstruction($code, $name, $dataType, $param, $description);
        $example = $this->exampleValueForParameterInput($code, $name, $dataType);

        $parts = array_filter([
            $instruction,
            $example !== '' ? "Contoh: {$example}." : null,
            $this->describeParameterErpSource($param),
            ($description !== '' && ! str_contains($instruction, $description)) ? $description : null,
        ]);

        return $this->appendManualInputSourceSuffix(implode(' ', $parts), (string) $pv->source_type);
    }

    private function appendManualInputSourceSuffix(string $hint, string $sourceType): string
    {
        $hint = trim($hint);
        if ($hint === '') {
            return $hint;
        }

        if ($sourceType === 'hybrid') {
            return $hint . ' Kosongkan untuk pakai nilai ERP; isi angka untuk override manual.';
        }

        if ($sourceType === 'manual') {
            return $hint . ' Parameter ini wajib diisi manual (tidak ada sumber ERP).';
        }

        return $hint;
    }

    private function resolveEffectiveParameterDataType(?KpiParameter $param, string $name): string
    {
        $dataType = (string) ($param?->data_type ?? 'decimal');
        $lowerName = strtolower($name);

        if ($dataType === 'decimal' && (str_contains($name, '%') || str_contains($lowerName, 'percent') || str_contains($lowerName, 'persen') || str_contains($lowerName, 'ratio %'))) {
            return 'percent';
        }

        return $dataType;
    }

    private function resolveParameterInputUnit(?KpiParameter $param, string $code, string $name): string
    {
        $dataType = $this->resolveEffectiveParameterDataType($param, $name);
        $lowerName = strtolower($name);

        if ($dataType === 'percent') {
            return '%';
        }

        if ($dataType === 'hours') {
            return 'jam';
        }

        if ($dataType === 'integer') {
            return match (true) {
                $code === 'D032' || str_contains($lowerName, 'coaching') || str_contains($lowerName, 'person') => 'orang',
                str_contains($lowerName, 'product') || str_contains($lowerName, 'npd') || str_contains($lowerName, 'benchmark') => 'produk',
                str_contains($lowerName, 'visit') && str_contains($lowerName, 'count') => 'hari',
                default => '',
            };
        }

        if (str_contains($lowerName, 'minute')) {
            return 'menit';
        }

        if (str_contains($lowerName, 'google review') || str_contains($lowerName, 'rating')) {
            return '/ 5';
        }

        return '';
    }

    private function buildParameterSpecificInputInstruction(
        string $code,
        string $name,
        string $dataType,
        ?KpiParameter $param,
        string $description,
    ): string {
        $lowerName = strtolower($name);

        if (preg_match('/^kpi\d+/i', $code)) {
            return $this->buildKpiParameterInputInstruction($name, $dataType, $description);
        }

        return match (true) {
            $code === 'D001' || (str_contains($lowerName, 'actual') && str_contains($lowerName, 'revenue'))
                => 'Isi total pendapatan F&B aktual (MTD) dalam rupiah — angka penuh tanpa pemisah ribuan.',
            $code === 'D002' || (str_contains($lowerName, 'budget') && str_contains($lowerName, 'revenue'))
                => 'Isi target/budget pendapatan F&B bulanan dalam rupiah.',
            $code === 'D048' || (str_contains($lowerName, 'cogs') && str_contains($lowerName, 'ratio'))
                => 'Isi persentase rasio COGS (%) dari menu Manual COGS, Deviation & Catcost.',
            $code === 'D049' || str_contains($lowerName, 'deviation')
                => 'Isi persentase deviation (%) dari menu Manual COGS, Deviation & Catcost.',
            $code === 'D050' || str_contains($lowerName, 'category cost')
                => 'Isi persentase category cost (%) dari menu Manual COGS, Deviation & Catcost.',
            $code === 'D051' || (str_contains($lowerName, 'loss') && str_contains($lowerName, 'breakage'))
                => 'Isi persentase Loss & Breakage (%) dari Asset Manual Monthly L&B.',
            $code === 'D052' || str_contains($lowerName, 'labor cost')
                => 'Isi persentase labor cost (%) dari Manual Monthly Labor Cost.',
            $code === 'D053' || (str_contains($lowerName, 'resolution') && str_contains($lowerName, 'hour'))
                => 'Isi rata-rata jam resolusi komplain CVCC sejak di-assign ke regional (boleh desimal).',
            in_array($code, ['D054', 'D055'], true) || (str_contains($lowerName, 'cvcc') && $dataType === 'integer')
                => 'Isi jumlah kasus/review CVCC (bilangan bulat).',
            $code === 'D016' || str_contains($lowerName, 'qa audit')
                => 'Isi skor kepatuhan QA audit dalam persen (C / (C+NC) × 100).',
            $code === 'D017' || str_contains($lowerName, 'recipe compliance') || str_contains($lowerName, 'sop compliance')
                => 'Skor % kepatuhan recipe dari QA2 Audits (BRA-1.5.3 & BRA-1.4.6): C / (C+NC) × 100 per outlet.',
            $code === 'D018' || str_contains($lowerName, 'just academy')
                => 'Isi persentase penyelesaian modul wajib Just Academy (0–100, tanpa simbol %).',
            $code === 'D021' || str_contains($lowerName, 'visit count')
                => 'Isi jumlah hari kunjungan outlet dari absensi (bilangan bulat).',
            $code === 'D022' || str_contains($lowerName, 'target outlet visit')
                => 'Isi target total kunjungan outlet per bulan dari Regional Management (bilangan bulat).',
            $code === 'D023' || str_contains($lowerName, 'closed') && str_contains($lowerName, 'improvement')
                => 'Isi jumlah ticket improvement yang compliant / tidak overdue (bilangan bulat).',
            $code === 'D024' || str_contains($lowerName, 'total improvement')
                => 'Isi total ticket improvement di periode (bilangan bulat).',
            $code === 'D026' || str_contains($lowerName, 'google review rating')
                => 'Isi rating Google Review rata-rata (skala 1–5) dari menu Manual Monthly Google Review.',
            $code === 'D027' || str_contains($lowerName, 'actual upselling')
                => 'Isi actual F&B revenue upselling dari menu Upselling Sales Achievement (bulan data).',
            $code === 'D028' || str_contains($lowerName, 'target upselling')
                => 'Isi target F&B revenue upselling dari menu Upselling Sales Achievement (bulan data).',
            $code === 'D029' || str_contains($lowerName, 'current period average check')
                => 'Isi rata-rata check per pax bulan pembanding terbaru (bulan data KPI) dari Outlet Sales Report.',
            $code === 'D030' || str_contains($lowerName, 'previous period average check')
                => 'Isi rata-rata check per pax bulan sebelumnya (bulan data KPI − 1) dari Outlet Sales Report.',
            $code === 'D031' || str_contains($lowerName, 'induction completion')
                => 'Isi persentase minggu induction onboarding yang selesai tepat waktu (0–100, tanpa simbol %).',
            $code === 'D032' || str_contains($lowerName, 'coaching visit')
                => 'Isi jumlah karyawan unik yang di-coaching (bilangan bulat).',
            $code === 'D043' || str_contains($lowerName, 'new products developed')
                => 'Jumlah produk NPD approved dari menu NPD Plan & Report (PIC / creator) — otomatis dari ERP bila hybrid.',
            $code === 'D046' || str_contains($lowerName, 'benchmark reports')
                => 'Jumlah benchmark competitor dari menu Competitor Benchmark Report (PIC / creator) — otomatis dari ERP bila hybrid.',
            $code === 'D036' || str_contains($lowerName, 'taste calibration')
                => 'Persentase schedule F&B Product Calibration yang selesai (conductor = karyawan dinilai atau bawahan langsung) per outlet bulan data.',
            $dataType === 'percent' || str_contains($name, '%')
                => "Isi nilai persentase untuk «{$name}» tanpa simbol %.",
            $dataType === 'integer'
                => "Isi jumlah (bilangan bulat) untuk «{$name}».",
            $dataType === 'hours'
                => "Isi durasi dalam jam untuk «{$name}».",
            $dataType === 'text'
                => "Isi teks untuk «{$name}».",
            default
                => "Isi nilai numerik untuk «{$name}».",
        };
    }

    private function buildKpiParameterInputInstruction(string $name, string $dataType, string $description): string
    {
        if ($description !== '') {
            return "Isi nilai untuk KPI «{$name}». {$description}";
        }

        return match ($dataType) {
            'percent' => "Isi persentase pencapaian/target untuk KPI «{$name}» (tanpa simbol %).",
            'integer' => "Isi jumlah/skor bilangan bulat untuk KPI «{$name}».",
            'hours' => "Isi nilai jam untuk KPI «{$name}».",
            default => "Isi nilai numerik untuk KPI «{$name}».",
        };
    }

    private function exampleValueForParameterInput(string $code, string $name, string $dataType): string
    {
        return match (true) {
            in_array($code, ['D001', 'D002'], true) || str_contains(strtolower($name), 'revenue')
                => '19384769236',
            in_array($code, ['D048', 'D049', 'D050', 'D051', 'D052'], true) || $dataType === 'percent'
                => '42,5',
            $code === 'D053' || $dataType === 'hours'
                => '24,5',
            $code === 'D026' || str_contains(strtolower($name), 'google review')
                => '4,75',
            $dataType === 'integer'
                => '12',
            default
                => '45,04',
        };
    }

    private function describeParameterErpSource(?KpiParameter $param): ?string
    {
        if ($param === null || ! in_array($param->source_type, ['erp', 'hybrid'], true)) {
            return null;
        }

        $resolverKey = $param->relationLoaded('erpMapping')
            ? ($param->erpMapping?->resolver_key)
            : null;

        if ($resolverKey === null || $resolverKey === '') {
            return null;
        }

        $labels = [
            'daily_revenue_forecast' => 'Sumber ERP: POS Orders — Revenue MTD.',
            'daily_revenue_forecast_budget' => 'Sumber ERP: Daily Revenue Forecast (budget).',
            'manual_cogs_percent' => 'Sumber ERP: Manual COGS — COGS % per outlet.',
            'manual_deviation_percent' => 'Sumber ERP: Manual COGS — Deviation %.',
            'manual_catcost_percent' => 'Sumber ERP: Manual COGS — Category Cost %.',
            'manual_lost_breakage_percent' => 'Sumber ERP: Asset Manual Monthly L&B %.',
            'manual_labor_cost_percent' => 'Sumber ERP: Manual Monthly Labor Cost %.',
            'manual_google_review_rating_avg' => 'Sumber ERP: Manual Monthly Google Review — rata-rata rating outlet.',
            'upselling_actual_fb_revenue' => 'Sumber ERP: Upselling Sales Achievement — actual F&B revenue.',
            'upselling_target_fb_revenue' => 'Sumber ERP: Upselling Sales Achievement — target F&B revenue.',
            'outlet_avg_check_data_month' => 'Sumber ERP: Outlet Sales Report — avg check/pax bulan data.',
            'outlet_avg_check_prev_month' => 'Sumber ERP: Outlet Sales Report — avg check/pax bulan sebelumnya.',
            'employee_induction_on_time_percent' => 'Sumber ERP: Employee Onboarding — % minggu induction tepat waktu.',
            'employee_coaching_person_count' => 'Sumber ERP: Employee Coaching — jumlah karyawan unik di-coaching.',
            'cvcc_avg_resolution_hours' => 'Sumber ERP: CVCC — jam resolusi sejak assign regional.',
            'cvcc_service_negative_complaint_count' => 'Sumber ERP: CVCC — negative + CAPA.',
            'cvcc_total_review_count' => 'Sumber ERP: CVCC — total review.',
            'qa2_audit1_score' => 'Sumber ERP: QA2 Audits — skor kepatuhan (semua parameter).',
            'qa2_recipe_compliance_score' => 'Sumber ERP: QA2 Audits — recipe compliance BRA-1.5.3 & BRA-1.4.6 (C / (C+NC)).',
            'just_academy_training_completion' => 'Sumber ERP: Just Academy — completion %.',
            'just_academy_competency_assessment_score' => 'Sumber ERP: Just Academy — skor competency assessment.',
            'regional_visit_report' => 'Sumber ERP: absensi kunjungan outlet.',
            'regional_target_outlet_visits' => 'Sumber ERP: target kunjungan Regional Management.',
            'ticket_improvement_closed' => 'Sumber ERP: ticket improvement compliant.',
            'ticket_improvement_total' => 'Sumber ERP: total ticket improvement.',
        ];

        return $labels[$resolverKey] ?? ('Sumber ERP: ' . str_replace('_', ' ', $resolverKey) . '.');
    }

    protected function normalizeImprovementPlanDueDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *     user: object,
     *     template: KpiTemplate|null,
     *     period: array<string, string>,
     *     template_hint: string|null,
     *     erp_scope_suggestion: array<string, mixed>|null
     * }
     */
    public function previewEmployee(int $userId, string $periodMonth): array
    {
        $user = $this->getUserSnapshot($userId);
        $template = $this->resolveTemplate((int) $user->id_jabatan, $periodMonth);
        $period = $this->buildKpiPeriodInfo($periodMonth);

        return [
            'user' => $user,
            'template' => $template,
            'period' => $period,
            'template_hint' => $template ? null : $this->explainMissingTemplate((int) $user->id_jabatan, $periodMonth),
            'erp_scope_suggestion' => $this->suggestErpScopeFromRegional($userId),
        ];
    }

    /**
     * Outlet scope dari Regional Management (user_regional.outlet_visit_targets).
     *
     * @return array{
     *     erp_data_scope: string,
     *     erp_scope_outlet_ids: list<int>,
     *     regional_area: string|null,
     *     outlet_names: list<string>
     * }|null
     */
    public function suggestErpScopeFromRegional(int $userId): ?array
    {
        $assignment = UserRegional::query()->where('user_id', $userId)->first();
        if (! $assignment) {
            return null;
        }

        $targets = $assignment->outlet_visit_targets ?? [];
        if (! is_array($targets)) {
            $targets = [];
        }

        $outletIds = collect($targets)
            ->pluck('outlet_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($outletIds === []) {
            return null;
        }

        $outletNames = DB::table('tbl_data_outlet')
            ->whereIn('id_outlet', $outletIds)
            ->orderBy('nama_outlet')
            ->pluck('nama_outlet')
            ->map(fn ($name) => (string) $name)
            ->values()
            ->all();

        return [
            'erp_data_scope' => count($outletIds) === 1 ? 'single_outlet' : 'multiple_outlets',
            'erp_scope_outlet_ids' => $outletIds,
            'regional_area' => implode(', ', $assignment->resolveAreas()) ?: $assignment->area,
            'outlet_names' => $outletNames,
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
            $this->resolveCreateErpScope($scopeInput, $template),
            $scopeInput['erp_scope_outlet_ids'] ?? $template->erp_scope_outlet_ids ?? [],
            (int) $user->id_outlet,
        );

        $this->validateErpScope($erpDataScope, $erpScopeOutletIds);

        $period = $this->buildKpiPeriodInfo($periodMonth);
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
            if ($parameterValues !== []) {
                $this->applyParameterValueRows($evaluation, $parameterValues);
            }

            foreach ($items as $row) {
                KpiEvaluationItem::where('kpi_evaluation_id', $evaluation->id)
                    ->where('id', $row['id'] ?? 0)
                    ->update([
                        'improvement_plan' => $row['improvement_plan'] ?? null,
                        'improvement_plan_due_date' => $this->normalizeImprovementPlanDueDate($row['improvement_plan_due_date'] ?? null),
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

        @set_time_limit(600);

        $evaluation = $this->syncEvaluationDataPeriod($evaluation);

        $this->syncParameterValuesFromTemplate($evaluation);
        $this->syncItemFormulasFromTemplate($evaluation);
        $evaluation->load('parameterValues');

        $context = $this->buildErpContext($evaluation);

        $parameterRows = $evaluation->parameterValues()->with('parameter.erpMapping')->get();
        $evaluation->loadMissing('items');
        $prefetchMonths = $this->collectPrefetchDataMonths($evaluation, $parameterRows);
        foreach ($prefetchMonths as $month) {
            $this->resolver->clearPersistentCaches($context['outlet_ids'] ?? [], $month);
        }

        $this->resolver->clearCache();
        Cache::forget($this->bulkBreakdownCacheKey($evaluation));

        if ($prefetchMonths !== []) {
            $this->resolver->prefetchForParametersMultiMonth(
                $context,
                $parameterRows->pluck('parameter')->filter(),
                $prefetchMonths,
            );
        }

        foreach ($parameterRows as $pv) {
            $param = $pv->parameter;
            if (! $param) {
                continue;
            }

            $sync = [];
            if ($pv->source_type !== $param->source_type) {
                $sync['source_type'] = $param->source_type;
            }
            if ($pv->scope_type !== $param->scope_type) {
                $sync['scope_type'] = $param->scope_type;
            }
            if ($pv->parameter_name !== $param->name) {
                $sync['parameter_name'] = $param->name;
            }
            if ($sync !== []) {
                $pv->update($sync);
                $pv->refresh();
            }
        }

        foreach ($parameterRows as $pv) {
            $pv->refresh();
            $param = $pv->parameter;
            if (! $param) {
                continue;
            }

            $sourceType = $param->source_type;
            if (! in_array($sourceType, ['erp', 'hybrid'], true)) {
                continue;
            }

            $frequency = $this->effectiveParameterFrequency($evaluation, $param);
            $dataMonths = $this->resolveFrequencyDataMonths($frequency, (string) $evaluation->period_month);
            $erpValue = $this->resolver->resolveAcrossMonths($param, $context, $dataMonths);
            $finalValue = $this->resolveFinalValue(
                $sourceType,
                $erpValue,
                $pv->manual_value !== null ? (float) $pv->manual_value : null,
                (bool) $pv->is_overridden,
            );

            $pv->update([
                'source_type' => $sourceType,
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
        $context = $this->buildErpContext($evaluation, $scopeOverride, $outletIdsOverride);
        $context['parameter_codes'] = $evaluation->parameterValues()
            ->pluck('parameter_code')
            ->all();

        $result = $this->resolver->diagnose($context);

        return $this->appendParameterSyncHints($evaluation, $result);
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

        $period = $this->buildKpiPeriodInfo((string) $evaluation->period_month);

        return [
            'outlet_ids' => $outletIds,
            'outlet_id' => $outletIds[0] ?? (int) $evaluation->id_outlet,
            'user_id' => $evaluation->user_id,
            'evaluation_period_month' => $evaluation->period_month,
            'data_period_month' => $period['data_period_month'],
            'period_month' => $period['data_period_month'],
            'period_start' => $period['start_date'],
            'period_end' => $period['end_date'],
            'attendance_start' => $period['attendance_start'],
            'attendance_end' => $period['attendance_end'],
            'use_full_calendar_month' => true,
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
     * Scope ERP saat buat evaluasi: hanya pakai all_outlets jika user eksplisit memilih.
     * Template default all_outlets tidak boleh override pilihan outlet per evaluasi.
     */
    private function resolveCreateErpScope(array $scopeInput, KpiTemplate $template): string
    {
        if (isset($scopeInput['erp_data_scope']) && $scopeInput['erp_data_scope'] !== '') {
            return (string) $scopeInput['erp_data_scope'];
        }

        $templateScope = (string) ($template->erp_data_scope ?? 'employee_outlet');

        return $templateScope === 'all_outlets' ? 'employee_outlet' : $templateScope;
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
        $evaluation->load(['parameterValues.parameter', 'items']);

        $storedMap = $evaluation->parameterValues
            ->mapWithKeys(fn ($pv) => [$pv->parameter_code => $pv->final_value !== null ? (float) $pv->final_value : null])
            ->all();

        $baseContext = $this->buildErpContext($evaluation);
        $rules = $evaluation->scoring_rules ?? $this->templateService->defaultScoringRules();
        $totalScore = 0.0;

        foreach ($evaluation->items as $item) {
            $valueMap = $this->buildItemFormulaValueMap($item, $storedMap, $evaluation, $baseContext);
            $achievement = $this->evaluateFormula($item->formula, $valueMap);
            $scoring = $this->scoreItem($achievement, $item->target_direction, $rules, $item->target_value);
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

    /**
     * @param  array<string, float|null>  $storedMap
     * @return array<string, float|null>
     */
    protected function buildItemFormulaValueMap(
        KpiEvaluationItem $item,
        array $storedMap,
        KpiEvaluation $evaluation,
        array $baseContext,
    ): array {
        $valueMap = $storedMap;
        $itemFrequency = (string) ($item->frequency ?? 'monthly');
        $codes = $this->extractCodes($item->formula);

        if ($itemFrequency === 'monthly' || $codes === []) {
            return $valueMap;
        }

        $dataMonths = $this->resolveFrequencyDataMonths($itemFrequency, (string) $evaluation->period_month);
        if (count($dataMonths) <= 1) {
            return $valueMap;
        }

        $erpParams = [];
        foreach ($codes as $code) {
            if (! preg_match('/^D\d{3}$/', $code)) {
                continue;
            }

            $pv = $evaluation->parameterValues->firstWhere('parameter_code', $code);
            $param = $pv?->parameter;
            if (! $param || ! in_array($param->source_type, ['erp', 'hybrid'], true)) {
                continue;
            }

            $erpParams[] = $param;
        }

        if ($erpParams !== []) {
            $this->resolver->prefetchForParametersMultiMonth($baseContext, $erpParams, $dataMonths);
        }

        foreach ($codes as $code) {
            if (! preg_match('/^D\d{3}$/', $code)) {
                continue;
            }

            $pv = $evaluation->parameterValues->firstWhere('parameter_code', $code);
            $param = $pv?->parameter;
            if (! $param || ! in_array($param->source_type, ['erp', 'hybrid'], true)) {
                continue;
            }

            $erpValue = $this->resolver->resolveAcrossMonths($param, $baseContext, $dataMonths);
            $valueMap[$code] = $this->resolveFinalValue(
                $param->source_type,
                $erpValue,
                $pv->manual_value !== null ? (float) $pv->manual_value : null,
                (bool) $pv->is_overridden,
            );
        }

        return $valueMap;
    }

    /**
     * @return array<string, string>
     */
    protected function buildCodeFrequencyBoostFromItems(?iterable $items): array
    {
        $map = [];

        if ($items === null) {
            return $map;
        }

        foreach ($items as $item) {
            foreach ($this->extractCodes($item->formula ?? null) as $code) {
                $map[$code] = $this->maxFrequency(
                    $map[$code] ?? 'monthly',
                    (string) ($item->frequency ?? 'monthly'),
                );
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    protected function buildCodeFrequencyBoostMap(KpiEvaluation $evaluation): array
    {
        return $this->buildCodeFrequencyBoostFromItems($evaluation->items);
    }

    protected function effectiveParameterFrequency(KpiEvaluation $evaluation, KpiParameter $param): string
    {
        $boost = $this->buildCodeFrequencyBoostMap($evaluation);

        return $this->maxFrequency(
            (string) ($param->frequency ?? 'monthly'),
            $boost[$param->code] ?? 'monthly',
        );
    }

    /**
     * @return list<string>
     */
    protected function collectPrefetchDataMonths(KpiEvaluation $evaluation, Collection $parameterRows): array
    {
        $months = [];
        $boost = $this->buildCodeFrequencyBoostMap($evaluation);

        foreach ($parameterRows as $pv) {
            $param = $pv->parameter;
            if (! $param) {
                continue;
            }

            $frequency = $this->maxFrequency(
                (string) ($param->frequency ?? 'monthly'),
                $boost[$param->code] ?? 'monthly',
            );
            $months = array_merge(
                $months,
                $this->resolveFrequencyDataMonths($frequency, (string) $evaluation->period_month),
            );
        }

        foreach ($evaluation->items as $item) {
            $months = array_merge(
                $months,
                $this->resolveFrequencyDataMonths((string) ($item->frequency ?? 'monthly'), (string) $evaluation->period_month),
            );
        }

        return array_values(array_unique($months));
    }

    /**
     * Simpan input parameter dari form lalu hitung ulang skor KPI.
     *
     * @param  array<int, array<string, mixed>>  $parameterValues
     * @param  array<int, array<string, mixed>>  $items
     */
    public function recalculateFromForm(KpiEvaluation $evaluation, array $parameterValues = [], array $items = []): KpiEvaluation
    {
        if (!$evaluation->isEditable()) {
            throw ValidationException::withMessages(['eval_status' => 'Evaluasi sudah disubmit.']);
        }

        return DB::transaction(function () use ($evaluation, $parameterValues, $items) {
            if ($parameterValues !== []) {
                $this->applyParameterValueRows($evaluation, $parameterValues);
            }

            foreach ($items as $row) {
                KpiEvaluationItem::where('kpi_evaluation_id', $evaluation->id)
                    ->where('id', $row['id'] ?? 0)
                    ->update([
                        'improvement_plan' => $row['improvement_plan'] ?? null,
                        'improvement_plan_due_date' => $this->normalizeImprovementPlanDueDate($row['improvement_plan_due_date'] ?? null),
                    ]);
            }

            $this->recalculate($evaluation->fresh());

            return $evaluation->fresh(['template', 'parameterValues', 'items']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $parameterValues
     */
    protected function applyParameterValueRows(KpiEvaluation $evaluation, array $parameterValues): void
    {
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

            if ($pv->source_type === 'hybrid' && $manualValue !== null) {
                $isOverridden = $pv->erp_value !== null
                    ? $manualValue != $pv->erp_value
                    : true;
            }

            $finalValue = $this->resolveFinalValue($pv->source_type, $pv->erp_value, $manualValue, $isOverridden);

            $pv->update([
                'manual_value' => $manualValue,
                'final_value' => $finalValue,
                'is_overridden' => $isOverridden,
                'override_reason' => $row['override_reason'] ?? null,
            ]);
        }
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
                    if (str_starts_with($code, 'D') || str_starts_with($code, 'KPI')) {
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
    /**
     * Tambah baris parameter evaluasi yang belum ada vs template aktif.
     */
    protected function syncParameterValuesFromTemplate(KpiEvaluation $evaluation): void
    {
        $evaluation->loadMissing([
            'template.strategies.items.itemParameters.parameter',
        ]);

        $template = $evaluation->template;
        if (! $template) {
            return;
        }

        $requiredCodes = $this->collectDataParameterCodes($template);
        $existingCodes = $evaluation->parameterValues()
            ->pluck('parameter_code')
            ->all();

        $missingCodes = array_values(array_diff($requiredCodes, $existingCodes));
        if ($missingCodes === []) {
            return;
        }

        $this->seedParameterValues($evaluation, $missingCodes, fetchErp: false);
    }

    /**
     * Samakan formula item evaluasi dengan parameter/template terbaru (mis. KPI12 → D019).
     */
    protected function syncItemFormulasFromTemplate(KpiEvaluation $evaluation): void
    {
        $evaluation->loadMissing([
            'template.strategies.items.itemParameters.parameter',
        ]);

        $template = $evaluation->template;
        if (! $template) {
            return;
        }

        $templateItems = $template->strategies
            ->flatMap(fn ($strategy) => $strategy->items)
            ->keyBy('id');

        foreach ($evaluation->items()->get() as $item) {
            $templateItem = $templateItems->get($item->kpi_template_item_id);
            if (! $templateItem) {
                continue;
            }

            $param = $templateItem->itemParameters->first()?->parameter;
            $expectedFormula = trim((string) ($param?->formula ?? $templateItem->formula ?? ''));
            $expectedTarget = $this->resolveTemplateItemTarget($templateItem, $param);
            $expectedFrequency = $this->resolveTemplateItemFrequency($templateItem, $param);

            $updates = [];
            if ($expectedFormula !== '' && $item->formula !== $expectedFormula) {
                $updates['formula'] = $expectedFormula;
            }
            if ($expectedTarget !== null && $item->target_value !== $expectedTarget) {
                $updates['target_value'] = $expectedTarget;
            }
            if ($item->frequency !== $expectedFrequency) {
                $updates['frequency'] = $expectedFrequency;
            }

            if ($updates !== []) {
                $item->update($updates);
            }
        }
    }

    protected function resolveTemplateItemTarget($templateItem, ?KpiParameter $param): ?string
    {
        $itemTarget = trim((string) ($templateItem->target_value ?? ''));

        return $itemTarget !== '' ? $itemTarget : $param?->target_value;
    }

    protected function resolveTemplateItemFrequency($templateItem, ?KpiParameter $param): string
    {
        $itemFrequency = trim((string) ($templateItem->frequency ?? ''));

        return $itemFrequency !== '' ? $itemFrequency : (string) ($param?->frequency ?? 'monthly');
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    protected function appendParameterSyncHints(KpiEvaluation $evaluation, array $diagnostics): array
    {
        $evaluation->loadMissing([
            'template.strategies.items.itemParameters.parameter',
        ]);

        $template = $evaluation->template;
        if (! $template) {
            return $diagnostics;
        }

        $requiredCodes = $this->collectDataParameterCodes($template);
        $existingCodes = $evaluation->parameterValues()->pluck('parameter_code')->all();
        $missingEvalCodes = array_values(array_diff($requiredCodes, $existingCodes));

        $activeMasterCodes = KpiParameter::query()
            ->whereIn('code', $requiredCodes)
            ->where('status', 'A')
            ->pluck('code')
            ->all();

        $missingMasterCodes = array_values(array_diff($requiredCodes, $activeMasterCodes));
        $hints = $diagnostics['hints'] ?? [];

        if ($missingMasterCodes !== []) {
            $hints[] = 'Parameter master belum ada di database: '
                . implode(', ', $missingMasterCodes)
                . ' — jalankan seed_kpi_template_justus_sample.sql lalu Refresh ERP.';
        }

        if ($missingEvalCodes !== []) {
            $hints[] = 'Baris parameter evaluasi belum lengkap (' . count($missingEvalCodes) . ' kode) — buka ulang halaman atau klik Refresh ERP.';
        }

        $diagnostics['hints'] = $hints;
        $diagnostics['missing_master_codes'] = $missingMasterCodes;
        $diagnostics['missing_eval_codes'] = $missingEvalCodes;

        return $diagnostics;
    }

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
                    'target_value' => $this->resolveTemplateItemTarget($item, $param),
                    'target_direction' => $param?->target_direction ?? $item->target_direction ?? 'higher_better',
                    'frequency' => $this->resolveTemplateItemFrequency($item, $param),
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

        $codesNeeded = $this->extractCodes($trimmed);
        if ($codesNeeded === []) {
            return null;
        }

        foreach ($codesNeeded as $code) {
            if (!array_key_exists($code, $valueMap) || $valueMap[$code] === null) {
                return null;
            }
        }

        $expr = $trimmed;
        usort($codesNeeded, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($codesNeeded as $code) {
            $expr = preg_replace(
                '/\b' . preg_quote($code, '/') . '\b/',
                (string) ((float) $valueMap[$code]),
                $expr,
            );
        }

        if (preg_match('/\b(D\d{3}|KPI\d{2})\b/', $expr)) {
            return null;
        }

        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return null;
        }

        try {
            /** @var float|int $result */
            $result = eval('return ' . $expr . ';');

            if (!is_numeric($result) || !is_finite((float) $result)) {
                return null;
            }

            return round((float) $result, 4);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{level: string, score: float}
     */
    protected function scoreItem(?float $achievement, string $direction, array $rules, ?string $targetValue = null): array
    {
        if ($achievement === null) {
            return ['level' => 'below', 'score' => 0.0];
        }

        $bounds = $this->parseTargetValue($targetValue);
        if ($bounds !== null) {
            return $this->scoreItemAgainstTarget($achievement, $direction, $bounds, $rules);
        }

        return $this->scoreItemWithLegacyRules($achievement, $direction, $rules);
    }

    /**
     * @return array{comparator: string, min: ?float, max: ?float}|null
     */
    protected function parseTargetValue(?string $targetValue): ?array
    {
        if ($targetValue === null) {
            return null;
        }

        $target = trim($targetValue);
        if ($target === '') {
            return null;
        }

        if (preg_match('/^<=\s*(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)\s*%?$/i', $target, $matches)) {
            return [
                'comparator' => 'lte_range',
                'min' => (float) $matches[1],
                'max' => (float) $matches[2],
            ];
        }

        if (preg_match('/^<=\s*(\d+(?:\.\d+)?)\s*%?$/i', $target, $matches)) {
            return [
                'comparator' => 'lte',
                'min' => null,
                'max' => (float) $matches[1],
            ];
        }

        if (preg_match('/^<=\s*(\d+(?:\.\d+)?)\s*hours?$/i', $target, $matches)) {
            return [
                'comparator' => 'lte',
                'min' => null,
                'max' => (float) $matches[1],
            ];
        }

        if (preg_match('/^>=\s*(\d+(?:\.\d+)?)\s*%?$/i', $target, $matches)) {
            return [
                'comparator' => 'gte',
                'min' => (float) $matches[1],
                'max' => null,
            ];
        }

        if (preg_match('/^Min\.\s*(\d+(?:\.\d+)?)\s*(?:Products?|\/\s*Month)?/i', $target, $matches)) {
            return [
                'comparator' => 'gte',
                'min' => (float) $matches[1],
                'max' => null,
            ];
        }

        if (preg_match('/^>=\s*(\d+(?:\.\d+)?)\s*Person/i', $target, $matches)) {
            return [
                'comparator' => 'gte',
                'min' => (float) $matches[1],
                'max' => null,
            ];
        }

        if (preg_match('/^>\s*(\d+(?:\.\d+)?)\s*Person/i', $target, $matches)) {
            return [
                'comparator' => 'gte',
                'min' => (float) $matches[1] + 0.0001,
                'max' => null,
            ];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*%\s*Completion/i', $target, $matches)) {
            return [
                'comparator' => 'gte',
                'min' => (float) $matches[1],
                'max' => null,
            ];
        }

        return null;
    }

    /**
     * @param  array{comparator: string, min: ?float, max: ?float}  $bounds
     * @param  array<string, mixed>  $rules
     * @return array{level: string, score: float}
     */
    protected function scoreItemAgainstTarget(float $achievement, string $direction, array $bounds, array $rules): array
    {
        if ($direction === 'lower_better') {
            if ($bounds['comparator'] === 'lte_range' && $bounds['min'] !== null && $bounds['max'] !== null) {
                $min = (float) $bounds['min'];
                $max = (float) $bounds['max'];

                if ($achievement > $max) {
                    return [
                        'level' => 'below',
                        'score' => $this->belowScoreForOverMax($achievement, $max),
                    ];
                }

                if ($achievement <= $min) {
                    return ['level' => 'exceeding', 'score' => 100.0];
                }

                return ['level' => 'meeting', 'score' => 85.0];
            }

            if ($bounds['comparator'] === 'lte' && $bounds['max'] !== null) {
                $max = (float) $bounds['max'];

                if ($achievement > $max) {
                    return [
                        'level' => 'below',
                        'score' => $this->belowScoreForOverMax($achievement, $max),
                    ];
                }

                return ['level' => 'exceeding', 'score' => 100.0];
            }
        }

        if ($direction === 'higher_better' && $bounds['comparator'] === 'gte' && $bounds['min'] !== null) {
            $min = (float) $bounds['min'];
            $meetingThreshold = $min * ((float) ($rules['meeting_min'] ?? 85) / 100);

            if ($achievement >= $min) {
                return ['level' => 'exceeding', 'score' => 100.0];
            }

            if ($achievement >= $meetingThreshold) {
                return ['level' => 'meeting', 'score' => 85.0];
            }

            return [
                'level' => 'below',
                'score' => max(0.0, min(84.0, round(($achievement / max($meetingThreshold, 0.01)) * 84, 2))),
            ];
        }

        return $this->scoreItemWithLegacyRules($achievement, $direction, $rules);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{level: string, score: float}
     */
    protected function scoreItemWithLegacyRules(float $achievement, string $direction, array $rules): array
    {
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

    protected function belowScoreForOverMax(float $achievement, float $max): float
    {
        $overshootPercent = (($achievement - $max) / max($max, 0.01)) * 100;

        return max(0.0, min(84.0, round(85 - $overshootPercent, 2)));
    }

    /**
     * Breakdown achievement KPI per outlet dalam scope evaluasi.
     *
     * @return array<string, mixed>
     */
    public function getItemOutletBreakdown(KpiEvaluation $evaluation, KpiEvaluationItem $item): array
    {
        $bulk = $this->getBulkItemOutletBreakdowns($evaluation);

        return $bulk['items'][$item->id] ?? $this->unavailableItemBreakdown($item, 'Data breakdown tidak tersedia.');
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, generated_at: string|null}
     */
    public function getBulkItemOutletBreakdowns(KpiEvaluation $evaluation): array
    {
        return Cache::remember(
            $this->bulkBreakdownCacheKey($evaluation),
            now()->addHours(6),
            fn () => $this->buildBulkItemOutletBreakdowns($evaluation),
        );
    }

    private function bulkBreakdownCacheKey(KpiEvaluation $evaluation): string
    {
        $version = $evaluation->updated_at?->getTimestamp() ?? 0;

        return 'kpi_bulk_breakdown:v3:' . $evaluation->id . ':' . $version;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, generated_at: string}
     */
    private function buildBulkItemOutletBreakdowns(KpiEvaluation $evaluation): array
    {
        @set_time_limit(300);

        $outletIds = $this->resolveErpOutletIds($evaluation);
        $items = $evaluation->items()->get();

        if ($outletIds === []) {
            $results = [];
            foreach ($items as $item) {
                $results[$item->id] = $this->unavailableItemBreakdown($item, 'Tidak ada outlet dalam scope evaluasi.', 0);
            }

            return [
                'items' => $results,
                'generated_at' => now()->toIso8601String(),
            ];
        }

        $itemMeta = [];
        $allDCodes = [];

        foreach ($items as $item) {
            $formula = trim((string) ($item->formula ?? ''));
            if ($formula === '') {
                $itemMeta[$item->id] = ['unsupported' => true, 'message' => 'KPI ini tidak punya formula.'];
                continue;
            }

            $codes = $this->extractCodes($formula);
            $dCodes = array_values(array_filter($codes, fn (string $c) => preg_match('/^D\d{3}$/', $c)));
            if ($dCodes === [] || count($dCodes) !== count($codes)) {
                $itemMeta[$item->id] = ['unsupported' => true, 'message' => 'Breakdown per outlet belum mendukung formula yang memakai KPI lain.'];
                continue;
            }

            $itemMeta[$item->id] = [
                'item' => $item,
                'formula' => $formula,
                'd_codes' => $dCodes,
            ];
            $allDCodes = array_merge($allDCodes, $dCodes);
        }

        $allDCodes = array_values(array_unique($allDCodes));
        $parameters = $allDCodes === []
            ? collect()
            : KpiParameter::query()
                ->whereIn('code', $allDCodes)
                ->where('status', 'A')
                ->with('erpMapping')
                ->get();

        $baseContext = $this->buildErpContext($evaluation);
        $paramMetaByCode = $evaluation->parameterValues()
            ->whereIn('parameter_code', $allDCodes)
            ->get()
            ->keyBy('parameter_code');

        $outletRows = $outletIds === []
            ? collect()
            : DB::table('tbl_data_outlet')
                ->whereIn('id_outlet', $outletIds)
                ->orderBy('nama_outlet')
                ->get(['id_outlet', 'nama_outlet', 'qr_code']);

        $results = [];
        foreach ($items as $item) {
            $meta = $itemMeta[$item->id] ?? null;
            if ($meta === null) {
                continue;
            }

            if (! empty($meta['unsupported'])) {
                $results[$item->id] = $this->unavailableItemBreakdown($item, (string) $meta['message'], count($outletIds));
                continue;
            }

            $paramByCode = $parameters->keyBy('code');
            $itemParams = $parameters->filter(fn (KpiParameter $p) => in_array($p->code, $meta['d_codes'], true));
            $itemFrequency = (string) ($item->frequency ?? 'monthly');
            $parameterGrid = $outletIds !== [] && $itemParams->isNotEmpty()
                ? $this->resolver->resolveParameterGridForOutlets($outletIds, $itemParams, $baseContext, $itemFrequency)
                : [];

            $assembler = match (true) {
                $this->isRegionalVisitCoverageFormula($meta['d_codes']) => 'assembleRegionalVisitCoverageBreakdown',
                $this->isTicketFollowUpFormula($meta['d_codes']) => 'assembleTicketFollowUpBreakdown',
                $this->isPortfolioBreakdownFormula($meta['d_codes'], $paramByCode) => 'assemblePortfolioItemBreakdownFromGrid',
                default => 'assembleItemBreakdownFromGrid',
            };

            $results[$item->id] = $this->{$assembler}(
                $item,
                $meta['formula'],
                $meta['d_codes'],
                $paramByCode,
                $paramMetaByCode,
                $outletRows,
                $parameterGrid,
                $evaluation,
                $baseContext,
            );
        }

        return [
            'items' => $results,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Formula Outlet Visit Coverage (D021 / D022).
     *
     * @param  list<string>  $dCodes
     */
    protected function isRegionalVisitCoverageFormula(array $dCodes): bool
    {
        $normalized = array_values(array_unique($dCodes));
        sort($normalized);

        return $normalized === ['D021', 'D022'];
    }

    /**
     * Formula Follow-up Improvement Action (D023 / D024).
     *
     * @param  list<string>  $dCodes
     */
    protected function isTicketFollowUpFormula(array $dCodes): bool
    {
        $normalized = array_values(array_unique($dCodes));
        sort($normalized);

        return $normalized === ['D023', 'D024'];
    }

    /**
     * Formula campuran scope outlet + employee (mis. D021/D022) — target tidak per outlet.
     *
     * @param  list<string>  $dCodes
     * @param  Collection<string, KpiParameter>  $parameters
     */
    protected function isPortfolioBreakdownFormula(array $dCodes, Collection $parameters): bool
    {
        $hasOutlet = false;
        $hasEmployee = false;

        foreach ($dCodes as $code) {
            $param = $parameters[$code] ?? null;
            if (! $param) {
                continue;
            }
            $scope = $param->scope_type ?? 'outlet';
            if ($scope === 'employee') {
                $hasEmployee = true;
            } elseif ($scope === 'outlet') {
                $hasOutlet = true;
            }
        }

        return $hasOutlet && $hasEmployee;
    }

    /**
     * @param  list<string>  $dCodes
     * @param  Collection<string, KpiParameter>  $parameters
     * @param  Collection<string, KpiEvaluationParameterValue>  $paramMetaByCode
     * @param  array<int, array<string, ?float>>  $parameterGrid
     * @param  array<string, mixed>  $baseContext
     * @return array<string, mixed>
     */
    private function assemblePortfolioItemBreakdownFromGrid(
        KpiEvaluationItem $item,
        string $formula,
        array $dCodes,
        Collection $parameters,
        Collection $paramMetaByCode,
        Collection $outletRows,
        array $parameterGrid,
        KpiEvaluation $evaluation,
        array $baseContext,
    ): array {
        if ($this->isRegionalVisitCoverageFormula($dCodes)) {
            return $this->assembleRegionalVisitCoverageBreakdown(
                $item,
                $formula,
                $dCodes,
                $parameters,
                $paramMetaByCode,
                $outletRows,
                $parameterGrid,
                $evaluation,
                $baseContext,
            );
        }

        $portfolioValues = [];
        foreach ($dCodes as $code) {
            $param = $parameters[$code] ?? null;
            if ($param && $param->scope_type === 'employee') {
                $portfolioValues[$code] = $this->resolver->resolve($param, $baseContext);
            }
        }

        $parameterColumns = [];
        foreach ($dCodes as $code) {
            $param = $parameters[$code] ?? null;
            $parameterColumns[] = [
                'code' => $code,
                'name' => $paramMetaByCode[$code]->parameter_name ?? $param->name ?? $code,
                'scope_type' => $param->scope_type ?? 'outlet',
            ];
        }

        $outletScopedCodes = [];
        foreach ($dCodes as $code) {
            if (($parameters[$code]->scope_type ?? 'outlet') === 'outlet') {
                $outletScopedCodes[] = $code;
            }
        }
        $primaryOutletCode = $outletScopedCodes[0] ?? null;

        $rows = [];
        $totalVisits = 0.0;

        foreach ($outletRows as $outlet) {
            $outletId = (int) $outlet->id_outlet;
            $valueMap = [];
            $outletContribution = 0.0;

            foreach ($dCodes as $code) {
                $param = $parameters[$code] ?? null;
                if ($param && $param->scope_type === 'employee') {
                    $valueMap[$code] = null;
                    continue;
                }

                $value = $parameterGrid[$outletId][$code] ?? null;
                $valueMap[$code] = $value;
                if ($code === $primaryOutletCode && $value !== null) {
                    $outletContribution = (float) $value;
                }
            }

            $totalVisits += $outletContribution;
            $visited = $outletContribution > 0;

            $rows[] = [
                'outlet_id' => $outletId,
                'outlet_name' => (string) $outlet->nama_outlet,
                'outlet_label' => trim($outlet->nama_outlet . ($outlet->qr_code ? " ({$outlet->qr_code})" : '')),
                'parameter_values' => $valueMap,
                'achievement_percent' => null,
                'performance_level' => $visited ? 'visited' : 'not_visited',
                'score' => null,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $visitedOrder = ['not_visited' => 0, 'visited' => 1];
            $la = $visitedOrder[$a['performance_level']] ?? 0;
            $lb = $visitedOrder[$b['performance_level']] ?? 0;
            if ($la !== $lb) {
                return $lb <=> $la;
            }

            return strcmp((string) $a['outlet_name'], (string) $b['outlet_name']);
        });

        $visitedOutlets = count(array_filter($rows, fn (array $r) => $r['performance_level'] === 'visited'));

        return [
            'available' => true,
            'breakdown_mode' => 'portfolio',
            'outlet_count' => $outletRows->count(),
            'item_name' => $item->item_name,
            'formula' => $formula,
            'target_value' => $item->target_value,
            'target_direction' => $item->target_direction,
            'aggregate_achievement' => $item->achievement_percent !== null ? (float) $item->achievement_percent : null,
            'parameter_columns' => $parameterColumns,
            'portfolio_values' => $portfolioValues,
            'portfolio_note' => 'Target kunjungan (D022) adalah total keseluruhan per bulan, bukan target per outlet.',
            'rows' => $rows,
            'summary' => [
                'exceeding' => 0,
                'meeting' => 0,
                'below' => 0,
                'visited_outlets' => $visitedOutlets,
                'total_visits' => round($totalVisits, 2),
                'portfolio_target' => $portfolioValues['D022'] ?? null,
            ],
        ];
    }

    /**
     * Breakdown kunjungan regional: outlet target setting + outlet non-coverage (ada absen, bukan target).
     *
     * @param  list<string>  $dCodes
     * @param  Collection<string, KpiParameter>  $parameters
     * @param  Collection<string, KpiEvaluationParameterValue>  $paramMetaByCode
     * @param  array<int, array<string, ?float>>  $parameterGrid
     * @param  array<string, mixed>  $baseContext
     * @return array<string, mixed>
     */
    private function assembleRegionalVisitCoverageBreakdown(
        KpiEvaluationItem $item,
        string $formula,
        array $dCodes,
        Collection $parameters,
        Collection $paramMetaByCode,
        Collection $outletRows,
        array $parameterGrid,
        KpiEvaluation $evaluation,
        array $baseContext,
    ): array {
        $userId = (int) ($baseContext['user_id'] ?? 0);
        $assignment = UserRegional::query()->where('user_id', $userId)->first();

        if ($assignment === null) {
            return $this->unavailableItemBreakdown($item, 'Karyawan belum terdaftar di Regional Management.');
        }

        $targetEntries = $this->normalizeRegionalOutletTargets($assignment->outlet_visit_targets ?? []);
        if ($targetEntries === []) {
            return $this->unavailableItemBreakdown($item, 'Belum ada outlet target di Regional Management.');
        }

        $startDate = (string) ($baseContext['period_start'] ?? '');
        $endDate = (string) ($baseContext['period_end'] ?? '');

        $targetOutletIds = array_column($targetEntries, 'outlet_id');
        $visitedOutletIds = $this->regionalVisits->getUserVisitedOutletIds($userId, $startDate, $endDate);
        $lookupIds = array_values(array_unique(array_merge($targetOutletIds, $visitedOutletIds)));

        $outletMeta = $lookupIds === []
            ? collect()
            : DB::table('tbl_data_outlet')
                ->whereIn('id_outlet', $lookupIds)
                ->get(['id_outlet', 'nama_outlet', 'qr_code'])
                ->keyBy('id_outlet');

        $formatLabel = function (int $outletId) use ($outletMeta): string {
            $outlet = $outletMeta->get($outletId);
            if ($outlet === null) {
                return "Outlet #{$outletId}";
            }

            return trim($outlet->nama_outlet . ($outlet->qr_code ? " ({$outlet->qr_code})" : ''));
        };

        $configuredRows = [];
        $totalActual = 0;
        $totalTarget = 0;
        $visitedConfigured = 0;

        foreach ($targetEntries as $entry) {
            $outletId = $entry['outlet_id'];
            $targetVisits = $entry['target_visits'];
            $detail = $this->regionalVisits->getOutletVisitDetail([$userId], $outletId, $startDate, $endDate);
            $actualVisits = (int) ($detail['summary']['visit_days'] ?? 0);

            if ($actualVisits > 0) {
                $visitedConfigured++;
            }

            $performanceLevel = match (true) {
                $targetVisits > 0 && $actualVisits >= $targetVisits => 'exceeding',
                $actualVisits > 0 => 'meeting',
                default => 'not_visited',
            };

            $configuredRows[] = [
                'outlet_id' => $outletId,
                'outlet_name' => (string) ($outletMeta->get($outletId)?->nama_outlet ?? ''),
                'outlet_label' => $formatLabel($outletId),
                'target_visits' => $targetVisits,
                'actual_visits' => $actualVisits,
                'performance_level' => $performanceLevel,
            ];

            $totalActual += $actualVisits;
            $totalTarget += $targetVisits;
        }

        usort($configuredRows, function (array $a, array $b): int {
            $order = ['not_visited' => 0, 'meeting' => 1, 'exceeding' => 2];
            $la = $order[$a['performance_level']] ?? 0;
            $lb = $order[$b['performance_level']] ?? 0;
            if ($la !== $lb) {
                return $lb <=> $la;
            }

            return strcmp((string) $a['outlet_name'], (string) $b['outlet_name']);
        });

        $targetIdSet = array_flip($targetOutletIds);
        $nonCoverageRows = [];

        foreach ($visitedOutletIds as $outletId) {
            if (isset($targetIdSet[$outletId])) {
                continue;
            }

            $detail = $this->regionalVisits->getOutletVisitDetail([$userId], $outletId, $startDate, $endDate);
            $actualVisits = (int) ($detail['summary']['visit_days'] ?? 0);
            if ($actualVisits <= 0) {
                continue;
            }

            $nonCoverageRows[] = [
                'outlet_id' => $outletId,
                'outlet_name' => (string) ($outletMeta->get($outletId)?->nama_outlet ?? ''),
                'outlet_label' => $formatLabel($outletId),
                'actual_visits' => $actualVisits,
            ];
        }

        usort($nonCoverageRows, function (array $a, array $b): int {
            $visitCompare = ($b['actual_visits'] ?? 0) <=> ($a['actual_visits'] ?? 0);
            if ($visitCompare !== 0) {
                return $visitCompare;
            }

            return strcmp((string) $a['outlet_name'], (string) $b['outlet_name']);
        });

        $d022Param = $parameters['D022'] ?? null;
        $portfolioTarget = $d022Param
            ? $this->resolver->resolve($d022Param, $baseContext)
            : ($totalTarget > 0 ? (float) $totalTarget : null);

        return [
            'available' => true,
            'breakdown_mode' => 'regional_visit',
            'outlet_count' => count($configuredRows) + count($nonCoverageRows),
            'item_name' => $item->item_name,
            'formula' => $formula,
            'target_value' => $item->target_value,
            'target_direction' => $item->target_direction,
            'aggregate_achievement' => $item->achievement_percent !== null ? (float) $item->achievement_percent : null,
            'parameter_columns' => [],
            'portfolio_note' => 'Kunjungan dihitung dari absensi (hari kunjungan unik). Target outlet dari Regional Management.',
            'configured_rows' => $configuredRows,
            'non_coverage_rows' => $nonCoverageRows,
            'rows' => [],
            'summary' => [
                'configured_outlet_count' => count($configuredRows),
                'visited_configured' => $visitedConfigured,
                'non_coverage_count' => count($nonCoverageRows),
                'total_visits' => $totalActual,
                'portfolio_target' => $portfolioTarget,
                'exceeding' => count(array_filter($configuredRows, fn (array $r) => $r['performance_level'] === 'exceeding')),
                'meeting' => count(array_filter($configuredRows, fn (array $r) => $r['performance_level'] === 'meeting')),
                'below' => count(array_filter($configuredRows, fn (array $r) => $r['performance_level'] === 'not_visited')),
            ],
        ];
    }

    /**
     * Breakdown ticket follow-up per outlet: total, closed, overdue, compliant (D023).
     *
     * @param  list<string>  $dCodes
     * @param  Collection<string, KpiParameter>  $parameters
     * @param  Collection<string, KpiEvaluationParameterValue>  $paramMetaByCode
     * @param  array<int, array<string, ?float>>  $parameterGrid
     * @param  array<string, mixed>  $baseContext
     * @return array<string, mixed>
     */
    private function assembleTicketFollowUpBreakdown(
        KpiEvaluationItem $item,
        string $formula,
        array $dCodes,
        Collection $parameters,
        Collection $paramMetaByCode,
        Collection $outletRows,
        array $parameterGrid,
        KpiEvaluation $evaluation,
        array $baseContext,
    ): array {
        $startDate = (string) ($baseContext['period_start'] ?? '');
        $endDate = (string) ($baseContext['period_end'] ?? '');
        $outletIds = $outletRows->pluck('id_outlet')->map(fn ($id) => (int) $id)->all();
        $stats = $this->resolver->getImprovementTicketStatsByOutlet($outletIds, $startDate, $endDate);

        $rules = $evaluation->scoring_rules ?? $this->templateService->defaultScoringRules();
        $rows = [];
        $sumTotal = 0.0;
        $sumCompliant = 0.0;
        $sumClosed = 0.0;
        $sumOverdue = 0.0;

        foreach ($outletRows as $outlet) {
            $outletId = (int) $outlet->id_outlet;
            $row = $stats[$outletId] ?? ['total' => 0.0, 'closed' => 0.0, 'overdue' => 0.0, 'compliant' => 0.0];
            $total = (float) $row['total'];
            $compliant = (float) $row['compliant'];
            $achievement = $total > 0 ? round(($compliant / $total) * 100, 2) : null;
            $scoring = $this->scoreItem($achievement, $item->target_direction, $rules, $item->target_value);

            $rows[] = [
                'outlet_id' => $outletId,
                'outlet_name' => (string) $outlet->nama_outlet,
                'outlet_label' => trim($outlet->nama_outlet . ($outlet->qr_code ? " ({$outlet->qr_code})" : '')),
                'total_tickets' => $total,
                'closed_tickets' => (float) $row['closed'],
                'overdue_tickets' => (float) $row['overdue'],
                'compliant_tickets' => $compliant,
                'parameter_values' => [
                    'D023' => $compliant,
                    'D024' => $total,
                ],
                'achievement_percent' => $achievement,
                'performance_level' => $scoring['level'],
                'score' => $scoring['score'],
            ];

            $sumTotal += $total;
            $sumCompliant += $compliant;
            $sumClosed += (float) $row['closed'];
            $sumOverdue += (float) $row['overdue'];
        }

        $levelOrder = ['below' => 0, 'meeting' => 1, 'exceeding' => 2];
        usort($rows, function (array $a, array $b) use ($levelOrder): int {
            $la = $levelOrder[$a['performance_level']] ?? 9;
            $lb = $levelOrder[$b['performance_level']] ?? 9;
            if ($la !== $lb) {
                return $la <=> $lb;
            }

            return ($b['overdue_tickets'] ?? 0) <=> ($a['overdue_tickets'] ?? 0);
        });

        return [
            'available' => true,
            'breakdown_mode' => 'ticket_follow_up',
            'outlet_count' => $outletRows->count(),
            'item_name' => $item->item_name,
            'formula' => $formula,
            'target_value' => $item->target_value,
            'target_direction' => $item->target_direction,
            'aggregate_achievement' => $item->achievement_percent !== null ? (float) $item->achievement_percent : null,
            'parameter_columns' => [],
            'portfolio_note' => 'Compliant = total ticket − overdue. Overdue = belum closed & due date lewat akhir periode data.',
            'rows' => $rows,
            'summary' => [
                'total_tickets' => $sumTotal,
                'closed_tickets' => $sumClosed,
                'overdue_tickets' => $sumOverdue,
                'compliant_tickets' => $sumCompliant,
                'exceeding' => count(array_filter($rows, fn (array $r) => $r['performance_level'] === 'exceeding')),
                'meeting' => count(array_filter($rows, fn (array $r) => $r['performance_level'] === 'meeting')),
                'below' => count(array_filter($rows, fn (array $r) => $r['performance_level'] === 'below')),
            ],
        ];
    }

    /**
     * @param  mixed  $raw
     * @return list<array{outlet_id: int, target_visits: int}>
     */
    private function normalizeRegionalOutletTargets(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $entries = [];
        foreach ($raw as $row) {
            $outletId = (int) ($row['outlet_id'] ?? 0);
            if ($outletId <= 0) {
                continue;
            }

            $entries[] = [
                'outlet_id' => $outletId,
                'target_visits' => max(0, (int) ($row['target_visits'] ?? 0)),
            ];
        }

        return $entries;
    }

    /**
     * @param  list<string>  $dCodes
     * @param  Collection<string, KpiParameter>  $parameters
     * @param  Collection<string, KpiEvaluationParameterValue>  $paramMetaByCode
     * @param  array<int, array<string, ?float>>  $parameterGrid
     * @return array<string, mixed>
     */
    private function assembleItemBreakdownFromGrid(
        KpiEvaluationItem $item,
        string $formula,
        array $dCodes,
        Collection $parameters,
        Collection $paramMetaByCode,
        Collection $outletRows,
        array $parameterGrid,
        KpiEvaluation $evaluation,
        array $baseContext = [],
    ): array {
        $parameterColumns = [];
        foreach ($dCodes as $code) {
            $parameterColumns[] = [
                'code' => $code,
                'name' => $paramMetaByCode[$code]->parameter_name ?? $parameters[$code]->name ?? $code,
            ];
        }

        $rules = $evaluation->scoring_rules ?? $this->templateService->defaultScoringRules();
        $rows = [];

        foreach ($outletRows as $outlet) {
            $outletId = (int) $outlet->id_outlet;
            $valueMap = [];
            foreach ($dCodes as $code) {
                $valueMap[$code] = $parameterGrid[$outletId][$code] ?? null;
            }

            $achievement = $this->evaluateFormula($formula, $valueMap);
            $scoring = $this->scoreItem($achievement, $item->target_direction, $rules, $item->target_value);

            $rows[] = [
                'outlet_id' => $outletId,
                'outlet_name' => (string) $outlet->nama_outlet,
                'outlet_label' => trim($outlet->nama_outlet . ($outlet->qr_code ? " ({$outlet->qr_code})" : '')),
                'parameter_values' => $valueMap,
                'achievement_percent' => $achievement,
                'performance_level' => $scoring['level'],
                'score' => $scoring['score'],
            ];
        }

        $levelOrder = ['below' => 0, 'meeting' => 1, 'exceeding' => 2];
        usort($rows, function (array $a, array $b) use ($levelOrder, $item): int {
            $la = $levelOrder[$a['performance_level']] ?? 9;
            $lb = $levelOrder[$b['performance_level']] ?? 9;
            if ($la !== $lb) {
                return $la <=> $lb;
            }

            if ($item->target_direction === 'lower_better') {
                return ($b['achievement_percent'] ?? 0) <=> ($a['achievement_percent'] ?? 0);
            }

            return ($a['achievement_percent'] ?? 0) <=> ($b['achievement_percent'] ?? 0);
        });

        return [
            'available' => true,
            'breakdown_mode' => 'per_outlet',
            'outlet_count' => $outletRows->count(),
            'item_name' => $item->item_name,
            'formula' => $formula,
            'target_value' => $item->target_value,
            'target_direction' => $item->target_direction,
            'aggregate_achievement' => $item->achievement_percent !== null ? (float) $item->achievement_percent : null,
            'parameter_columns' => $parameterColumns,
            'rows' => $rows,
            'summary' => [
                'exceeding' => count(array_filter($rows, fn (array $r) => $r['performance_level'] === 'exceeding')),
                'meeting' => count(array_filter($rows, fn (array $r) => $r['performance_level'] === 'meeting')),
                'below' => count(array_filter($rows, fn (array $r) => $r['performance_level'] === 'below')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableItemBreakdown(KpiEvaluationItem $item, string $message, ?int $outletCount = null): array
    {
        return [
            'available' => false,
            'message' => $message,
            'outlet_count' => $outletCount ?? 0,
            'item_name' => $item->item_name,
            'formula' => $item->formula,
            'target_value' => $item->target_value,
            'target_direction' => $item->target_direction,
            'aggregate_achievement' => $item->achievement_percent !== null ? (float) $item->achievement_percent : null,
            'parameter_columns' => [],
            'rows' => [],
            'summary' => ['exceeding' => 0, 'meeting' => 0, 'below' => 0],
        ];
    }
}
