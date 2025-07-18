<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsQuizQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_quiz_questions';

    protected $fillable = [
        'quiz_id',
        'question_text',
        'question_type',
        'points',
        'order_number',
        'is_required',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'points' => 'integer',
        'order_number' => 'integer',
        'is_required' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function quiz()
    {
        return $this->belongsTo(LmsQuiz::class, 'quiz_id');
    }

    public function options()
    {
        return $this->hasMany(LmsQuizOption::class, 'question_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_number');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('question_type', $type);
    }

    // Accessors
    public function getQuestionTypeTextAttribute()
    {
        $types = [
            'multiple_choice' => 'Pilihan Ganda',
            'true_false' => 'Benar/Salah',
            'short_answer' => 'Jawaban Singkat',
            'essay' => 'Essay',
            'matching' => 'Menjodohkan'
        ];
        
        return $types[$this->question_type] ?? $this->question_type;
    }

    public function getCorrectOptionsAttribute()
    {
        return $this->options()->where('is_correct', true)->get();
    }

    public function getOptionsCountAttribute()
    {
        return $this->options()->count();
    }

    // Methods
    public function getCorrectAnswer()
    {
        if ($this->question_type === 'multiple_choice') {
            return $this->options()->where('is_correct', true)->first();
        } elseif ($this->question_type === 'true_false') {
            return $this->options()->where('is_correct', true)->first();
        }
        
        return null;
    }

    public function isCorrectAnswer($answer)
    {
        if ($this->question_type === 'multiple_choice') {
            $correctOption = $this->getCorrectAnswer();
            return $correctOption && $correctOption->id == $answer;
        } elseif ($this->question_type === 'true_false') {
            $correctOption = $this->getCorrectAnswer();
            return $correctOption && $correctOption->id == $answer;
        }
        
        return false;
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($question) {
            if (!$question->created_by) {
                $question->created_by = auth()->id();
            }
            if (!$question->updated_by) {
                $question->updated_by = auth()->id();
            }
            if (!$question->order_number) {
                $question->order_number = static::where('quiz_id', $question->quiz_id)->max('order_number') + 1;
            }
        });

        static::updating(function ($question) {
            $question->updated_by = auth()->id();
        });
    }
} 