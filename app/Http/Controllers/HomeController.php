<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function show(Request $request)
    {
        $user = Auth::user();
        return Inertia::render('Home', [
            'nama_lengkap' => $user->nama_lengkap ?? $user->name,
            'avatar' => $user->avatar ?? null,
        ]);
    }
} 