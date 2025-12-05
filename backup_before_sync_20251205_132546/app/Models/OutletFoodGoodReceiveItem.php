<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutletFoodGoodReceiveItem extends Model
{
    use SoftDeletes;
    protected $table = 'outlet_food_good_receive_items';
    protected $guarded = [];
    public $timestamps = true;
} 