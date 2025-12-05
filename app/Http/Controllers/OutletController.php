<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $query = Outlet::with(['region']);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama_outlet', 'like', "%$search%")
                  ->orWhere('lokasi', 'like', "%$search%")
                  ->orWhere('qr_code', 'like', "%$search%");
            });
        }
        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 'A' : 'N';
            $query->where('status', $status);
        }
        $outlets = $query->orderBy('id_outlet', 'desc')->paginate(10)->withQueryString();
        
        return Inertia::render('Outlets/Index', [
            'outlets' => $outlets,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function getActiveOutlets()
    {
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet', 'asc')
            ->get(['id_outlet', 'nama_outlet', 'lokasi']);
        
        return response()->json($outlets);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_outlet' => 'required|string|max:100',
            'lokasi' => 'required|string',
            'qr_code' => 'nullable|string|max:255',
            'lat' => 'nullable|string|max:50',
            'long' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
            'region_id' => 'required|exists:regions,id',
            'status' => 'required|in:A,N',
            'url_places' => 'nullable|string',
            'sn' => 'nullable|string|max:100',
            'activation_code' => 'nullable|string|max:100',
        ]);
        
        // Always set status to 'A' for new records
        $validated['status'] = 'A';
        
        // Generate QR code if not provided
        if (empty($validated['qr_code'])) {
            $validated['qr_code'] = 'OUTLET-' . time();
        }
        
        $outlet = Outlet::create($validated);
        
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'outlets',
            'description' => 'Menambahkan outlet baru: ' . $outlet->nama_outlet,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $outlet->toArray(),
        ]);
        
        return redirect()->route('outlets.index')->with('success', 'Outlet berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nama_outlet' => 'required|string|max:100',
            'lokasi' => 'required|string',
            'qr_code' => 'nullable|string|max:255',
            'lat' => 'nullable|string|max:50',
            'long' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
            'region_id' => 'required|exists:regions,id',
            'status' => 'required|in:A,N',
            'url_places' => 'nullable|string',
            'sn' => 'nullable|string|max:100',
            'activation_code' => 'nullable|string|max:100',
        ]);
        
        try {
            $outlet = Outlet::find($id);
            if (!$outlet) {
                return redirect()->route('outlets.index')->with('error', 'Outlet tidak ditemukan!');
            }
            
            $oldData = $outlet->toArray();
            $outlet->update($validated);
            
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'outlets',
                'description' => 'Mengupdate outlet: ' . $outlet->nama_outlet,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => $oldData,
                'new_data' => $outlet->fresh()->toArray(),
            ]);
            
            return redirect()->route('outlets.index')->with('success', 'Outlet berhasil diupdate!');
        } catch (\Exception $e) {
            \Log::error('Error updating outlet: ' . $e->getMessage(), [
                'id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return redirect()->route('outlets.index')->with('error', 'Terjadi kesalahan saat mengupdate outlet!');
        }
    }

    public function destroy($id)
    {
        try {
            $outlet = Outlet::find($id);
            if (!$outlet) {
                return redirect()->route('outlets.index')->with('error', 'Outlet tidak ditemukan!');
            }
            
            $oldData = $outlet->toArray();
            $outlet->update(['status' => 'N']);
            
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'outlets',
                'description' => 'Menonaktifkan outlet: ' . $outlet->nama_outlet,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => $oldData,
                'new_data' => $outlet->fresh()->toArray(),
            ]);
            
            return redirect()->route('outlets.index')->with('success', 'Outlet berhasil dinonaktifkan!');
        } catch (\Exception $e) {
            \Log::error('Error destroying outlet: ' . $e->getMessage(), [
                'id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return redirect()->route('outlets.index')->with('error', 'Terjadi kesalahan saat menonaktifkan outlet!');
        }
    }

    public function toggleStatus($id, Request $request)
    {
        try {
            $outlet = Outlet::find($id);
            if (!$outlet) {
                return response()->json(['success' => false, 'message' => 'Outlet tidak ditemukan'], 404);
            }
            
            $newStatus = $outlet->status === 'A' ? 'N' : 'A';
            $outlet->update(['status' => $newStatus]);
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Error toggling outlet status: ' . $e->getMessage(), [
                'id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat mengubah status'], 500);
        }
    }

    public function getDropdownData()
    {
        try {
            $regions = Region::where('status', 'active')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            // Selalu return 200, walaupun data kosong
            return response()->json([
                'success' => true,
                'regions' => $regions,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching dropdown data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dropdown data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $outlet = Outlet::with(['region'])
                ->find($id);
            
            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet tidak ditemukan'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'outlet' => $outlet
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching outlet: ' . $e->getMessage(), [
                'id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data outlet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function debugDatabase()
    {
        try {
            $debug = [];
            
            // Check database connection
            $debug['connection'] = 'Connected';
            
            // Check if tables exist
            $tables = ['tbl_data_outlet', 'regions'];
            foreach ($tables as $table) {
                try {
                    $count = DB::table($table)->count();
                    $debug['tables'][$table] = [
                        'exists' => true,
                        'total_count' => $count,
                        'active_count' => DB::table($table)->where('status', 'A')->count()
                    ];
                } catch (\Exception $e) {
                    $debug['tables'][$table] = [
                        'exists' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Check model queries
            try {
                $debug['models']['outlet'] = [
                    'total' => Outlet::count(),
                    'active' => Outlet::where('status', 'A')->count(),
                    'sample' => Outlet::where('status', 'A')->first()
                ];
            } catch (\Exception $e) {
                $debug['models']['outlet'] = ['error' => $e->getMessage()];
            }
            
            try {
                $debug['models']['region'] = [
                    'total' => Region::count(),
                    'active' => Region::where('status', 'active')->count(),
                    'sample' => Region::where('status', 'active')->first()
                ];
            } catch (\Exception $e) {
                $debug['models']['region'] = ['error' => $e->getMessage()];
            }
            
            return response()->json([
                'success' => true,
                'debug' => $debug
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function downloadQr($id)
    {
        $outlet = Outlet::find($id);
        if (!$outlet) {
            abort(404);
        }
        $qrCode = \QrCode::format('png')->size(400)->generate($outlet->qr_code);
        $filename = 'qr_' . $outlet->qr_code . '.png';
        return response($qrCode)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function apiList()
    {
        $outlets = Outlet::where('status', 'A')
            ->with('region')
            ->select('id_outlet', 'nama_outlet', 'lokasi', 'region_id', 'qr_code', 'lat', 'long', 'keterangan', 'status', 'url_places', 'created_at', 'updated_at')
            ->orderBy('nama_outlet')
            ->get();
        return response()->json($outlets);
    }

    public function apiShow($id)
    {
        $outlet = Outlet::where('id_outlet', $id)
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet', 'lokasi', 'region_id', 'qr_code', 'lat', 'long', 'keterangan', 'status', 'url_places', 'created_at', 'updated_at')
            ->first();
            
        if (!$outlet) {
            return response()->json(['error' => 'Outlet tidak ditemukan'], 404);
        }
        
        return response()->json($outlet);
    }
} 