<?php

namespace App\Models\JustAcademy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaScheduleParticipant extends Model
{
    protected $table = 'ja_schedule_participants';

    protected $fillable = [
        'schedule_id',
        'user_id',
        'invite_source',
        'status',
        'invited_at',
        'invited_by',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(JaSchedule::class, 'schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
