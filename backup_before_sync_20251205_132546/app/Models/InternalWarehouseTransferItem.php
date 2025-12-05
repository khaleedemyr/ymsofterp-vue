<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalWarehouseTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_warehouse_transfer_id',
        'item_id',
        'unit_id',
        'qty_small',
        'qty_medium',
        'qty_large',
        'cost_small',
        'cost_medium',
        'cost_large',
        'total_cost',
    ];

    protected $casts = [
        'qty_small' => 'decimal:2',
        'qty_medium' => 'decimal:2',
        'qty_large' => 'decimal:2',
        'cost_small' => 'decimal:4',
        'cost_medium' => 'decimal:4',
        'cost_large' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    public function transfer()
    {
        return $this->belongsTo(InternalWarehouseTransfer::class, 'internal_warehouse_transfer_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
