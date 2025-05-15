<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodInventoryCard extends Model
{
    protected $table = 'food_inventory_cards';
    protected $fillable = [
        'inventory_item_id',
        'warehouse_id',
        'date',
        'reference_type',
        'reference_id',
        'in_qty_small',
        'in_qty_medium',
        'in_qty_large',
        'out_qty_small',
        'out_qty_medium',
        'out_qty_large',
        'cost_per_small',
        'cost_per_medium',
        'cost_per_large',
        'value_in',
        'value_out',
        'saldo_qty_small',
        'saldo_qty_medium',
        'saldo_qty_large',
        'saldo_value',
        'description',
        'created_at',
    ];
}
