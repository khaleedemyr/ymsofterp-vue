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
                    'id_jabatan' => $request->user()->id_jabatan,
                    'status' => $request->user()->status,
                    'division_id' => $request->user()->division_id,
                    'nama_lengkap' => $request->user()->nama_lengkap ?? $request->user()->name,
                    'avatar' => $request->user()->avatar ?? null,
                    'banner' => $request->user()->banner ?? null,
                    'jabatan' => $request->user()->load(['jabatan.level'])->jabatan ? [
                        'nama_jabatan' => $request->user()->jabatan->nama_jabatan,
                        'level' => $request->user()->jabatan->level ? [
                            'nama_level' => $request->user()->jabatan->level->nama_level
                        ] : null
                    ] : null,
                    'divisi' => $request->user()->load('divisi')->divisi ? [
                        'nama_divisi' => $request->user()->divisi->nama_divisi
                    ] : null,
                    'region' => optional($request->user()->region)->name,
                    'outlet' => $request->user()->load('outlet')->outlet ? [
                        'nama_outlet' => $request->user()->outlet->nama_outlet
                    ] : null,
                    'pending_approvals_count' => $request->user()->pendingApprovals()->count(),
                    'pending_hrd_approvals_count' => $request->user()->division_id == 6 ? $request->user()->pendingHrdApprovals()->count() : 0,
                    'signature_path' => $request->user()->signature_path,
                    'pin_payroll' => $request->user()->pin_payroll,
                    'region_id' => $request->user()->id_outlet
                        ? \DB::table('tbl_data_outlet')
                            ->where('id_outlet', $request->user()->id_outlet)
                            ->value('region_id')
                        : null,
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
