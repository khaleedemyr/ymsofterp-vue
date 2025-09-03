<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsCurriculumItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_curriculum_items';

    protected $fillable = [
        'course_id',
        'session_number',
        'session_title',
        'session_description',
        'order_number',
        'is_required',
        'estimated_duration_minutes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'estimated_duration_minutes' => 'integer',
        'order_number' => 'integer',
        'session_number' => 'integer',
    ];

    protected $appends = [
        'quiz_count',
        'material_count',
        'questionnaire_count',
        'total_duration_minutes',
    ];

    // Relationships
    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function quiz()
    {
        return $this->belongsTo(LmsQuiz::class, 'quiz_id');
    }

    public function questionnaire()
    {
        return $this->belongsTo(LmsQuestionnaire::class, 'questionnaire_id');
    }

    public function materials()
    {
        return $this->hasMany(LmsCurriculumMaterial::class, 'curriculum_item_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors
    public function getQuizCountAttribute()
    {
        return $this->quiz_id ? 1 : 0;
    }

    public function getMaterialCountAttribute()
    {
        return $this->materials()->count();
    }

    public function getQuestionnaireCountAttribute()
    {
        return $this->questionnaire_id ? 1 : 0;
    }

    public function getTotalDurationMinutesAttribute()
    {
        $duration = $this->estimated_duration_minutes ?? 0;
        
        // Add quiz duration if exists
        if ($this->quiz) {
            $duration += $this->quiz->estimated_duration_minutes ?? 0;
        }
        
        // Add questionnaire duration if exists
        if ($this->questionnaire) {
            $duration += $this->questionnaire->estimated_duration_minutes ?? 0;
        }
        
        // Add materials duration
        $duration += $this->materials()->sum('estimated_duration_minutes');
        
        return $duration;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByOrder($query)
    {
        return $query->orderBy('order_number');
    }

    public function scopeBySession($query, $sessionNumber)
    {
        return $query->where('session_number', $sessionNumber);
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (!$item->created_by) {
                $item->created_by = auth()->id();
            }
            if (!$item->updated_by) {
                $item->updated_by = auth()->id();
            }
        });

        static::updating(function ($item) {
            $item->updated_by = auth()->id();
        });
    }
}
