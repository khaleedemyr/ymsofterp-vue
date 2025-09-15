<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserTrainingHours extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_training_hours';

    protected $fillable = [
        'user_id',
        'course_id',
        'enrollment_id',
        'hours_completed',
        'total_course_hours',
        'last_updated',
        'completion_date',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'hours_completed' => 'decimal:2',
        'total_course_hours' => 'decimal:2',
        'last_updated' => 'datetime',
        'completion_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function enrollment()
    {
        return $this->belongsTo(LmsEnrollment::class, 'enrollment_id');
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
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeDropped($query)
    {
        return $query->where('status', 'dropped');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    // Accessors
    public function getProgressPercentageAttribute()
    {
        if ($this->total_course_hours <= 0) {
            return 0;
        }
        return round(($this->hours_completed / $this->total_course_hours) * 100, 2);
    }

    public function getStatusTextAttribute()
    {
        $statusMap = [
            'in_progress' => 'Sedang Belajar',
            'completed' => 'Selesai',
            'dropped' => 'Dibatalkan'
        ];
        return $statusMap[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        $colorMap = [
            'in_progress' => 'yellow',
            'completed' => 'green',
            'dropped' => 'red'
        ];
        return $colorMap[$this->status] ?? 'gray';
    }

    public function getRemainingHoursAttribute()
    {
        return max(0, $this->total_course_hours - $this->hours_completed);
    }

    // Methods
    public static function getTrainingHoursForUser($userId)
    {
        return self::with(['course.category', 'course.trainers'])
            ->byUser($userId)
            ->orderBy('last_updated', 'desc')
            ->get();
    }

    public static function getCompletedTrainingsForUser($userId)
    {
        return self::with(['course.category', 'course.trainers'])
            ->byUser($userId)
            ->completed()
            ->orderBy('completion_date', 'desc')
            ->get();
    }

    public static function getInProgressTrainingsForUser($userId)
    {
        return self::with(['course.category', 'course.trainers'])
            ->byUser($userId)
            ->inProgress()
            ->orderBy('last_updated', 'desc')
            ->get();
    }

    public static function getTotalTrainingHoursForUser($userId)
    {
        return self::byUser($userId)
            ->completed()
            ->sum('hours_completed');
    }

    public static function getUsersForCourse($courseId)
    {
        return self::with(['user.jabatan', 'user.divisi'])
            ->byCourse($courseId)
            ->orderBy('hours_completed', 'desc')
            ->get();
    }

    public function updateProgress($hoursCompleted, $notes = null)
    {
        $this->hours_completed = $hoursCompleted;
        $this->last_updated = now();
        
        if ($hoursCompleted >= $this->total_course_hours) {
            $this->status = 'completed';
            $this->completion_date = now();
        }
        
        if ($notes) {
            $this->notes = $notes;
        }
        
        $this->save();
        
        // Update user's total training hours
        $this->updateUserTotalTrainingHours();
    }

    public function markAsCompleted()
    {
        $this->status = 'completed';
        $this->hours_completed = $this->total_course_hours;
        $this->completion_date = now();
        $this->last_updated = now();
        $this->save();
        
        $this->updateUserTotalTrainingHours();
    }

    public function markAsDropped($notes = null)
    {
        $this->status = 'dropped';
        $this->last_updated = now();
        
        if ($notes) {
            $this->notes = $notes;
        }
        
        $this->save();
    }

    private function updateUserTotalTrainingHours()
    {
        $totalHours = self::byUser($this->user_id)
            ->completed()
            ->sum('hours_completed');
            
        $this->user->update(['total_training_hours' => $totalHours]);
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->created_by) {
                $model->created_by = auth()->id();
            }
            if (!$model->updated_by) {
                $model->updated_by = auth()->id();
            }
            if (!$model->last_updated) {
                $model->last_updated = now();
            }
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->id();
        });
    }
}
