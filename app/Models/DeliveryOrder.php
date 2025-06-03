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
} 