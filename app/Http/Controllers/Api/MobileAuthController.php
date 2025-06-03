<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $user = User::where('email', $request->email)
            ->where('status', 'A')
            ->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau password salah'], 401);
        }
        // Pastikan remember_token terisi
        if (!$user->remember_token) {
            $user->remember_token = bin2hex(random_bytes(32));
            $user->save();
        }
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->nama_lengkap,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
            ],
            'token' => $user->remember_token
        ]);
    }
} 