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

    public function bankAccounts()
    {
        return $this->belongsToMany(BankAccount::class, 'bank_account_payment_type', 'payment_type_id', 'bank_account_id')
            ->withPivot('outlet_id')
            ->withTimestamps();
    }

    /**
     * Get bank accounts for a specific outlet
     * If outlet_id is null, returns Head Office banks (outlet_id = null in pivot)
     */
    public function getBankAccountsForOutlet($outletId = null)
    {
        $query = $this->bankAccounts();
        
        if ($outletId === null) {
            // Get Head Office banks (outlet_id = null in pivot)
            $query->wherePivot('outlet_id', null);
        } else {
            // Get banks for specific outlet or Head Office banks
            $query->where(function($q) use ($outletId) {
                $q->wherePivot('outlet_id', $outletId)
                  ->orWherePivot('outlet_id', null);
            });
        }
        
        return $query->get();
    }
} 