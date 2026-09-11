<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimeSubmission extends Model
{
    use SoftDeletes;

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'number',
        'submission_date',
        'notes',
        'status',
        'edit_reason',
        'edit_changes',
        'edited_at',
        'edited_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'submission_date' => 'date:Y-m-d',
        'edit_changes' => 'array',
        'edited_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OvertimeSubmissionItem::class, 'submission_id');
    }

    public function approvalFlows(): HasMany
    {
        return $this->hasMany(OvertimeSubmissionApprovalFlow::class, 'overtime_submission_id')
            ->orderBy('approval_level');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function hasPendingEditHighlight(): bool
    {
        return is_array($this->edit_changes) && $this->edit_changes !== [];
    }
}
