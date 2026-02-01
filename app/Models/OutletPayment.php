<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutletPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number',
        'outlet_id',
        'warehouse_id',
        'gr_id',
        'retail_sales_id',
        'date',
        'total_amount',
        'status',
        'notes',
        'payment_method',
        'bank_id',
        'receiver_bank_id',
        'coa_id',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2'
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function goodReceive()
    {
        return $this->belongsTo(OutletFoodGoodReceive::class, 'gr_id');
    }

    public function retailSales()
    {
        return $this->belongsTo(\App\Models\RetailWarehouseSale::class, 'retail_sales_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items()
    {
        return $this->goodReceive->items;
    }

    public function bank()
    {
        return $this->belongsTo(\App\Models\BankAccount::class, 'bank_id');
    }

    public function receiverBank()
    {
        return $this->belongsTo(\App\Models\BankAccount::class, 'receiver_bank_id');
    }

    public function coa()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'coa_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'warehouse_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            \Log::info('DEBUG: OutletPayment creating', [
                'auth_id' => auth()->id(),
                'data' => $model->toArray()
            ]);
            $today = date('Ymd');
            $prefix = 'OPY-' . $today . '-';
            
            // Cari nomor terakhir hari ini
            $lastNumber = static::where('payment_number', 'like', $prefix . '%')
                ->orderBy('payment_number', 'desc')
                ->first();
                
            if ($lastNumber) {
                $sequence = (int) substr($lastNumber->payment_number, -4) + 1;
            } else {
                $sequence = 1;
            }
            
            $model->payment_number = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            $model->created_by = auth()->id();
            $model->updated_by = auth()->id();
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->id();
        });
    }
} 