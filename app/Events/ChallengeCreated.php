<?php

namespace App\Events;

use App\Models\MemberAppsChallenge;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChallengeCreated
{
    use Dispatchable, SerializesModels;

    public $challenge;

    /**
     * Create a new event instance.
     *
     * @param MemberAppsChallenge $challenge
     */
    public function __construct(MemberAppsChallenge $challenge)
    {
        $this->challenge = $challenge;
    }
}
