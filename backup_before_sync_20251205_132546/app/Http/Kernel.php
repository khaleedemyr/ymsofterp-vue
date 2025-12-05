<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        \App\Http\Middleware\HandleCors::class,
        // ... existing code ...
    ];

    protected $middlewareGroups = [
        //'api' => [
            // ...jangan ada VerifyCsrfToken di sini!
        //    'throttle:api',
         //   \Illuminate\Routing\Middleware\SubstituteBindings::class,
        //],
    ];

    // ... existing code ...
} 