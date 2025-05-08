<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\ActivityLog;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('tbl_data_outlet as o')
            ->leftJoin('regions as r', 'o.region_id', '=', 'r.id')
            ->select('o.*', 'r.name as region_name');
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('o.nama_outlet', 'like', "%$search%")
                  ->orWhere('o.lokasi', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('status')) {
            $query->where('o.status', $request->status);
        }
        $outlets = $query->orderBy('o.id_outlet', 'desc')->paginate(10)->withQueryString();
        $regions = DB::table('regions')->select('id', 'name')->orderBy('name')->get();
        return Inertia::render('Outlets/Index', [
            'outlets' => $outlets,
            'filters' => [
                'search' => $request->search,
            ],
            'regions' => $regions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_outlet' => 'required|string|max:100',
            'lokasi' => 'required|string|max:255',
            'region_id' => 'required|exists:regions,id',
            'status' => 'required|in:A,N',
            'qr_code' => 'nullable|string|max:255',
            'lat' => 'nullable|string|max:50',
            'long' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string|max:255',
        ]);
        $qrCode = $validated['qr_code'] ?? null;
        if (!$qrCode) {
            $qrCode = 'OUTLET-' . time();
        }
        $id = DB::table('tbl_data_outlet')->insertGetId([
            'nama_outlet' => $validated['nama_outlet'],
            'lokasi' => $validated['lokasi'],
            'region_id' => $validated['region_id'],
            'status' => $validated['status'],
            'qr_code' => $qrCode,
            'lat' => $validated['lat'] ?? null,
            'long' => $validated['long'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'outlets',
            'description' => 'Menambahkan outlet baru: ' . $outlet->nama_outlet,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => json_encode($outlet),
        ]);
        return redirect()->route('outlets.index')->with('success', 'Outlet berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nama_outlet' => 'required|string|max:100',
            'lokasi' => 'required|string|max:255',
            'region_id' => 'required|exists:regions,id',
            'status' => 'required|in:A,N',
            'qr_code' => 'nullable|string|max:255',
            'lat' => 'nullable|string|max:50',
            'long' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string|max:255',
        ]);
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $id)->first();
        $oldData = $outlet;
        $qrCode = $validated['qr_code'] ?? null;
        if (!$qrCode) {
            $qrCode = $outlet->qr_code ?: ('OUTLET-' . time());
        }
        DB::table('tbl_data_outlet')->where('id_outlet', $id)->update([
            'nama_outlet' => $validated['nama_outlet'],
            'lokasi' => $validated['lokasi'],
            'region_id' => $validated['region_id'],
            'status' => $validated['status'],
            'qr_code' => $qrCode,
            'lat' => $validated['lat'] ?? null,
            'long' => $validated['long'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
            'updated_at' => now(),
        ]);
        $newData = DB::table('tbl_data_outlet')->where('id_outlet', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'outlets',
            'description' => 'Mengupdate outlet: ' . $newData->nama_outlet,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return redirect()->route('outlets.index')->with('success', 'Outlet berhasil diupdate!');
    }

    public function destroy($id)
    {
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $id)->first();
        $oldData = $outlet;
        DB::table('tbl_data_outlet')->where('id_outlet', $id)->update([
            'status' => 'N',
            'updated_at' => now(),
        ]);
        $newData = DB::table('tbl_data_outlet')->where('id_outlet', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'outlets',
            'description' => 'Menonaktifkan outlet: ' . $outlet->nama_outlet,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return redirect()->route('outlets.index')->with('success', 'Outlet berhasil dinonaktifkan!');
    }

    public function toggleStatus($id, Request $request)
    {
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $id)->first();
        $oldData = $outlet;
        DB::table('tbl_data_outlet')->where('id_outlet', $id)->update([
            'status' => $request->status,
            'updated_at' => now(),
        ]);
        $newData = DB::table('tbl_data_outlet')->where('id_outlet', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'status_toggle',
            'module' => 'outlets',
            'description' => 'Mengubah status outlet: ' . $outlet->nama_outlet . ' menjadi ' . $request->status,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return response()->json(['success' => true]);
    }

    public function downloadQr($id)
    {
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $id)->first();
        if (!$outlet) {
            abort(404);
        }
        $qrCode = \QrCode::format('png')->size(400)->generate($outlet->qr_code);
        $filename = 'qr_' . $outlet->qr_code . '.png';
        return response($qrCode)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
} 