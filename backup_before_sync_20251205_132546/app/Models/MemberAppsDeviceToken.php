<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsDeviceToken extends Model
{
    protected $table = 'member_apps_device_tokens';
    
    protected $fillable = [
        'member_id',
        'device_token',
        'device_type',
        'device_id',
        'app_version',
        'is_active',
        'last_used_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_id');
    }
}

