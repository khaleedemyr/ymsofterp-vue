<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderFoodApprovalFlow extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_food_approval_flows';

    protected $fillable = [
        'purchase_order_food_id',
        'approver_id',
        'approval_level',
        'status',
        'approved_at',
        'rejected_at',
        'comments',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function purchaseOrderFood()
    {
        return $this->belongsTo(PurchaseOrderFood::class, 'purchase_order_food_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
