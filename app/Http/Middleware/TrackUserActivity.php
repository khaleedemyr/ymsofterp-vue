<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track for authenticated users and non-API monitoring routes
        if (Auth::check() && !$request->is('api/monitoring/*')) {
            try {
                $user = Auth::user();
                $route = $request->route() ? $request->route()->getName() : $request->path();
                $method = $request->method();
                $path = $request->path();
                
                // Store in session for quick access
                session([
                    'last_route' => $route,
                    'last_path' => $path,
                    'last_method' => $method,
                    'last_route_time' => now()->toDateTimeString()
                ]);

                // Optionally log to activity_logs table if it exists
                // Uncomment if you want to log every request
                /*
                if (DB::getSchemaBuilder()->hasTable('activity_logs')) {
                    DB::table('activity_logs')->insert([
                        'log_name' => 'user_activity',
                        'description' => "Accessed {$method} {$path}",
                        'subject_type' => null,
                        'subject_id' => null,
                        'causer_type' => 'App\\Models\\User',
                        'causer_id' => $user->id,
                        'properties' => json_encode([
                            'route' => $route,
                            'path' => $path,
                            'method' => $method,
                            'url' => $request->fullUrl()
                        ]),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
                */
            } catch (\Exception $e) {
                // Silent fail - don't break the request
            }
        }

        return $response;
    }
}
