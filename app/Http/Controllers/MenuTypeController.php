<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\ActivityLog;

class MenuTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('menu_type');
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('type', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $menuTypes = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        return Inertia::render('MenuTypes/Index', [
            'menuTypes' => $menuTypes,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:100',
            'status' => 'required|in:active,inactive',
        ]);
        $id = DB::table('menu_type')->insertGetId([
            'type' => $validated['type'],
            'status' => $validated['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $menuType = DB::table('menu_type')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'menu_type',
            'description' => 'Menambahkan menu type: ' . $menuType->type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => json_encode($menuType),
        ]);
        return redirect()->route('menu-types.index')->with('success', 'Menu Type berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:100',
            'status' => 'required|in:active,inactive',
        ]);
        $menuType = DB::table('menu_type')->where('id', $id)->first();
        $oldData = $menuType;
        DB::table('menu_type')->where('id', $id)->update([
            'type' => $validated['type'],
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);
        $newData = DB::table('menu_type')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'menu_type',
            'description' => 'Mengupdate menu type: ' . $newData->type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return redirect()->route('menu-types.index')->with('success', 'Menu Type berhasil diupdate!');
    }

    public function destroy($id)
    {
        $menuType = DB::table('menu_type')->where('id', $id)->first();
        $oldData = $menuType;
        DB::table('menu_type')->where('id', $id)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);
        $newData = DB::table('menu_type')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'menu_type',
            'description' => 'Menonaktifkan menu type: ' . $menuType->type,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return redirect()->route('menu-types.index')->with('success', 'Menu Type berhasil dinonaktifkan!');
    }

    public function toggleStatus($id, Request $request)
    {
        $menuType = DB::table('menu_type')->where('id', $id)->first();
        $oldData = $menuType;
        DB::table('menu_type')->where('id', $id)->update([
            'status' => $request->status,
            'updated_at' => now(),
        ]);
        $newData = DB::table('menu_type')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'status_toggle',
            'module' => 'menu_type',
            'description' => 'Mengubah status menu type: ' . $menuType->type . ' menjadi ' . $request->status,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return response()->json(['success' => true]);
    }

    public function apiIndex(Request $request)
    {
        $query = DB::table('menu_type');
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where('type', 'like', "%{$search}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) ($request->query('per_page') ?? 10);
        $perPage = max(1, min(100, $perPage));
        $menuTypes = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'menuTypes' => $menuTypes,
        ]);
    }

    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:100',
            'status' => 'required|in:active,inactive',
        ]);

        $id = DB::table('menu_type')->insertGetId([
            'type' => $validated['type'],
            'status' => $validated['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $menuType = DB::table('menu_type')->where('id', $id)->first();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'menu_type',
            'description' => 'Menambahkan menu type: '.$menuType->type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => json_encode($menuType),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu Type berhasil ditambahkan!',
            'menuType' => $menuType,
        ]);
    }

    public function apiUpdate(Request $request, int $id)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:100',
            'status' => 'required|in:active,inactive',
        ]);

        $menuType = DB::table('menu_type')->where('id', $id)->first();
        if (! $menuType) {
            return response()->json(['success' => false, 'message' => 'Menu Type tidak ditemukan'], 404);
        }
        $oldData = $menuType;

        DB::table('menu_type')->where('id', $id)->update([
            'type' => $validated['type'],
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);
        $newData = DB::table('menu_type')->where('id', $id)->first();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'menu_type',
            'description' => 'Mengupdate menu type: '.$newData->type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu Type berhasil diupdate!',
            'menuType' => $newData,
        ]);
    }

    public function apiDestroy(int $id, Request $request)
    {
        $menuType = DB::table('menu_type')->where('id', $id)->first();
        if (! $menuType) {
            return response()->json(['success' => false, 'message' => 'Menu Type tidak ditemukan'], 404);
        }
        $oldData = $menuType;

        DB::table('menu_type')->where('id', $id)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);
        $newData = DB::table('menu_type')->where('id', $id)->first();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'menu_type',
            'description' => 'Menonaktifkan menu type: '.$menuType->type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu Type berhasil dinonaktifkan!',
        ]);
    }

    public function apiToggleStatus(int $id, Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $menuType = DB::table('menu_type')->where('id', $id)->first();
        if (! $menuType) {
            return response()->json(['success' => false, 'message' => 'Menu Type tidak ditemukan'], 404);
        }
        $oldData = $menuType;

        DB::table('menu_type')->where('id', $id)->update([
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);
        $newData = DB::table('menu_type')->where('id', $id)->first();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'status_toggle',
            'module' => 'menu_type',
            'description' => 'Mengubah status menu type: '.$menuType->type.' menjadi '.$validated['status'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status Menu Type berhasil diubah',
            'menuType' => $newData,
        ]);
    }
} 