<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeOnboardingWeekSubmission extends Model
{
    protected $fillable = [
        'onboarding_id',
        'week_number',
        'status',
        'submitted_at',
        'submitted_by',
        'approved_at',
    ];

    protected $casts = [
        'week_number' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(EmployeeOnboarding::class, 'onboarding_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvalFlows(): HasMany
    {
        return $this->hasMany(EmployeeOnboardingApprovalFlow::class, 'week_submission_id')->orderBy('approval_level');
    }
}
