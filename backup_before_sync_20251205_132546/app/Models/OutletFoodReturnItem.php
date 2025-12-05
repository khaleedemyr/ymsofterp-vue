<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutletFoodReturnItem extends Model
{
    use HasFactory;

    protected $table = 'outlet_food_return_items';

    protected $fillable = [
        'outlet_food_return_id',
        'outlet_food_good_receive_item_id',
        'item_id',
        'unit_id',
        'return_qty'
    ];

    public function return()
    {
        return $this->belongsTo(OutletFoodReturn::class, 'outlet_food_return_id');
    }

    public function goodReceiveItem()
    {
        return $this->belongsTo(OutletFoodGoodReceiveItem::class, 'outlet_food_good_receive_item_id');
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
