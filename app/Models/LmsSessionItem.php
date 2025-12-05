<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsSessionItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_session_items';

    protected $fillable = [
        'session_id',
        'item_type',
        'item_id',
        'title',
        'description',
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
    ];

    // Relationships
    public function session()
    {
        return $this->belongsTo(LmsSession::class, 'session_id');
    }

    public function quiz()
    {
        return $this->belongsTo(LmsQuiz::class, 'item_id');
    }

    public function material()
    {
        return $this->belongsTo(LmsCurriculumMaterial::class, 'item_id');
    }

    public function questionnaire()
    {
        return $this->belongsTo(LmsQuestionnaire::class, 'item_id');
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

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('item_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_number');
    }

    // Accessors
    public function getDisplayTitleAttribute()
    {
        if ($this->title) {
            return $this->title;
        }

        switch ($this->item_type) {
            case 'quiz':
                return $this->quiz->title ?? 'Quiz';
            case 'material':
                return $this->material->title ?? 'Material';
            case 'questionnaire':
                return $this->questionnaire->title ?? 'Questionnaire';
            default:
                return ucfirst($this->item_type);
        }
    }

    public function getDisplayDescriptionAttribute()
    {
        if ($this->description) {
            return $this->description;
        }

        switch ($this->item_type) {
            case 'quiz':
                return $this->quiz->description ?? '';
            case 'material':
                return $this->material->description ?? '';
            case 'questionnaire':
                return $this->questionnaire->description ?? '';
            default:
                return '';
        }
    }

    public function getTypeIconAttribute()
    {
        switch ($this->item_type) {
            case 'quiz':
                return 'quiz-icon';
            case 'material':
                return 'material-icon';
            case 'questionnaire':
                return 'questionnaire-icon';
            default:
                return 'item-icon';
        }
    }

    public function getTypeColorAttribute()
    {
        switch ($this->item_type) {
            case 'quiz':
                return 'blue';
            case 'material':
                return 'green';
            case 'questionnaire':
                return 'purple';
            default:
                return 'gray';
        }
    }

    // Methods
    public function getReferencedItem()
    {
        switch ($this->item_type) {
            case 'quiz':
                return $this->quiz;
            case 'material':
                return $this->material;
            case 'questionnaire':
                return $this->questionnaire;
            default:
                return null;
        }
    }

    public function isQuiz()
    {
        return $this->item_type === 'quiz';
    }

    public function isMaterial()
    {
        return $this->item_type === 'material';
    }

    public function isQuestionnaire()
    {
        return $this->item_type === 'questionnaire';
    }

    public function updateOrder($newOrder)
    {
        $this->update(['order_number' => $newOrder]);
    }
}
