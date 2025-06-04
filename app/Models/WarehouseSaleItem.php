<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseSaleItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'warehouse_sale_id',
        'item_id',
        'qty_small',
        'qty_medium',
        'qty_large',
        'price',
        'total',
        'note'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function warehouseSale()
    {
        return $this->belongsTo(WarehouseSale::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
} 