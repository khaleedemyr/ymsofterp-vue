<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoodInventoryAdjustment extends Model
{
    use HasFactory;

    protected $table = 'food_inventory_adjustments';

    protected $fillable = [
        'inventory_item_id',
        'warehouse_id',
        'date',
        'type',
        'qty_small',
        'qty_medium',
        'qty_large',
        'reason',
        'created_by',
    ];

    public $timestamps = true;

    public function inventoryItem()
    {
        return $this->belongsTo(FoodInventoryItem::class, 'inventory_item_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assistantSsdManager()
    {
        return $this->belongsTo(User::class, 'approved_by_assistant_ssd_manager');
    }

    public function ssdManager()
    {
        return $this->belongsTo(User::class, 'approved_by_ssd_manager');
    }

    public function costControlManager()
    {
        return $this->belongsTo(User::class, 'approved_by_cost_control_manager');
    }
} 