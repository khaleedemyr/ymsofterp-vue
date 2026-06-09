<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiEvaluation extends Model
{
    protected $table = 'kpi_evaluations';

    protected $fillable = [
        'evaluation_code',
        'user_id',
        'kpi_template_id',
        'template_version',
        'id_jabatan',
        'id_outlet',
        'division_id',
        'employee_name',
        'jabatan_name',
        'outlet_name',
        'division_name',
        'period_month',
        'period_start',
        'period_end',
        'eval_status',
        'total_score',
        'scoring_rules',
        'assessed_by',
        'employee_comments',
        'assessor_comments',
        'submitted_at',
        'locked_at',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_score' => 'decimal:2',
        'scoring_rules' => 'array',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parameterValues(): HasMany
    {
        return $this->hasMany(KpiEvaluationParameterValue::class, 'kpi_evaluation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KpiEvaluationItem::class, 'kpi_evaluation_id')->orderBy('sort_order');
    }

    public function isEditable(): bool
    {
        return $this->eval_status === 'draft';
    }
}
