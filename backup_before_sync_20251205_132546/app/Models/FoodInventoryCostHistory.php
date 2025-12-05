<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodInventoryCostHistory extends Model
{
    protected $table = 'food_inventory_cost_histories';
    protected $fillable = [
        'inventory_item_id',
        'warehouse_id',
        'date',
        'old_cost',
        'new_cost',
        'type',
        'reference_type',
        'reference_id',
        'created_at',
    ];
}
