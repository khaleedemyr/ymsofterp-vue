<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarcommTrackerApprovalFlow extends Model
{
    protected $fillable = [
        'marcomm_tracker_id',
        'approver_id',
        'approval_level',
        'status',
        'notes',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function tracker(): BelongsTo
    {
        return $this->belongsTo(MarcommTracker::class, 'marcomm_tracker_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
