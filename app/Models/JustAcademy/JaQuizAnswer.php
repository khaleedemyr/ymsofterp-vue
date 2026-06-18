<?php

namespace App\Models\JustAcademy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaQuizAnswer extends Model
{
    protected $table = 'ja_quiz_answers';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'option_id',
        'answer_text',
        'is_correct',
        'points_earned',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'points_earned' => 'decimal:2',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(JaQuizAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(JaQuizQuestion::class, 'question_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(JaQuizOption::class, 'option_id');
    }
}
