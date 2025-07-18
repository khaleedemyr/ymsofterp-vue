<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsQuiz extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_quizzes';

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'instructions',
        'time_limit_minutes',
        'passing_score',
        'max_attempts',
        'is_randomized',
        'show_results',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'time_limit_minutes' => 'integer',
        'passing_score' => 'integer',
        'max_attempts' => 'integer',
        'is_randomized' => 'boolean',
        'show_results' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function questions()
    {
        return $this->hasMany(LmsQuizQuestion::class, 'quiz_id');
    }

    public function attempts()
    {
        return $this->hasMany(LmsQuizAttempt::class, 'quiz_id');
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
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Accessors
    public function getQuestionsCountAttribute()
    {
        return $this->questions()->count();
    }

    public function getAttemptsCountAttribute()
    {
        return $this->attempts()->count();
    }

    public function getAverageScoreAttribute()
    {
        return $this->attempts()->avg('score') ?? 0;
    }

    public function getPassRateAttribute()
    {
        $totalAttempts = $this->attempts()->count();
        if ($totalAttempts === 0) {
            return 0;
        }

        $passedAttempts = $this->attempts()
            ->where('score', '>=', $this->passing_score)
            ->count();

        return round(($passedAttempts / $totalAttempts) * 100, 2);
    }

    // Methods
    public function canBeTakenByUser($userId)
    {
        $userAttempts = $this->attempts()->where('user_id', $userId)->count();
        return $userAttempts < $this->max_attempts;
    }

    public function getUserBestScore($userId)
    {
        return $this->attempts()
            ->where('user_id', $userId)
            ->max('score') ?? 0;
    }

    public function getUserAttempts($userId)
    {
        return $this->attempts()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');
    }

    public function isPassedByUser($userId)
    {
        $bestScore = $this->getUserBestScore($userId);
        return $bestScore >= $this->passing_score;
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quiz) {
            if (!$quiz->created_by) {
                $quiz->created_by = auth()->id();
            }
            if (!$quiz->updated_by) {
                $quiz->updated_by = auth()->id();
            }
        });

        static::updating(function ($quiz) {
            $quiz->updated_by = auth()->id();
        });
    }
} 