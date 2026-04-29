<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function jobVacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }
}

