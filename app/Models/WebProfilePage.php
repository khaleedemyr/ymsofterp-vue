<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebProfilePage extends Model
{
    use HasFactory;

    protected $table = 'web_profile_pages';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'is_published',
        'order'
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function sections()
    {
        return $this->hasMany(WebProfilePageSection::class, 'page_id')->orderBy('order');
    }

    public function activeSections()
    {
        return $this->hasMany(WebProfilePageSection::class, 'page_id')
            ->where('is_active', true)
            ->orderBy('order');
    }
}

