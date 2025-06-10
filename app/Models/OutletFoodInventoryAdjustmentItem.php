<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutletFoodInventoryAdjustmentItem extends Model
{
    use HasFactory;

    protected $table = 'outlet_food_inventory_adjustment_items';

    protected $fillable = [
        'adjustment_id',
        'item_id',
        'qty',
        'unit',
        'note'
    ];

    public function adjustment()
    {
        return $this->belongsTo(OutletFoodInventoryAdjustment::class, 'adjustment_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
} 