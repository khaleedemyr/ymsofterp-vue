<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_assignments';

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'instructions',
        'due_date',
        'max_score',
        'file_required',
        'allowed_file_types',
        'max_file_size',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'max_score' => 'integer',
        'file_required' => 'boolean',
        'max_file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function submissions()
    {
        return $this->hasMany(LmsAssignmentSubmission::class, 'assignment_id');
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
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now());
    }

    // Accessors
    public function getSubmissionsCountAttribute()
    {
        return $this->submissions()->count();
    }

    public function getAverageScoreAttribute()
    {
        return $this->submissions()->avg('score') ?? 0;
    }

    public function getIsOverdueAttribute()
    {
        return $this->due_date && $this->due_date->isPast();
    }

    public function getDaysUntilDueAttribute()
    {
        if (!$this->due_date) {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }

    public function getAllowedFileTypesArrayAttribute()
    {
        if (!$this->allowed_file_types) {
            return [];
        }

        return explode(',', $this->allowed_file_types);
    }

    // Methods
    public function getUserSubmission($userId)
    {
        return $this->submissions()
            ->where('user_id', $userId)
            ->latest()
            ->first();
    }

    public function canBeSubmittedByUser($userId)
    {
        $submission = $this->getUserSubmission($userId);
        return !$submission || $submission->status === 'draft';
    }

    public function isSubmittedByUser($userId)
    {
        return $this->submissions()
            ->where('user_id', $userId)
            ->where('status', 'submitted')
            ->exists();
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($assignment) {
            if (!$assignment->created_by) {
                $assignment->created_by = auth()->id();
            }
            if (!$assignment->updated_by) {
                $assignment->updated_by = auth()->id();
            }
        });

        static::updating(function ($assignment) {
            $assignment->updated_by = auth()->id();
        });
    }
} 