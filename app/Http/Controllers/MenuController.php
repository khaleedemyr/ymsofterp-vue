<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Menu;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class MenuController extends Controller
{
    public function index()
    {
        $menus = Menu::with('parent')->orderBy('parent_id')->orderBy('name')->get();
        return inertia('Menu/Index', ['menus' => $menus]);
    }

    public function create()
    {
        $parents = Menu::whereNull('parent_id')->get();
        return inertia('Menu/Form', ['parents' => $parents]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:100|unique:erp_menu,code',
            'parent_id' => 'nullable|exists:erp_menu,id',
            'route' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:100',
        ]);
        
        $menu = Menu::create($data);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'menus',
            'description' => 'Membuat menu baru: ' . $menu->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $menu->toArray()
        ]);

        return redirect()->route('menus.index')->with('success', 'Menu created!');
    }

    public function edit(Menu $menu)
    {
        $parents = Menu::whereNull('parent_id')->where('id', '!=', $menu->id)->get();
        return inertia('Menu/Form', ['menu' => $menu, 'parents' => $parents]);
    }

    public function update(Request $request, Menu $menu)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:100|unique:erp_menu,code,' . $menu->id,
            'parent_id' => 'nullable|exists:erp_menu,id',
            'route' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:100',
        ]);

        $oldData = $menu->toArray();
        $menu->update($data);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'menus',
            'description' => 'Mengupdate menu: ' . $menu->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $menu->fresh()->toArray()
        ]);

        return redirect()->route('menus.index')->with('success', 'Menu updated!');
    }

    public function destroy(Menu $menu)
    {
        $oldData = $menu->toArray();
        $menu->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'menus',
            'description' => 'Menghapus menu: ' . $menu->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => null
        ]);

        return redirect()->route('menus.index')->with('success', 'Menu deleted!');
    }
} 