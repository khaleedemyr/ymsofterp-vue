<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetailWarehouseFood extends Model
{
    use SoftDeletes;

    protected $table = 'retail_warehouse_food';
    protected $guarded = [];
    protected $fillable = [
        'retail_number',
        'warehouse_id',
        'warehouse_division_id',
        'created_by',
        'transaction_date',
        'total_amount',
        'notes',
        'payment_method',
        'supplier_id',
        'status'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'total_amount' => 'decimal:2'
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseDivision()
    {
        return $this->belongsTo(WarehouseDivision::class, 'warehouse_division_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(RetailWarehouseFoodItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(RetailWarehouseFoodInvoice::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public static function getDailyTotal($date, $warehouseId = null)
    {
        $query = self::whereDate('transaction_date', $date)
            ->where('status', 'approved');
        
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        
        return $query->sum('total_amount');
    }
}

