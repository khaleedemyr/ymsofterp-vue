<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\DB;

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
        $userId = auth()->id();
        $allowedMenus = [];
        
        if ($userId) {
            $allowedMenus = DB::table('users as u')
                ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
                ->join('erp_role as r', 'ur.role_id', '=', 'r.id')
                ->join('erp_role_permission as rp', 'rp.role_id', '=', 'r.id')
                ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
                ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
                ->where('u.id', $userId)
                ->where('p.action', 'view')
                ->distinct()
                ->pluck('m.code')
                ->toArray();
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'email' => $request->user()->email,
                    'id_outlet' => $request->user()->id_outlet,
                    'id_role' => $request->user()->id_role,
                    'status' => $request->user()->status,
                    'division_id' => $request->user()->division_id,
                    'nama_lengkap' => $request->user()->nama_lengkap ?? $request->user()->name,
                    'avatar' => $request->user()->avatar ?? null,
                    'jabatan' => optional($request->user()->jabatan)->nama_jabatan,
                    'divisi' => optional($request->user()->divisi)->nama_divisi,
                    'region' => optional($request->user()->region)->name,
                    'outlet' => optional($request->user()->outlet)->nama_outlet,
                    'signature_path' => $request->user()->signature_path,
                ] : null,
            ],
            'result' => fn () => $request->session()->get('result'),
            'flash' => [
                'message' => fn () => $request->session()->get('message')
            ],
            'allowedMenus' => $allowedMenus,
        ]);
    }
}
