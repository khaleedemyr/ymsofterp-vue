<?php

namespace App\Models\JustAcademy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaQuiz extends Model
{
    protected $table = 'ja_quizzes';

    protected $fillable = [
        'program_id',
        'title',
        'type',
        'pass_score',
        'time_limit_min',
        'is_active',
    ];

    protected $casts = [
        'pass_score' => 'decimal:2',
        'time_limit_min' => 'integer',
        'is_active' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(JaProgram::class, 'program_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(JaQuizQuestion::class, 'quiz_id')->orderBy('sort_order');
    }
}
