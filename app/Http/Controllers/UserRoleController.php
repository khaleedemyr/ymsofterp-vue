<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class UserRoleController extends Controller
{
    public function index()
    {
        $users = DB::table('users as u')
            ->leftJoin('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
            ->leftJoin('erp_role as r', 'ur.role_id', '=', 'r.id')
            ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('u.status', 'A')
            ->select('u.id', 'u.nama_lengkap', 'o.nama_outlet', 'j.nama_jabatan', 'r.id as role_id', 'r.name as role_name')
            ->get();
        $roles = Role::all();
        return Inertia::render('UserRole/Index', [
            'users' => $users,
            'roles' => $roles
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'role_id' => 'required|exists:erp_role,id'
        ]);

        // Get old data
        $oldData = DB::table('erp_user_role')
            ->where('user_id', $id)
            ->first();

        // Get user and role info for logging
        $user = DB::table('users')->where('id', $id)->first();
        $newRole = DB::table('erp_role')->where('id', $request->role_id)->first();
        $oldRole = $oldData ? DB::table('erp_role')->where('id', $oldData->role_id)->first() : null;

        // Update or insert ke pivot
        DB::table('erp_user_role')->updateOrInsert(
            ['user_id' => $id],
            ['role_id' => $request->role_id, 'updated_at' => now()]
        );

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'user_roles',
            'description' => 'Mengupdate role user ' . $user->nama_lengkap . 
                           ' dari ' . ($oldRole ? $oldRole->name : 'tidak ada role') . 
                           ' menjadi ' . $newRole->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData ? ['role_id' => $oldData->role_id, 'role_name' => $oldRole->name] : null,
            'new_data' => ['role_id' => $request->role_id, 'role_name' => $newRole->name]
        ]);

        return redirect()->back();
    }
} 