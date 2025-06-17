<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutletFoodInventoryStock extends Model
{
    use HasFactory;
    protected $table = 'outlet_food_inventory_stocks';
    protected $fillable = [
        'inventory_item_id',
        'id_outlet',
        'warehouse_outlet_id',
        'qty_small',
        'qty_medium',
        'qty_large',
        'value',
        'last_cost_small',
        'last_cost_medium',
        'last_cost_large',
        'created_at',
        'updated_at',
    ];

    public function inventoryItem()
    {
        return $this->belongsTo(OutletFoodInventoryItem::class, 'inventory_item_id');
    }
    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'id_outlet');
    }
} 