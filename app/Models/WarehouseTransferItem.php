<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseTransferItem extends Model
{
    protected $table = 'warehouse_transfer_items';
    protected $guarded = [];

    public function transfer()
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function unit()
    {
        return $this->belongsTo(\App\Models\Unit::class, 'unit_id');
    }
} 