<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodGoodReceiveItem extends Model
{
    protected $table = 'food_good_receive_items';
    protected $guarded = [];

    public function goodReceive()
    {
        return $this->belongsTo(FoodGoodReceive::class, 'good_receive_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
} 