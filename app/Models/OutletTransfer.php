<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutletTransfer extends Model
{
    protected $table = 'outlet_transfers';
    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(OutletTransferItem::class, 'outlet_transfer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouseOutletFrom()
    {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_from_id');
    }

    public function warehouseOutletTo()
    {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_to_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approval_by');
    }

    public function approvalFlows()
    {
        return $this->hasMany(OutletTransferApprovalFlow::class, 'outlet_transfer_id');
    }
}
