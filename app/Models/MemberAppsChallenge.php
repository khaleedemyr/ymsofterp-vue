<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsChallenge extends Model
{
    protected $table = 'member_apps_challenges';
    
    protected $fillable = [
        'title',
        'description',
        'rules',
        'image',
        'points_reward',
        'is_active',
        'start_date',
        'end_date'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
