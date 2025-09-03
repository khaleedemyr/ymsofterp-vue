<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsCurriculum extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_curriculum';

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'order_number',
        'is_required',
        'estimated_duration_minutes',
        'status',
        'created_by'
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'estimated_duration_minutes' => 'integer',
        'order_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'duration_formatted',
        'total_items_count',
        'quiz_count',
        'questionnaire_count',
        'material_count',
        'is_completed_by_user'
    ];

    // Relationships
    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function items()
    {
        return $this->hasMany(LmsCurriculumItem::class, 'curriculum_id')->orderBy('order_number');
    }

    public function quizzes()
    {
        return $this->hasMany(LmsCurriculumItem::class, 'curriculum_id')
                    ->where('item_type', 'quiz')
                    ->orderBy('order_number');
    }

    public function questionnaires()
    {
        return $this->hasMany(LmsCurriculumItem::class, 'curriculum_id')
                    ->where('item_type', 'questionnaire')
                    ->orderBy('order_number');
    }

    public function materials()
    {
        return $this->hasMany(LmsCurriculumItem::class, 'curriculum_id')
                    ->where('item_type', 'material')
                    ->orderBy('order_number');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function progress()
    {
        return $this->hasManyThrough(
            LmsCurriculumProgress::class,
            LmsCurriculumItem::class,
            'curriculum_id',
            'curriculum_item_id'
        );
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_number');
    }

    // Accessors
    public function getDurationFormattedAttribute()
    {
        if (!$this->estimated_duration_minutes) {
            return 'Tidak ditentukan';
        }

        $hours = floor($this->estimated_duration_minutes / 60);
        $minutes = $this->estimated_duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours} jam {$minutes} menit";
        } elseif ($hours > 0) {
            return "{$hours} jam";
        } else {
            return "{$minutes} menit";
        }
    }

    public function getTotalItemsCountAttribute()
    {
        return $this->items()->count();
    }

    public function getQuizCountAttribute()
    {
        return $this->quizzes()->count();
    }

    public function getQuestionnaireCountAttribute()
    {
        return $this->questionnaires()->count();
    }

    public function getMaterialCountAttribute()
    {
        return $this->materials()->count();
    }

    public function getIsCompletedByUserAttribute()
    {
        if (!auth()->check()) {
            return false;
        }

        $userId = auth()->id();
        $requiredItems = $this->items()->where('is_required', true)->get();
        
        if ($requiredItems->isEmpty()) {
            return true;
        }

        foreach ($requiredItems as $item) {
            $progress = LmsCurriculumProgress::where('user_id', $userId)
                                           ->where('curriculum_item_id', $item->id)
                                           ->where('status', 'completed')
                                           ->first();
            
            if (!$progress) {
                return false;
            }
        }

        return true;
    }

    // Methods
    public function getProgressForUser($userId)
    {
        $totalItems = $this->items()->where('is_required', true)->count();
        if ($totalItems === 0) {
            return 100;
        }

        $completedItems = 0;
        $requiredItems = $this->items()->where('is_required', true)->get();

        foreach ($requiredItems as $item) {
            $progress = LmsCurriculumProgress::where('user_id', $userId)
                                           ->where('curriculum_item_id', $item->id)
                                           ->where('status', 'completed')
                                           ->first();
            
            if ($progress) {
                $completedItems++;
            }
        }

        return round(($completedItems / $totalItems) * 100);
    }

    public function getEstimatedTotalDuration()
    {
        return $this->items()->sum('estimated_duration_minutes');
    }

    public function isAccessibleByUser($userId)
    {
        // Check if user is enrolled in the course
        $enrollment = LmsEnrollment::where('user_id', $userId)
                                  ->where('course_id', $this->course_id)
                                  ->where('status', 'active')
                                  ->first();

        return $enrollment !== null;
    }

    public function getNextItem($currentItemOrder = 0)
    {
        return $this->items()
                    ->where('order_number', '>', $currentItemOrder)
                    ->orderBy('order_number')
                    ->first();
    }

    public function getPreviousItem($currentItemOrder = 0)
    {
        return $this->items()
                    ->where('order_number', '<', $currentItemOrder)
                    ->orderBy('order_number', 'desc')
                    ->first();
    }

    public function reorderItems($itemIds)
    {
        foreach ($itemIds as $index => $itemId) {
            $this->items()->where('id', $itemId)->update(['order_number' => $index + 1]);
        }
    }

    public function duplicate()
    {
        $newCurriculum = $this->replicate();
        $newCurriculum->title = $this->title . ' (Copy)';
        $newCurriculum->order_number = $this->order_number + 1;
        $newCurriculum->save();

        // Duplicate items
        foreach ($this->items as $item) {
            $newItem = $item->replicate();
            $newItem->curriculum_id = $newCurriculum->id;
            $newItem->save();

            // Duplicate materials if any
            foreach ($item->materials as $material) {
                $newMaterial = $material->replicate();
                $newMaterial->curriculum_item_id = $newItem->id;
                $newMaterial->save();
            }
        }

        return $newCurriculum;
    }
}
