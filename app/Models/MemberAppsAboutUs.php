<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsAboutUs extends Model
{
    protected $table = 'member_apps_about_us';
    
    protected $fillable = [
        'title',
        'content',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

