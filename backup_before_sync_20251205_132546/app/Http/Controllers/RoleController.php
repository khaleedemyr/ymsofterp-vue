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
    public function index()
    {
        return Inertia::render('Role/Index', [
            'roles' => Role::with('permissions.menu')->get(),
            'menus' => Menu::all()
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

    public function destroy(Role $role)
    {
        $oldData = $role->toArray();
        
        $role->permissions()->detach();
        $role->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'roles',
            'description' => 'Menghapus role: ' . $role->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => null
        ]);

        return redirect()->back();
    }
} 