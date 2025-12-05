<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\FoodGoodReceiveItem;

class ContraBonItem extends Model
{
    use HasFactory;

    protected $table = 'food_contra_bon_items';

    protected $fillable = [
        'contra_bon_id',
        'item_id',
        'po_item_id',
        'gr_item_id', // Untuk tracking item dari good receive yang sudah dibuat contra bon
        'retail_food_item_id', // OPSIONAL: Untuk tracking item dari retail food (jika kolom ada)
        'warehouse_retail_food_item_id', // OPSIONAL: Untuk tracking item dari warehouse retail food (jika kolom ada)
        'quantity',
        'unit_id',
        'price',
        'discount_percent',
        'discount_amount',
        'total',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2'
    ];

    public function contraBon()
    {
        return $this->belongsTo(ContraBon::class, 'contra_bon_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function poItem()
    {
        return $this->belongsTo(PurchaseOrderFoodItem::class, 'po_item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function grItem()
    {
        return $this->belongsTo(FoodGoodReceiveItem::class, 'gr_item_id');
    }
} 