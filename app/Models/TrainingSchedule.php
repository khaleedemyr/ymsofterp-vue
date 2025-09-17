<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSchedule extends Model
{
    use HasFactory;

    protected $table = 'training_schedules';

    protected $fillable = [
        'course_id',
        'trainer_id',
        'external_trainer_name',
        'outlet_id',
        'scheduled_date',
        'start_time',
        'end_time',
        'status',
        'notes',
        'created_by'
    ];

    protected $casts = [
        // Serialize as 'YYYY-MM-DD' to avoid timezone shifts in JSON
        'scheduled_date' => 'date:Y-m-d',
        // Keep time values as plain strings (DB column type TIME)
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    // Ensure scheduleTrainers is available in camelCase for frontend
    protected $appends = ['scheduleTrainers'];

    // Relationships
    public function course(): BelongsTo
    {
        return $this->belongsTo(\App\Models\LmsCourse::class, 'course_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TrainingInvitation::class, 'schedule_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TrainingReview::class, 'training_schedule_id');
    }

    // New training system relationships
    public function scheduleTrainers()
    {
        return $this->hasMany(TrainingScheduleTrainer::class, 'schedule_id');
    }

    public function primaryScheduleTrainer()
    {
        return $this->hasOne(TrainingScheduleTrainer::class, 'schedule_id')->where('is_primary_trainer', true);
    }

    public function secondaryScheduleTrainers()
    {
        return $this->hasMany(TrainingScheduleTrainer::class, 'schedule_id')->where('is_primary_trainer', false);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByDate($query, $date)
    {
        return $query->where('scheduled_date', $date);
    }

    public function scopeByMonth($query, $year, $month)
    {
        return $query->whereYear('scheduled_date', $year)
                    ->whereMonth('scheduled_date', $month);
    }

    // Accessors
    public function getDurationAttribute()
    {
        if ($this->start_time && $this->end_time) {
            $start = \Carbon\Carbon::parse($this->start_time);
            $end = \Carbon\Carbon::parse($this->end_time);
            return $start->diffInMinutes($end);
        }
        return 0;
    }

    public function getDurationFormattedAttribute()
    {
        $minutes = $this->duration;
        if ($minutes < 60) {
            return $minutes . ' menit';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes == 0) {
            return $hours . ' jam';
        }
        
        return $hours . ' jam ' . $remainingMinutes . ' menit';
    }

    public function getParticipantCountAttribute()
    {
        return $this->invitations()->count();
    }

    public function getAttendedCountAttribute()
    {
        return $this->invitations()->where('status', 'attended')->count();
    }

    public function getAbsentCountAttribute()
    {
        return $this->invitations()->where('status', 'absent')->count();
    }

    public function getStatusTextAttribute()
    {
        $statuses = [
            'draft' => 'Draft',
            'published' => 'Dipublikasi',
            'ongoing' => 'Sedang Berlangsung',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan'
        ];
        
        return $statuses[$this->status] ?? $this->status;
    }

    public function getTrainerNameAttribute()
    {
        if ($this->external_trainer_name) {
            return $this->external_trainer_name;
        }
        
        // Get trainer from schedule trainer relationship
        if ($this->trainer) {
            return $this->trainer->nama_lengkap;
        }
        
        // Get trainer from course instructor as fallback
        if ($this->course && $this->course->instructor) {
            return $this->course->instructor->nama_lengkap;
        }
        
        return 'Trainer tidak ditentukan';
    }

    public function getIsTodayAttribute()
    {
        return $this->scheduled_date->format('Y-m-d') === now()->format('Y-m-d');
    }

    public function getQrCodeUrlAttribute()
    {
        // Generate unique QR code for this training schedule
        $data = [
            'schedule_id' => $this->id,
            'course_id' => $this->course_id,
            'scheduled_date' => $this->scheduled_date->format('Y-m-d'),
            'hash' => hash('sha256', $this->id . $this->course_id . $this->scheduled_date->format('Y-m-d'))
        ];
        
        $qrData = base64_encode(json_encode($data));
        
        // Use QR Server API (more reliable than Google Charts)
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrData);
        
        // Debug: Log QR code generation
        \Log::info('QR Code generated for schedule ' . $this->id . ': ' . $qrUrl);
        
        return $qrUrl;
    }

    // Methods
    public function canBeEditedBy(User $user): bool
    {
        // Admin can edit all trainings
        if ($user->is_admin) {
            return true;
        }
        
        // Creator can edit their own training
        if ($user->id === $this->created_by) {
            return true;
        }
        
        // Course creator can edit training for their course
        if ($this->course && $this->course->created_by === $user->id) {
            return true;
        }
        
        // For now, allow all authenticated users to edit training schedules
        // You can add more specific logic here based on your requirements
        return true;
    }

    public function canInviteParticipants(User $user): bool
    {
        // For now, allow all authenticated users to invite participants
        // You can add more specific logic here based on your requirements
        return true;
    }

    public function hasAvailableSlots(): bool
    {
        // No capacity limit, always has available slots
        return true;
    }

    public function getAvailableSlots(): int
    {
        // No capacity limit, return -1 to indicate unlimited
        return -1;
    }

    public function isToday(): bool
    {
        return $this->scheduled_date->isToday();
    }

    public function isPast(): bool
    {
        return $this->scheduled_date->isPast();
    }

    public function isFuture(): bool
    {
        return $this->scheduled_date->isFuture();
    }

    public function shouldAutoComplete(): bool
    {
        if ($this->status !== 'ongoing') {
            return false;
        }
        
        $now = now();
        $scheduleDateTime = $this->scheduled_date->setTimeFrom($this->end_time);
        
        return $now->gt($scheduleDateTime);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
        
        // Auto mark absent for participants who haven't checked in
        $this->invitations()
            ->where('status', 'invited')
            ->update(['status' => 'absent']);
    }

    // Accessor to ensure scheduleTrainers is available in camelCase
    public function getScheduleTrainersAttribute()
    {
        return $this->scheduleTrainers()->with(['trainer.jabatan', 'trainer.divisi'])->get();
    }

}
