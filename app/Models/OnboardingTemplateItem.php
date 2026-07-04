<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTemplateItem extends Model
{
    protected $fillable = [
        'template_id',
        'area_id',
        'checklist_text',
        'pic_role_hint',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'template_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplateArea::class, 'area_id');
    }
}
