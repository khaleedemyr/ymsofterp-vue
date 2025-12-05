<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitionItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_requisition_items';

    protected $fillable = [
        'purchase_requisition_id',
        'outlet_id',
        'category_id',
        'item_type', // For travel_application: transport, allowance, others
        'item_name',
        'qty',
        'unit',
        'unit_price',
        'subtotal',
        'allowance_recipient_name', // For allowance type
        'allowance_account_number', // For allowance type
        'others_notes', // For others type
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Relationships
    public function purchaseRequisition()
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function category()
    {
        return $this->belongsTo(PurchaseRequisitionCategory::class, 'category_id');
    }

    // Accessors
    public function getFormattedSubtotalAttribute()
    {
        return 'Rp. ' . number_format($this->subtotal, 0, ',', '.');
    }

    public function getFormattedUnitPriceAttribute()
    {
        return 'Rp. ' . number_format($this->unit_price, 0, ',', '.');
    }
}
