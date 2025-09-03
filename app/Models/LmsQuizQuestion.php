<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LmsQuizQuestion extends Model
{
    use HasFactory;

    protected $table = 'lms_quiz_questions';

    protected $fillable = [
        'quiz_id',
        'question_text',
        'question_type',
        'points',
        'image_path',
        'image_alt_text',
        'is_required',
        'order_number'
    ];

    protected $casts = [
        'points' => 'integer',
        'is_required' => 'boolean',
        'order_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function quiz()
    {
        return $this->belongsTo(LmsQuiz::class, 'quiz_id');
    }

    public function options()
    {
        return $this->hasMany(LmsQuizOption::class, 'question_id')->orderBy('order_number');
    }

    public function answers()
    {
        return $this->hasMany(LmsQuizAnswer::class, 'question_id');
    }

    // Scopes
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_number');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('question_type', $type);
    }

    // Accessors
    public function getCorrectOptionsAttribute()
    {
        return $this->options()->where('is_correct', true)->get();
    }

    public function getHasCorrectAnswerAttribute()
    {
        return $this->options()->where('is_correct', true)->exists();
    }

    // Methods
    public function isCorrectAnswer($answer)
    {
        if ($this->question_type === 'multiple_choice') {
            $correctOption = $this->options()->where('is_correct', true)->first();
            return $correctOption && $answer === $correctOption->id;
        }

        if ($this->question_type === 'true_false') {
            $correctOption = $this->options()->where('is_correct', true)->first();
            return $correctOption && $answer === $correctOption->option_text;
        }

        // Essay questions need manual grading
        return null;
    }

    public function getMaxPoints()
    {
        return $this->points;
    }

    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return request()->getSchemeAndHttpHost() . '/storage/' . $this->image_path;
        }
        return null;
    }

    public function hasImage()
    {
        return !empty($this->image_path);
    }
} 