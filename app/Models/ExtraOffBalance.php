<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtraOffBalance extends Model
{
    use HasFactory;

    protected $table = 'extra_off_balance';

    protected $fillable = [
        'user_id',
        'balance'
    ];

    protected $casts = [
        'balance' => 'integer'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(ExtraOffTransaction::class, 'user_id', 'user_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereHas('user', function ($q) {
            $q->where('status', 'A');
        });
    }

    // Accessors
    public function getBalanceTextAttribute()
    {
        return $this->balance . ' hari';
    }

    // Methods
    public function addBalance($amount, $description = null)
    {
        $this->increment('balance', $amount);
        
        // Create transaction record
        ExtraOffTransaction::create([
            'user_id' => $this->user_id,
            'transaction_type' => 'earned',
            'amount' => $amount,
            'source_type' => 'manual_adjustment',
            'description' => $description ?? 'Manual adjustment',
            'status' => 'approved'
        ]);

        return $this;
    }

    public function useBalance($amount, $useDate, $description = null)
    {
        if ($this->balance < $amount) {
            throw new \Exception('Insufficient extra off balance');
        }

        $this->decrement('balance', $amount);
        
        // Create transaction record
        ExtraOffTransaction::create([
            'user_id' => $this->user_id,
            'transaction_type' => 'used',
            'amount' => -$amount,
            'source_type' => 'manual_adjustment',
            'used_date' => $useDate,
            'description' => $description ?? 'Extra off used',
            'status' => 'approved'
        ]);

        return $this;
    }

    public function hasBalance($amount = 1)
    {
        return $this->balance >= $amount;
    }
}
