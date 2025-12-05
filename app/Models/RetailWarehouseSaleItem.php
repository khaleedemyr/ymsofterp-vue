<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailWarehouseSaleItem extends Model
{
    protected $table = 'retail_warehouse_sale_items';
    protected $guarded = [];
    public $timestamps = true;

    protected $casts = [
        'qty' => 'decimal:2',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function retailWarehouseSale()
    {
        return $this->belongsTo(RetailWarehouseSale::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
} 