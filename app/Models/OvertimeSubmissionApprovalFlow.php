<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeSubmissionApprovalFlow extends Model
{
    protected $table = 'overtime_submission_approval_flows';

    protected $fillable = [
        'overtime_submission_id',
        'approver_id',
        'approval_level',
        'status',
        'approved_at',
        'rejected_at',
        'comments',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(OvertimeSubmission::class, 'overtime_submission_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
