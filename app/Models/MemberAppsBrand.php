<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsBrand extends Model
{
    protected $table = 'member_apps_brands';
    
    protected $fillable = [
        'name',
        'description',
        'image',
        'pdf_file',
        'website_url',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
