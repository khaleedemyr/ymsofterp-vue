<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingTemplateWeek extends Model
{
    protected $fillable = [
        'template_id',
        'week_number',
        'week_label',
        'sort_order',
    ];

    protected $casts = [
        'week_number' => 'integer',
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'template_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(OnboardingTemplateArea::class, 'week_id')->orderBy('sort_order');
    }
}
