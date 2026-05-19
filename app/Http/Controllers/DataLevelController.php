<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\DataLevel;
use App\Models\BpjsKategori;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class DataLevelController extends Controller
{
    /**
     * Normalisasi id_bpjs_kategori kosong / string ke null atau int (untuk validasi exists).
     */
    protected function prepareBpjsKategoriInput(Request $request): void
    {
        $raw = $request->input('id_bpjs_kategori');
        if ($raw === '' || $raw === null) {
            $request->merge(['id_bpjs_kategori' => null]);
        } elseif (is_numeric($raw)) {
            $request->merge(['id_bpjs_kategori' => (int) $raw]);
        }
    }

    /**
     * Sinkron kolom legacy nilai_dasar_potongan_bpjs (max dari kesehatan & ketenagakerjaan).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function syncLegacyDasarPotonganBpjs(array $validated): array
    {
        $kes = (int) ($validated['nilai_dasar_potongan_bpjs_kesehatan'] ?? 0);
        $tk = (int) ($validated['nilai_dasar_potongan_bpjs_ketenagakerjaan'] ?? 0);
        $validated['nilai_dasar_potongan_bpjs'] = max($kes, $tk);

        return $validated;
    }

    public function index(Request $request)
    {
        $query = DataLevel::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama_level', 'like', "%$search%")
                  ->orWhere('nilai_level', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 'A' : 'N';
            $query->where('status', $status);
        }
        $dataLevels = $query->with(['bpjsKategori:id,nama_kategori'])
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();
        $bpjsKategoriOptions = BpjsKategori::query()
            ->where('status', 'A')
            ->orderBy('nama_kategori')
            ->get(['id', 'nama_kategori']);

        return Inertia::render('DataLevel/Index', [
            'dataLevels' => $dataLevels,
            'bpjsKategoriOptions' => $bpjsKategoriOptions,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->prepareBpjsKategoriInput($request);
        $validated = $request->validate([
            'nama_level' => 'required|string|max:100',
            'nilai_level' => 'required|string|max:100',
            'nilai_public_holiday' => 'required|integer|min:0',
            'nilai_dasar_potongan_bpjs_kesehatan' => 'required|integer|min:0',
            'nilai_dasar_potongan_bpjs_ketenagakerjaan' => 'required|integer|min:0',
            'id_bpjs_kategori' => 'nullable|integer|exists:tbl_bpjs_kategori,id',
            'nilai_point' => 'required|integer|min:0',
        ]);
        // Always set status to 'A' for new records
        $validated['status'] = 'A';
        $validated['id_bpjs_kategori'] = $validated['id_bpjs_kategori'] ?? null;
        $validated = $this->syncLegacyDasarPotonganBpjs($validated);
        $dataLevel = DataLevel::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'data_levels',
            'description' => 'Menambahkan data level baru: ' . $dataLevel->nama_level,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $dataLevel->toArray(),
        ]);
        return redirect()->route('data-levels.index')->with('success', 'Data Level berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $this->prepareBpjsKategoriInput($request);
        $validated = $request->validate([
            'nama_level' => 'required|string|max:100',
            'nilai_level' => 'required|string|max:100',
            'nilai_public_holiday' => 'required|integer|min:0',
            'nilai_dasar_potongan_bpjs_kesehatan' => 'required|integer|min:0',
            'nilai_dasar_potongan_bpjs_ketenagakerjaan' => 'required|integer|min:0',
            'id_bpjs_kategori' => 'nullable|integer|exists:tbl_bpjs_kategori,id',
            'nilai_point' => 'required|integer|min:0',
        ]);
        $validated['id_bpjs_kategori'] = $validated['id_bpjs_kategori'] ?? null;
        $validated = $this->syncLegacyDasarPotonganBpjs($validated);
        // Don't update status in edit mode
        $dataLevel = DataLevel::findOrFail($id);
        $oldData = $dataLevel->toArray();
        $dataLevel->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'data_levels',
            'description' => 'Mengupdate data level: ' . $dataLevel->nama_level,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $dataLevel->fresh()->toArray(),
        ]);
        return redirect()->route('data-levels.index')->with('success', 'Data Level berhasil diupdate!');
    }

    public function destroy($id)
    {
        $dataLevel = DataLevel::findOrFail($id);
        $oldData = $dataLevel->toArray();
        $dataLevel->update(['status' => 'N']);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'data_levels',
            'description' => 'Menonaktifkan data level: ' . $dataLevel->nama_level,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $dataLevel->fresh()->toArray(),
        ]);
        return redirect()->route('data-levels.index')->with('success', 'Data Level berhasil dinonaktifkan!');
    }

    public function toggleStatus($id, Request $request)
    {
        $dataLevel = DataLevel::findOrFail($id);
        $newStatus = $dataLevel->status === 'A' ? 'N' : 'A';
        $dataLevel->update(['status' => $newStatus]);
        return response()->json(['success' => true]);
    }

    /**
     * API: List data levels for mobile app.
     */
    public function apiIndex(Request $request)
    {
        $query = DataLevel::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_level', 'like', "%$search%")
                    ->orWhere('nilai_level', 'like', "%$search%");
            });
        }
        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 'A' : ($request->status === 'inactive' ? 'N' : null);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }
        $perPage = (int) $request->get('per_page', 15);
        $paginator = $query->with(['bpjsKategori:id,nama_kategori'])
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
        return response()->json($paginator);
    }

    /**
     * API: Show single data level.
     */
    public function apiShow($id)
    {
        $dataLevel = DataLevel::with('bpjsKategori')->findOrFail($id);

        return response()->json($dataLevel);
    }

    /**
     * API: Store new data level.
     */
    public function apiStore(Request $request)
    {
        $this->prepareBpjsKategoriInput($request);
        $validated = $request->validate([
            'nama_level' => 'required|string|max:100',
            'nilai_level' => 'required|string|max:100',
            'nilai_public_holiday' => 'required|integer|min:0',
            'nilai_dasar_potongan_bpjs_kesehatan' => 'required|integer|min:0',
            'nilai_dasar_potongan_bpjs_ketenagakerjaan' => 'required|integer|min:0',
            'id_bpjs_kategori' => 'nullable|integer|exists:tbl_bpjs_kategori,id',
            'nilai_point' => 'required|integer|min:0',
        ]);
        $validated['status'] = 'A';
        $validated['id_bpjs_kategori'] = $validated['id_bpjs_kategori'] ?? null;
        $validated = $this->syncLegacyDasarPotonganBpjs($validated);
        $dataLevel = DataLevel::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'data_levels',
            'description' => 'Menambahkan data level baru: ' . $dataLevel->nama_level,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $dataLevel->toArray(),
        ]);

        return response()->json(['success' => true, 'data' => $dataLevel->load('bpjsKategori')]);
    }

    /**
     * API: Update data level.
     */
    public function apiUpdate(Request $request, $id)
    {
        $this->prepareBpjsKategoriInput($request);
        $validated = $request->validate([
            'nama_level' => 'required|string|max:100',
            'nilai_level' => 'required|string|max:100',
            'nilai_public_holiday' => 'required|integer|min:0',
            'nilai_dasar_potongan_bpjs_kesehatan' => 'required|integer|min:0',
            'nilai_dasar_potongan_bpjs_ketenagakerjaan' => 'required|integer|min:0',
            'id_bpjs_kategori' => 'nullable|integer|exists:tbl_bpjs_kategori,id',
            'nilai_point' => 'required|integer|min:0',
        ]);
        $validated['id_bpjs_kategori'] = $validated['id_bpjs_kategori'] ?? null;
        $validated = $this->syncLegacyDasarPotonganBpjs($validated);
        $dataLevel = DataLevel::findOrFail($id);
        $oldData = $dataLevel->toArray();
        $dataLevel->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'data_levels',
            'description' => 'Mengupdate data level: ' . $dataLevel->nama_level,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $dataLevel->fresh()->toArray(),
        ]);

        return response()->json(['success' => true, 'data' => $dataLevel->fresh()->load('bpjsKategori')]);
    }

    /**
     * API: Soft delete (set status N).
     */
    public function apiDestroy($id)
    {
        $dataLevel = DataLevel::findOrFail($id);
        $oldData = $dataLevel->toArray();
        $dataLevel->update(['status' => 'N']);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'data_levels',
            'description' => 'Menonaktifkan data level: ' . $dataLevel->nama_level,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $dataLevel->fresh()->toArray(),
        ]);
        return response()->json(['success' => true]);
    }

    /**
     * API: Toggle status A/N.
     */
    public function apiToggleStatus($id)
    {
        $dataLevel = DataLevel::findOrFail($id);
        $newStatus = $dataLevel->status === 'A' ? 'N' : 'A';
        $dataLevel->update(['status' => $newStatus]);
        return response()->json(['success' => true, 'data' => $dataLevel->fresh()]);
    }
} 