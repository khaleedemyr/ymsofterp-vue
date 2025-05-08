<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Unit;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $query = Unit::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%$search%")
                  ->orWhere('name', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $units = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        return Inertia::render('Units/Index', [
            'units' => $units,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:units,code',
            'name' => 'required|string|max:100',
            'status' => 'required|in:active,inactive',
        ]);
        $unit = Unit::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'units',
            'description' => 'Menambahkan unit baru: ' . $unit->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $unit->toArray(),
        ]);
        return redirect()->route('units.index')->with('success', 'Unit berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:units,code,' . $id,
            'name' => 'required|string|max:100',
            'status' => 'required|in:active,inactive',
        ]);
        $unit = Unit::findOrFail($id);
        $oldData = $unit->toArray();
        $unit->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'units',
            'description' => 'Mengupdate unit: ' . $unit->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $unit->fresh()->toArray(),
        ]);
        return redirect()->route('units.index')->with('success', 'Unit berhasil diupdate!');
    }

    public function destroy($id)
    {
        $unit = Unit::findOrFail($id);
        $oldData = $unit->toArray();
        $unit->update(['status' => 'inactive']);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'units',
            'description' => 'Menonaktifkan unit: ' . $unit->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $unit->fresh()->toArray(),
        ]);
        return redirect()->route('units.index')->with('success', 'Unit berhasil dinonaktifkan!');
    }

    public function toggleStatus($id, Request $request)
    {
        $unit = Unit::findOrFail($id);
        $unit->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }
} 