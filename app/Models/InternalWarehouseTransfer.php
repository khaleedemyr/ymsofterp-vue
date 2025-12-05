<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalWarehouseTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'transfer_date',
        'outlet_id',
        'warehouse_outlet_from_id',
        'warehouse_outlet_to_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(InternalWarehouseTransferItem::class, 'internal_warehouse_transfer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function warehouseOutletFrom()
    {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_from_id');
    }

    public function warehouseOutletTo()
    {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_to_id');
    }
}
