<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LmsQuizAnswer extends Model
{
    use HasFactory;

    protected $table = 'lms_quiz_answers';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_option_id',
        'essay_answer',
        'is_correct',
        'points_earned'
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'points_earned' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function attempt()
    {
        return $this->belongsTo(LmsQuizAttempt::class, 'attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(LmsQuizQuestion::class, 'question_id');
    }

    public function selectedOption()
    {
        return $this->belongsTo(LmsQuizOption::class, 'selected_option_id');
    }

    // Scopes
    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }

    public function scopeIncorrect($query)
    {
        return $query->where('is_correct', false);
    }

    // Methods
    public function checkAnswer()
    {
        if ($this->question->question_type === 'multiple_choice') {
            $this->is_correct = $this->selectedOption && $this->selectedOption->is_correct;
            $this->points_earned = $this->is_correct ? $this->question->points : 0;
        } elseif ($this->question->question_type === 'true_false') {
            $this->is_correct = $this->selectedOption && $this->selectedOption->is_correct;
            $this->points_earned = $this->is_correct ? $this->question->points : 0;
        } elseif ($this->question->question_type === 'essay') {
            // Essay answers need manual grading
            $this->is_correct = null;
            $this->points_earned = null;
        }

        $this->save();
    }
}
