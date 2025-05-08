<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $query = Warehouse::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%$search%")
                  ->orWhere('name', 'like', "%$search%")
                  ->orWhere('location', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $warehouses = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        return Inertia::render('Warehouses/Index', [
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:warehouses',
            'name' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);
        $warehouse = Warehouse::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'warehouses',
            'description' => 'Menambahkan warehouse baru: ' . $warehouse->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $warehouse->toArray(),
        ]);
        return redirect()->route('warehouses.index')->with('success', 'Warehouse berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:warehouses,code,' . $id,
            'name' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);
        $warehouse = Warehouse::findOrFail($id);
        $oldData = $warehouse->toArray();
        $warehouse->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'warehouses',
            'description' => 'Mengupdate warehouse: ' . $warehouse->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $warehouse->fresh()->toArray(),
        ]);
        return redirect()->route('warehouses.index')->with('success', 'Warehouse berhasil diupdate!');
    }

    public function destroy($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $oldData = $warehouse->toArray();
        $warehouse->update(['status' => 'inactive']);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'warehouses',
            'description' => 'Menonaktifkan warehouse: ' . $warehouse->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $warehouse->fresh()->toArray(),
        ]);
        return redirect()->route('warehouses.index')->with('success', 'Warehouse berhasil dinonaktifkan!');
    }

    public function toggleStatus($id, Request $request)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }
} 