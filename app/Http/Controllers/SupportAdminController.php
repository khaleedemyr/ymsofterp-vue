<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SupportAdminController extends Controller
{
    public function index()
    {
        // Check if user has support admin panel view permission using the same system as HandleInertiaRequests
        $userId = auth()->id();
        $hasPermission = \DB::table('users as u')
            ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
            ->join('erp_role as r', 'ur.role_id', '=', 'r.id')
            ->join('erp_role_permission as rp', 'rp.role_id', '=', 'r.id')
            ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
            ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
            ->where('u.id', $userId)
            ->where('m.code', 'support_admin_panel')
            ->where('p.action', 'view')
            ->exists();

        if (!$hasPermission) {
            abort(403, 'Unauthorized access to support admin panel');
        }

        return Inertia::render('Support/AdminPanel');
    }
}
