<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutletFoodGoodReceiveScan extends Model
{
    use SoftDeletes;
    protected $table = 'outlet_food_good_receive_scans';
    protected $guarded = [];
    public $timestamps = true;
} 