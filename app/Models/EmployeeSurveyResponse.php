<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'question_category',
        'question_text',
        'score',
        'comment'
    ];

    public function survey()
    {
        return $this->belongsTo(EmployeeSurvey::class, 'survey_id');
    }
}
