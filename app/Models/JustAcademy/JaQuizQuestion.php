<?php

namespace App\Models\JustAcademy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaQuizQuestion extends Model
{
    protected $table = 'ja_quiz_questions';

    protected $fillable = [
        'quiz_id',
        'question',
        'type',
        'sort_order',
        'points',
    ];

    protected $casts = [
        'points' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(JaQuiz::class, 'quiz_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(JaQuizOption::class, 'question_id')->orderBy('sort_order');
    }
}
