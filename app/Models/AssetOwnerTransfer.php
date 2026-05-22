<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetOwnerTransfer extends Model
{
    protected $table = 'asset_owner_transfers';
    protected $guarded = [];

    protected $casts = [
        'transfer_date' => 'date',
        'approval_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(AssetOwnerTransferItem::class, 'asset_owner_transfer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approval_by');
    }

    public function warehouseOutlet()
    {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_id');
    }

    public function approvalFlows()
    {
        return $this->hasMany(AssetOwnerTransferApprovalFlow::class, 'asset_owner_transfer_id');
    }

    public static function generateNumber(): string
    {
        $prefix = 'AOT-' . date('Ymd') . '-';
        $last = self::where('transfer_number', 'like', $prefix . '%')
            ->orderByDesc('transfer_number')
            ->first();

        $nextNum = $last ? ((int) substr($last->transfer_number, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }
}
