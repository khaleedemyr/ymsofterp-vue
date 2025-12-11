<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $table = 'outlet_stock_opname_items';

    protected $fillable = [
        'stock_opname_id',
        'inventory_item_id',
        'qty_system_small',
        'qty_system_medium',
        'qty_system_large',
        'qty_physical_small',
        'qty_physical_medium',
        'qty_physical_large',
        'qty_diff_small',
        'qty_diff_medium',
        'qty_diff_large',
        'reason',
        'mac_before',
        'mac_after',
        'value_adjustment',
    ];

    protected $casts = [
        'qty_system_small' => 'decimal:2',
        'qty_system_medium' => 'decimal:2',
        'qty_system_large' => 'decimal:2',
        'qty_physical_small' => 'decimal:2',
        'qty_physical_medium' => 'decimal:2',
        'qty_physical_large' => 'decimal:2',
        'qty_diff_small' => 'decimal:2',
        'qty_diff_medium' => 'decimal:2',
        'qty_diff_large' => 'decimal:2',
        'mac_before' => 'decimal:4',
        'mac_after' => 'decimal:4',
        'value_adjustment' => 'decimal:2',
    ];

    // Relationships
    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(\App\Models\OutletFoodInventoryItem::class, 'inventory_item_id');
    }

    // Methods
    public function hasDifference()
    {
        return $this->qty_diff_small != 0 || $this->qty_diff_medium != 0 || $this->qty_diff_large != 0;
    }

    public function calculateDifference()
    {
        $this->qty_diff_small = ($this->qty_physical_small ?? $this->qty_system_small) - $this->qty_system_small;
        $this->qty_diff_medium = ($this->qty_physical_medium ?? $this->qty_system_medium) - $this->qty_system_medium;
        $this->qty_diff_large = ($this->qty_physical_large ?? $this->qty_system_large) - $this->qty_system_large;
    }

    public function calculateValueAdjustment($mac)
    {
        // Value adjustment = qty_diff * MAC (untuk small unit)
        $this->value_adjustment = $this->qty_diff_small * $mac;
    }
}

