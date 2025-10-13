<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtraOffTransaction extends Model
{
    use HasFactory;

    protected $table = 'extra_off_transactions';

    protected $fillable = [
        'user_id',
        'transaction_type',
        'amount',
        'source_type',
        'source_date',
        'description',
        'used_date',
        'approved_by',
        'status'
    ];

    protected $casts = [
        'amount' => 'integer',
        'source_date' => 'date',
        'used_date' => 'date'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeEarned($query)
    {
        return $query->where('transaction_type', 'earned');
    }

    public function scopeUsed($query)
    {
        return $query->where('transaction_type', 'used');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnscheduledWork($query)
    {
        return $query->where('source_type', 'unscheduled_work');
    }

    public function scopeManualAdjustment($query)
    {
        return $query->where('source_type', 'manual_adjustment');
    }

    public function scopeHolidayWork($query)
    {
        return $query->where('source_type', 'holiday_work');
    }

    // Accessors
    public function getTransactionTypeTextAttribute()
    {
        return match($this->transaction_type) {
            'earned' => 'Dapat',
            'used' => 'Gunakan',
            default => 'Unknown'
        };
    }

    public function getSourceTypeTextAttribute()
    {
        return match($this->source_type) {
            'unscheduled_work' => 'Kerja Tanpa Shift (>8 jam)',
            'overtime_work' => 'Lembur Kerja Tanpa Shift (≤8 jam)',
            'manual_adjustment' => 'Penyesuaian Manual',
            'holiday_work' => 'Kerja Hari Libur',
            default => 'Unknown'
        };
    }

    public function getStatusTextAttribute()
    {
        return match($this->status) {
            'pending' => 'Pending',
            'approved' => 'Approved',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }

    public function getAmountTextAttribute()
    {
        $sign = $this->amount > 0 ? '+' : '';
        return $sign . $this->amount . ' hari';
    }

    // Methods
    public function approve($approvedBy = null)
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy ?? auth()->id()
        ]);

        // Update user balance
        $balance = ExtraOffBalance::where('user_id', $this->user_id)->first();
        if ($balance) {
            if ($this->transaction_type === 'earned') {
                $balance->increment('balance', $this->amount);
            } else {
                $balance->decrement('balance', abs($this->amount));
            }
        }

        return $this;
    }

    public function cancel()
    {
        $this->update(['status' => 'cancelled']);
        return $this;
    }
}
