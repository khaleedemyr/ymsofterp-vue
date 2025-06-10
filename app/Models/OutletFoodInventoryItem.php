<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutletFoodInventoryItem extends Model
{
    use HasFactory;
    protected $table = 'outlet_food_inventory_items';
    protected $fillable = [
        'item_id',
        'small_unit_id',
        'medium_unit_id',
        'large_unit_id',
        'created_at',
        'updated_at',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
    public function stocks()
    {
        return $this->hasMany(OutletFoodInventoryStock::class, 'inventory_item_id');
    }
    public function cards()
    {
        return $this->hasMany(OutletFoodInventoryCard::class, 'inventory_item_id');
    }
    public function costHistories()
    {
        return $this->hasMany(OutletFoodInventoryCostHistory::class, 'inventory_item_id');
    }
} 