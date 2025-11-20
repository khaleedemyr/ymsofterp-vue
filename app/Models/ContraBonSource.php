<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContraBonSource extends Model
{
    protected $table = 'food_contra_bon_sources';

    protected $fillable = [
        'contra_bon_id',
        'source_type',
        'source_id',
        'po_id',
        'gr_id',
    ];

    public function contraBon()
    {
        return $this->belongsTo(ContraBon::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrderFood::class, 'po_id');
    }

    public function retailFood()
    {
        return $this->belongsTo(RetailFood::class, 'source_id');
    }

    public function warehouseRetailFood()
    {
        return $this->belongsTo(RetailWarehouseFood::class, 'source_id');
    }
}

