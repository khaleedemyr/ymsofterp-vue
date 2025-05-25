<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderFood extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_foods';

    protected $fillable = [
        'number',
        'date',
        'supplier_id',
        'status',
        'created_by',
        'purchasing_manager_approved_at',
        'purchasing_manager_approved_by',
        'purchasing_manager_note',
        'gm_finance_approved_at',
        'gm_finance_approved_by',
        'gm_finance_note',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderFoodItem::class, 'purchase_order_food_id');
    }

    public function butcherPoItems()
    {
        return $this->hasMany(PurchaseOrderFoodItem::class, 'purchase_order_food_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchasing_manager()
    {
        return $this->belongsTo(User::class, 'purchasing_manager_approved_by');
    }

    public function gm_finance()
    {
        return $this->belongsTo(User::class, 'gm_finance_approved_by');
    }

    public function purchase_requests()
    {
        return $this->belongsToMany(
            \App\Models\PurchaseRequisitionFood::class,
            'purchase_order_food_purchase_request',
            'purchase_order_food_id',
            'purchase_requisition_food_id'
        );
    }

    public function getPrNumbersAttribute()
    {
        $prItemIds = $this->items()->pluck('pr_food_item_id')->toArray();
        $prIds = \App\Models\PurchaseRequisitionFoodItem::whereIn('id', $prItemIds)->pluck('pr_food_id')->unique()->toArray();
        return \App\Models\PurchaseRequisitionFood::whereIn('id', $prIds)->pluck('pr_number')->unique()->toArray();
    }
} 