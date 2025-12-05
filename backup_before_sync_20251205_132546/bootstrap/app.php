<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Exclude API routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Register Approval App middleware alias
        $middleware->alias([
            'approval.app.auth' => \App\Http\Middleware\ApprovalAppAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Log all exceptions with detailed information
        $exceptions->report(function (\Throwable $e) {
            try {
                $request = request();
                \Log::error('Exception Caught', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                    'trace' => $e->getTraceAsString(),
                    'url' => $request ? $request->fullUrl() : 'N/A',
                    'method' => $request ? $request->method() : 'N/A',
                    'ip' => $request ? $request->ip() : 'N/A',
                    'user_id' => auth()->check() ? auth()->id() : 'guest',
                ]);
            } catch (\Exception $logError) {
                // Fallback if logging itself fails
                error_log('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        });
    })->create();
