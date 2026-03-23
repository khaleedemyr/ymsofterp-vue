<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebProfileHomeServicePackage extends Model
{
    protected $table = 'web_profile_home_service_packages';

    protected $fillable = [
        'web_profile_brand_id',
        'title',
        'price_label',
        'body_html',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(WebProfileBrand::class, 'web_profile_brand_id');
    }
}
