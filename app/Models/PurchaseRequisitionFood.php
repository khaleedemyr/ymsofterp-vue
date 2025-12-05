<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitionFood extends Model
{
    use HasFactory;

    protected $table = 'pr_foods';

    protected $fillable = [
        'pr_number',
        'tanggal',
        'status',
        'requested_by',
        'warehouse_id',
        'ssd_manager_approved_at',
        'ssd_manager_approved_by',
        'ssd_manager_note',
        'vice_coo_approved_at',
        'vice_coo_approved_by',
        'vice_coo_note',
        'description',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'ssd_manager_approved_at' => 'datetime',
        'vice_coo_approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseRequisitionFoodItem::class, 'pr_food_id');
    }

    public function purchaseOrderItems()
    {
        return $this->hasManyThrough(
            PurchaseOrderFoodItem::class,
            PurchaseRequisitionFoodItem::class,
            'pr_food_id', // Foreign key on pr_food_items table
            'purchase_requisition_item_id', // Foreign key on purchase_order_items table
            'id', // Local key on pr_foods table
            'id' // Local key on pr_food_items table
        );
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function ssdManager()
    {
        return $this->belongsTo(User::class, 'ssd_manager_approved_by');
    }

    public function viceCoo()
    {
        return $this->belongsTo(User::class, 'vice_coo_approved_by');
    }
} 