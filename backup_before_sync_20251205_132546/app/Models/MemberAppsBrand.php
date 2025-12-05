<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsBrand extends Model
{
    protected $table = 'member_apps_brands';
    
    protected $fillable = [
        'outlet_id',
        'name',
        'description',
        'whatsapp_number',
        'image',
        'logo',
        'pdf_file',
        'pdf_menu',
        'pdf_new_dining_experience',
        'website_url',
        'facility',
        'tripadvisor_link',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'facility' => 'array',
    ];

    public function outlet()
    {
        return $this->belongsTo(\App\Models\Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function galleries()
    {
        return $this->hasMany(MemberAppsBrandGallery::class, 'brand_id');
    }
}

