<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsContactUs extends Model
{
    protected $table = 'member_apps_contact_us';
    
    protected $fillable = [
        'title',
        'content',
        'whatsapp_number',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

