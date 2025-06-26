<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailFoodInvoice extends Model
{
    protected $table = 'retail_food_invoices';
    protected $guarded = [];

    public function retailFood()
    {
        return $this->belongsTo(RetailFood::class);
    }
} 