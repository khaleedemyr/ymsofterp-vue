<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsFaq extends Model
{
    protected $table = 'member_apps_faqs';
    
    protected $fillable = [
        'question',
        'answer',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

