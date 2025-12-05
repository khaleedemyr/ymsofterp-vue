<?php

namespace App\Events;

use App\Models\MemberAppsMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChallengeCompleted
{
    use Dispatchable, SerializesModels;

    public $member;
    public $challengeId;
    public $challengeTitle;
    public $rewardType; // 'point', 'item', 'voucher'
    public $rewardData; // Additional data about the reward

    /**
     * Create a new event instance.
     *
     * @param MemberAppsMember $member
     * @param int $challengeId
     * @param string $challengeTitle
     * @param string $rewardType 'point', 'item', or 'voucher'
     * @param array $rewardData Additional data (points, item_name, voucher_name, etc.)
     */
    public function __construct(
        MemberAppsMember $member,
        int $challengeId,
        string $challengeTitle,
        string $rewardType,
        array $rewardData = []
    ) {
        $this->member = $member;
        $this->challengeId = $challengeId;
        $this->challengeTitle = $challengeTitle;
        $this->rewardType = $rewardType;
        $this->rewardData = $rewardData;
    }
}

