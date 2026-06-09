<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiTemplateStrategy extends Model
{
    protected $table = 'kpi_template_strategies';

    protected $fillable = [
        'kpi_template_id',
        'kpi_key_strategy_id',
        'weight_percent',
        'sort_order',
    ];

    protected $casts = [
        'weight_percent' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    public function keyStrategy(): BelongsTo
    {
        return $this->belongsTo(KpiKeyStrategy::class, 'kpi_key_strategy_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KpiTemplateItem::class, 'kpi_template_strategy_id')->orderBy('sort_order');
    }
}
