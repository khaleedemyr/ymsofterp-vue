<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseSale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'source_warehouse_id',
        'target_warehouse_id',
        'date',
        'note',
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function sourceWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function targetWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function items()
    {
        return $this->hasMany(WarehouseSaleItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
} 