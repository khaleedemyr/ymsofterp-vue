<?php

namespace App\Http\Controllers;

use App\Models\ModifierOption;
use App\Models\Modifier;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class ModifierOptionController extends Controller
{
    public function index(Request $request)
    {
        $query = ModifierOption::with('modifier');
        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        if ($request->modifier_id) {
            $query->where('modifier_id', $request->modifier_id);
        }
        $modifierOptions = $query->paginate(10)->withQueryString();
        $modifiers = Modifier::all();
        return Inertia::render('ModifierOptions/Index', [
            'modifierOptions' => $modifierOptions,
            'modifiers' => $modifiers,
            'filters' => $request->only(['search', 'modifier_id']),
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('STORE MODIFIER OPTION - REQUEST', $request->all());
        $validated = $request->validate([
            'modifier_id' => 'required|exists:modifiers,id',
            'name' => 'required|string|max:100',
            'modifier_bom_json' => 'nullable|string',
        ]);
        \Log::info('STORE MODIFIER OPTION - VALIDATED', $validated);
        $option = ModifierOption::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'modifier_options',
            'description' => 'Menambahkan modifier option: ' . $option->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $option->toArray(),
        ]);
        return redirect()->back();
    }

    public function update(Request $request, ModifierOption $modifierOption)
    {
        \Log::info('UPDATE MODIFIER OPTION - REQUEST', $request->all());
        $validated = $request->validate([
            'modifier_id' => 'required|exists:modifiers,id',
            'name' => 'required|string|max:100',
            'modifier_bom_json' => 'nullable|string',
        ]);
        \Log::info('UPDATE MODIFIER OPTION - VALIDATED', $validated);
        $oldData = $modifierOption->toArray();
        $modifierOption->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'modifier_options',
            'description' => 'Mengupdate modifier option: ' . $modifierOption->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $modifierOption->fresh()->toArray(),
        ]);
        return redirect()->back();
    }

    public function destroy(ModifierOption $modifierOption)
    {
        $oldData = $modifierOption->toArray();
        $modifierOption->delete();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'modifier_options',
            'description' => 'Menghapus modifier option: ' . $modifierOption->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => null,
        ]);
        return redirect()->back();
    }

    public function apiCreateData()
    {
        $modifiers = Modifier::orderBy('name')->get(['id', 'name']);
        return response()->json([
            'success' => true,
            'modifiers' => $modifiers,
        ]);
    }

    public function apiIndex(Request $request)
    {
        $query = ModifierOption::with('modifier');
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where('name', 'like', "%{$search}%");
        }
        if ($request->filled('modifier_id')) {
            $query->where('modifier_id', $request->query('modifier_id'));
        }

        $perPage = (int) ($request->query('per_page') ?? 10);
        $perPage = max(1, min(200, $perPage));
        $modifierOptions = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'modifierOptions' => $modifierOptions,
        ]);
    }

    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'modifier_id' => 'required|exists:modifiers,id',
            'name' => 'required|string|max:100',
            'modifier_bom_json' => 'nullable|string',
        ]);

        $option = ModifierOption::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'modifier_options',
            'description' => 'Menambahkan modifier option: '.$option->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $option->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Modifier option berhasil ditambahkan!',
            'modifierOption' => $option->fresh(['modifier']),
        ]);
    }

    public function apiUpdate(Request $request, int $id)
    {
        $modifierOption = ModifierOption::find($id);
        if (! $modifierOption) {
            return response()->json(['success' => false, 'message' => 'Modifier option tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'modifier_id' => 'required|exists:modifiers,id',
            'name' => 'required|string|max:100',
            'modifier_bom_json' => 'nullable|string',
        ]);

        $oldData = $modifierOption->toArray();
        $modifierOption->update($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'modifier_options',
            'description' => 'Mengupdate modifier option: '.$modifierOption->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $modifierOption->fresh()->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Modifier option berhasil diupdate!',
            'modifierOption' => $modifierOption->fresh(['modifier']),
        ]);
    }

    public function apiDestroy(int $id)
    {
        $modifierOption = ModifierOption::find($id);
        if (! $modifierOption) {
            return response()->json(['success' => false, 'message' => 'Modifier option tidak ditemukan'], 404);
        }

        $oldData = $modifierOption->toArray();
        $name = $modifierOption->name;
        $modifierOption->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'modifier_options',
            'description' => 'Menghapus modifier option: '.$name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Modifier option berhasil dihapus!',
        ]);
    }
} 