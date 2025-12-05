<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsQuestionnaire extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_questionnaires';

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'instructions',
        'is_anonymous',
        'allow_multiple_responses',
        'status',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'questions_count',
        'responses_count',
        'completion_rate',
        'is_active'
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'allow_multiple_responses' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function questions()
    {
        return $this->hasMany(LmsQuestionnaireQuestion::class, 'questionnaire_id')->orderBy('order_number');
    }

    public function responses()
    {
        return $this->hasMany(LmsQuestionnaireResponse::class, 'questionnaire_id');
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
        $now = now()->toDateString();
        return $query->where('status', 'published')
                    ->where(function($q) use ($now) {
                        $q->whereNull('start_date')
                          ->orWhere('start_date', '<=', $now);
                    })
                    ->where(function($q) use ($now) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', $now);
                    });
    }

    // Accessors
    public function getQuestionsCountAttribute()
    {
        if (isset($this->attributes['questions_count'])) {
            return $this->attributes['questions_count'];
        }
        return $this->questions()->count();
    }

    public function getResponsesCountAttribute()
    {
        if (isset($this->attributes['responses_count'])) {
            return $this->attributes['responses_count'];
        }
        return $this->responses()->count();
    }

    public function getCompletionRateAttribute()
    {
        if (isset($this->attributes['completion_rate'])) {
            return $this->attributes['completion_rate'];
        }
        
        $totalQuestions = $this->questions()->count();
        if ($totalQuestions === 0) {
            return 0;
        }

        $totalResponses = $this->responses()->count();
        if ($totalResponses === 0) {
            return 0;
        }

        // Calculate average completion rate based on answered questions
        $totalAnsweredQuestions = 0;
        foreach ($this->responses as $response) {
            $totalAnsweredQuestions += $response->answers()->count();
        }

        return round(($totalAnsweredQuestions / ($totalQuestions * $totalResponses)) * 100, 1);
    }

    // Accessors
    public function getIsActiveAttribute()
    {
        if ($this->status !== 'published') {
            return false;
        }

        $now = now()->toDateString();
        
        if ($this->start_date && $this->start_date > $now) {
            return false;
        }

        if ($this->end_date && $this->end_date < $now) {
            return false;
        }

        return true;
    }

    public function canBeRespondedByUser($userId = null)
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->is_anonymous) {
            return true;
        }

        if (!$userId) {
            return false;
        }

        if (!$this->allow_multiple_responses) {
            $existingResponse = $this->responses()->where('user_id', $userId)->first();
            return !$existingResponse;
        }

        return true;
    }

    public function getUserResponses($userId)
    {
        return $this->responses()->where('user_id', $userId)->orderBy('submitted_at', 'desc');
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($questionnaire) {
            if (!$questionnaire->created_by) {
                $questionnaire->created_by = auth()->id();
            }
            if (!$questionnaire->updated_by) {
                $questionnaire->updated_by = auth()->id();
            }
        });

        static::updating(function ($questionnaire) {
            $questionnaire->updated_by = auth()->id();
        });
    }
}
