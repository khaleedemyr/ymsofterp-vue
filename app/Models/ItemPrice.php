<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemPrice extends Model
{
    use HasFactory;

    protected $table = 'item_prices';

    protected $fillable = [
        'item_id',
        'availability_price_type',
        'region_id',
        'outlet_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
} 