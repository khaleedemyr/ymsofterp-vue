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
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'pass_score' => 'decimal:2',
        'time_limit_min' => 'integer',
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
}
