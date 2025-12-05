<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainerTeachingHours extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'trainer_teaching_hours';

    protected $fillable = [
        'trainer_id',
        'course_id',
        'schedule_id',
        'hours_taught',
        'teaching_date',
        'start_time',
        'end_time',
        'participant_count',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'hours_taught' => 'decimal:2',
        'teaching_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function schedule()
    {
        return $this->belongsTo(TrainingSchedule::class, 'schedule_id');
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
    public function scopeByTrainer($query, $trainerId)
    {
        return $query->where('trainer_id', $trainerId);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('teaching_date', [$startDate, $endDate]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('teaching_date', now()->month)
            ->whereYear('teaching_date', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('teaching_date', now()->year);
    }

    // Accessors
    public function getDurationTextAttribute()
    {
        if ($this->start_time && $this->end_time) {
            $start = \Carbon\Carbon::parse($this->start_time);
            $end = \Carbon\Carbon::parse($this->end_time);
            $duration = $start->diffInMinutes($end);
            
            $hours = floor($duration / 60);
            $minutes = $duration % 60;
            
            if ($hours > 0) {
                return $hours . 'j ' . $minutes . 'm';
            }
            return $minutes . ' menit';
        }
        return $this->hours_taught . ' jam';
    }

    public function getTeachingDateFormattedAttribute()
    {
        return $this->teaching_date->format('d/m/Y');
    }

    public function getTimeRangeAttribute()
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
        }
        return '-';
    }

    // Methods
    public static function getTeachingHoursForTrainer($trainerId, $startDate = null, $endDate = null)
    {
        $query = self::with(['course.category', 'schedule'])
            ->byTrainer($trainerId);
            
        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }
        
        return $query->orderBy('teaching_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();
    }

    public static function getTotalTeachingHoursForTrainer($trainerId, $startDate = null, $endDate = null)
    {
        $query = self::byTrainer($trainerId);
        
        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }
        
        return $query->sum('hours_taught');
    }

    public static function getTeachingHoursThisMonthForTrainer($trainerId)
    {
        return self::byTrainer($trainerId)
            ->thisMonth()
            ->sum('hours_taught');
    }

    public static function getTeachingHoursThisYearForTrainer($trainerId)
    {
        return self::byTrainer($trainerId)
            ->thisYear()
            ->sum('hours_taught');
    }

    public static function getTrainersForCourse($courseId, $startDate = null, $endDate = null)
    {
        $query = self::with(['trainer.jabatan', 'trainer.divisi'])
            ->byCourse($courseId);
            
        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }
        
        return $query->orderBy('teaching_date', 'desc')
            ->get();
    }

    public static function getTopTrainersByHours($limit = 10, $startDate = null, $endDate = null)
    {
        $query = self::with(['trainer.jabatan', 'trainer.divisi'])
            ->selectRaw('trainer_id, SUM(hours_taught) as total_hours, COUNT(*) as session_count')
            ->groupBy('trainer_id');
            
        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }
        
        return $query->orderBy('total_hours', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function getTeachingStatistics($startDate = null, $endDate = null)
    {
        $query = self::query();
        
        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }
        
        return [
            'total_hours' => $query->sum('hours_taught'),
            'total_sessions' => $query->count(),
            'total_participants' => $query->sum('participant_count'),
            'unique_trainers' => $query->distinct('trainer_id')->count(),
            'unique_courses' => $query->distinct('course_id')->count(),
        ];
    }

    public function updateTrainerTotalTeachingHours()
    {
        $totalHours = self::byTrainer($this->trainer_id)
            ->sum('hours_taught');
            
        $this->trainer->update(['total_teaching_hours' => $totalHours]);
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
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->id();
        });

        static::saved(function ($model) {
            $model->updateTrainerTotalTeachingHours();
        });

        static::deleted(function ($model) {
            $model->updateTrainerTotalTeachingHours();
        });
    }
}
