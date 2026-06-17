<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobVacancyRecruitmentConfig extends Model
{
    protected $table = 'job_vacancy_recruitment_configs';

    protected $fillable = [
        'job_vacancy_id',
        'pic',
        'headcount_needed',
        'is_hold',
        'search_start_date',
        'target_fulfill_date',
        'hr_interview_notes',
        'user_interview_notes',
        'final_notes',
    ];

    protected $casts = [
        'is_hold' => 'boolean',
        'search_start_date' => 'date',
        'target_fulfill_date' => 'date',
    ];

    public function jobVacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }
}
