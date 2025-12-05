<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsPointEarning extends Model
{
    protected $table = 'member_apps_point_earnings';
    
    protected $fillable = [
        'member_id',
        'point_transaction_id',
        'point_amount',
        'remaining_points',
        'earned_at',
        'expires_at',
        'is_expired',
        'expired_at',
        'is_fully_redeemed'
    ];

    protected $casts = [
        'point_amount' => 'integer',
        'remaining_points' => 'integer',
        'earned_at' => 'date',
        'expires_at' => 'date',
        'is_expired' => 'boolean',
        'expired_at' => 'datetime',
        'is_fully_redeemed' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_id');
    }

    public function transaction()
    {
        return $this->belongsTo(MemberAppsPointTransaction::class, 'point_transaction_id');
    }

    public function redemptionDetails()
    {
        return $this->hasMany(MemberAppsPointRedemptionDetail::class, 'point_earning_id');
    }
}

