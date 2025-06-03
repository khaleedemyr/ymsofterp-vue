<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutletFoodGoodReceive extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'outlet_id',
        'delivery_order_id',
        'receive_date',
        'notes',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'receive_date' => 'date'
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function items()
    {
        return $this->hasMany(OutletFoodGoodReceiveItem::class);
    }

    public function scans()
    {
        return $this->hasMany(OutletFoodGoodReceiveScan::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
} 