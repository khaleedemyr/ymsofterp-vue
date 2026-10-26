<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarcommTracker extends Model
{
    public const JOB_CATEGORIES = [
        'Production',
        'Creative',
        'Martech',
        'Customer Relation',
        'Creative Brief',
        'Reporting',
        'Influencer',
        'Event Activation',
        'Administration',
        'Mall Relation',
    ];

    public const JOB_STATUSES = [
        'on_progress' => 'On Progress',
        'rev_1' => 'Rev 1',
        'rev_2' => 'Rev 2',
    ];

    public const WORKFLOW_STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    protected $fillable = [
        'number',
        'job_category',
        'pic_user_id',
        'task',
        'due_date',
        'job_status',
        'status_remarks',
        'output_links',
        'remarks',
        'workflow_status',
        'created_by',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'status_remarks' => 'array',
        'output_links' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvalFlows(): HasMany
    {
        return $this->hasMany(MarcommTrackerApprovalFlow::class)->orderBy('approval_level');
    }

    public function jobStatusLabel(): string
    {
        return self::JOB_STATUSES[$this->job_status] ?? $this->job_status;
    }

    public function workflowStatusLabel(): string
    {
        return self::WORKFLOW_STATUSES[$this->workflow_status] ?? $this->workflow_status;
    }
}
