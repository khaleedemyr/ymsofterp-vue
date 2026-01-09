<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodPayment extends Model
{
    protected $fillable = [
        'number', 'date', 'supplier_id', 'total', 'payment_type', 'bank_id', 'notes', 'bukti_transfer_path', 'status',
        'finance_manager_approved_at', 'finance_manager_approved_by', 'finance_manager_note',
        'gm_finance_approved_at', 'gm_finance_approved_by', 'gm_finance_note',
        'created_by'
    ];

    protected $casts = [
        'finance_manager_approved_at' => 'datetime',
        'gm_finance_approved_at' => 'datetime',
    ];

    public function contraBons() {
        return $this->belongsToMany(\App\Models\ContraBon::class, 'food_payment_contra_bons', 'food_payment_id', 'contra_bon_id');
    }
    public function supplier() {
        return $this->belongsTo(\App\Models\Supplier::class);
    }
    public function creator() {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
    public function financeManager() {
        return $this->belongsTo(\App\Models\User::class, 'finance_manager_approved_by');
    }
    public function gmFinance() {
        return $this->belongsTo(\App\Models\User::class, 'gm_finance_approved_by');
    }
    
    public function bank() {
        return $this->belongsTo(\App\Models\BankAccount::class, 'bank_id');
    }

    // Methods
    public function canBePaid()
    {
        return $this->status === 'approved';
    }
} 