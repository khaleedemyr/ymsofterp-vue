<?php

namespace App\Http\Controllers;

use App\Models\WarehouseOutlet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class WarehouseOutletController extends Controller
{
    public function index(Request $request)
    {
        $query = WarehouseOutlet::with('outlet');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('code', 'like', "%{$request->search}%")
                    ->orWhereHas('outlet', function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->search}%");
                    })
                    ->orWhere('location', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'active');
        }

        $warehouseOutlets = $query->latest()->paginate(10)->withQueryString();

        // Debug relasi outlet
        // Hapus baris ini setelah pengecekan
         //dd($warehouseOutlets->first()->outlet);

        // Get active outlets for the dropdown
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('WarehouseOutlets/Index', [
            'warehouseOutlets' => $warehouseOutlets,
            'filters' => $request->only(['search', 'status']),
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:warehouse_outlets',
            'name' => 'required|string|max:255',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'location' => 'required|string',
            'status' => 'required|in:active,inactive',
        ]);

        WarehouseOutlet::create($validated);

        return redirect()->back();
    }

    public function update(Request $request, WarehouseOutlet $warehouseOutlet)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:warehouse_outlets,code,' . $warehouseOutlet->id,
            'name' => 'required|string|max:255',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'location' => 'required|string',
            'status' => 'required|in:active,inactive',
        ]);

        $warehouseOutlet->update($validated);

        return redirect()->back();
    }

    public function destroy(WarehouseOutlet $warehouseOutlet)
    {
        $warehouseOutlet->delete();
        return redirect()->back();
    }

    public function toggleStatus(WarehouseOutlet $warehouseOutlet)
    {
        $warehouseOutlet->update([
            'status' => $warehouseOutlet->status === 'active' ? 'inactive' : 'active'
        ]);

        return redirect()->back();
    }

    // API: List warehouse outlet by outlet_id (aktif saja)
    public function apiListByOutlet(Request $request)
    {
        $outletId = $request->outlet_id;
        $status = $request->status ?? 'active';
        $query = WarehouseOutlet::query();
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        if ($status) {
            $query->where('status', $status);
        }
        $data = $query->orderBy('name')->get(['id', 'code', 'name', 'outlet_id', 'location', 'status']);
        return response()->json($data);
    }

    // API: Get warehouse outlets by outlet ID from URL path
    public function getByOutletId($outletId)
    {
        $warehouseOutlets = WarehouseOutlet::where('outlet_id', $outletId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'outlet_id']);
        
        return response()->json($warehouseOutlets);
    }

    public function apiMasterCreateData()
    {
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return response()->json([
            'success' => true,
            'outlets' => $outlets,
        ]);
    }

    public function apiMasterIndex(Request $request)
    {
        $query = WarehouseOutlet::with('outlet');
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('outlet', function ($sub) use ($search) {
                        $sub->where('nama_outlet', 'like', "%{$search}%");
                    });
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } else {
            $query->where('status', 'active');
        }

        $perPage = (int) ($request->query('per_page') ?? 10);
        $perPage = max(1, min(100, $perPage));
        $warehouseOutlets = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'warehouseOutlets' => $warehouseOutlets,
        ]);
    }

    public function apiMasterStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:warehouse_outlets',
            'name' => 'required|string|max:255',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'location' => 'required|string',
            'status' => 'required|in:active,inactive',
        ]);

        $warehouseOutlet = WarehouseOutlet::create($validated);
        return response()->json([
            'success' => true,
            'message' => 'Gudang outlet berhasil ditambahkan!',
            'warehouseOutlet' => $warehouseOutlet->fresh(['outlet']),
        ]);
    }

    public function apiMasterUpdate(Request $request, int $id)
    {
        $warehouseOutlet = WarehouseOutlet::find($id);
        if (! $warehouseOutlet) {
            return response()->json(['success' => false, 'message' => 'Gudang outlet tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:warehouse_outlets,code,'.$warehouseOutlet->id,
            'name' => 'required|string|max:255',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'location' => 'required|string',
            'status' => 'required|in:active,inactive',
        ]);

        $warehouseOutlet->update($validated);
        return response()->json([
            'success' => true,
            'message' => 'Gudang outlet berhasil diupdate!',
            'warehouseOutlet' => $warehouseOutlet->fresh(['outlet']),
        ]);
    }

    public function apiMasterDestroy(int $id)
    {
        $warehouseOutlet = WarehouseOutlet::find($id);
        if (! $warehouseOutlet) {
            return response()->json(['success' => false, 'message' => 'Gudang outlet tidak ditemukan'], 404);
        }

        $warehouseOutlet->delete();
        return response()->json([
            'success' => true,
            'message' => 'Gudang outlet berhasil dihapus!',
        ]);
    }

    public function apiMasterToggleStatus(int $id, Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:active,inactive',
        ]);

        $warehouseOutlet = WarehouseOutlet::find($id);
        if (! $warehouseOutlet) {
            return response()->json(['success' => false, 'message' => 'Gudang outlet tidak ditemukan'], 404);
        }

        $nextStatus = $validated['status'] ?? ($warehouseOutlet->status === 'active' ? 'inactive' : 'active');
        $warehouseOutlet->update(['status' => $nextStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Status gudang outlet berhasil diubah',
            'warehouseOutlet' => $warehouseOutlet->fresh(['outlet']),
        ]);
    }
} 