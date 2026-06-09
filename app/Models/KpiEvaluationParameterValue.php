<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiEvaluationParameterValue extends Model
{
    protected $table = 'kpi_evaluation_parameter_values';

    protected $fillable = [
        'kpi_evaluation_id',
        'kpi_parameter_id',
        'parameter_code',
        'parameter_name',
        'source_type',
        'scope_type',
        'erp_value',
        'manual_value',
        'final_value',
        'is_overridden',
        'override_reason',
        'erp_fetched_at',
    ];

    protected $casts = [
        'erp_value' => 'decimal:4',
        'manual_value' => 'decimal:4',
        'final_value' => 'decimal:4',
        'is_overridden' => 'boolean',
        'erp_fetched_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(KpiEvaluation::class, 'kpi_evaluation_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(KpiParameter::class, 'kpi_parameter_id');
    }
}
