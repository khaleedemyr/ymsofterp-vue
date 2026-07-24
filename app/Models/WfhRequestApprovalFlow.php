<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WfhRequestApprovalFlow extends Model
{
    protected $table = 'wfh_request_approval_flows';

    protected $fillable = [
        'wfh_request_id',
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

    public function request(): BelongsTo
    {
        return $this->belongsTo(WfhRequest::class, 'wfh_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
