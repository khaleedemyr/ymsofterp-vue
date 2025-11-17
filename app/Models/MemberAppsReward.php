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
}
