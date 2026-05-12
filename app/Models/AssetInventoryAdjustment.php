<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetInventoryAdjustment extends Model
{
    protected $table = 'asset_inventory_adjustments';
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(AssetInventoryAdjustmentItem::class, 'adjustment_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function warehouseOutlet()
    {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvalFlows()
    {
        return $this->hasMany(AssetInventoryAdjustmentApprovalFlow::class, 'adjustment_id');
    }

    public static function generateNumber()
    {
        $prefix = 'ASA' . date('Ymd');
        $last = self::where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->first();

        if ($last) {
            $lastNum = (int) substr($last->number, strlen($prefix));
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
}
