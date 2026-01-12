<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoodPaymentOutlet extends Model
{
    use HasFactory;

    protected $table = 'food_payment_outlets';

    protected $fillable = [
        'food_payment_id',
        'outlet_id',
        'amount',
        'bank_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // Relationships
    public function foodPayment()
    {
        return $this->belongsTo(FoodPayment::class);
    }

    public function outlet()
    {
        return $this->belongsTo(\App\Models\Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function bank()
    {
        return $this->belongsTo(\App\Models\BankAccount::class, 'bank_id');
    }
}
