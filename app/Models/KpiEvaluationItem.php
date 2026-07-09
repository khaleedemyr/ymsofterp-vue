<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiEvaluationItem extends Model
{
    protected $table = 'kpi_evaluation_items';

    protected $fillable = [
        'kpi_evaluation_id',
        'kpi_template_strategy_id',
        'kpi_template_item_id',
        'kpi_parameter_id',
        'key_strategy_name',
        'strategy_weight_percent',
        'item_name',
        'weight_percent',
        'target_value',
        'target_direction',
        'frequency',
        'formula',
        'achievement_percent',
        'performance_level',
        'score',
        'weighted_score',
        'improvement_plan',
        'improvement_plan_due_date',
        'sort_order',
    ];

    protected $casts = [
        'strategy_weight_percent' => 'decimal:2',
        'weight_percent' => 'decimal:2',
        'achievement_percent' => 'decimal:4',
        'score' => 'decimal:2',
        'weighted_score' => 'decimal:4',
        'improvement_plan_due_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(KpiEvaluation::class, 'kpi_evaluation_id');
    }
}
