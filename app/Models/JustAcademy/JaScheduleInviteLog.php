<?php

namespace App\Models\JustAcademy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaScheduleInviteLog extends Model
{
    protected $table = 'ja_schedule_invite_logs';

    public $timestamps = false;

    protected $fillable = [
        'schedule_id',
        'invited_by',
        'filter_type',
        'filter_payload',
        'participants_added',
        'created_at',
    ];

    protected $casts = [
        'filter_payload' => 'array',
        'participants_added' => 'integer',
        'created_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(JaSchedule::class, 'schedule_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
