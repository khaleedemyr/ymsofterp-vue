<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDeviceToken extends Model
{
    protected $table = 'employee_device_tokens';
    
    protected $fillable = [
        'user_id',
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

