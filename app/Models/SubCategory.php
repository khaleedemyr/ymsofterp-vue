<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'show_pos',
        'category_id'
    ];

    protected $casts = [
        'show_pos' => 'integer'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function availabilities()
    {
        return $this->hasMany(SubCategoryAvailability::class, 'sub_category_id', 'id');
    }
} 