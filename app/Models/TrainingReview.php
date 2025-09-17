<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_schedule_id',
        'user_id',
        'trainer_id',
        'training_rating',
        'training_feedback',
        'trainer_rating',
        'trainer_feedback',
        'overall_satisfaction',
        'improvement_suggestions',
    ];

    protected $casts = [
        'training_rating' => 'integer',
        'trainer_rating' => 'integer',
        'overall_satisfaction' => 'integer',
    ];

    /**
     * Get the training schedule that owns the review.
     */
    public function trainingSchedule(): BelongsTo
    {
        return $this->belongsTo(TrainingSchedule::class);
    }

    /**
     * Get the user that owns the review.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the trainer that was reviewed.
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }


    /**
     * Check if user has already reviewed this training.
     */
    public static function hasReviewed($trainingScheduleId, $userId)
    {
        return self::where('training_schedule_id', $trainingScheduleId)
                   ->where('user_id', $userId)
                   ->exists();
    }

    /**
     * Get average rating for training.
     */
    public static function getAverageTrainingRating($trainingScheduleId)
    {
        return self::where('training_schedule_id', $trainingScheduleId)
                   ->avg('training_rating');
    }

    /**
     * Get average rating for trainer.
     */
    public static function getAverageTrainerRating($trainingScheduleId)
    {
        return self::where('training_schedule_id', $trainingScheduleId)
                   ->avg('trainer_rating');
    }

    /**
     * Get recommendation percentage based on overall satisfaction.
     */
    public static function getRecommendationPercentage($trainingScheduleId)
    {
        $total = self::where('training_schedule_id', $trainingScheduleId)->count();
        if ($total === 0) return 0;
        
        // Consider ratings 4 and 5 as recommendations
        $recommended = self::where('training_schedule_id', $trainingScheduleId)
                          ->where('overall_satisfaction', '>=', 4)
                          ->count();
        
        return round(($recommended / $total) * 100, 1);
    }
}
