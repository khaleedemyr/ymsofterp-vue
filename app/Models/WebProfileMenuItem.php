<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebProfileMenuItem extends Model
{
    use HasFactory;

    protected $table = 'web_profile_menu_items';

    protected $fillable = [
        'label',
        'url',
        'page_id',
        'parent_id',
        'order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function page()
    {
        return $this->belongsTo(WebProfilePage::class, 'page_id');
    }

    public function children()
    {
        return $this->hasMany(WebProfileMenuItem::class, 'parent_id')->orderBy('order');
    }

    public function parent()
    {
        return $this->belongsTo(WebProfileMenuItem::class, 'parent_id');
    }
}

