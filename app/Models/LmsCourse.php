<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsCourse extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_courses';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'short_description',
        'category_id',
        'instructor_id',
        'difficulty_level',
        'target_type',
        'target_division_id',
        'target_divisions',
        'duration_minutes',
        'thumbnail',
        'status',
        'is_featured',
        'meta_title',
        'meta_description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'is_featured' => 'boolean',
        'target_divisions' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(LmsCategory::class, 'category_id');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function targetDivision()
    {
        return $this->belongsTo(Divisi::class, 'target_division_id');
    }

    public function targetDivisions()
    {
        return $this->belongsToMany(Divisi::class, 'lms_course_divisions', 'course_id', 'division_id');
    }

    public function lessons()
    {
        return $this->hasMany(LmsLesson::class, 'course_id')->orderBy('order_number');
    }

    public function enrollments()
    {
        return $this->hasMany(LmsEnrollment::class, 'course_id');
    }

    public function quizzes()
    {
        return $this->hasMany(LmsQuiz::class, 'course_id');
    }

    public function assignments()
    {
        return $this->hasMany(LmsAssignment::class, 'course_id');
    }

    public function discussions()
    {
        return $this->hasMany(LmsDiscussion::class, 'course_id');
    }

    public function certificates()
    {
        return $this->hasMany(LmsCertificate::class, 'course_id');
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

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }

    public function scopeByDivision($query, $divisionId)
    {
        return $query->where(function ($q) use ($divisionId) {
            $q->where('target_type', 'all')
              ->orWhere('target_division_id', $divisionId)
              ->orWhereJsonContains('target_divisions', $divisionId);
        });
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('short_description', 'like', "%{$search}%");
        });
    }

    // Accessors
    public function getDurationFormattedAttribute()
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;
        
        if ($hours > 0) {
            return $hours . 'j ' . $minutes . 'm';
        }
        
        return $minutes . ' menit';
    }

    public function getDifficultyTextAttribute()
    {
        $difficulties = [
            'beginner' => 'Pemula',
            'intermediate' => 'Menengah',
            'advanced' => 'Lanjutan'
        ];
        
        return $difficulties[$this->difficulty_level] ?? $this->difficulty_level;
    }

    public function getInstructorNameAttribute()
    {
        return $this->instructor ? $this->instructor->nama_lengkap : 'Trainer Internal';
    }

    public function getTargetTypeTextAttribute()
    {
        $types = [
            'single' => '1 Divisi',
            'multiple' => 'Multi Divisi',
            'all' => 'Semua Divisi'
        ];
        
        return $types[$this->target_type] ?? $this->target_type;
    }

    public function getTargetDivisionNameAttribute()
    {
        if ($this->target_type === 'all') {
            return 'Semua Divisi';
        } elseif ($this->target_type === 'single' && $this->targetDivision) {
            return $this->targetDivision->nama_divisi;
        } elseif ($this->target_type === 'multiple' && $this->targetDivisions->count() > 0) {
            return $this->targetDivisions->pluck('nama_divisi')->implode(', ');
        }
        
        return 'Divisi tidak ditentukan';
    }

    public function getTargetDivisionIdsAttribute()
    {
        if ($this->target_type === 'all') {
            return Divisi::pluck('id')->toArray();
        } elseif ($this->target_type === 'single' && $this->target_division_id) {
            return [$this->target_division_id];
        } elseif ($this->target_type === 'multiple' && $this->target_divisions) {
            return $this->target_divisions;
        }
        
        return [];
    }

    public function getLessonsCountAttribute()
    {
        return $this->lessons()->count();
    }

    public function getEnrollmentsCountAttribute()
    {
        return $this->enrollments()->count();
    }

    public function getCompletedEnrollmentsCountAttribute()
    {
        return $this->enrollments()->where('status', 'completed')->count();
    }

    public function getAverageProgressAttribute()
    {
        return $this->enrollments()->avg('progress_percentage') ?? 0;
    }

    public function getThumbnailUrlAttribute()
    {
        if ($this->thumbnail) {
            return asset('storage/' . $this->thumbnail);
        }
        
        return asset('images/default-course-thumbnail.jpg');
    }

    public function getSlugAttribute($value)
    {
        if (!$value) {
            $value = \Str::slug($this->title);
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

    // Methods for target divisions
    public function isTargetedForDivision($divisionId)
    {
        if ($this->target_type === 'all') {
            return true;
        } elseif ($this->target_type === 'single') {
            return $this->target_division_id == $divisionId;
        } elseif ($this->target_type === 'multiple') {
            return in_array($divisionId, $this->target_divisions ?? []);
        }
        
        return false;
    }

    public function syncTargetDivisions($divisionIds)
    {
        if (empty($divisionIds)) {
            $this->target_type = 'all';
            $this->target_divisions = null;
            $this->target_division_id = null;
        } elseif (count($divisionIds) === 1) {
            $this->target_type = 'single';
            $this->target_division_id = $divisionIds[0];
            $this->target_divisions = null;
        } else {
            $this->target_type = 'multiple';
            $this->target_divisions = $divisionIds;
            $this->target_division_id = null;
        }
        
        $this->save();
        
        // Sync many-to-many relationship
        if ($this->target_type === 'multiple') {
            $this->targetDivisions()->sync($divisionIds);
        } else {
            $this->targetDivisions()->detach();
        }
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($course) {
            if (!$course->created_by) {
                $course->created_by = auth()->id();
            }
            if (!$course->updated_by) {
                $course->updated_by = auth()->id();
            }
        });

        static::updating(function ($course) {
            $course->updated_by = auth()->id();
        });
    }
} 