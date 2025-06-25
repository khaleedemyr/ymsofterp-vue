<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailWarehouseSale extends Model
{
    protected $table = 'retail_warehouse_sales';
    protected $guarded = [];
    public $timestamps = true;

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseDivision()
    {
        return $this->belongsTo(WarehouseDivision::class);
    }

    public function items()
    {
        return $this->hasMany(RetailWarehouseSaleItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
} 