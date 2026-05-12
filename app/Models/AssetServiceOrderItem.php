<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetServiceOrderItem extends Model
{
    protected $table = 'asset_service_order_items';
    protected $guarded = [];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function serviceOrder()
    {
        return $this->belongsTo(AssetServiceOrder::class, 'service_order_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
