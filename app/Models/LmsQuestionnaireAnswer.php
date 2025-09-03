<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LmsQuestionnaireAnswer extends Model
{
    use HasFactory;

    protected $table = 'lms_questionnaire_answers';

    protected $fillable = [
        'response_id',
        'question_id',
        'answer_text',
        'selected_option_id',
        'rating_value'
    ];

    protected $casts = [
        'rating_value' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function response()
    {
        return $this->belongsTo(LmsQuestionnaireResponse::class, 'response_id');
    }

    public function question()
    {
        return $this->belongsTo(LmsQuestionnaireQuestion::class, 'question_id');
    }

    public function selectedOption()
    {
        return $this->belongsTo(LmsQuestionnaireOption::class, 'selected_option_id');
    }

    // Accessors
    public function getAnswerDisplayAttribute()
    {
        if ($this->answer_text) {
            return $this->answer_text;
        }

        if ($this->selected_option_id && $this->selectedOption) {
            return $this->selectedOption->option_text;
        }

        if ($this->rating_value) {
            return $this->rating_value . ' / 5';
        }

        return 'No answer';
    }
}
