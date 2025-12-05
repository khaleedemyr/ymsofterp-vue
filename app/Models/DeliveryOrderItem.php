<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrderItem extends Model
{
    protected $table = 'delivery_order_items';
    protected $guarded = [];
    public $timestamps = true;
} 