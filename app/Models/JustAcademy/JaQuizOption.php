<?php

namespace App\Models\JustAcademy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaQuizOption extends Model
{
    protected $table = 'ja_quiz_options';

    protected $fillable = [
        'question_id',
        'option_text',
        'is_correct',
        'sort_order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(JaQuizQuestion::class, 'question_id');
    }
}
