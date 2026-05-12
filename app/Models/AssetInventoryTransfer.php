<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetInventoryTransfer extends Model
{
    protected $table = 'asset_inventory_transfers';
    protected $guarded = [];

    protected $casts = [
        'transfer_date' => 'date',
        'approval_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(AssetInventoryTransferItem::class, 'asset_inventory_transfer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approval_by');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function warehouseOutletFrom()
    {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_from_id');
    }

    public function warehouseOutletTo()
    {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_to_id');
    }

    public function approvalFlows()
    {
        return $this->hasMany(AssetInventoryTransferApprovalFlow::class, 'asset_inventory_transfer_id');
    }

    public static function generateNumber()
    {
        $prefix = 'AIT-' . date('Ymd') . '-';
        $last = self::where('transfer_number', 'like', $prefix . '%')
            ->orderByDesc('transfer_number')
            ->first();

        if ($last) {
            $lastNum = (int) substr($last->transfer_number, strlen($prefix));
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
}
