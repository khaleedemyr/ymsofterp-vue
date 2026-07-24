<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WfhRequest extends Model
{
    use SoftDeletes;

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'number',
        'user_id',
        'wfh_date',
        'reason',
        'status',
        'outlet_id',
        'shift_id',
        'shift_name',
        'time_start',
        'time_end',
        'sn',
        'pin',
        'att_log_written_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'wfh_date' => 'date:Y-m-d',
        'att_log_written_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WfhRequestTask::class, 'wfh_request_id')->orderBy('sort_order');
    }

    public function approvalFlows(): HasMany
    {
        return $this->hasMany(WfhRequestApprovalFlow::class, 'wfh_request_id')
            ->orderBy('approval_level');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
