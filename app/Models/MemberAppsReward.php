<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsReward extends Model
{
    protected $table = 'member_apps_rewards';
    
    protected $fillable = [
        'item_id',
        'points_required',
        'serial_code',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function outlets()
    {
        return $this->belongsToMany(
            \App\Models\Outlet::class,
            'member_apps_reward_outlets',
            'reward_id',
            'outlet_id',
            'id',
            'id_outlet'
        )->where('is_fc', 0);
    }
}
