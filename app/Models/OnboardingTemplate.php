<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'total_weeks',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'total_weeks' => 'integer',
    ];

    public function weeks(): HasMany
    {
        return $this->hasMany(OnboardingTemplateWeek::class, 'template_id')->orderBy('week_number');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(OnboardingTemplateArea::class, 'template_id')->orderBy('sort_order');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OnboardingTemplateItem::class, 'template_id')->orderBy('sort_order');
    }

    public function weekApprovers(): HasMany
    {
        return $this->hasMany(OnboardingTemplateWeekApprover::class, 'template_id')->orderBy('week_number')->orderBy('approval_level');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
