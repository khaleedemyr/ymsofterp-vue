<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::with('permissions.menu')->get();
        $menus = Menu::all();
        
        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'roles' => $roles,
                'menus' => $menus
            ]);
        }
        
        return Inertia::render('Role/Index', [
            'roles' => $roles,
            'menus' => $menus
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'array'
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'description' => $validated['description']
        ]);

        \Log::info('PERMISSIONS FROM REQUEST', $validated['permissions'] ?? []);
        if (isset($validated['permissions'])) {
            $permissionIds = $this->mapPermissionKeysToIds($validated['permissions']);
            \Log::info('PERMISSION IDS', $permissionIds);
            $role->permissions()->sync($permissionIds);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'roles',
            'description' => 'Membuat role baru: ' . $role->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $role->toArray()
        ]);

        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Role berhasil dibuat',
                'role' => $role->load('permissions.menu')
            ]);
        }

        return redirect()->back();
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'array'
        ]);

        $oldData = $role->toArray();
        
        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description']
        ]);

        if (isset($validated['permissions'])) {
            $permissionIds = $this->mapPermissionKeysToIds($validated['permissions']);
            $role->permissions()->sync($permissionIds);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'roles',
            'description' => 'Mengupdate role: ' . $role->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $role->fresh()->toArray()
        ]);

        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Role berhasil diupdate',
                'role' => $role->fresh()->load('permissions.menu')
            ]);
        }

        return redirect()->back();
    }

    private function mapPermissionKeysToIds($permissionKeys) {
        if (!$permissionKeys) return [];
        $ids = [];
        foreach ($permissionKeys as $key) {
            [$menu_id, $action] = explode('-', $key);
            $permission = Permission::where('menu_id', $menu_id)
                ->where('action', $action)
                ->first();
            if ($permission) {
                $ids[] = $permission->id;
            }
        }
        return $ids;
    }

    public function destroy(Request $request, Role $role)
    {
        $oldData = $role->toArray();
        $roleName = $role->name;
        
        $role->permissions()->detach();
        $role->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'roles',
            'description' => 'Menghapus role: ' . $roleName,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => null
        ]);

        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Role berhasil dihapus'
            ]);
        }

        return redirect()->back();
    }
} 