<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOnboardingApprovalFlow extends Model
{
    protected $fillable = [
        'week_submission_id',
        'approver_id',
        'approval_level',
        'status',
        'approved_at',
        'rejected_at',
        'comments',
    ];

    protected $casts = [
        'approval_level' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function weekSubmission(): BelongsTo
    {
        return $this->belongsTo(EmployeeOnboardingWeekSubmission::class, 'week_submission_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
