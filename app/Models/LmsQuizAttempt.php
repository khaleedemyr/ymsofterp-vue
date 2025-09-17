<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LmsQuizAttempt extends Model
{
    use HasFactory;

    protected $table = 'lms_quiz_attempts';

    protected $fillable = [
        'quiz_id',
        'user_id',
        'started_at',
        'completed_at',
        'score',
        'is_passed',
        'attempt_number',
        'status'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'score' => 'decimal:2',
        'is_passed' => 'boolean',
        'attempt_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function quiz()
    {
        return $this->belongsTo(LmsQuiz::class, 'quiz_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function answers()
    {
        return $this->hasMany(LmsQuizAnswer::class, 'attempt_id');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopePassed($query)
    {
        return $query->where('is_passed', true);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Accessors
    public function getDurationAttribute()
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    public function getDurationFormattedAttribute()
    {
        $duration = $this->duration;
        if (!$duration) {
            return 'N/A';
        }

        $minutes = floor($duration / 60);
        $seconds = $duration % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    // Methods
    public function calculateScore()
    {
        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($this->answers as $answer) {
            $totalPoints += $answer->question->points;
            if ($answer->is_correct) {
                $earnedPoints += $answer->points_earned ?? $answer->question->points;
            }
        }

        if ($totalPoints === 0) {
            return 0;
        }

        return round(($earnedPoints / $totalPoints) * 100, 2);
    }

    public function markAsCompleted()
    {
        $this->update([
            'completed_at' => now(),
            'score' => $this->calculateScore(),
            'is_passed' => $this->score >= $this->quiz->passing_score,
            'status' => 'completed'
        ]);
    }
}
