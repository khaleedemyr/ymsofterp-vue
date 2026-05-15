<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RoleMenuService
{
    /**
     * Kode menu (erp_menu.code) dengan permission view untuk role tertentu.
     *
     * @return list<string>
     */
    public static function allowedMenuCodesForRole(?int $roleId): array
    {
        if (!$roleId) {
            return [];
        }

        return DB::table('erp_role_permission as rp')
            ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
            ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
            ->where('rp.role_id', $roleId)
            ->where('p.action', 'view')
            ->distinct()
            ->orderBy('m.code')
            ->pluck('m.code')
            ->all();
    }

    /**
     * Kode menu untuk user internal (erp_user_role).
     *
     * @return list<string>
     */
    public static function allowedMenuCodesForInternalUser(int $userId): array
    {
        return DB::table('users as u')
            ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
            ->join('erp_role_permission as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
            ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
            ->where('u.id', $userId)
            ->where('p.action', 'view')
            ->distinct()
            ->pluck('m.code')
            ->all();
    }

    public static function hasMenuAccess(?int $roleId, string $menuCode): bool
    {
        if (!$roleId || $menuCode === '') {
            return false;
        }

        return in_array($menuCode, self::allowedMenuCodesForRole($roleId), true);
    }

    /**
     * Route halaman pertama yang boleh diakses role (dari erp_menu.route).
     */
    public static function firstMenuRouteForRole(?int $roleId): ?string
    {
        if (!$roleId) {
            return null;
        }

        $route = DB::table('erp_role_permission as rp')
            ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
            ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
            ->where('rp.role_id', $roleId)
            ->where('p.action', 'view')
            ->whereNotNull('m.route')
            ->where('m.route', '!=', '')
            ->orderBy('m.sort_order')
            ->orderBy('m.id')
            ->value('m.route');

        return $route ?: null;
    }
}
