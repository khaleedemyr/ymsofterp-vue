<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsWhatsOn extends Model
{
    protected $table = 'member_apps_whats_on';
    
    protected $fillable = [
        'title',
        'content',
        'image',
        'is_active',
        'is_featured',
        'published_at',
        'category_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(MemberAppsWhatsOnCategory::class, 'category_id');
    }
}
