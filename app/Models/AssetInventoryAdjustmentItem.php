<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetInventoryAdjustmentItem extends Model
{
    protected $table = 'asset_inventory_adjustment_items';
    protected $guarded = [];

    public function adjustment()
    {
        return $this->belongsTo(AssetInventoryAdjustment::class, 'adjustment_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
