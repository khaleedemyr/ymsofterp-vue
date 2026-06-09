<?php

namespace App\Services;

use App\Models\KpiTemplate;
use App\Models\KpiTemplateItem;
use App\Models\KpiTemplateItemParameter;
use App\Models\KpiTemplatePosition;
use App\Models\KpiTemplateStrategy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpiTemplateService
{
    public function defaultScoringRules(): array
    {
        return [
            'exceeding_min' => 100,
            'meeting_min' => 85,
            'below_max' => 85,
        ];
    }

    public function loadForEdit(int $id): KpiTemplate
    {
        return KpiTemplate::with([
            'positions.jabatan:id_jabatan,nama_jabatan',
            'strategies.keyStrategy',
            'strategies.items.itemParameters.parameter',
        ])->findOrFail($id);
    }

    public function validateWeights(array $strategies): void
    {
        $strategyTotal = collect($strategies)->sum(fn ($s) => (float) ($s['weight_percent'] ?? 0));
        if (round($strategyTotal, 2) !== 100.0) {
            throw ValidationException::withMessages([
                'strategies' => 'Total bobot Key Strategy harus 100%. Saat ini: ' . round($strategyTotal, 2) . '%',
            ]);
        }

        $itemTotal = 0;
        foreach ($strategies as $strategy) {
            foreach ($strategy['items'] ?? [] as $item) {
                $itemTotal += (float) ($item['weight_percent'] ?? 0);
            }
        }

        if (round($itemTotal, 2) !== 100.0) {
            throw ValidationException::withMessages([
                'strategies' => 'Total bobot KPI (semua item) harus 100%. Saat ini: ' . round($itemTotal, 2) . '%',
            ]);
        }
    }

    public function save(array $data, ?KpiTemplate $template = null): KpiTemplate
    {
        $this->validateWeights($data['strategies'] ?? []);

        return DB::transaction(function () use ($data, $template) {
            $payload = [
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'template_status' => $data['template_status'] ?? 'draft',
                'scoring_rules' => $data['scoring_rules'] ?? $this->defaultScoringRules(),
                'status' => $data['status'] ?? 'A',
            ];

            if ($template) {
                $template->update($payload);
            } else {
                $payload['version'] = 1;
                $payload['created_by'] = Auth::id();
                $template = KpiTemplate::create($payload);
            }

            $this->syncPositions($template, $data['jabatan_ids'] ?? []);
            $this->syncStrategies($template, $data['strategies'] ?? []);

            return $template->fresh([
                'positions.jabatan:id_jabatan,nama_jabatan',
                'strategies.keyStrategy',
                'strategies.items.itemParameters.parameter',
            ]);
        });
    }

    public function publish(KpiTemplate $template): KpiTemplate
    {
        if ($template->positions()->where('status', 'A')->count() === 0) {
            throw ValidationException::withMessages([
                'jabatan_ids' => 'Template harus di-assign minimal ke 1 jabatan sebelum publish.',
            ]);
        }

        if ($template->strategies()->count() === 0) {
            throw ValidationException::withMessages([
                'strategies' => 'Template harus memiliki minimal 1 Key Strategy sebelum publish.',
            ]);
        }

        $strategies = $template->strategies()->with('items')->get()->map(function ($strategy) {
            return [
                'weight_percent' => $strategy->weight_percent,
                'items' => $strategy->items->map(fn ($item) => ['weight_percent' => $item->weight_percent])->all(),
            ];
        })->all();

        $this->validateWeights($strategies);

        $template->update(['template_status' => 'active']);

        return $template;
    }

    protected function syncPositions(KpiTemplate $template, array $jabatanIds): void
    {
        $jabatanIds = array_values(array_unique(array_filter($jabatanIds)));
        $existing = KpiTemplatePosition::where('kpi_template_id', $template->id)
            ->get()
            ->keyBy('id_jabatan');

        foreach ($jabatanIds as $jabatanId) {
            if ($existing->has($jabatanId)) {
                $existing[$jabatanId]->update(['status' => 'A']);

                continue;
            }

            KpiTemplatePosition::create([
                'kpi_template_id' => $template->id,
                'id_jabatan' => $jabatanId,
                'effective_from' => null,
                'effective_to' => null,
                'status' => 'A',
            ]);
        }

        KpiTemplatePosition::where('kpi_template_id', $template->id)
            ->whereNotIn('id_jabatan', $jabatanIds)
            ->delete();
    }

    protected function syncStrategies(KpiTemplate $template, array $strategies): void
    {
        $existingStrategyIds = $template->strategies()->pluck('id')->all();
        $keptStrategyIds = [];

        foreach ($strategies as $strategyIndex => $strategyData) {
            $strategy = isset($strategyData['id'])
                ? KpiTemplateStrategy::where('kpi_template_id', $template->id)->find($strategyData['id'])
                : null;

            if ($strategy) {
                $strategy->update([
                    'kpi_key_strategy_id' => $strategyData['kpi_key_strategy_id'],
                    'weight_percent' => $strategyData['weight_percent'],
                    'sort_order' => $strategyData['sort_order'] ?? $strategyIndex,
                ]);
            } else {
                $strategy = KpiTemplateStrategy::create([
                    'kpi_template_id' => $template->id,
                    'kpi_key_strategy_id' => $strategyData['kpi_key_strategy_id'],
                    'weight_percent' => $strategyData['weight_percent'],
                    'sort_order' => $strategyData['sort_order'] ?? $strategyIndex,
                ]);
            }

            $keptStrategyIds[] = $strategy->id;
            $this->syncItems($strategy, $strategyData['items'] ?? []);
        }

        $removeStrategyIds = array_diff($existingStrategyIds, $keptStrategyIds);
        if (!empty($removeStrategyIds)) {
            KpiTemplateStrategy::whereIn('id', $removeStrategyIds)->delete();
        }
    }

    protected function syncItems(KpiTemplateStrategy $strategy, array $items): void
    {
        $existingItemIds = $strategy->items()->pluck('id')->all();
        $keptItemIds = [];

        foreach ($items as $itemIndex => $itemData) {
            $item = isset($itemData['id'])
                ? KpiTemplateItem::where('kpi_template_strategy_id', $strategy->id)->find($itemData['id'])
                : null;

            $itemPayload = [
                'name' => $itemData['name'],
                'description' => $itemData['description'] ?? null,
                'weight_percent' => $itemData['weight_percent'],
                'target_value' => $itemData['target_value'] ?? null,
                'target_direction' => $itemData['target_direction'] ?? 'higher_better',
                'frequency' => $itemData['frequency'] ?? 'monthly',
                'formula' => $itemData['formula'] ?? null,
                'scoring_levels' => $itemData['scoring_levels'] ?? null,
                'sort_order' => $itemData['sort_order'] ?? $itemIndex,
                'status' => $itemData['status'] ?? 'A',
            ];

            if ($item) {
                $item->update($itemPayload);
            } else {
                $item = KpiTemplateItem::create(array_merge($itemPayload, [
                    'kpi_template_strategy_id' => $strategy->id,
                ]));
            }

            $keptItemIds[] = $item->id;
            $this->syncItemParameters($item, $itemData['parameter_ids'] ?? []);
        }

        $removeItemIds = array_diff($existingItemIds, $keptItemIds);
        if (!empty($removeItemIds)) {
            KpiTemplateItem::whereIn('id', $removeItemIds)->delete();
        }
    }

    protected function syncItemParameters(KpiTemplateItem $item, array $parameterIds): void
    {
        KpiTemplateItemParameter::where('kpi_template_item_id', $item->id)->delete();

        foreach (array_values(array_unique($parameterIds)) as $index => $parameterId) {
            if (!$parameterId) {
                continue;
            }

            KpiTemplateItemParameter::create([
                'kpi_template_item_id' => $item->id,
                'kpi_parameter_id' => $parameterId,
                'is_required' => true,
                'sort_order' => $index,
            ]);
        }
    }
}
