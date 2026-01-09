<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    protected $table = 'bank_accounts';
    
    protected $fillable = [
        'bank_name',
        'account_number',
        'account_name',
        'outlet_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'outlet_id' => 'integer',
    ];

    /**
     * Get the outlet that owns the bank account
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(DataOutlet::class, 'outlet_id', 'id_outlet');
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    /**
     * Get the payment types that use this bank account
     */
    public function paymentTypes()
    {
        return $this->belongsToMany(PaymentType::class, 'bank_account_payment_type', 'bank_account_id', 'payment_type_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }
}

