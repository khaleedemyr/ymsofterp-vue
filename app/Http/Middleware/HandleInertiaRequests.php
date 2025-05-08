<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'id_outlet' => $request->user()->id_outlet,
                    'id_role' => $request->user()->id_role,
                    'status' => $request->user()->status,
                    'division_id' => $request->user()->division_id,
                    'nama_lengkap' => $request->user()->nama_lengkap ?? $request->user()->name,
                    'avatar' => $request->user()->avatar ?? null,
                    'jabatan' => optional($request->user()->jabatan)->nama_jabatan,
                    'divisi' => optional($request->user()->divisi)->nama_divisi,
                    'outlet' => optional($request->user()->outlet)->nama_outlet,
                    'signature_path' => $request->user()->signature_path,
                ] : null,
            ],
        ];
    }
}
