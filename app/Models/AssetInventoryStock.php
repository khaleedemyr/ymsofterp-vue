<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetInventoryStock extends Model
{
    protected $table = 'asset_inventory_stocks';
    protected $fillable = [
        'inventory_item_id', 'outlet_id', 'warehouse_outlet_id',
        'qty_small', 'qty_medium', 'qty_large', 'value',
        'last_cost_small', 'last_cost_medium', 'last_cost_large',
    ];

    public function inventoryItem() { return $this->belongsTo(AssetInventoryItem::class, 'inventory_item_id'); }
    public function outlet() { return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet'); }
    public function warehouseOutlet() { return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_id'); }
}
