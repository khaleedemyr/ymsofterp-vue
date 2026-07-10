<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodInventoryAdjustmentItem extends Model
{
    protected $table = 'food_inventory_adjustment_items';

    protected $guarded = [];

    public function adjustment()
    {
        return $this->belongsTo(FoodInventoryAdjustment::class, 'adjustment_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
