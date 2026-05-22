<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetOwnerTransferItem extends Model
{
    protected $table = 'asset_owner_transfer_items';
    protected $guarded = [];

    public function transfer()
    {
        return $this->belongsTo(AssetOwnerTransfer::class, 'asset_owner_transfer_id');
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
