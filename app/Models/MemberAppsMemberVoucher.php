<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsMemberVoucher extends Model
{
    protected $table = 'member_apps_member_vouchers';
    
    protected $fillable = [
        'voucher_id',
        'voucher_distribution_id',
        'member_id',
        'voucher_code',
        'serial_code',
        'status',
        'used_at',
        'used_in_transaction_id',
        'used_in_outlet_id',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'date',
        'used_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(MemberAppsVoucher::class, 'voucher_id');
    }

    public function distribution()
    {
        return $this->belongsTo(MemberAppsVoucherDistribution::class, 'voucher_distribution_id');
    }

    public function member()
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_id');
    }
}

