<?php

namespace App\Events;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsMemberVoucher;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoucherReceived
{
    use Dispatchable, SerializesModels;

    public $member;
    public $memberVoucher;
    public $voucher;

    /**
     * Create a new event instance.
     *
     * @param MemberAppsMember $member
     * @param MemberAppsMemberVoucher $memberVoucher
     */
    public function __construct(
        MemberAppsMember $member,
        MemberAppsMemberVoucher $memberVoucher
    ) {
        $this->member = $member;
        $this->memberVoucher = $memberVoucher;
        $this->voucher = $memberVoucher->voucher;
    }
}

