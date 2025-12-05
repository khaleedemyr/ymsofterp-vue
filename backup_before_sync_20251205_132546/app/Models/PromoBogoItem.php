<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoBogoItem extends Model
{
    protected $table = 'promo_bogo_items';
    
    protected $fillable = [
        'promo_id',
        'buy_item_id',
        'get_item_id'
    ];

    public function promo()
    {
        return $this->belongsTo(Promo::class);
    }

    public function buyItem()
    {
        return $this->belongsTo(Item::class, 'buy_item_id');
    }

    public function getItem()
    {
        return $this->belongsTo(Item::class, 'get_item_id');
    }
} 