<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LmsQuestionnaireQuestion extends Model
{
    use HasFactory;

    protected $table = 'lms_questionnaire_questions';

    protected $fillable = [
        'questionnaire_id',
        'question_text',
        'question_type',
        'is_required',
        'order_number'
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'order_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function questionnaire()
    {
        return $this->belongsTo(LmsQuestionnaire::class, 'questionnaire_id');
    }

    public function options()
    {
        return $this->hasMany(LmsQuestionnaireOption::class, 'question_id')->orderBy('order_number');
    }

    public function answers()
    {
        return $this->hasMany(LmsQuestionnaireAnswer::class, 'question_id');
    }

    // Accessors
    public function getQuestionTypeTextAttribute()
    {
        $typeMap = [
            'multiple_choice' => 'Pilihan Ganda',
            'essay' => 'Essay',
            'true_false' => 'Benar/Salah',
            'rating' => 'Rating',
            'checkbox' => 'Checkbox'
        ];
        return $typeMap[$this->question_type] ?? $this->question_type;
    }

    public function getHasOptionsAttribute()
    {
        return in_array($this->question_type, ['multiple_choice', 'true_false', 'checkbox']);
    }

    public function getIsRatingAttribute()
    {
        return $this->question_type === 'rating';
    }

    public function getIsEssayAttribute()
    {
        return $this->question_type === 'essay';
    }
}
