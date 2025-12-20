<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetailNonFood extends Model
{
    use SoftDeletes;

    protected $table = 'retail_non_food';
    protected $guarded = [];
    protected $fillable = [
        'retail_number',
        'outlet_id',
        'warehouse_outlet_id',
        'category_budget_id',
        'supplier_id',
        'payment_method',
        'created_by',
        'transaction_date',
        'total_amount',
        'notes',
        'status'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'total_amount' => 'decimal:2'
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(RetailNonFoodItem::class);
    }

    public function warehouseOutlet()
    {
        return $this->belongsTo(\App\Models\WarehouseOutlet::class, 'warehouse_outlet_id');
    }

    public function invoices()
    {
        return $this->hasMany(RetailNonFoodInvoice::class);
    }

    public function categoryBudget()
    {
        return $this->belongsTo(\App\Models\PurchaseRequisitionCategory::class, 'category_budget_id');
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class, 'supplier_id');
    }

    public static function getDailyTotal($date)
    {
        return self::whereDate('transaction_date', $date)
            ->where('status', 'approved')
            ->sum('total_amount');
    }
} 