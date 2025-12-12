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
    public function index(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $roleId = $request->input('role_id');
        $search = $request->input('search', '');
        
        // Dropdown data
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get()
            ->map(fn($o) => ['id' => $o->id_outlet, 'name' => $o->nama_outlet]);
            
        $divisions = DB::table('tbl_data_divisi')
            ->where('status', 'A')
            ->select('id', 'nama_divisi')
            ->orderBy('nama_divisi')
            ->get()
            ->map(fn($d) => ['id' => $d->id, 'name' => $d->nama_divisi]);

        // Query users dengan filter
        $query = DB::table('users as u')
            ->leftJoin('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
            ->leftJoin('erp_role as r', 'ur.role_id', '=', 'r.id')
            ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->where('u.status', 'A');
            
        // Apply filters
        if ($outletId) {
            $query->where('u.id_outlet', $outletId);
        }
        
        if ($divisionId) {
            $query->where('u.division_id', $divisionId);
        }
        
        if ($roleId) {
            $query->where('ur.role_id', $roleId);
        }
        
        // Search filter - search by name or email
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('u.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('u.email', 'like', "%{$search}%");
            });
        }
        
        $users = $query->select(
            'u.id', 
            'u.nama_lengkap', 
            'o.nama_outlet', 
            'j.nama_jabatan',
            'd.nama_divisi',
            'r.id as role_id', 
            'r.name as role_name'
        )->get();
        
        $roles = Role::all();
        
        // Return JSON for API requests (mobile app)
        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'roles' => $roles,
                    'outlets' => $outlets,
                    'divisions' => $divisions,
                ],
                'filters' => [
                    'outlet_id' => $outletId,
                    'division_id' => $divisionId,
                    'role_id' => $roleId,
                    'search' => $search
                ]
            ]);
        }
        
        return Inertia::render('UserRole/Index', [
            'users' => $users,
            'roles' => $roles,
            'outlets' => $outlets,
            'divisions' => $divisions,
            'filters' => [
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
                'role_id' => $roleId
            ]
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

        // Return JSON for API requests (mobile app)
        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Role berhasil diupdate',
                'data' => [
                    'user_id' => $id,
                    'role_id' => $request->role_id,
                    'role_name' => $newRole->name
                ]
            ]);
        }

        return redirect()->back();
    }

    public function bulkAssign(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'role_id' => 'required|exists:erp_role,id'
        ]);

        try {
            DB::beginTransaction();
            
            $userIds = $request->user_ids;
            $roleId = $request->role_id;
            $updatedCount = 0;
            
            foreach ($userIds as $userId) {
                // Get old data for logging
                $oldData = DB::table('erp_user_role')
                    ->where('user_id', $userId)
                    ->first();
                
                // Get user and role info for logging
                $user = DB::table('users')->where('id', $userId)->first();
                $newRole = DB::table('erp_role')->where('id', $roleId)->first();
                $oldRole = $oldData ? DB::table('erp_role')->where('id', $oldData->role_id)->first() : null;
                
                // Update or insert ke pivot
                DB::table('erp_user_role')->updateOrInsert(
                    ['user_id' => $userId],
                    ['role_id' => $roleId, 'updated_at' => now()]
                );
                
                // Log activity
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'activity_type' => 'update',
                    'module' => 'user_roles',
                    'description' => 'Bulk assign role: ' . ($user->nama_lengkap ?? 'Unknown') . ' -> ' . ($newRole->name ?? 'Unknown'),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => $oldData ? ['role_id' => $oldData->role_id, 'role_name' => $oldRole->name ?? null] : null,
                    'new_data' => ['role_id' => $roleId, 'role_name' => $newRole->name ?? null],
                ]);
                
                $updatedCount++;
            }
            
            DB::commit();
            
            // Return JSON for API requests (mobile app)
            if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Role berhasil diassign ke {$updatedCount} user!",
                    'data' => [
                        'updated_count' => $updatedCount,
                        'role_id' => $roleId
                    ]
                ]);
            }
            
            return redirect()->back()->with('success', "Role berhasil diassign ke {$updatedCount} user!");
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Return JSON for API requests (mobile app)
            if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal assign role: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Gagal assign role: ' . $e->getMessage());
        }
    }
} 