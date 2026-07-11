<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodFloorOrderApprovalFlow extends Model
{
    protected $table = 'food_floor_order_approval_flows';

    protected $fillable = [
        'food_floor_order_id',
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

    public function floorOrder()
    {
        return $this->belongsTo(FoodFloorOrder::class, 'food_floor_order_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id', 'id');
    }
}
