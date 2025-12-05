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
        'end_date',
        'challenge_type_id',
        'validity_period_days'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'rules' => 'array',
    ];
    
    /**
     * Get the outlets for the challenge (exclude franchise outlets)
     */
    public function outlets()
    {
        return $this->belongsToMany(
            \App\Models\Outlet::class,
            'member_apps_challenge_outlets',
            'challenge_id',
            'outlet_id',
            'id',
            'id_outlet'
        )->where('is_fc', 0);
    }
}
