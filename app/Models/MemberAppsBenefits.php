<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsBenefits extends Model
{
    protected $table = 'member_apps_benefits';
    
    protected $fillable = [
        'title',
        'content',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

