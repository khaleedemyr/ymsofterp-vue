<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodInventoryStock extends Model
{
    protected $table = 'food_inventory_stocks';
    protected $fillable = [
        'inventory_item_id',
        'warehouse_id',
        'qty_small',
        'qty_medium',
        'qty_large',
        'value',
        'last_cost_small',
        'last_cost_medium',
        'last_cost_large',
        'updated_at',
    ];
}
