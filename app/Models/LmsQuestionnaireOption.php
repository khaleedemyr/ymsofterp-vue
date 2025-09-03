<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LmsQuestionnaireOption extends Model
{
    use HasFactory;

    protected $table = 'lms_questionnaire_options';

    protected $fillable = [
        'question_id',
        'option_text',
        'order_number'
    ];

    protected $casts = [
        'order_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function question()
    {
        return $this->belongsTo(LmsQuestionnaireQuestion::class, 'question_id');
    }

    public function answers()
    {
        return $this->hasMany(LmsQuestionnaireAnswer::class, 'selected_option_id');
    }
}
