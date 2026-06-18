<?php

namespace App\Models\JustAcademy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaQuizAttempt extends Model
{
    protected $table = 'ja_quiz_attempts';

    protected $fillable = [
        'schedule_id',
        'quiz_id',
        'user_id',
        'question_ids',
        'option_orders',
        'quiz_progress',
        'score',
        'passed',
        'started_at',
        'submitted_at',
    ];

    protected $casts = [
        'question_ids' => 'array',
        'option_orders' => 'array',
        'quiz_progress' => 'array',
        'score' => 'decimal:2',
        'passed' => 'boolean',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(JaSchedule::class, 'schedule_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(JaQuiz::class, 'quiz_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(JaQuizAnswer::class, 'attempt_id');
    }
}
