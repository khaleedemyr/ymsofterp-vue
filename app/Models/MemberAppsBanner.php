<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsBanner extends Model
{
    protected $table = 'member_apps_banners';
    
    protected $fillable = [
        'title',
        'image',
        'description',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
