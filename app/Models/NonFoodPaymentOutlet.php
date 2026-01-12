<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NonFoodPaymentOutlet extends Model
{
    use HasFactory;

    protected $table = 'non_food_payment_outlets';

    protected $fillable = [
        'non_food_payment_id',
        'outlet_id',
        'category_id',
        'amount',
        'bank_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // Relationships
    public function nonFoodPayment()
    {
        return $this->belongsTo(NonFoodPayment::class);
    }

    public function outlet()
    {
        return $this->belongsTo(\App\Models\Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function category()
    {
        return $this->belongsTo(PurchaseRequisitionCategory::class, 'category_id');
    }

    public function bank()
    {
        return $this->belongsTo(\App\Models\BankAccount::class, 'bank_id');
    }
}
