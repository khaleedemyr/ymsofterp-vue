<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSurvey extends Model
{
    use HasFactory;

    protected $fillable = [
        'surveyor_id',
        'surveyor_name',
        'surveyor_position',
        'surveyor_division',
        'surveyor_outlet',
        'survey_date',
        'status'
    ];

    protected $casts = [
        'survey_date' => 'date',
    ];

    public function surveyor()
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function responses()
    {
        return $this->hasMany(EmployeeSurveyResponse::class, 'survey_id');
    }
}
