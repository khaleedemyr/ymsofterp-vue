<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiTemplateItem extends Model
{
    protected $table = 'kpi_template_items';

    protected $fillable = [
        'kpi_template_strategy_id',
        'name',
        'description',
        'weight_percent',
        'target_value',
        'target_direction',
        'frequency',
        'formula',
        'scoring_levels',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'weight_percent' => 'decimal:2',
        'scoring_levels' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(KpiTemplateStrategy::class, 'kpi_template_strategy_id');
    }

    public function itemParameters(): HasMany
    {
        return $this->hasMany(KpiTemplateItemParameter::class, 'kpi_template_item_id')->orderBy('sort_order');
    }
}
