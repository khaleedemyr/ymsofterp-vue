<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingScheduleTrainer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'training_schedule_trainers';

    protected $fillable = [
        'schedule_id',
        'trainer_id',
        'trainer_type',
        'external_trainer_name',
        'external_trainer_email',
        'external_trainer_phone',
        'external_trainer_company',
        'is_primary_trainer',
        'hours_taught',
        'start_time',
        'end_time',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'trainer_type' => 'internal',
        'status' => 'invited',
    ];

    protected $casts = [
        'is_primary_trainer' => 'boolean',
        'hours_taught' => 'decimal:2',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['trainer_name', 'trainer_email', 'trainer_type_text', 'trainer_type_color', 'status_text', 'duration_text', 'time_range', 'role_text', 'role_color'];

    // Relationships
    public function schedule()
    {
        return $this->belongsTo(TrainingSchedule::class, 'schedule_id');
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
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
        return $query->where('is_primary_trainer', true);
    }

    public function scopeSecondary($query)
    {
        return $query->where('is_primary_trainer', false);
    }

    public function scopeBySchedule($query, $scheduleId)
    {
        return $query->where('schedule_id', $scheduleId);
    }

    public function scopeByTrainer($query, $trainerId)
    {
        return $query->where('trainer_id', $trainerId);
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

    public function getTimeRangeAttribute()
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
        }
        return '-';
    }

    public function getRoleTextAttribute()
    {
        return $this->is_primary_trainer ? 'Primary Trainer' : 'Secondary Trainer';
    }

    public function getRoleColorAttribute()
    {
        return $this->is_primary_trainer ? 'green' : 'blue';
    }

    public function getStatusTextAttribute()
    {
        $statusMap = [
            'invited' => 'Diundang',
            'confirmed' => 'Dikonfirmasi',
            'attended' => 'Hadir',
            'absent' => 'Tidak Hadir'
        ];
        
        return $statusMap[$this->status] ?? 'Diundang';
    }

    public function getTrainerNameAttribute()
    {
        if ($this->trainer_type === 'external') {
            return $this->external_trainer_name;
        }
        
        return $this->trainer ? $this->trainer->nama_lengkap : 'Trainer tidak ditemukan';
    }

    public function getTrainerEmailAttribute()
    {
        if ($this->trainer_type === 'external') {
            return $this->external_trainer_email;
        }
        
        return $this->trainer ? $this->trainer->email : null;
    }

    public function getTrainerTypeTextAttribute()
    {
        return $this->trainer_type === 'external' ? 'External Trainer' : 'Internal Trainer';
    }

    public function getTrainerTypeColorAttribute()
    {
        return $this->trainer_type === 'external' ? 'purple' : 'blue';
    }

    // Methods
    public static function getTrainersForSchedule($scheduleId)
    {
        return self::with(['trainer.jabatan', 'trainer.divisi'])
            ->bySchedule($scheduleId)
            ->orderBy('is_primary_trainer', 'desc')
            ->orderBy('trainer.nama_lengkap')
            ->get();
    }

    public static function getPrimaryTrainerForSchedule($scheduleId)
    {
        return self::with(['trainer.jabatan', 'trainer.divisi'])
            ->bySchedule($scheduleId)
            ->primary()
            ->first();
    }

    public static function getSecondaryTrainersForSchedule($scheduleId)
    {
        return self::with(['trainer.jabatan', 'trainer.divisi'])
            ->bySchedule($scheduleId)
            ->secondary()
            ->orderBy('trainer.nama_lengkap')
            ->get();
    }

    public static function getSchedulesForTrainer($trainerId)
    {
        return self::with(['schedule.course'])
            ->byTrainer($trainerId)
            ->orderBy('schedule.scheduled_date', 'desc')
            ->get();
    }

    public function calculateHoursFromTimeRange()
    {
        if ($this->start_time && $this->end_time) {
            $start = \Carbon\Carbon::parse($this->start_time);
            $end = \Carbon\Carbon::parse($this->end_time);
            $duration = $start->diffInMinutes($end);
            
            $this->hours_taught = round($duration / 60, 2);
            $this->save();
        }
    }

    public function updateTrainerTeachingHours()
    {
        // Update TrainerTeachingHours table
        TrainerTeachingHours::updateOrCreate(
            [
                'trainer_id' => $this->trainer_id,
                'course_id' => $this->schedule->course_id,
                'schedule_id' => $this->schedule_id,
            ],
            [
                'hours_taught' => $this->hours_taught,
                'teaching_date' => $this->schedule->scheduled_date,
                'start_time' => $this->start_time,
                'end_time' => $this->end_time,
                'participant_count' => $this->schedule->invitations()->where('status', 'attended')->count(),
                'notes' => $this->notes,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );
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
            $model->updateTrainerTeachingHours();
        });

        static::deleted(function ($model) {
            // Remove from TrainerTeachingHours when deleted
            TrainerTeachingHours::where('trainer_id', $model->trainer_id)
                ->where('schedule_id', $model->schedule_id)
                ->delete();
        });
    }
}
