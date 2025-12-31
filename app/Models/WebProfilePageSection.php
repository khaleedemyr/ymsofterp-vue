<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebProfilePageSection extends Model
{
    use HasFactory;

    protected $table = 'web_profile_page_sections';

    protected $fillable = [
        'page_id',
        'type',
        'title',
        'content',
        'data',
        'order',
        'is_active'
    ];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
    ];

    public function page()
    {
        return $this->belongsTo(WebProfilePage::class, 'page_id');
    }
}

