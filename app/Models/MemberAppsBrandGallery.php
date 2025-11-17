<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsBrandGallery extends Model
{
    protected $table = 'member_apps_brand_galleries';
    
    protected $fillable = [
        'brand_id',
        'image',
        'sort_order'
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function brand()
    {
        return $this->belongsTo(MemberAppsBrand::class, 'brand_id');
    }
}

