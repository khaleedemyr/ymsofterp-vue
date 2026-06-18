<?php

namespace App\Models\JustAcademy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaQuiz extends Model
{
    protected $table = 'ja_quizzes';

    protected $fillable = [
        'title',
        'pass_score',
        'time_limit_min',
        'time_limit_mode',
        'time_limit_question_sec',
        'questions_per_attempt',
        'randomize_questions',
        'randomize_options',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'pass_score' => 'decimal:2',
        'time_limit_min' => 'integer',
        'time_limit_question_sec' => 'integer',
        'questions_per_attempt' => 'integer',
        'randomize_questions' => 'boolean',
        'randomize_options' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(JaQuizQuestion::class, 'quiz_id')->orderBy('sort_order');
    }

    public function programItems(): HasMany
    {
        return $this->hasMany(JaProgramItem::class, 'quiz_id');
    }

    public function effectiveTimeLimitMode(): string
    {
        if (!empty($this->time_limit_mode) && $this->time_limit_mode !== 'none') {
            return $this->time_limit_mode;
        }

        return $this->time_limit_min ? 'quiz' : 'none';
    }
}
