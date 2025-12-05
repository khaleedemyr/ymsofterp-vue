<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodGoodReceive extends Model
{
    protected $table = 'food_good_receives';
    protected $guarded = [];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(FoodGoodReceiveItem::class, 'good_receive_id');
    }

    public function butcherPurchaseOrder()
    {
        return $this->belongsTo(PurchaseOrderFood::class, 'po_id');
    }
} 