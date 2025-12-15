<?php

namespace App\Events;

use App\Models\MemberAppsMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChallengeRolledBack
{
    use Dispatchable, SerializesModels;

    public $member;
    public $rewardsRolledBack; // Array of rolled back rewards
    public $orderId;

    /**
     * Create a new event instance.
     *
     * @param MemberAppsMember $member
     * @param array $rewardsRolledBack Array of rolled back rewards with challenge info
     * @param string $orderId The voided order ID
     */
    public function __construct(
        MemberAppsMember $member,
        array $rewardsRolledBack,
        string $orderId
    ) {
        $this->member = $member;
        $this->rewardsRolledBack = $rewardsRolledBack;
        $this->orderId = $orderId;
    }
}
