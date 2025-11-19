<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderFoodItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_food_items';

    protected $fillable = [
        'purchase_order_food_id',
        'item_id',
        'quantity',
        'unit_id',
        'price',
        'discount_percent',
        'discount_amount',
        'total',
        'created_by',
        'arrival_date',
        'pr_food_item_id',
        'source_type',
        'source_id',
        'ro_id',
        'ro_number',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrderFood::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function butcherPrFoodItem()
    {
        return $this->belongsTo(PrFoodItem::class, 'pr_food_item_id');
    }
} 