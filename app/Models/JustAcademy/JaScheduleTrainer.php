<?php

namespace App\Models\JustAcademy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaScheduleTrainer extends Model
{
    protected $table = 'ja_schedule_trainers';

    protected $fillable = [
        'schedule_id',
        'user_id',
        'role',
        'hours',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(JaSchedule::class, 'schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
