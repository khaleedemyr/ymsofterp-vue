<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\DataLevel;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class DataLevelController extends Controller
{
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
        $dataLevels = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        return Inertia::render('DataLevel/Index', [
            'dataLevels' => $dataLevels,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_level' => 'required|string|max:100',
            'nilai_level' => 'required|string|max:100',
            'nilai_public_holiday' => 'required|integer|min:0',
            'nilai_dasar_potongan_bpjs' => 'required|integer|min:0',
            'nilai_point' => 'required|integer|min:0',
        ]);
        // Always set status to 'A' for new records
        $validated['status'] = 'A';
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
        $validated = $request->validate([
            'nama_level' => 'required|string|max:100',
            'nilai_level' => 'required|string|max:100',
            'nilai_public_holiday' => 'required|integer|min:0',
            'nilai_dasar_potongan_bpjs' => 'required|integer|min:0',
            'nilai_point' => 'required|integer|min:0',
        ]);
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
} 