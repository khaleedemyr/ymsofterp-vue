<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsPointRedemption extends Model
{
    protected $table = 'member_apps_point_redemptions';
    
    protected $fillable = [
        'member_id',
        'point_transaction_id',
        'redemption_type',
        'redemption_date',
        'point_amount',
        'cash_value',
        'product_id',
        'product_name',
        'product_price',
        'discount_voucher_type',
        'discount_voucher_points',
        'discount_voucher_code',
        'discount_voucher_expires_at',
        'discount_voucher_used_at',
        'reference_id',
        'status'
    ];

    protected $casts = [
        'redemption_date' => 'date',
        'point_amount' => 'integer',
        'cash_value' => 'decimal:2',
        'product_price' => 'decimal:2',
        'discount_voucher_points' => 'integer',
        'discount_voucher_expires_at' => 'date',
        'discount_voucher_used_at' => 'datetime',
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
        return $this->hasMany(MemberAppsPointRedemptionDetail::class, 'redemption_id');
    }
}

