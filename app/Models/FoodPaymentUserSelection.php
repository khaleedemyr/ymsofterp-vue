<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodPaymentUserSelection extends Model
{
    protected $fillable = [
        'user_id',
        'food_payment_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function foodPayment()
    {
        return $this->belongsTo(FoodPayment::class);
    }
}
