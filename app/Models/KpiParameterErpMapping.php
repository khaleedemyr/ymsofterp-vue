<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiParameterErpMapping extends Model
{
    protected $table = 'kpi_parameter_erp_mappings';

    protected $fillable = [
        'kpi_parameter_id',
        'resolver_key',
        'static_filters',
        'dynamic_filter_bindings',
        'aggregation',
        'status',
    ];

    protected $casts = [
        'static_filters' => 'array',
        'dynamic_filter_bindings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(KpiParameter::class, 'kpi_parameter_id');
    }
}
