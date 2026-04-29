<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobVacancyApplication extends Model
{
    protected $table = 'job_vacancy_applications';

    protected $fillable = [
        'job_vacancy_id',
        'full_name',
        'email',
        'phone',
        'cover_letter',
        'cv_file',
        'status',
    ];
}

