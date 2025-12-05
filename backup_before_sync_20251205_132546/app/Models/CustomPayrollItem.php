<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomPayrollItem extends Model
{
    protected $table = 'custom_payroll_items';
    
    protected $fillable = [
        'user_id',
        'outlet_id',
        'payroll_period_month',
        'payroll_period_year',
        'item_type',
        'item_name',
        'item_amount',
        'item_description'
    ];

    protected $casts = [
        'item_amount' => 'decimal:2',
        'payroll_period_month' => 'integer',
        'payroll_period_year' => 'integer',
    ];

    // Relationship dengan User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relationship dengan Outlet
    public function outlet()
    {
        return $this->belongsTo(DB::table('tbl_data_outlet'), 'outlet_id', 'id_outlet');
    }

    // Scope untuk filter berdasarkan periode
    public function scopeForPeriod($query, $month, $year)
    {
        return $query->where('payroll_period_month', $month)
                    ->where('payroll_period_year', $year);
    }

    // Scope untuk filter berdasarkan outlet
    public function scopeForOutlet($query, $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }

    // Scope untuk filter berdasarkan user
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Scope untuk earnings
    public function scopeEarnings($query)
    {
        return $query->where('item_type', 'earn');
    }

    // Scope untuk deductions
    public function scopeDeductions($query)
    {
        return $query->where('item_type', 'deduction');
    }
}
