<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobVacancy extends Model
{
    protected $table = 'job_vacancies';
    protected $fillable = [
        'position',
        'description',
        'requirements',
        'location',
        'closing_date',
        'banner',
        'is_active',
    ];
} 