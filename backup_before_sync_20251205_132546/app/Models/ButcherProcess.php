<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ButcherProcess extends Model
{
    protected $guarded = [];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function goodReceive()
    {
        return $this->belongsTo(FoodGoodReceive::class, 'good_receive_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(ButcherProcessItem::class);
    }

    public function certificates()
    {
        return $this->hasMany(ButcherHalalCertificate::class);
    }
} 