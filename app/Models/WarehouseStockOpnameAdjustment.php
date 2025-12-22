<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseStockOpnameAdjustment extends Model
{
    use HasFactory;

    protected $table = 'warehouse_stock_opname_adjustments';

    protected $fillable = [
        'stock_opname_id',
        'stock_opname_item_id',
        'inventory_item_id',
        'warehouse_id',
        'warehouse_division_id',
        'qty_diff_small',
        'qty_diff_medium',
        'qty_diff_large',
        'reason',
        'mac_before',
        'mac_after',
        'value_adjustment',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'qty_diff_small' => 'decimal:2',
        'qty_diff_medium' => 'decimal:2',
        'qty_diff_large' => 'decimal:2',
        'mac_before' => 'decimal:4',
        'mac_after' => 'decimal:4',
        'value_adjustment' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    // Relationships
    public function stockOpname()
    {
        return $this->belongsTo(WarehouseStockOpname::class, 'stock_opname_id');
    }

    public function stockOpnameItem()
    {
        return $this->belongsTo(WarehouseStockOpnameItem::class, 'stock_opname_item_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(\App\Models\FoodInventoryItem::class, 'inventory_item_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'processed_by');
    }

    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'warehouse_id');
    }

    public function warehouseDivision()
    {
        return $this->belongsTo(\App\Models\WarehouseDivision::class, 'warehouse_division_id');
    }
}

