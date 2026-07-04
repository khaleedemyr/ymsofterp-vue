<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeOnboarding extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'template_id',
        'template_name',
        'employee_user_id',
        'outlet_id',
        'outlet_name',
        'start_date',
        'current_week',
        'unlocked_week',
        'total_weeks',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'current_week' => 'integer',
        'unlocked_week' => 'integer',
        'total_weeks' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'template_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EmployeeOnboardingItem::class, 'onboarding_id')->orderBy('week_number')->orderBy('sort_order');
    }

    public function weekSubmissions(): HasMany
    {
        return $this->hasMany(EmployeeOnboardingWeekSubmission::class, 'onboarding_id')->orderBy('week_number');
    }
}
