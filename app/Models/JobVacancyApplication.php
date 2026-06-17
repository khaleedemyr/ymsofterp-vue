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
        'domicile',
        'last_education',
        'birth_date',
        'cover_letter',
        'cv_file',
        'photo_file',
        'status',
        'screening_status',
        'hr_interview_status',
        'user_interview_status',
        'loi_status',
        'stage_notes',
        'joined_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'joined_at' => 'date',
    ];

    public function jobVacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }
}

