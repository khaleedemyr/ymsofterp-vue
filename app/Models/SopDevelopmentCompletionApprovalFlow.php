<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SopDevelopmentCompletionApprovalFlow extends Model
{
    protected $table = 'sop_development_completion_approval_flows';

    protected $fillable = [
        'sop_development_completion_id',
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

    public function sopDevelopmentCompletion(): BelongsTo
    {
        return $this->belongsTo(SopDevelopmentCompletion::class, 'sop_development_completion_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeOrderedByLevel($query)
    {
        return $query->orderBy('approval_level');
    }
}
