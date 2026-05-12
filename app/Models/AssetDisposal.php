<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetDisposal extends Model
{
    protected $table = 'asset_disposals';
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'total_sale_price' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(AssetDisposalItem::class, 'disposal_id');
    }

    public function photos()
    {
        return $this->hasMany(AssetDisposalPhoto::class, 'disposal_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
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
        return $this->hasMany(AssetDisposalApprovalFlow::class, 'disposal_id');
    }

    public static function generateNumber()
    {
        $prefix = 'ADP' . date('Ymd');
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
