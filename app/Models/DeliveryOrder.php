<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    protected $table = 'delivery_orders';
    protected $guarded = [];
    public $timestamps = true;

    public function items()
    {
        return $this->hasMany(DeliveryOrderItem::class, 'delivery_order_id');
    }

    public function packingList()
    {
        return $this->belongsTo(FoodPackingList::class, 'packing_list_id');
    }

    public function floorOrder()
    {
        return $this->belongsTo(FoodFloorOrder::class, 'floor_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }


} 