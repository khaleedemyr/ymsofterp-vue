<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetailFood extends Model
{
    use SoftDeletes;

    protected $table = 'retail_food';
    protected $guarded = [];
    protected $fillable = [
        'retail_number',
        'outlet_id',
        'warehouse_outlet_id',
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
        return $this->hasMany(RetailFoodItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(RetailFoodInvoice::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public static function getDailyTotal($date)
    {
        return self::whereDate('transaction_date', $date)
            ->where('status', 'approved')
            ->sum('total_amount');
    }
}
