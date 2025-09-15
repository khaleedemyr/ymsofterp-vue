<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseTrainer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'course_trainers';

    protected $fillable = [
        'course_id',
        'user_id',
        'is_primary',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'user_id');
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
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeSecondary($query)
    {
        return $query->where('is_primary', false);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeByTrainer($query, $trainerId)
    {
        return $query->where('user_id', $trainerId);
    }

    // Accessors
    public function getRoleTextAttribute()
    {
        return $this->is_primary ? 'Primary Trainer' : 'Secondary Trainer';
    }

    public function getRoleColorAttribute()
    {
        return $this->is_primary ? 'green' : 'blue';
    }

    // Methods
    public static function getTrainersForCourse($courseId)
    {
        return self::with(['trainer.jabatan', 'trainer.divisi'])
            ->byCourse($courseId)
            ->orderBy('is_primary', 'desc')
            ->orderBy('trainer.nama_lengkap')
            ->get();
    }

    public static function getPrimaryTrainerForCourse($courseId)
    {
        return self::with(['trainer.jabatan', 'trainer.divisi'])
            ->byCourse($courseId)
            ->primary()
            ->first();
    }

    public static function getSecondaryTrainersForCourse($courseId)
    {
        return self::with(['trainer.jabatan', 'trainer.divisi'])
            ->byCourse($courseId)
            ->secondary()
            ->orderBy('trainer.nama_lengkap')
            ->get();
    }

    public static function getCoursesForTrainer($trainerId)
    {
        return self::with(['course.category'])
            ->byTrainer($trainerId)
            ->orderBy('course.title')
            ->get();
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

        // Ensure only one primary trainer per course
        static::creating(function ($model) {
            if ($model->is_primary) {
                self::where('course_id', $model->course_id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });

        static::updating(function ($model) {
            if ($model->is_primary && $model->isDirty('is_primary')) {
                self::where('course_id', $model->course_id)
                    ->where('id', '!=', $model->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });
    }
}
