<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPlanApprovalFlow extends Model
{
    protected $fillable = [
        'content_plan_id',
        'approver_id',
        'approval_level',
        'status',
        'notes',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function contentPlan(): BelongsTo
    {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
