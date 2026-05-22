<?php

namespace App\Http\Middleware;

use App\Models\ExternalUser;
use App\Models\User;
use App\Services\RoleMenuService;
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
        $crmMenusFromDb = [];

        if ($userId) {
            $allowedMenus = array_values(RoleMenuService::allowedMenuCodesForInternalUser($userId));

            // Menu CRM (parent_id 138) dari DB — sama join permission dengan allowedMenus
            $crmMenusFromDb = DB::table('erp_menu as m')
                ->join('erp_permission as p', 'p.menu_id', '=', 'm.id')
                ->join('erp_role_permission as rp', 'rp.permission_id', '=', 'p.id')
                ->join('erp_user_role as ur', 'ur.role_id', '=', 'rp.role_id')
                ->where('ur.user_id', $userId)
                ->where('m.parent_id', 138)
                ->where('p.action', 'view')
                ->whereNotNull('m.route')
                ->where('m.route', '!=', '')
                ->select('m.id', 'm.code', 'm.route', 'm.icon', 'm.name')
                ->groupBy('m.id', 'm.code', 'm.route', 'm.icon', 'm.name')
                ->orderBy('m.id')
                ->get()
                ->map(fn ($row) => [
                    'code' => $row->code,
                    'route' => $row->route,
                    'icon' => $row->icon ?: 'fa-solid fa-circle',
                    'name' => $row->name,
                ])
                ->values()
                ->all();
        }


        $authUser = $request->user();

        $sharedAuthUser = null;
        if ($authUser instanceof User) {
            $sharedAuthUser = [
                'id' => $authUser->id,
                'email' => $authUser->email,
                'id_outlet' => $authUser->id_outlet,
                'id_role' => $authUser->id_role,
                'id_jabatan' => $authUser->id_jabatan,
                'status' => $authUser->status,
                'division_id' => $authUser->division_id,
                'nama_lengkap' => $authUser->nama_lengkap ?? $authUser->name,
                'avatar' => $authUser->avatar ?? null,
                'banner' => $authUser->banner ?? null,
                'jabatan' => $authUser->load(['jabatan.level'])->jabatan ? [
                    'nama_jabatan' => $authUser->jabatan->nama_jabatan,
                    'level' => $authUser->jabatan->level ? [
                        'nama_level' => $authUser->jabatan->level->nama_level
                    ] : null
                ] : null,
                'divisi' => $authUser->load('divisi')->divisi ? [
                    'nama_divisi' => $authUser->divisi->nama_divisi
                ] : null,
                'region' => optional($authUser->region)->name,
                'outlet' => $authUser->load('outlet')->outlet ? [
                    'nama_outlet' => $authUser->outlet->nama_outlet
                ] : null,
                'pending_approvals_count' => $authUser->pendingApprovals()->count(),
                'pending_hrd_approvals_count' => $authUser->division_id == 6 ? $authUser->pendingHrdApprovals()->count() : 0,
                'signature_path' => $authUser->signature_path,
                'pin_payroll' => $authUser->pin_payroll,
                'region_id' => $authUser->id_outlet
                    ? DB::table('tbl_data_outlet')
                        ->where('id_outlet', $authUser->id_outlet)
                        ->value('region_id')
                    : null,
            ];
        } elseif ($authUser instanceof ExternalUser) {
            $sharedAuthUser = [
                'id' => $authUser->id,
                'email' => $authUser->email,
                'name' => $authUser->name,
                'nama_lengkap' => $authUser->name,
                'kode_outlet' => $authUser->kode_outlet,
                'nama_outlet' => $authUser->nama_outlet,
                'status' => $authUser->status,
                'is_external' => true,
            ];
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $sharedAuthUser,
            ],
            'result' => fn () => $request->session()->get('result'),
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'allowedMenus' => $allowedMenus,
            'crmMenusFromDb' => $crmMenusFromDb,
        ]);
    }
}
