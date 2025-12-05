<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_bank',
        'bank_name',
        'bank_account',
        'bank_account_name',
        'description',
        'status'
    ];

    protected $casts = [
        'is_bank' => 'boolean'
    ];

    public function outlets()
    {
        return $this->belongsToMany(Outlet::class, 'payment_type_outlets', 'payment_type_id', 'outlet_id', 'id', 'id_outlet');
    }

    public function regions()
    {
        return $this->belongsToMany(Region::class, 'payment_type_regions');
    }
} 