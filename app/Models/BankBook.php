<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankBook extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'transaction_date',
        'transaction_type',
        'amount',
        'description',
        'reference_type',
        'reference_id',
        'balance',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /**
     * Get the bank account that owns the bank book entry
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * Get the user who created this entry
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated this entry
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the reference model based on reference_type
     */
    public function getReferenceAttribute()
    {
        if (!$this->reference_type || !$this->reference_id) {
            return null;
        }

        return match($this->reference_type) {
            'outlet_payment' => OutletPayment::find($this->reference_id),
            'food_payment' => FoodPayment::find($this->reference_id),
            'non_food_payment' => NonFoodPayment::find($this->reference_id),
            default => null,
        };
    }

    /**
     * Boot method to auto-set created_by and updated_by
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    /**
     * Calculate and update balance for all entries after this one
     */
    public static function recalculateBalance($bankAccountId, $fromDate = null)
    {
        $query = static::where('bank_account_id', $bankAccountId)
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($fromDate) {
            $query->where('transaction_date', '>=', $fromDate);
        }

        $entries = $query->get();
        $balance = 0;

        // Get starting balance (balance from last entry before fromDate)
        if ($fromDate) {
            $lastEntry = static::where('bank_account_id', $bankAccountId)
                ->where('transaction_date', '<', $fromDate)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            
            if ($lastEntry) {
                $balance = $lastEntry->balance;
            }
        }

        foreach ($entries as $entry) {
            if ($entry->transaction_type === 'credit') {
                $balance += $entry->amount;
            } else {
                $balance -= $entry->amount;
            }
            
            $entry->balance = $balance;
            $entry->saveQuietly(); // Use saveQuietly to avoid triggering events
        }
    }
}
