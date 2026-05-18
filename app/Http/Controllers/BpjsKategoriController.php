<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BpjsKategori;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BpjsKategoriController extends Controller
{
    public function index(Request $request)
    {
        $query = BpjsKategori::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('nama_kategori', 'like', "%{$search}%");
        }
        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 'A' : 'N';
            $query->where('status', $status);
        } else {
            $query->where('status', 'A');
        }
        $rows = $query->orderBy('id')->paginate(15)->withQueryString();

        return Inertia::render('BpjsKategori/Index', [
            'bpjsKategoris' => $rows,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated['status'] = 'A';
        $row = BpjsKategori::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'bpjs_kategori',
            'description' => 'Menambah kategori BPJS: '.$row->nama_kategori,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $row->toArray(),
        ]);

        return redirect()->route('bpjs-kategori.index')->with('success', 'Kategori BPJS berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate($this->rules());
        $row = BpjsKategori::findOrFail($id);
        $old = $row->toArray();
        $row->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'bpjs_kategori',
            'description' => 'Update kategori BPJS: '.$row->nama_kategori,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $old,
            'new_data' => $row->fresh()->toArray(),
        ]);

        return redirect()->route('bpjs-kategori.index')->with('success', 'Kategori BPJS berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $row = BpjsKategori::findOrFail($id);
        $old = $row->toArray();
        $row->update(['status' => 'N']);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'bpjs_kategori',
            'description' => 'Menonaktifkan kategori BPJS: '.$row->nama_kategori,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $old,
            'new_data' => $row->fresh()->toArray(),
        ]);

        return redirect()->route('bpjs-kategori.index')->with('success', 'Kategori BPJS dinonaktifkan.');
    }

    public function toggleStatus($id)
    {
        $row = BpjsKategori::findOrFail($id);
        $row->update(['status' => $row->status === 'A' ? 'N' : 'A']);

        return response()->json(['success' => true]);
    }

    private function rules(): array
    {
        return [
            'nama_kategori' => 'required|string|max:150',
            'pct_kes_perusahaan' => 'required|numeric|min:0|max:100',
            'pct_kes_karyawan' => 'required|numeric|min:0|max:100',
            'pct_jht_perusahaan' => 'required|numeric|min:0|max:100',
            'pct_jp_perusahaan' => 'required|numeric|min:0|max:100',
            'pct_jkk_perusahaan' => 'required|numeric|min:0|max:100',
            'pct_jkm_perusahaan' => 'required|numeric|min:0|max:100',
            'pct_jht_karyawan' => 'required|numeric|min:0|max:100',
            'pct_jp_karyawan' => 'required|numeric|min:0|max:100',
        ];
    }
}
