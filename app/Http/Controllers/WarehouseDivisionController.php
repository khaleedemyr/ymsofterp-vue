<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\ActivityLog;

class WarehouseDivisionController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('warehouse_division as wd')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->select('wd.*', 'w.name as warehouse_name');
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('wd.name', 'like', "%$search%")
                  ->orWhere('w.name', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('status')) {
            $query->where('wd.status', $request->status);
        }
        $divisions = $query->orderBy('wd.id', 'desc')->paginate(10)->withQueryString();
        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();
        return Inertia::render('WarehouseDivisions/Index', [
            'divisions' => $divisions,
            'filters' => [
                'search' => $request->search,
            ],
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'warehouse_id' => 'required|exists:warehouses,id',
            'status' => 'required|in:active,inactive',
        ]);
        $id = DB::table('warehouse_division')->insertGetId([
            'name' => $validated['name'],
            'warehouse_id' => $validated['warehouse_id'],
            'status' => $validated['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $division = DB::table('warehouse_division')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'warehouse_division',
            'description' => 'Menambahkan warehouse division: ' . $division->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => json_encode($division),
        ]);
        return redirect()->route('warehouse-divisions.index')->with('success', 'Warehouse Division berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'warehouse_id' => 'required|exists:warehouses,id',
            'status' => 'required|in:active,inactive',
        ]);
        $division = DB::table('warehouse_division')->where('id', $id)->first();
        $oldData = $division;
        DB::table('warehouse_division')->where('id', $id)->update([
            'name' => $validated['name'],
            'warehouse_id' => $validated['warehouse_id'],
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);
        $newData = DB::table('warehouse_division')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'warehouse_division',
            'description' => 'Mengupdate warehouse division: ' . $newData->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return redirect()->route('warehouse-divisions.index')->with('success', 'Warehouse Division berhasil diupdate!');
    }

    public function destroy($id)
    {
        $division = DB::table('warehouse_division')->where('id', $id)->first();
        $oldData = $division;
        DB::table('warehouse_division')->where('id', $id)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);
        $newData = DB::table('warehouse_division')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'warehouse_division',
            'description' => 'Menonaktifkan warehouse division: ' . $division->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return redirect()->route('warehouse-divisions.index')->with('success', 'Warehouse Division berhasil dinonaktifkan!');
    }

    public function toggleStatus($id, Request $request)
    {
        $division = DB::table('warehouse_division')->where('id', $id)->first();
        $oldData = $division;
        DB::table('warehouse_division')->where('id', $id)->update([
            'status' => $request->status,
            'updated_at' => now(),
        ]);
        $newData = DB::table('warehouse_division')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'status_toggle',
            'module' => 'warehouse_division',
            'description' => 'Mengubah status warehouse division: ' . $division->name . ' menjadi ' . $request->status,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return response()->json(['success' => true]);
    }

    public function apiMasterCreateData()
    {
        $warehouses = DB::table('warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'warehouses' => $warehouses,
        ]);
    }

    public function apiMasterIndex(Request $request)
    {
        $query = DB::table('warehouse_division as wd')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->select('wd.*', 'w.name as warehouse_name');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('wd.name', 'like', "%{$search}%")
                    ->orWhere('w.name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('wd.status', $request->query('status'));
        }

        $perPage = (int) ($request->query('per_page') ?? 10);
        $perPage = max(1, min(100, $perPage));
        $divisions = $query->orderByDesc('wd.id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'divisions' => $divisions,
        ]);
    }

    public function apiMasterStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'warehouse_id' => 'required|exists:warehouses,id',
            'status' => 'required|in:active,inactive',
        ]);

        $id = DB::table('warehouse_division')->insertGetId([
            'name' => $validated['name'],
            'warehouse_id' => $validated['warehouse_id'],
            'status' => $validated['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $division = DB::table('warehouse_division')->where('id', $id)->first();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'warehouse_division',
            'description' => 'Menambahkan warehouse division: '.$division->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => json_encode($division),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse Division berhasil ditambahkan!',
            'division' => $division,
        ]);
    }

    public function apiMasterUpdate(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'warehouse_id' => 'required|exists:warehouses,id',
            'status' => 'required|in:active,inactive',
        ]);

        $division = DB::table('warehouse_division')->where('id', $id)->first();
        if (! $division) {
            return response()->json(['success' => false, 'message' => 'Warehouse Division tidak ditemukan'], 404);
        }
        $oldData = $division;

        DB::table('warehouse_division')->where('id', $id)->update([
            'name' => $validated['name'],
            'warehouse_id' => $validated['warehouse_id'],
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);
        $newData = DB::table('warehouse_division')->where('id', $id)->first();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'warehouse_division',
            'description' => 'Mengupdate warehouse division: '.$newData->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse Division berhasil diupdate!',
            'division' => $newData,
        ]);
    }

    public function apiMasterDestroy(int $id, Request $request)
    {
        $division = DB::table('warehouse_division')->where('id', $id)->first();
        if (! $division) {
            return response()->json(['success' => false, 'message' => 'Warehouse Division tidak ditemukan'], 404);
        }
        $oldData = $division;

        DB::table('warehouse_division')->where('id', $id)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);
        $newData = DB::table('warehouse_division')->where('id', $id)->first();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'warehouse_division',
            'description' => 'Menonaktifkan warehouse division: '.$division->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse Division berhasil dinonaktifkan!',
        ]);
    }

    public function apiMasterToggleStatus(int $id, Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $division = DB::table('warehouse_division')->where('id', $id)->first();
        if (! $division) {
            return response()->json(['success' => false, 'message' => 'Warehouse Division tidak ditemukan'], 404);
        }
        $oldData = $division;

        DB::table('warehouse_division')->where('id', $id)->update([
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);
        $newData = DB::table('warehouse_division')->where('id', $id)->first();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'status_toggle',
            'module' => 'warehouse_division',
            'description' => 'Mengubah status warehouse division: '.$division->name.' menjadi '.$validated['status'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status Warehouse Division berhasil diubah',
            'division' => $newData,
        ]);
    }
} 