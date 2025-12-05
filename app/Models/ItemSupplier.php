<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemSupplier extends Model
{
    protected $table = 'item_supplier';
    protected $guarded = [];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function itemSupplierOutlets()
    {
        return $this->hasMany(ItemSupplierOutlet::class, 'item_supplier_id');
    }
} 