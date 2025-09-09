<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HolidayAttendanceCompensation extends Model
{
    use HasFactory;

    protected $table = 'holiday_attendance_compensations';

    protected $fillable = [
        'user_id',
        'holiday_date',
        'compensation_type',
        'compensation_amount',
        'compensation_description',
        'status',
        'used_date',
        'notes'
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'used_date' => 'date',
        'compensation_amount' => 'decimal:2'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function holiday()
    {
        return $this->belongsTo(KalenderPerusahaan::class, 'holiday_date', 'tgl_libur');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeUsed($query)
    {
        return $query->where('status', 'used');
    }

    public function scopeExtraOff($query)
    {
        return $query->where('compensation_type', 'extra_off');
    }

    public function scopeBonus($query)
    {
        return $query->where('compensation_type', 'bonus');
    }

    // Accessors
    public function getStatusTextAttribute()
    {
        return match($this->status) {
            'pending' => 'Pending',
            'approved' => 'Approved',
            'used' => 'Used',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }

    public function getCompensationTypeTextAttribute()
    {
        return match($this->compensation_type) {
            'extra_off' => 'Extra Off Day',
            'bonus' => 'Holiday Bonus',
            default => 'Unknown'
        };
    }

    public function getCanUseAttribute()
    {
        return $this->compensation_type === 'extra_off' && $this->status === 'pending';
    }
}
