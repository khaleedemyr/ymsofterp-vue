<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsTermCondition extends Model
{
    protected $table = 'member_apps_terms_conditions';
    
    protected $fillable = [
        'title',
        'content',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

