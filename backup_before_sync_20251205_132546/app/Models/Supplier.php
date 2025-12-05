<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'city',
        'province',
        'postal_code',
        'npwp',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'payment_term',
        'payment_days',
        'status'
    ];

    protected $casts = [
        'payment_days' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function purchaseOrders()
    {
        return $this->hasMany(MaintenancePurchaseOrder::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(MaintenancePurchaseOrderItem::class);
    }
} 