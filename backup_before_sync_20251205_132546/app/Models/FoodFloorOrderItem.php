<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodFloorOrderItem extends Model
{
    protected $table = 'food_floor_order_items';
    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(FoodFloorOrder::class, 'floor_order_id');
    }

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class, 'category_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
} 