<?php

namespace App\Http\Controllers;

use App\Models\Departemen;
use App\Models\Area;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MasterReportController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $type = $request->get('type', 'departemen'); // 'departemen' or 'area'
        $status = $request->get('status', 'A');
        $perPage = $request->get('per_page', 15);

        $query = null;
        $data = null;

        if ($type === 'departemen') {
            $query = Departemen::query();
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('nama_departemen', 'like', "%{$search}%")
                      ->orWhere('kode_departemen', 'like', "%{$search}%")
                      ->orWhere('deskripsi', 'like', "%{$search}%");
                });
            }
            
            if ($status !== 'all') {
                $query->where('status', $status);
            }
            
            $data = $query->orderBy('nama_departemen')->paginate($perPage)->withQueryString();
        } else {
            $query = Area::with('departemen');
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('nama_area', 'like', "%{$search}%")
                      ->orWhere('kode_area', 'like', "%{$search}%")
                      ->orWhere('deskripsi', 'like', "%{$search}%")
                      ->orWhereHas('departemen', function($depQuery) use ($search) {
                          $depQuery->where('nama_departemen', 'like', "%{$search}%");
                      });
                });
            }
            
            if ($status !== 'all') {
                $query->where('status', $status);
            }
            
            $data = $query->orderBy('nama_area')->paginate($perPage)->withQueryString();
        }

        // Get statistics
        $statistics = [
            'total' => $type === 'departemen' ? Departemen::count() : Area::count(),
            'active' => $type === 'departemen' ? Departemen::where('status', 'A')->count() : Area::where('status', 'A')->count(),
            'inactive' => $type === 'departemen' ? Departemen::where('status', 'N')->count() : Area::where('status', 'N')->count(),
        ];

        // Get dropdown data
        $departemens = Departemen::active()->orderBy('nama_departemen')->get();

        return Inertia::render('MasterReport/Index', [
            'data' => $data,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'statistics' => $statistics,
            'departemens' => $departemens,
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->get('type');
        
        if ($type === 'departemen') {
            $validator = Validator::make($request->all(), [
                'nama_departemen' => 'required|string|max:255',
                'kode_departemen' => 'required|string|max:50|unique:departemens,kode_departemen',
                'deskripsi' => 'nullable|string',
                'status' => 'required|in:A,N',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $departemen = Departemen::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Departemen berhasil ditambahkan',
                'data' => $departemen
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'nama_area' => 'required|string|max:255',
                'departemen_id' => 'required|exists:departemens,id',
                'deskripsi' => 'nullable|string',
                'status' => 'required|in:A,N',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Auto generate kode area
            $kodeArea = $this->generateKodeArea($request->nama_area, $request->departemen_id);
            
            $areaData = $request->all();
            $areaData['kode_area'] = $kodeArea;
            
            $area = Area::create($areaData);

            return response()->json([
                'success' => true,
                'message' => 'Area berhasil ditambahkan',
                'data' => $area->load('departemen')
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        $type = $request->get('type');
        
        if ($type === 'departemen') {
            $departemen = Departemen::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'nama_departemen' => 'required|string|max:255',
                'kode_departemen' => 'required|string|max:50|unique:departemens,kode_departemen,' . $id,
                'deskripsi' => 'nullable|string',
                'status' => 'required|in:A,N',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $departemen->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Departemen berhasil diperbarui',
                'data' => $departemen
            ]);
        } else {
            $area = Area::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'nama_area' => 'required|string|max:255',
                'departemen_id' => 'required|exists:departemens,id',
                'deskripsi' => 'nullable|string',
                'status' => 'required|in:A,N',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Auto generate kode area baru jika nama area berubah
            if ($area->nama_area !== $request->nama_area || $area->departemen_id !== $request->departemen_id) {
                $kodeArea = $this->generateKodeArea($request->nama_area, $request->departemen_id, $id);
                $request->merge(['kode_area' => $kodeArea]);
            }

            $area->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Area berhasil diperbarui',
                'data' => $area->load('departemen')
            ]);
        }
    }

    public function destroy($id, Request $request)
    {
        $type = $request->get('type');
        
        if ($type === 'departemen') {
            $departemen = Departemen::findOrFail($id);
            
            // Check if departemen has areas
            if ($departemen->areas()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus departemen yang memiliki area'
                ], 422);
            }
            
            $departemen->delete();

            return response()->json([
                'success' => true,
                'message' => 'Departemen berhasil dihapus'
            ]);
        } else {
            $area = Area::findOrFail($id);
            $area->delete();

            return response()->json([
                'success' => true,
                'message' => 'Area berhasil dihapus'
            ]);
        }
    }

    public function toggleStatus($id, Request $request)
    {
        $type = $request->get('type');
        
        if ($type === 'departemen') {
            $departemen = Departemen::findOrFail($id);
            $departemen->status = $departemen->status === 'A' ? 'N' : 'A';
            $departemen->save();

            return response()->json([
                'success' => true,
                'message' => 'Status departemen berhasil diubah',
                'data' => $departemen
            ]);
        } else {
            $area = Area::findOrFail($id);
            $area->status = $area->status === 'A' ? 'N' : 'A';
            $area->save();

            return response()->json([
                'success' => true,
                'message' => 'Status area berhasil diubah',
                'data' => $area->load('departemen')
            ]);
        }
    }

    /**
     * Generate kode area otomatis dengan sequence
     * 
     * @param string $namaArea
     * @param int $departemenId
     * @param int|null $excludeId (untuk update, exclude current record)
     * @return string
     */
    private function generateKodeArea($namaArea, $departemenId, $excludeId = null)
    {
        // Ambil kode departemen
        $departemen = Departemen::find($departemenId);
        $kodeDepartemen = $departemen ? strtoupper(substr($departemen->kode_departemen, 0, 3)) : 'OPS';
        
        // Cari kode area terakhir berdasarkan departemen
        $lastArea = Area::where('departemen_id', $departemenId);
        if ($excludeId) {
            $lastArea->where('id', '!=', $excludeId);
        }
        $lastArea = $lastArea->orderBy('kode_area', 'desc')->first();
        
        // Generate nomor urut berikutnya
        $nextNumber = 1;
        if ($lastArea && preg_match('/OPS(\d{3})$/', $lastArea->kode_area, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        } else {
            // Jika tidak ada area sebelumnya, cari nomor terakhir dari semua area
            $lastAllArea = Area::orderBy('kode_area', 'desc')->first();
            if ($lastAllArea && preg_match('/OPS(\d{3})$/', $lastAllArea->kode_area, $matches)) {
                $nextNumber = (int)$matches[1] + 1;
            }
        }
        
        // Format kode dengan 3 digit
        $finalKode = 'OPS' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        
        // Pastikan kode unik (jika ada duplikasi, increment)
        $counter = 0;
        while (Area::where('kode_area', $finalKode)->where('id', '!=', $excludeId)->exists()) {
            $counter++;
            $nextNumber++;
            $finalKode = 'OPS' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            
            // Safety check untuk mencegah infinite loop
            if ($counter > 1000) {
                break;
            }
        }
        
        return $finalKode;
    }

    /**
     * Get next area code for preview
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNextAreaCode(Request $request)
    {
        $departemenId = $request->get('departemen_id');
        
        if (!$departemenId) {
            return response()->json([
                'success' => false,
                'message' => 'Departemen ID diperlukan'
            ], 400);
        }

        // Generate kode area berikutnya
        $nextCode = $this->generateKodeArea('', $departemenId);

        return response()->json([
            'success' => true,
            'next_code' => $nextCode
        ]);
    }
}
