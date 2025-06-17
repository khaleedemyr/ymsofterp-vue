<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutletFoodInventoryCostHistory extends Model
{
    use HasFactory;
    protected $table = 'outlet_food_inventory_cost_histories';
    protected $fillable = [
        'inventory_item_id',
        'id_outlet',
        'warehouse_outlet_id',
        'date',
        'old_cost',
        'new_cost',
        'mac',
        'type',
        'reference_type',
        'reference_id',
        'created_at',
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