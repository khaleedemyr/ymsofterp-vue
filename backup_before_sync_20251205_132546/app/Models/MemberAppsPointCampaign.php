<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsPointCampaign extends Model
{
    protected $table = 'member_apps_point_campaigns';
    
    protected $fillable = [
        'name',
        'description',
        'campaign_type',
        'multiplier',
        'bonus_points',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'applicable_channels',
        'applicable_member_levels',
        'is_active'
    ];

    protected $casts = [
        'multiplier' => 'decimal:2',
        'bonus_points' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'applicable_channels' => 'array',
        'applicable_member_levels' => 'array',
        'is_active' => 'boolean',
    ];
}

