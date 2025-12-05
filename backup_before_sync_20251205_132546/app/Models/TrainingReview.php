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
        'would_recommend',
        'improvement_suggestions',
        // Trainer ratings
        'trainer_mastery',
        'trainer_language',
        'trainer_intonation',
        'trainer_presentation',
        'trainer_qna',
        // Training material ratings
        'material_benefit',
        'material_clarity',
        'material_display',
        'material_suggestions',
        'material_needs',
    ];

    protected $casts = [
        'training_rating' => 'integer',
        'trainer_rating' => 'integer',
        'overall_satisfaction' => 'integer',
        'would_recommend' => 'integer',
        // Trainer ratings
        'trainer_mastery' => 'integer',
        'trainer_language' => 'integer',
        'trainer_intonation' => 'integer',
        'trainer_presentation' => 'integer',
        'trainer_qna' => 'integer',
        // Training material ratings
        'material_benefit' => 'integer',
        'material_clarity' => 'integer',
        'material_display' => 'integer',
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
     * Get average rating for trainer.
     */
    public static function getAverageTrainerRating($trainingScheduleId)
    {
        $reviews = self::where('training_schedule_id', $trainingScheduleId)->get();
        if ($reviews->isEmpty()) return 0;
        
        $totalRating = 0;
        $totalCount = 0;
        
        foreach ($reviews as $review) {
            $totalRating += $review->trainer_mastery + $review->trainer_language + 
                           $review->trainer_intonation + $review->trainer_presentation + 
                           $review->trainer_qna;
            $totalCount += 5;
        }
        
        return $totalCount > 0 ? round($totalRating / $totalCount, 2) : 0;
    }

    /**
     * Get average rating for training material.
     */
    public static function getAverageMaterialRating($trainingScheduleId)
    {
        $reviews = self::where('training_schedule_id', $trainingScheduleId)->get();
        if ($reviews->isEmpty()) return 0;
        
        $totalRating = 0;
        $totalCount = 0;
        
        foreach ($reviews as $review) {
            $totalRating += $review->material_benefit + $review->material_clarity + 
                           $review->material_display;
            $totalCount += 3;
        }
        
        return $totalCount > 0 ? round($totalRating / $totalCount, 2) : 0;
    }

    /**
     * Get recommendation percentage based on average ratings.
     */
    public static function getRecommendationPercentage($trainingScheduleId)
    {
        $reviews = self::where('training_schedule_id', $trainingScheduleId)->get();
        if ($reviews->isEmpty()) return 0;
        
        $recommended = 0;
        
        foreach ($reviews as $review) {
            // Calculate average trainer rating
            $trainerAvg = ($review->trainer_mastery + $review->trainer_language + 
                          $review->trainer_intonation + $review->trainer_presentation + 
                          $review->trainer_qna) / 5;
            
            // Calculate average material rating
            $materialAvg = ($review->material_benefit + $review->material_clarity + 
                           $review->material_display) / 3;
            
            // Consider recommended if both averages are 4 or higher
            if ($trainerAvg >= 4 && $materialAvg >= 4) {
                $recommended++;
            }
        }
        
        return round(($recommended / $reviews->count()) * 100, 1);
    }
}
