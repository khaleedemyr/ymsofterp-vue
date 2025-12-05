<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodReceiveOutlet extends Model
{
    public function outletPayment()
    {
        return $this->hasOne(OutletPayment::class, 'gr_id');
    }
} 