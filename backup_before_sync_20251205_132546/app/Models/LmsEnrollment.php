<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsEnrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_enrollments';

    protected $fillable = [
        'course_id',
        'user_id',
        'status',
        'progress_percentage',
        'completed_at',
        'started_at',
        'last_accessed_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'progress_percentage' => 'decimal:2',
        'completed_at' => 'datetime',
        'started_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sessionProgress()
    {
        return $this->hasMany(LmsSessionProgress::class, 'enrollment_id');
    }

    public function quizAttempts()
    {
        return $this->hasMany(LmsQuizAttempt::class, 'enrollment_id');
    }

    public function assignmentSubmissions()
    {
        return $this->hasMany(LmsAssignmentSubmission::class, 'enrollment_id');
    }

    public function certificate()
    {
        return $this->hasOne(LmsCertificate::class, 'enrollment_id');
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
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['enrolled', 'in_progress']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Accessors
    public function getStatusTextAttribute()
    {
        $statuses = [
            'enrolled' => 'Terdaftar',
            'in_progress' => 'Sedang Belajar',
            'completed' => 'Selesai',
            'dropped' => 'Dibatalkan',
            'paused' => 'Dijeda'
        ];
        
        return $statuses[$this->status] ?? $this->status;
    }

    public function getProgressTextAttribute()
    {
        return number_format($this->progress_percentage, 1) . '%';
    }

    public function getIsCompletedAttribute()
    {
        return $this->status === 'completed';
    }

    public function getIsActiveAttribute()
    {
        return in_array($this->status, ['enrolled', 'in_progress']);
    }

    public function getDurationAttribute()
    {
        if (!$this->started_at) {
            return null;
        }

        $endDate = $this->completed_at ?? now();
        return $this->started_at->diffInDays($endDate);
    }

    public function getLastActivityAttribute()
    {
        return $this->last_accessed_at ? $this->last_accessed_at->diffForHumans() : 'Belum ada aktivitas';
    }

    // Methods
    public function updateProgress()
    {
        $totalSessions = $this->course->sessions()->count();
        if ($totalSessions === 0) {
            $this->progress_percentage = 0;
            return;
        }

        $completedSessions = $this->sessionProgress()
            ->where('is_completed', true)
            ->count();

        $this->progress_percentage = round(($completedSessions / $totalSessions) * 100, 2);
        
        // Update status based on progress
        if ($this->progress_percentage >= 100) {
            $this->status = 'completed';
            $this->completed_at = now();
        } elseif ($this->progress_percentage > 0) {
            $this->status = 'in_progress';
            if (!$this->started_at) {
                $this->started_at = now();
            }
        }

        $this->last_accessed_at = now();
        $this->save();
    }

    public function markAsCompleted()
    {
        $this->status = 'completed';
        $this->progress_percentage = 100;
        $this->completed_at = now();
        $this->last_accessed_at = now();
        $this->save();
    }

    public function pause()
    {
        $this->status = 'paused';
        $this->save();
    }

    public function resume()
    {
        if ($this->progress_percentage > 0) {
            $this->status = 'in_progress';
        } else {
            $this->status = 'enrolled';
        }
        $this->save();
    }

    public function drop()
    {
        $this->status = 'dropped';
        $this->save();
    }

    public function canAccessSession($sessionId)
    {
        // Check if user is enrolled and active
        if (!$this->isActive) {
            return false;
        }

        // Check if session belongs to the course
        $session = $this->course->sessions()->find($sessionId);
        if (!$session) {
            return false;
        }

        // For now, allow access to all sessions
        // You can implement prerequisites logic here
        return true;
    }

    public function canTakeQuiz($quizId)
    {
        // Check if user is enrolled and active
        if (!$this->isActive) {
            return false;
        }

        // Check if quiz belongs to the course
        $quiz = $this->course->quizzes()->find($quizId);
        if (!$quiz) {
            return false;
        }

        return true;
    }

    public function canSubmitAssignment($assignmentId)
    {
        // Check if user is enrolled and active
        if (!$this->isActive) {
            return false;
        }

        // Check if assignment belongs to the course
        $assignment = $this->course->assignments()->find($assignmentId);
        if (!$assignment) {
            return false;
        }

        return true;
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($enrollment) {
            if (!$enrollment->created_by) {
                $enrollment->created_by = auth()->id();
            }
            if (!$enrollment->updated_by) {
                $enrollment->updated_by = auth()->id();
            }
            if (!$enrollment->status) {
                $enrollment->status = 'enrolled';
            }
            if (!$enrollment->progress_percentage) {
                $enrollment->progress_percentage = 0;
            }
        });

        static::updating(function ($enrollment) {
            $enrollment->updated_by = auth()->id();
        });
    }
} 