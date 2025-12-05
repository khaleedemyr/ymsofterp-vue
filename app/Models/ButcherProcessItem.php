<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ButcherProcessItem extends Model
{
    protected $guarded = [];

    public function butcherProcess()
    {
        return $this->belongsTo(ButcherProcess::class);
    }

    public function wholeItem()
    {
        return $this->belongsTo(Item::class, 'whole_item_id');
    }

    public function pcsItem()
    {
        return $this->belongsTo(Item::class, 'pcs_item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function details()
    {
        return $this->hasMany(\App\Models\ButcherProcessItemDetail::class, 'butcher_process_item_id');
    }
} 