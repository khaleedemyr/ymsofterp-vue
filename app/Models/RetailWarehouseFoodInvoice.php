<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailWarehouseFoodInvoice extends Model
{
    protected $table = 'retail_warehouse_food_invoices';
    protected $guarded = [];

    public function retailWarehouseFood()
    {
        return $this->belongsTo(RetailWarehouseFood::class);
    }
}

