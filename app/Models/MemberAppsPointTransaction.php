<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsPointTransaction extends Model
{
    protected $table = 'member_apps_point_transactions';
    
    protected $fillable = [
        'member_id',
        'transaction_type',
        'transaction_date',
        'point_amount',
        'transaction_amount',
        'earning_rate',
        'channel',
        'reference_id',
        'description',
        'expires_at',
        'is_expired',
        'expired_at'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'point_amount' => 'integer',
        'transaction_amount' => 'decimal:2',
        'earning_rate' => 'decimal:2',
        'expires_at' => 'date',
        'is_expired' => 'boolean',
        'expired_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_id');
    }

    public function earning()
    {
        return $this->hasOne(MemberAppsPointEarning::class, 'point_transaction_id');
    }

    public function redemption()
    {
        return $this->hasOne(MemberAppsPointRedemption::class, 'point_transaction_id');
    }
}

