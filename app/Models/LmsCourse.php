<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\DataOutlet;

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
        'trainer_type',
        // 'difficulty_level', // REMOVED - field removed
        'target_type',
        'target_division_id',
        'target_divisions',
        'target_jabatan_ids',
        'target_outlet_ids',
        'duration_minutes',
        'duration_hours',
        'thumbnail',
        'thumbnail_path',
        'status',
        'learning_objectives',
        'requirements',
        'external_trainer_name',
        'external_trainer_description',
        'certificate_template_id',
        'type',
        'course_type',
        'is_featured',
        'meta_title',
        'meta_description',
        'max_students',
        'price',
        'is_free',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'target_divisions' => 'array',
        'target_jabatan_ids' => 'array',
        'target_outlet_ids' => 'array',
        'learning_objectives' => 'array',
        'requirements' => 'array',
        'type' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'thumbnail_url',
        'duration_formatted',
        // 'difficulty_text', // REMOVED - difficulty_level field removed
        'instructor_name',
        'target_division_name',
        'target_jabatan_names',
        'target_outlet_names',
        // 'lessons_count', // REMOVED - lessons relationship removed
        'enrollments_count',
        'type_text',
        'course_type_text',
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

    public function targetJabatans()
    {
        return $this->belongsToMany(Jabatan::class, 'lms_course_jabatans', 'course_id', 'jabatan_id', 'id', 'id_jabatan');
    }

    public function targetOutlets()
    {
        return $this->belongsToMany(DataOutlet::class, 'lms_course_outlets', 'course_id', 'outlet_id', 'id', 'id_outlet');
    }

    // public function lessons() // REMOVED - using sessions instead
    // {
    //     return $this->hasMany(LmsLesson::class, 'course_id')->orderBy('order_number');
    // }

    // public function curriculumItems() // REMOVED - using sessions instead
    // {
    //     return $this->hasMany(LmsCurriculumItem::class, 'course_id')->orderBy('order_number');
    // }

    public function sessions()
    {
        return $this->hasMany(LmsSession::class, 'course_id')->orderBy('order_number');
    }

    public function enrollments()
    {
        return $this->hasMany(LmsEnrollment::class, 'course_id');
    }

    public function quizzes()
    {
        return $this->hasMany(LmsQuiz::class, 'course_id');
    }

    public function questionnaires()
    {
        return $this->hasMany(LmsQuestionnaire::class, 'course_id');
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

    public function certificateTemplate()
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
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

    // public function scopeByDifficulty($query, $difficulty) // REMOVED - difficulty_level field removed
    // {
    //     return $query->where('difficulty_level', $difficulty);
    // }

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

    // public function getDifficultyTextAttribute() // REMOVED - difficulty_level field removed
    // {
    //     $difficulties = [
    //         'beginner' => 'Pemula',
    //         'intermediate' => 'Menengah',
    //         'advanced' => 'Lanjutan'
    //     ];
    //     
    //     return $difficulties[$this->difficulty_level] ?? $this->difficulty_level;
    // }

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
        } elseif ($this->target_type === 'multiple' && is_array($this->target_divisions) && count($this->target_divisions) > 0) {
            // Fallback untuk data yang belum di-sync dengan relasi
            $divisionNames = [];
            foreach ($this->target_divisions as $divisionId) {
                $division = \App\Models\Divisi::find($divisionId);
                if ($division) {
                    $divisionNames[] = $division->nama_divisi;
                }
            }
            return implode(', ', $divisionNames);
        }
        
        return 'Divisi tidak ditentukan';
    }

    public function getTargetJabatanNamesAttribute()
    {
        if ($this->targetJabatans->count() > 0) {
            return $this->targetJabatans->pluck('nama_jabatan')->implode(', ');
        }
        return 'Jabatan tidak ditentukan';
    }

    public function getTargetOutletNamesAttribute()
    {
        if ($this->targetOutlets->count() > 0) {
            return $this->targetOutlets->pluck('nama_outlet')->implode(', ');
        }
        return 'Outlet tidak ditentukan';
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

    // public function getLessonsCountAttribute() // REMOVED - lessons relationship removed
    // {
    //     return $this->lessons()->count();
    // }

    public function getEnrollmentsCountAttribute()
    {
        return $this->enrollments()->count();
    }

    public function getTypeTextAttribute()
    {
        $types = [
            'online' => 'Online',
            'offline' => 'Offline'
        ];
        
        return $types[$this->type] ?? $this->type;
    }

    public function getCourseTypeTextAttribute()
    {
        $types = [
            'mandatory' => 'Wajib',
            'optional' => 'Opsional'
        ];
        
        return $types[$this->course_type] ?? $this->course_type;
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
            return request()->getSchemeAndHttpHost() . '/storage/' . $this->thumbnail;
        }
        
        // Return null so frontend can handle fallback
        return null;
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

    // Methods for session management
    public function createSession($data)
    {
        $maxOrder = $this->sessions()->max('order_number') ?? 0;
        
        return $this->sessions()->create([
            'session_number' => $maxOrder + 1,
            'session_title' => $data['title'],
            'session_description' => $data['description'] ?? '',
            'order_number' => $maxOrder + 1,
            'estimated_duration_minutes' => $data['duration_minutes'] ?? 0,
            'created_by' => auth()->id(),
        ]);
    }

    public function reorderSessions($sessionIds)
    {
        foreach ($sessionIds as $index => $sessionId) {
            $this->sessions()->where('id', $sessionId)->update(['order_number' => $index + 1]);
        }
    }

    public function getTotalSessionDurationAttribute()
    {
        return $this->sessions()->sum('estimated_duration_minutes') ?? 0;
    }

    public function getActiveSessionsCountAttribute()
    {
        return $this->sessions()->where('status', 'active')->count();
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