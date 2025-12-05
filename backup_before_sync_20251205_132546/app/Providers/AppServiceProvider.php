<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Notification;
use App\Observers\NotificationObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Register Notification Observer for FCM push notifications
        // DISABLED: Notification service temporarily disabled
        // Notification::observe(NotificationObserver::class);

        Inertia::share([
            'auth' => [
                'user' => function () {
                    $user = Auth::user();
                    return $user ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'id_role' => $user->id_role,
                        'id_jabatan' => $user->id_jabatan,
                        'status' => $user->status,
                        'division_id' => $user->division_id,
                        // field lain yang dibutuhkan
                    ] : null;
                },
            ],
        ]);
    }
}
