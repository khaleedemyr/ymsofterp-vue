<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';
    protected $guarded = [];
    public $timestamps = true;

    public function retailWarehouseSales()
    {
        return $this->hasMany(RetailWarehouseSale::class);
    }
} 