<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiTemplate extends Model
{
    protected $table = 'kpi_templates';

    protected $fillable = [
        'code',
        'name',
        'description',
        'version',
        'template_status',
        'scoring_rules',
        'erp_data_scope',
        'erp_scope_outlet_ids',
        'status',
        'created_by',
    ];

    protected $casts = [
        'scoring_rules' => 'array',
        'erp_scope_outlet_ids' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(KpiTemplatePosition::class, 'kpi_template_id');
    }

    public function strategies(): HasMany
    {
        return $this->hasMany(KpiTemplateStrategy::class, 'kpi_template_id')->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }
}
