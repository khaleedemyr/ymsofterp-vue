<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobVacancy extends Model
{
    protected $table = 'job_vacancies';
    protected $fillable = [
        'position',
        'description',
        'requirements',
        'location',
        'job_scope',
        'closing_date',
        'banner',
        'is_active',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(JobVacancyApplication::class, 'job_vacancy_id');
    }

    public function recruitmentConfig(): HasOne
    {
        return $this->hasOne(JobVacancyRecruitmentConfig::class, 'job_vacancy_id');
    }

    public function pics(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'job_vacancy_pics', 'job_vacancy_id', 'user_id')
            ->withTimestamps();
    }
} 