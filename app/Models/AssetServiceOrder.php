<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetServiceOrder extends Model
{
    protected $table = 'asset_service_orders';
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'sent_date' => 'date',
        'return_date' => 'date',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(AssetServiceOrderItem::class, 'service_order_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function warehouseOutlet()
    {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvalFlows()
    {
        return $this->hasMany(AssetServiceOrderApprovalFlow::class, 'service_order_id');
    }

    public static function generateNumber()
    {
        $prefix = 'ASV' . date('Ymd');
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
