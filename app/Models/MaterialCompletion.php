<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'material_id',
        'schedule_id',
        'session_id',
        'session_item_id',
        'completed_at',
        'time_spent_seconds',
        'completion_data',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'completion_data' => 'array',
    ];

    /**
     * Get the user that completed the material
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the material that was completed
     */
    public function material()
    {
        return $this->belongsTo(LmsCurriculumMaterial::class, 'material_id');
    }

    /**
     * Get the training schedule
     */
    public function schedule()
    {
        return $this->belongsTo(TrainingSchedule::class);
    }

    /**
     * Get the session
     */
    public function session()
    {
        return $this->belongsTo(LmsSession::class);
    }

    /**
     * Get the session item
     */
    public function sessionItem()
    {
        return $this->belongsTo(LmsSessionItem::class);
    }

    /**
     * Check if a material is completed by a user for a specific schedule
     */
    public static function isCompleted($userId, $materialId, $scheduleId)
    {
        return static::where('user_id', $userId)
                    ->where('material_id', $materialId)
                    ->where('schedule_id', $scheduleId)
                    ->exists();
    }

    /**
     * Mark a material as completed
     */
    public static function markCompleted($userId, $materialId, $scheduleId, $sessionId, $sessionItemId, $timeSpentSeconds = null, $completionData = null)
    {
        return static::updateOrCreate(
            [
                'user_id' => $userId,
                'material_id' => $materialId,
                'schedule_id' => $scheduleId,
            ],
            [
                'session_id' => $sessionId,
                'session_item_id' => $sessionItemId,
                'completed_at' => now(),
                'time_spent_seconds' => $timeSpentSeconds,
                'completion_data' => $completionData,
            ]
        );
    }
}
