<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoItemPrice extends Model
{
    protected $table = 'promo_item_prices';
    protected $fillable = [
        'promo_id', 'item_id', 'outlet_id', 'region_id', 'old_price', 'new_price'
    ];

    public function outlet()
    {
        return $this->belongsTo(\App\Models\Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function region()
    {
        return $this->belongsTo(\App\Models\Region::class, 'region_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(\App\Models\Item::class, 'item_id', 'id');
    }
} 