<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingTemplateArea extends Model
{
    protected $fillable = [
        'template_id',
        'week_id',
        'area_name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'template_id');
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplateWeek::class, 'week_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OnboardingTemplateItem::class, 'area_id')->orderBy('sort_order');
    }
}
