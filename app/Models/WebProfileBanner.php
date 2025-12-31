<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebProfileBanner extends Model
{
    use HasFactory;

    protected $table = 'web_profile_banners';

    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'background_image',
        'content_image',
        'order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'background_image_url',
        'content_image_url'
    ];

    // Accessor untuk full URL
    public function getBackgroundImageUrlAttribute()
    {
        return $this->background_image ? asset('storage/' . $this->background_image) : null;
    }

    public function getContentImageUrlAttribute()
    {
        return $this->content_image ? asset('storage/' . $this->content_image) : null;
    }
}

