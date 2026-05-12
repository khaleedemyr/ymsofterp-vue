<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetInventoryTransferItem extends Model
{
    protected $table = 'asset_inventory_transfer_items';
    protected $guarded = [];

    public function transfer()
    {
        return $this->belongsTo(AssetInventoryTransfer::class, 'asset_inventory_transfer_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
