<?php

namespace App\Events;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PointEarned
{
    use Dispatchable, SerializesModels;

    public $member;
    public $pointTransaction;
    public $pointsEarned;
    public $source; // 'transaction' or 'challenge'
    public $sourceDetails; // Additional info about the source

    /**
     * Create a new event instance.
     *
     * @param MemberAppsMember $member
     * @param MemberAppsPointTransaction $pointTransaction
     * @param int $pointsEarned
     * @param string $source 'transaction' or 'challenge'
     * @param array $sourceDetails Additional details (e.g., order_id, challenge_title)
     */
    public function __construct(
        MemberAppsMember $member,
        MemberAppsPointTransaction $pointTransaction,
        int $pointsEarned,
        string $source = 'transaction',
        array $sourceDetails = []
    ) {
        $this->member = $member;
        $this->pointTransaction = $pointTransaction;
        $this->pointsEarned = $pointsEarned;
        $this->source = $source;
        $this->sourceDetails = $sourceDetails;
    }
}

