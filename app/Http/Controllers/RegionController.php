<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RegionController extends Controller
{
    public function index(Request $request)
    {
        $query = Region::query();
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
        $regions = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        return Inertia::render('Regions/Index', [
            'regions' => $regions,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:regions',
            'name' => 'required|string|max:100',
        ]);
        $region = Region::create([
            ...$validated,
            'status' => Region::STATUS_ACTIVE,
        ]);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'regions',
            'description' => 'Menambahkan region baru: ' . $region->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $region->toArray(),
        ]);
        return redirect()->route('regions.index')->with('success', 'Region berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:regions,code,' . $id,
            'name' => 'required|string|max:100',
        ]);
        $region = Region::findOrFail($id);
        $oldData = $region->toArray();
        $region->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'regions',
            'description' => 'Mengupdate region: ' . $region->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $region->fresh()->toArray(),
        ]);
        return redirect()->route('regions.index')->with('success', 'Region berhasil diupdate!');
    }

    public function destroy($id)
    {
        $region = Region::findOrFail($id);
        $oldData = $region->toArray();
        $region->update(['status' => 'inactive']);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'regions',
            'description' => 'Menonaktifkan region: ' . $region->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $region->fresh()->toArray(),
        ]);
        return redirect()->route('regions.index')->with('success', 'Region berhasil dinonaktifkan!');
    }

    public function toggleStatus($id, Request $request)
    {
        $region = Region::findOrFail($id);
        $region->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }
} 