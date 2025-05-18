<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodFloorOrder extends Model
{
    protected $table = 'food_floor_orders';
    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(FoodFloorOrderItem::class, 'floor_order_id');
    }

    public function outlet()
    {
        return $this->belongsTo(\App\Models\Outlet::class, 'id_outlet', 'id_outlet');
    }

    public function requester()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    public function foSchedule()
    {
        return $this->belongsTo(\App\Models\FOSchedule::class, 'fo_schedule_id');
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approval_by', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
} 