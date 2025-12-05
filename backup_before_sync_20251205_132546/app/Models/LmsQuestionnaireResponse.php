<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LmsQuestionnaireResponse extends Model
{
    use HasFactory;

    protected $table = 'lms_questionnaire_responses';

    protected $fillable = [
        'questionnaire_id',
        'user_id',
        'respondent_name',
        'respondent_email',
        'submitted_at'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function questionnaire()
    {
        return $this->belongsTo(LmsQuestionnaire::class, 'questionnaire_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function answers()
    {
        return $this->hasMany(LmsQuestionnaireAnswer::class, 'response_id');
    }

    // Accessors
    public function getRespondentDisplayNameAttribute()
    {
        if ($this->user) {
            return $this->user->nama_lengkap;
        }
        return $this->respondent_name ?? 'Anonymous';
    }

    public function getRespondentDisplayEmailAttribute()
    {
        if ($this->user) {
            return $this->user->email;
        }
        return $this->respondent_email ?? 'N/A';
    }
}
