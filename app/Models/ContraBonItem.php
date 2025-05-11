<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContraBonItem extends Model
{
    use HasFactory;

    protected $table = 'food_contra_bon_items';

    protected $fillable = [
        'contra_bon_id',
        'item_id',
        'po_item_id',
        'quantity',
        'unit_id',
        'price',
        'total',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
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
} 