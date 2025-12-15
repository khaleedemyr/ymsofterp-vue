<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        \App\Events\PointEarned::class => [
            \App\Listeners\SendPointEarnedNotification::class,
        ],
        \App\Events\PointReturned::class => [
            \App\Listeners\SendPointReturnedNotification::class,
        ],
        \App\Events\ChallengeCompleted::class => [
            \App\Listeners\SendChallengeCompletedNotification::class,
        ],
        \App\Events\ChallengeRolledBack::class => [
            \App\Listeners\SendChallengeRolledBackNotification::class,
        ],
        \App\Events\ChallengeCreated::class => [
            \App\Listeners\SendChallengeCreatedNotification::class,
        ],
        \App\Events\MemberTierUpgraded::class => [
            \App\Listeners\SendTierUpgradedNotification::class,
        ],
        \App\Events\VoucherReceived::class => [
            \App\Listeners\SendVoucherReceivedNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
} 