<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsLesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_lessons';

    protected $fillable = [
        'course_id',
        'title',
        'slug',
        'description',
        'content',
        'type',
        'order_number',
        'duration_minutes',
        'video_url',
        'file_path',
        'is_preview',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_number' => 'integer',
        'duration_minutes' => 'integer',
        'is_preview' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function progress()
    {
        return $this->hasMany(LmsLessonProgress::class, 'lesson_id');
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

    public function scopePreview($query)
    {
        return $query->where('is_preview', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_number');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Accessors
    public function getSlugAttribute($value)
    {
        if (!$value) {
            $value = \Str::slug($this->title);
        }
        return $value;
    }

    public function getDurationFormattedAttribute()
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;
        
        if ($hours > 0) {
            return $hours . 'j ' . $minutes . 'm';
        }
        
        return $minutes . ' menit';
    }

    public function getTypeTextAttribute()
    {
        $types = [
            'video' => 'Video',
            'document' => 'Dokumen',
            'quiz' => 'Quiz',
            'assignment' => 'Tugas',
            'discussion' => 'Diskusi'
        ];
        
        return $types[$this->type] ?? $this->type;
    }

    public function getVideoUrlAttribute($value)
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            return asset('storage/' . $value);
        }
        return $value;
    }

    public function getFilePathAttribute($value)
    {
        if ($value) {
            return asset('storage/' . $value);
        }
        return $value;
    }

    // Mutators
    public function setSlugAttribute($value)
    {
        if (!$value) {
            $value = \Str::slug($this->title);
        }
        $this->attributes['slug'] = $value;
    }

    // Methods
    public function getNextLesson()
    {
        return $this->course->lessons()
            ->where('order_number', '>', $this->order_number)
            ->orderBy('order_number')
            ->first();
    }

    public function getPreviousLesson()
    {
        return $this->course->lessons()
            ->where('order_number', '<', $this->order_number)
            ->orderBy('order_number', 'desc')
            ->first();
    }

    public function isCompletedByUser($userId)
    {
        return $this->progress()
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->exists();
    }

    public function getUserProgress($userId)
    {
        return $this->progress()
            ->where('user_id', $userId)
            ->first();
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($lesson) {
            if (!$lesson->created_by) {
                $lesson->created_by = auth()->id();
            }
            if (!$lesson->updated_by) {
                $lesson->updated_by = auth()->id();
            }
            if (!$lesson->order_number) {
                $lesson->order_number = static::where('course_id', $lesson->course_id)->max('order_number') + 1;
            }
        });

        static::updating(function ($lesson) {
            $lesson->updated_by = auth()->id();
        });
    }
} 