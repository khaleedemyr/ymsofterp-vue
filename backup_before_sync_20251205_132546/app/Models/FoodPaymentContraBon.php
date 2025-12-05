<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodPaymentContraBon extends Model
{
    protected $fillable = [
        'food_payment_id', 'contra_bon_id'
    ];

    public function foodPayment() {
        return $this->belongsTo(FoodPayment::class);
    }
    public function contraBon() {
        return $this->belongsTo(ContraBon::class);
    }
} 