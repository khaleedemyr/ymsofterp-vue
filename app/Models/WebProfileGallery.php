<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebProfileGallery extends Model
{
    use HasFactory;

    protected $table = 'web_profile_galleries';

    protected $fillable = [
        'title',
        'description',
        'image_path',
        'category',
        'order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

