<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutletFoodInventoryAdjustment extends Model
{
    use HasFactory;

    protected $table = 'outlet_food_inventory_adjustments';

    protected $fillable = [
        'number',
        'date',
        'id_outlet',
        'type',
        'reason',
        'status',
        'created_by',
        'approved_by_ssd_manager',
        'approved_at_ssd_manager',
        'ssd_manager_note',
        'approved_by_cost_control_manager',
        'approved_at_cost_control_manager',
        'cost_control_manager_note'
    ];

    public function items()
    {
        return $this->hasMany(OutletFoodInventoryAdjustmentItem::class, 'adjustment_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'id_outlet');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
} 