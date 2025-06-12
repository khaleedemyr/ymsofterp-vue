<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemSupplierOutlet extends Model
{
    protected $table = 'item_supplier_outlet';
    protected $guarded = [];

    public function itemSupplier()
    {
        return $this->belongsTo(ItemSupplier::class, 'item_supplier_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
} 