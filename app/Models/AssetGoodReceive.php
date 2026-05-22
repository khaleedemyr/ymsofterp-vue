<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetGoodReceive extends Model
{
    protected $table = 'asset_good_receives';

    protected $fillable = [
        'gr_number', 'po_id', 'owner_outlet_id', 'outlet_id', 'warehouse_outlet_id',
        'receive_date', 'received_by', 'status', 'notes',
    ];

    protected $casts = [
        'receive_date' => 'date',
    ];

    public function items() { return $this->hasMany(AssetGoodReceiveItem::class); }
    public function po() { return $this->belongsTo(PurchaseOrderOps::class, 'po_id'); }
    public function ownerOutlet() { return $this->belongsTo(Outlet::class, 'owner_outlet_id', 'id_outlet'); }
    public function outlet() { return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet'); }
    public function warehouseOutlet() { return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_id'); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }

    public static function generateNumber()
    {
        $prefix = 'AGR';
        $date = date('Ymd');
        $last = self::where('gr_number', 'like', $prefix . $date . '%')
            ->orderBy('gr_number', 'desc')
            ->value('gr_number');
        $seq = $last ? intval(substr($last, -4)) + 1 : 1;
        return $prefix . $date . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
