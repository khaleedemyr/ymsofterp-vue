<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiTemplateItemParameter extends Model
{
    protected $table = 'kpi_template_item_parameters';

    protected $fillable = [
        'kpi_template_item_id',
        'kpi_parameter_id',
        'alias',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(KpiTemplateItem::class, 'kpi_template_item_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(KpiParameter::class, 'kpi_parameter_id');
    }
}
