<?php

namespace App\Models\JustAcademy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaFeedback extends Model
{
    protected $table = 'ja_feedbacks';

    protected $fillable = [
        'schedule_id',
        'user_id',
        'trainer_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(JaSchedule::class, 'schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
}
