<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Jabatan;
use App\Models\Divisi;
use App\Models\SubDivisi;
use App\Models\DataLevel;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JabatanController extends Controller
{
    public function index(Request $request)
    {
        $query = Jabatan::with(['atasan', 'divisi', 'subDivisi', 'level']);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama_jabatan', 'like', "%$search%");
            });
        }
        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 'A' : 'N';
            $query->where('status', $status);
        }
        $jabatans = $query->orderBy('id_jabatan', 'desc')->paginate(10)->withQueryString();
        
        return Inertia::render('Jabatan/Index', [
            'jabatans' => $jabatans,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:100',
            'id_atasan' => 'nullable|exists:tbl_data_jabatan,id_jabatan',
            'id_divisi' => 'required|exists:tbl_data_divisi,id',
            'id_sub_divisi' => 'required|exists:tbl_data_sub_divisi,id',
            'id_level' => 'required|exists:tbl_data_level,id',
        ]);
        // Always set status to 'A' for new records
        $validated['status'] = 'A';
        $jabatan = Jabatan::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'jabatans',
            'description' => 'Menambahkan jabatan baru: ' . $jabatan->nama_jabatan,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $jabatan->toArray(),
        ]);
        return redirect()->route('jabatans.index')->with('success', 'Jabatan berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:100',
            'id_atasan' => 'nullable|exists:tbl_data_jabatan,id_jabatan',
            'id_divisi' => 'required|exists:tbl_data_divisi,id',
            'id_sub_divisi' => 'required|exists:tbl_data_sub_divisi,id',
            'id_level' => 'required|exists:tbl_data_level,id',
        ]);
        
        try {
            $jabatan = Jabatan::find($id);
            if (!$jabatan) {
                return redirect()->route('jabatans.index')->with('error', 'Jabatan tidak ditemukan!');
            }
            
            $oldData = $jabatan->toArray();
            $jabatan->update($validated);
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'jabatans',
                'description' => 'Mengupdate jabatan: ' . $jabatan->nama_jabatan,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => $oldData,
                'new_data' => $jabatan->fresh()->toArray(),
            ]);
            return redirect()->route('jabatans.index')->with('success', 'Jabatan berhasil diupdate!');
        } catch (\Exception $e) {
            \Log::error('Error updating jabatan: ' . $e->getMessage(), [
                'id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return redirect()->route('jabatans.index')->with('error', 'Terjadi kesalahan saat mengupdate jabatan!');
        }
    }

    public function destroy($id)
    {
        try {
            $jabatan = Jabatan::find($id);
            if (!$jabatan) {
                return redirect()->route('jabatans.index')->with('error', 'Jabatan tidak ditemukan!');
            }
            
            $oldData = $jabatan->toArray();
            $jabatan->update(['status' => 'N']);
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'jabatans',
                'description' => 'Menonaktifkan jabatan: ' . $jabatan->nama_jabatan,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => $oldData,
                'new_data' => $jabatan->fresh()->toArray(),
            ]);
            return redirect()->route('jabatans.index')->with('success', 'Jabatan berhasil dinonaktifkan!');
        } catch (\Exception $e) {
            \Log::error('Error destroying jabatan: ' . $e->getMessage(), [
                'id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return redirect()->route('jabatans.index')->with('error', 'Terjadi kesalahan saat menonaktifkan jabatan!');
        }
    }

    public function toggleStatus($id, Request $request)
    {
        try {
            $jabatan = Jabatan::find($id);
            if (!$jabatan) {
                return response()->json(['success' => false, 'message' => 'Jabatan tidak ditemukan'], 404);
            }
            
            $newStatus = $jabatan->status === 'A' ? 'N' : 'A';
            $jabatan->update(['status' => $newStatus]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Error toggling jabatan status: ' . $e->getMessage(), [
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
            $jabatans = Jabatan::where('status', 'A')
                ->select('id_jabatan', 'nama_jabatan')
                ->orderBy('nama_jabatan')
                ->get();

            $divisis = Divisi::where('status', 'A')
                ->select('id', 'nama_divisi')
                ->orderBy('nama_divisi')
                ->get();

            $subDivisis = SubDivisi::where('status', 'A')
                ->select('id', 'nama_sub_divisi')
                ->orderBy('nama_sub_divisi')
                ->get();

            $levels = DataLevel::where('status', 'A')
                ->select('id', 'nama_level')
                ->orderBy('nama_level')
                ->get();

            // Selalu return 200, walaupun data kosong
            return response()->json([
                'success' => true,
                'jabatans' => $jabatans,
                'divisis' => $divisis,
                'subDivisis' => $subDivisis,
                'levels' => $levels,
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
            $jabatan = Jabatan::with(['atasan', 'divisi', 'subDivisi', 'level'])
                ->find($id);
            
            if (!$jabatan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jabatan tidak ditemukan'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'jabatan' => $jabatan
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching jabatan: ' . $e->getMessage(), [
                'id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data jabatan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: List jabatans for mobile app.
     */
    public function apiIndex(Request $request)
    {
        $query = Jabatan::with(['atasan:id_jabatan,nama_jabatan', 'divisi:id,nama_divisi', 'subDivisi:id,nama_sub_divisi', 'level:id,nama_level']);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_jabatan', 'like', "%$search%");
            });
        }
        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 'A' : ($request->status === 'inactive' ? 'N' : null);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }
        $perPage = (int) $request->get('per_page', 15);
        $paginator = $query->orderBy('id_jabatan', 'desc')->paginate($perPage)->withQueryString();
        return response()->json($paginator);
    }

    /**
     * API: Show single jabatan.
     */
    public function apiShow($id)
    {
        $jabatan = Jabatan::with(['atasan:id_jabatan,nama_jabatan', 'divisi:id,nama_divisi', 'subDivisi:id,nama_sub_divisi', 'level:id,nama_level'])
            ->find($id);
        if (!$jabatan) {
            return response()->json(['success' => false, 'message' => 'Jabatan tidak ditemukan'], 404);
        }
        return response()->json($jabatan);
    }

    /**
     * API: Create form dropdown data (jabatans, divisis, subDivisis, levels).
     */
    public function apiCreateData()
    {
        try {
            $jabatans = Jabatan::where('status', 'A')->select('id_jabatan', 'nama_jabatan')->orderBy('nama_jabatan')->get();
            $divisis = Divisi::where('status', 'A')->select('id', 'nama_divisi')->orderBy('nama_divisi')->get();
            $subDivisis = SubDivisi::where('status', 'A')->select('id', 'id_divisi', 'nama_sub_divisi')->orderBy('nama_sub_divisi')->get();
            $levels = DataLevel::where('status', 'A')->select('id', 'nama_level')->orderBy('nama_level')->get();
            return response()->json([
                'success' => true,
                'jabatans' => $jabatans,
                'divisis' => $divisis,
                'subDivisis' => $subDivisis,
                'levels' => $levels,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error apiCreateData jabatan: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Store new jabatan.
     */
    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:100',
            'id_atasan' => 'nullable|exists:tbl_data_jabatan,id_jabatan',
            'id_divisi' => 'required|exists:tbl_data_divisi,id',
            'id_sub_divisi' => 'required|exists:tbl_data_sub_divisi,id',
            'id_level' => 'required|exists:tbl_data_level,id',
        ]);
        $validated['status'] = 'A';
        if (isset($validated['id_atasan']) && $validated['id_atasan'] === '') {
            $validated['id_atasan'] = null;
        }
        $jabatan = Jabatan::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'jabatans',
            'description' => 'Menambahkan jabatan baru: ' . $jabatan->nama_jabatan,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $jabatan->toArray(),
        ]);
        return response()->json(['success' => true, 'data' => $jabatan->load(['atasan', 'divisi', 'subDivisi', 'level'])]);
    }

    /**
     * API: Update jabatan.
     */
    public function apiUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:100',
            'id_atasan' => 'nullable|exists:tbl_data_jabatan,id_jabatan',
            'id_divisi' => 'required|exists:tbl_data_divisi,id',
            'id_sub_divisi' => 'required|exists:tbl_data_sub_divisi,id',
            'id_level' => 'required|exists:tbl_data_level,id',
        ]);
        if (array_key_exists('id_atasan', $validated) && $validated['id_atasan'] === '') {
            $validated['id_atasan'] = null;
        }
        $jabatan = Jabatan::find($id);
        if (!$jabatan) {
            return response()->json(['success' => false, 'message' => 'Jabatan tidak ditemukan'], 404);
        }
        $oldData = $jabatan->toArray();
        $jabatan->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'jabatans',
            'description' => 'Mengupdate jabatan: ' . $jabatan->nama_jabatan,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $jabatan->fresh()->toArray(),
        ]);
        return response()->json(['success' => true, 'data' => $jabatan->fresh()->load(['atasan', 'divisi', 'subDivisi', 'level'])]);
    }

    /**
     * API: Soft delete (set status N).
     */
    public function apiDestroy($id)
    {
        $jabatan = Jabatan::find($id);
        if (!$jabatan) {
            return response()->json(['success' => false, 'message' => 'Jabatan tidak ditemukan'], 404);
        }
        $oldData = $jabatan->toArray();
        $jabatan->update(['status' => 'N']);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'jabatans',
            'description' => 'Menonaktifkan jabatan: ' . $jabatan->nama_jabatan,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $jabatan->fresh()->toArray(),
        ]);
        return response()->json(['success' => true]);
    }

    /**
     * API: Toggle status A/N.
     */
    public function apiToggleStatus($id)
    {
        $jabatan = Jabatan::find($id);
        if (!$jabatan) {
            return response()->json(['success' => false, 'message' => 'Jabatan tidak ditemukan'], 404);
        }
        $newStatus = $jabatan->status === 'A' ? 'N' : 'A';
        $jabatan->update(['status' => $newStatus]);
        return response()->json(['success' => true, 'data' => $jabatan->fresh()]);
    }

    public function debugDatabase()
    {
        try {
            $debug = [];
            
            // Check database connection
            $debug['connection'] = 'Connected';
            
            // Check if tables exist
            $tables = ['tbl_data_jabatan', 'tbl_data_divisi', 'tbl_data_sub_divisi', 'tbl_data_level'];
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
                $debug['models']['jabatan'] = [
                    'total' => Jabatan::count(),
                    'active' => Jabatan::where('status', 'A')->count(),
                    'sample' => Jabatan::where('status', 'A')->first()
                ];
            } catch (\Exception $e) {
                $debug['models']['jabatan'] = ['error' => $e->getMessage()];
            }
            
            try {
                $debug['models']['divisi'] = [
                    'total' => Divisi::count(),
                    'active' => Divisi::where('status', 'A')->count(),
                    'sample' => Divisi::where('status', 'A')->first()
                ];
            } catch (\Exception $e) {
                $debug['models']['divisi'] = ['error' => $e->getMessage()];
            }
            
            try {
                $debug['models']['subDivisi'] = [
                    'total' => SubDivisi::count(),
                    'active' => SubDivisi::where('status', 'A')->count(),
                    'sample' => SubDivisi::where('status', 'A')->first()
                ];
            } catch (\Exception $e) {
                $debug['models']['subDivisi'] = ['error' => $e->getMessage()];
            }
            
            try {
                $debug['models']['dataLevel'] = [
                    'total' => DataLevel::count(),
                    'active' => DataLevel::where('status', 'A')->count(),
                    'sample' => DataLevel::where('status', 'A')->first()
                ];
            } catch (\Exception $e) {
                $debug['models']['dataLevel'] = ['error' => $e->getMessage()];
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
} 