<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LmsCurriculumProgress extends Model
{
    use HasFactory;

    protected $table = 'lms_curriculum_progress';

    protected $fillable = [
        'user_id',
        'curriculum_item_id',
        'status',
        'started_at',
        'completed_at',
        'score',
        'attempts_count',
        'last_attempt_at',
        'time_spent_minutes',
        'notes'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'score' => 'integer',
        'attempts_count' => 'integer',
        'time_spent_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'status_text',
        'time_spent_formatted',
        'progress_percentage',
        'is_passed',
        'can_retry'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function curriculumItem()
    {
        return $this->belongsTo(LmsCurriculumItem::class, 'curriculum_item_id');
    }

    public function curriculum()
    {
        return $this->hasOneThrough(
            LmsCurriculum::class,
            LmsCurriculumItem::class,
            'id',
            'id',
            'curriculum_item_id',
            'curriculum_id'
        );
    }

    public function course()
    {
        return $this->hasOneThrough(
            LmsCourse::class,
            LmsCurriculumItem::class,
            'id',
            'id',
            'curriculum_item_id',
            'curriculum_id'
        );
    }

    // Scopes
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeNotStarted($query)
    {
        return $query->where('status', 'not_started');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->whereHas('curriculumItem.curriculum', function ($q) use ($courseId) {
            $q->where('course_id', $courseId);
        });
    }

    public function scopeByCurriculum($query, $curriculumId)
    {
        return $query->whereHas('curriculumItem', function ($q) use ($curriculumId) {
            $q->where('curriculum_id', $curriculumId);
        });
    }

    // Accessors
    public function getStatusTextAttribute()
    {
        $statusMap = [
            'not_started' => 'Belum Dimulai',
            'in_progress' => 'Sedang Berlangsung',
            'completed' => 'Selesai',
            'failed' => 'Gagal'
        ];

        return $statusMap[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getTimeSpentFormattedAttribute()
    {
        if (!$this->time_spent_minutes) {
            return '0 menit';
        }

        $hours = floor($this->time_spent_minutes / 60);
        $minutes = $this->time_spent_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours} jam {$minutes} menit";
        } elseif ($hours > 0) {
            return "{$hours} jam";
        } else {
            return "{$minutes} menit";
        }
    }

    public function getProgressPercentageAttribute()
    {
        switch ($this->status) {
            case 'completed':
                return 100;
            case 'in_progress':
                return 50;
            case 'failed':
                return 0;
            case 'not_started':
            default:
                return 0;
        }
    }

    public function getIsPassedAttribute()
    {
        if ($this->status !== 'completed') {
            return false;
        }

        $curriculumItem = $this->curriculumItem;
        if (!$curriculumItem || !$curriculumItem->passing_score) {
            return true; // No passing score requirement
        }

        return $this->score >= $curriculumItem->passing_score;
    }

    public function getCanRetryAttribute()
    {
        $curriculumItem = $this->curriculumItem;
        if (!$curriculumItem) {
            return false;
        }

        return $this->attempts_count < $curriculumItem->max_attempts;
    }

    // Methods
    public function start()
    {
        $this->status = 'in_progress';
        $this->started_at = now();
        $this->attempts_count++;
        $this->last_attempt_at = now();
        $this->save();
    }

    public function complete($score = null, $timeSpent = 0)
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->score = $score;
        $this->time_spent_minutes += $timeSpent;
        $this->save();
    }

    public function fail($timeSpent = 0)
    {
        $this->status = 'failed';
        $this->time_spent_minutes += $timeSpent;
        $this->save();
    }

    public function reset()
    {
        $this->status = 'not_started';
        $this->started_at = null;
        $this->completed_at = null;
        $this->score = null;
        $this->time_spent_minutes = 0;
        $this->save();
    }

    public function updateTimeSpent($additionalMinutes)
    {
        $this->time_spent_minutes += $additionalMinutes;
        $this->save();
    }

    public function addNote($note)
    {
        $this->notes = $this->notes ? $this->notes . "\n" . $note : $note;
        $this->save();
    }

    public function getGrade()
    {
        if (!$this->score || $this->status !== 'completed') {
            return null;
        }

        $curriculumItem = $this->curriculumItem;
        if (!$curriculumItem) {
            return null;
        }

        // Calculate grade based on score
        if ($this->score >= 90) {
            return 'A';
        } elseif ($this->score >= 80) {
            return 'B';
        } elseif ($this->score >= 70) {
            return 'C';
        } elseif ($this->score >= 60) {
            return 'D';
        } else {
            return 'F';
        }
    }

    public function getGradeColor()
    {
        $grade = $this->getGrade();
        
        switch ($grade) {
            case 'A':
                return 'text-green-600';
            case 'B':
                return 'text-blue-600';
            case 'C':
                return 'text-yellow-600';
            case 'D':
                return 'text-orange-600';
            case 'F':
                return 'text-red-600';
            default:
                return 'text-gray-600';
        }
    }

    public function getStatusColor()
    {
        switch ($this->status) {
            case 'completed':
                return 'text-green-600';
            case 'in_progress':
                return 'text-blue-600';
            case 'failed':
                return 'text-red-600';
            case 'not_started':
            default:
                return 'text-gray-600';
        }
    }

    public function getStatusBadgeColor()
    {
        switch ($this->status) {
            case 'completed':
                return 'bg-green-100 text-green-800';
            case 'in_progress':
                return 'bg-blue-100 text-blue-800';
            case 'failed':
                return 'bg-red-100 text-red-800';
            case 'not_started':
            default:
                return 'bg-gray-100 text-gray-800';
        }
    }

    // Static methods
    public static function getOverallProgress($userId, $courseId)
    {
        $totalItems = LmsCurriculumItem::whereHas('curriculum', function ($q) use ($courseId) {
            $q->where('course_id', $courseId);
        })->where('is_required', true)->count();

        if ($totalItems === 0) {
            return 100;
        }

        $completedItems = self::where('user_id', $userId)
                              ->whereHas('curriculumItem.curriculum', function ($q) use ($courseId) {
                                  $q->where('course_id', $courseId);
                              })
                              ->where('status', 'completed')
                              ->count();

        return round(($completedItems / $totalItems) * 100);
    }

    public static function getCourseStats($courseId)
    {
        $totalEnrollments = LmsEnrollment::where('course_id', $courseId)->count();
        
        $stats = [
            'total_enrollments' => $totalEnrollments,
            'completed_courses' => 0,
            'in_progress_courses' => 0,
            'not_started_courses' => 0,
            'average_score' => 0,
            'completion_rate' => 0
        ];

        if ($totalEnrollments > 0) {
            $completedEnrollments = self::whereHas('curriculumItem.curriculum', function ($q) use ($courseId) {
                $q->where('course_id', $courseId);
            })->where('status', 'completed')->distinct('user_id')->count();

            $stats['completed_courses'] = $completedEnrollments;
            $stats['completion_rate'] = round(($completedEnrollments / $totalEnrollments) * 100, 2);

            // Calculate average score
            $averageScore = self::whereHas('curriculumItem.curriculum', function ($q) use ($courseId) {
                $q->where('course_id', $courseId);
            })->where('status', 'completed')->whereNotNull('score')->avg('score');

            $stats['average_score'] = round($averageScore ?? 0, 2);
        }

        return $stats;
    }
}
