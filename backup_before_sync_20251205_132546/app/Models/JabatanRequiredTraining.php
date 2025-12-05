<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JabatanRequiredTraining extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jabatan_required_trainings';

    protected $fillable = [
        'jabatan_id',
        'course_id',
        'is_mandatory',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id', 'id_jabatan');
    }

    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
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
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_mandatory', false);
    }

    public function scopeByJabatan($query, $jabatanId)
    {
        return $query->where('jabatan_id', $jabatanId);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    // Accessors
    public function getTypeTextAttribute()
    {
        return $this->is_mandatory ? 'Wajib' : 'Opsional';
    }

    public function getTypeColorAttribute()
    {
        return $this->is_mandatory ? 'red' : 'blue';
    }

    // Methods
    public static function getRequiredTrainingsForJabatan($jabatanId)
    {
        return self::with(['course.category', 'course.trainers'])
            ->byJabatan($jabatanId)
            ->orderBy('is_mandatory', 'desc')
            ->orderBy('course.title')
            ->get();
    }

    public static function getMandatoryTrainingsForJabatan($jabatanId)
    {
        return self::with(['course.category', 'course.trainers'])
            ->byJabatan($jabatanId)
            ->mandatory()
            ->orderBy('course.title')
            ->get();
    }

    public static function getOptionalTrainingsForJabatan($jabatanId)
    {
        return self::with(['course.category', 'course.trainers'])
            ->byJabatan($jabatanId)
            ->optional()
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
    }
}
