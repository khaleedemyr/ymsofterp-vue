<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetGoodReceiveItem extends Model
{
    protected $table = 'asset_good_receive_items';

    protected $fillable = [
        'asset_good_receive_id', 'po_item_id', 'item_id', 'unit_id',
        'qty_ordered', 'qty_received', 'price', 'total', 'notes',
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:2',
        'qty_received' => 'decimal:2',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function goodReceive() { return $this->belongsTo(AssetGoodReceive::class, 'asset_good_receive_id'); }
    public function poItem() { return $this->belongsTo(PurchaseOrderOpsItem::class, 'po_item_id'); }
    public function item() { return $this->belongsTo(Item::class, 'item_id'); }
    public function unit() { return $this->belongsTo(Unit::class, 'unit_id'); }
}
