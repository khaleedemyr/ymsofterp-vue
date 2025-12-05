<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailNonFoodInvoice extends Model
{
    protected $table = 'retail_non_food_invoices';
    protected $guarded = [];

    public function retailNonFood()
    {
        return $this->belongsTo(RetailNonFood::class);
    }
} 