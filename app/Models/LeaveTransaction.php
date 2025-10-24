<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveTransaction extends Model
{
    use HasFactory;

    protected $table = 'leave_transactions';

    protected $fillable = [
        'user_id',
        'transaction_type',
        'year',
        'month',
        'amount',
        'balance_after',
        'description',
        'created_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'year' => 'integer',
        'month' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeMonthlyCredit($query)
    {
        return $query->where('transaction_type', 'monthly_credit');
    }

    public function scopeBurning($query)
    {
        return $query->where('transaction_type', 'burning');
    }

    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    public function scopeByMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    // Accessors
    public function getTransactionTypeTextAttribute()
    {
        $types = [
            'monthly_credit' => 'Kredit Bulanan',
            'burning' => 'Burning Cuti',
            'manual_adjustment' => 'Penyesuaian Manual',
            'leave_usage' => 'Penggunaan Cuti',
            'initial_balance' => 'Saldo Awal'
        ];

        return $types[$this->transaction_type] ?? 'Unknown';
    }

    public function getFormattedAmountAttribute()
    {
        return $this->amount > 0 ? "+{$this->amount}" : "{$this->amount}";
    }
}
