<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsWhatsOnCategory extends Model
{
    protected $table = 'member_apps_whats_on_categories';
    
    protected $fillable = [
        'name',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function whatsOn()
    {
        return $this->hasMany(MemberAppsWhatsOn::class, 'category_id');
    }
}

