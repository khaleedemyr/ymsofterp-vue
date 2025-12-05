<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Outlet;

class MemberAppsVoucher extends Model
{
    protected $table = 'member_apps_vouchers';
    
    protected $fillable = [
        'name',
        'description',
        'voucher_type',
        'discount_percentage',
        'discount_amount',
        'min_purchase',
        'max_discount',
        'free_item_id',
        'free_item_name',
        'free_item_ids',
        'free_item_selection',
        'cashback_amount',
        'cashback_percentage',
        'valid_from',
        'valid_until',
        'usage_limit',
        'total_quantity',
        'applicable_channels',
        'applicable_days',
        'applicable_time_start',
        'applicable_time_end',
        'exclude_items',
        'exclude_categories',
        'image',
        'is_active',
        'is_for_sale',
        'points_required',
        'one_time_purchase',
        'is_birthday_voucher',
        'created_by'
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'cashback_amount' => 'decimal:2',
        'cashback_percentage' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'usage_limit' => 'integer',
        'total_quantity' => 'integer',
        'applicable_channels' => 'array',
        'applicable_days' => 'array',
        'applicable_time_start' => 'datetime',
        'applicable_time_end' => 'datetime',
        'exclude_items' => 'array',
        'exclude_categories' => 'array',
        'is_active' => 'boolean',
        'is_for_sale' => 'boolean',
        'points_required' => 'integer',
        'one_time_purchase' => 'boolean',
        'is_birthday_voucher' => 'boolean',
    ];

    public function distributions()
    {
        return $this->hasMany(MemberAppsVoucherDistribution::class, 'voucher_id');
    }

    public function memberVouchers()
    {
        return $this->hasMany(MemberAppsMemberVoucher::class, 'voucher_id');
    }

    public function outlets()
    {
        return $this->belongsToMany(Outlet::class, 'member_apps_voucher_outlets', 'voucher_id', 'outlet_id', 'id', 'id_outlet');
    }
}

