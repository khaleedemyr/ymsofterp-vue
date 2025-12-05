<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenancePurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_id',
        'supplier_id',
        'item_name',
        'description',
        'specifications',
        'quantity',
        'unit_id',
        'price',
        'supplier_price',
        'subtotal'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'supplier_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function purchaseOrder()
    {
        return $this->belongsTo(MaintenancePurchaseOrder::class, 'po_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
} 