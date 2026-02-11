<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promo extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'max_discount',
        'is_multiple',
        'min_transaction',
        'max_transaction',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'days',
        'status',
        'description',
        'terms',
        'banner',
        'need_member',
        'all_tiers',
        'tiers',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'value' => 'float',
        'min_transaction' => 'float',
        'max_discount' => 'float',
        'days' => 'array',
        'tiers' => 'array',
        'all_tiers' => 'boolean',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'promo_categories');
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'promo_items');
    }

    public function outlets()
    {
        return $this->belongsToMany(Outlet::class, 'promo_outlets', 'promo_id', 'outlet_id', 'id', 'id_outlet');
    }

    public function regions()
    {
        return $this->belongsToMany(\App\Models\Region::class, 'promo_regions', 'promo_id', 'region_id');
    }

    public function itemPrices()
    {
        return $this->hasMany(\App\Models\PromoItemPrice::class, 'promo_id');
    }

    public function bogoItems()
    {
        return $this->hasMany(\App\Models\PromoBogoItem::class, 'promo_id');
    }

    public function orderPromos()
    {
        return $this->hasMany(OrderPromo::class, 'promo_id');
    }
} 