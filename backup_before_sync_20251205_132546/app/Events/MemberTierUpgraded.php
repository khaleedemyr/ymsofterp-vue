<?php

namespace App\Events;

use App\Models\MemberAppsMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberTierUpgraded
{
    use Dispatchable, SerializesModels;

    public $member;
    public $oldTier;
    public $newTier;
    public $rollingSpending;

    /**
     * Create a new event instance.
     *
     * @param MemberAppsMember $member
     * @param string $oldTier
     * @param string $newTier
     * @param float $rollingSpending
     */
    public function __construct(
        MemberAppsMember $member,
        string $oldTier,
        string $newTier,
        float $rollingSpending
    ) {
        $this->member = $member;
        $this->oldTier = $oldTier;
        $this->newTier = $newTier;
        $this->rollingSpending = $rollingSpending;
    }
}

