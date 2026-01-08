<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        Notification::observe(NotificationObserver::class);

        // Log slow queries untuk identify performance issues
        // Hanya log jika query > 100ms atau jika LOG_SLOW_QUERIES=true di .env
        if (env('LOG_SLOW_QUERIES', false) || config('app.debug')) {
            DB::listen(function ($query) {
                $slowQueryThreshold = env('SLOW_QUERY_THRESHOLD', 100); // Default: 100ms
                
                if ($query->time > $slowQueryThreshold) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time . 'ms',
                        'connection' => $query->connectionName,
                    ]);
                }
            });
        }

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
