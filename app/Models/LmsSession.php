<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_sessions';

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

    // Relationships
    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function items()
    {
        return $this->hasMany(LmsSessionItem::class, 'session_id')->orderBy('order_number');
    }

    public function quizItems()
    {
        return $this->hasMany(LmsSessionItem::class, 'session_id')
            ->where('item_type', 'quiz')
            ->orderBy('order_number');
    }

    public function materialItems()
    {
        return $this->hasMany(LmsSessionItem::class, 'session_id')
            ->where('item_type', 'material')
            ->orderBy('order_number');
    }

    public function questionnaireItems()
    {
        return $this->hasMany(LmsSessionItem::class, 'session_id')
            ->where('item_type', 'questionnaire')
            ->orderBy('order_number');
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
        return $query->where('status', 'active');
    }

    public function scopeForCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_number');
    }

    // Accessors
    public function getTotalDurationAttribute()
    {
        return $this->items->sum('estimated_duration_minutes') ?? 0;
    }

    public function getItemCountAttribute()
    {
        return $this->items->count();
    }

    public function getQuizCountAttribute()
    {
        return $this->quizItems->count();
    }

    public function getMaterialCountAttribute()
    {
        return $this->materialItems->count();
    }

    public function getQuestionnaireCountAttribute()
    {
        return $this->questionnaireItems->count();
    }

    // Methods
    public function addItem($type, $itemId = null, $title = null, $description = null, $duration = null)
    {
        $maxOrder = $this->items()->max('order_number') ?? 0;
        
        return $this->items()->create([
            'item_type' => $type,
            'item_id' => $itemId,
            'title' => $title,
            'description' => $description,
            'estimated_duration_minutes' => $duration,
            'order_number' => $maxOrder + 1,
            'created_by' => auth()->id(),
        ]);
    }

    public function reorderItems($itemIds)
    {
        foreach ($itemIds as $index => $itemId) {
            $this->items()->where('id', $itemId)->update(['order_number' => $index + 1]);
        }
    }
}
