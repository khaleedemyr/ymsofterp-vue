<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    protected $guarded = [];
    protected $casts = [
        'show_pos' => 'integer',
    ];

    public function availabilities()
    {
        return $this->hasMany(\App\Models\CategoryOutlet::class, 'category_id', 'id');
    }
}
