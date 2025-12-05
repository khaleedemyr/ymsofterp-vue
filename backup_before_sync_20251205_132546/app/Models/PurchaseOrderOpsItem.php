<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderOpsItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_ops_items';

    protected $fillable = [
        'purchase_order_ops_id',
        'item_name',
        'quantity',
        'unit',
        'price',
        'discount_percent',
        'discount_amount',
        'total',
        'created_by',
        'arrival_date',
        'pr_ops_item_id',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'arrival_date' => 'date',
    ];

    // Relationships
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrderOps::class, 'purchase_order_ops_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function prOpsItem()
    {
        return $this->belongsTo(PurchaseRequisitionItem::class, 'pr_ops_item_id');
    }

    public function sourcePr()
    {
        return $this->belongsTo(PurchaseRequisition::class, 'source_id');
    }

    // Accessors
    public function getFormattedPriceAttribute()
    {
        return 'Rp. ' . number_format($this->price, 0, ',', '.');
    }

    public function getFormattedTotalAttribute()
    {
        return 'Rp. ' . number_format($this->total, 0, ',', '.');
    }

    // Methods
    public function calculateTotal()
    {
        $subtotal = $this->quantity * $this->price;
        $discount = $this->discount_amount ?? 0;
        return round($subtotal - $discount, 2);
    }
}
