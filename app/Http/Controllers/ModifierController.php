<?php

namespace App\Http\Controllers;

use App\Models\Modifier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ModifierController extends Controller
{
    public function index(Request $request)
    {
        $query = Modifier::query();

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $modifiers = $query->paginate(10)->withQueryString();

        return Inertia::render('Modifiers/Index', [
            'modifiers' => $modifiers,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:modifiers',
        ]);

        $modifier = Modifier::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'modifiers',
            'description' => 'Menambahkan modifier baru: ' . $modifier->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $modifier->toArray(),
        ]);
        return redirect()->back();
    }

    public function update(Request $request, Modifier $modifier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:modifiers,name,' . $modifier->id,
        ]);
        $oldData = $modifier->toArray();
        $modifier->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'modifiers',
            'description' => 'Mengupdate modifier: ' . $modifier->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $modifier->fresh()->toArray(),
        ]);
        return redirect()->back();
    }

    public function destroy(Modifier $modifier)
    {
        $oldData = $modifier->toArray();
        $modifier->delete();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'modifiers',
            'description' => 'Menghapus modifier: ' . $modifier->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => null,
        ]);
        return redirect()->back();
    }

    public function apiIndex(Request $request)
    {
        $query = Modifier::query();
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = (int) ($request->query('per_page') ?? 10);
        $perPage = max(1, min(200, $perPage));
        $modifiers = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'modifiers' => $modifiers,
        ]);
    }

    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:modifiers',
        ]);

        $modifier = Modifier::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'modifiers',
            'description' => 'Menambahkan modifier baru: '.$modifier->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $modifier->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Modifier berhasil ditambahkan!',
            'modifier' => $modifier,
        ]);
    }

    public function apiUpdate(Request $request, int $id)
    {
        $modifier = Modifier::find($id);
        if (! $modifier) {
            return response()->json(['success' => false, 'message' => 'Modifier tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:modifiers,name,'.$modifier->id,
        ]);

        $oldData = $modifier->toArray();
        $modifier->update($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'modifiers',
            'description' => 'Mengupdate modifier: '.$modifier->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $modifier->fresh()->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Modifier berhasil diupdate!',
            'modifier' => $modifier->fresh(),
        ]);
    }

    public function apiDestroy(int $id)
    {
        $modifier = Modifier::find($id);
        if (! $modifier) {
            return response()->json(['success' => false, 'message' => 'Modifier tidak ditemukan'], 404);
        }

        $oldData = $modifier->toArray();
        $name = $modifier->name;
        $modifier->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'modifiers',
            'description' => 'Menghapus modifier: '.$name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Modifier berhasil dihapus!',
        ]);
    }
} 