<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $table = 'erp_menu';
    protected $fillable = [
        'name', 'code', 'parent_id', 'route', 'icon'
    ];
    public function parent() {
        return $this->belongsTo(Menu::class, 'parent_id');
    }
    public function children() {
        return $this->hasMany(Menu::class, 'parent_id');
    }
} 