<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsVoucherDistribution extends Model
{
    protected $table = 'member_apps_voucher_distributions';
    
    protected $fillable = [
        'voucher_id',
        'distribution_type',
        'member_ids',
        'filter_criteria',
        'total_distributed',
        'total_used',
        'distributed_at',
        'created_by'
    ];

    protected $casts = [
        'member_ids' => 'array',
        'filter_criteria' => 'array',
        'total_distributed' => 'integer',
        'total_used' => 'integer',
        'distributed_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(MemberAppsVoucher::class, 'voucher_id');
    }

    public function memberVouchers()
    {
        return $this->hasMany(MemberAppsMemberVoucher::class, 'voucher_distribution_id');
    }
}

