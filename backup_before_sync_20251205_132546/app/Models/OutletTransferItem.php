<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutletTransferItem extends Model
{
    protected $table = 'outlet_transfer_items';
    protected $guarded = [];

    public function transfer()
    {
        return $this->belongsTo(OutletTransfer::class, 'outlet_transfer_id');
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
