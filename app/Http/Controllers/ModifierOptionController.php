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
} 