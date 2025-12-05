<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApprovalAppAuth
{
    /**
     * Handle an incoming request.
     * Authenticate using Bearer token (remember_token)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak ditemukan',
            ], 401);
        }

        $user = User::where('remember_token', $token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid',
            ], 401);
        }

        if ($user->status !== 'A') {
            return response()->json([
                'success' => false,
                'message' => 'User tidak aktif',
            ], 401);
        }

        // Authenticate the user using Auth facade
        // This makes auth()->user() work in controllers
        Auth::login($user);

        // Attach user to request
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Add user to request attributes for easy access
        $request->attributes->set('user', $user);

        return $next($request);
    }
}

