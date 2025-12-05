<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebDeviceToken extends Model
{
    protected $table = 'web_device_tokens';
    
    protected $fillable = [
        'user_id',
        'device_token',
        'browser',
        'user_agent',
        'is_active',
        'last_used_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

