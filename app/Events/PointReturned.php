<?php

namespace App\Events;

use App\Models\MemberAppsMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PointReturned
{
    use Dispatchable, SerializesModels;

    public $member;
    public $pointsReturned;
    public $pointsDeducted;
    public $source; // 'void_transaction'
    public $sourceDetails; // Additional info (order_id, order_nomor, etc.)

    /**
     * Create a new event instance.
     *
     * @param MemberAppsMember $member
     * @param int $pointsReturned Points returned from redemption
     * @param int $pointsDeducted Points deducted from earning
     * @param string $source 'void_transaction'
     * @param array $sourceDetails Additional details (order_id, order_nomor, etc.)
     */
    public function __construct(
        MemberAppsMember $member,
        int $pointsReturned = 0,
        int $pointsDeducted = 0,
        string $source = 'void_transaction',
        array $sourceDetails = []
    ) {
        $this->member = $member;
        $this->pointsReturned = $pointsReturned;
        $this->pointsDeducted = $pointsDeducted;
        $this->source = $source;
        $this->sourceDetails = $sourceDetails;
    }
}

