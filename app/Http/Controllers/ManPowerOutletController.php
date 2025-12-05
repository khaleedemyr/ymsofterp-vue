<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Divisi;
use App\Models\Jabatan;
use App\Models\DataLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManPowerOutletController extends Controller
{
    public function index(Request $request)
    {
        // Build base query for filter
        $baseQuery = User::query()
            ->where('users.id_outlet', '!=', 1)
            ->where('users.status', 'A');
        if ($request->filled('search')) {
            $search = $request->search;
            $baseQuery->where(function($q) use ($search) {
                $q->where('users.nik', 'like', "%{$search}%")
                  ->orWhere('users.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('users.email', 'like', "%{$search}%");
            });
        }
        if ($request->filled('divisi_id')) {
            $baseQuery->where('users.division_id', $request->divisi_id);
        }
        if ($request->filled('jabatan_id')) {
            $baseQuery->where('users.id_jabatan', $request->jabatan_id);
        }
        if ($request->filled('outlet_id')) {
            $baseQuery->where('users.id_outlet', $request->outlet_id);
        }

        // Data utama (dengan relasi)
        $query = $baseQuery->clone()->with(['jabatan', 'divisi', 'outlet']);
        $users = $query->orderBy('users.nama_lengkap')->paginate(15)->appends($request->all());

        // Summary: total MP sesuai filter
        $totalMP = (clone $baseQuery)->count();

        // Summary: divisi sesuai filter
        $divisiSummary = (clone $baseQuery)
            ->selectRaw('divisis.nama_divisi, COUNT(users.id) as total_karyawan')
            ->join('tbl_data_divisi as divisis', 'users.division_id', '=', 'divisis.id')
            ->groupBy('divisis.id', 'divisis.nama_divisi')
            ->orderBy('divisis.nama_divisi')
            ->get();

        // Get dropdown data for filters
        $outlets = \App\Models\Outlet::active()->orderBy('nama_outlet')->get();
        
        // Debug: Log outlet data structure
        \Log::info('Outlet data structure:', [
            'count' => $outlets->count(),
            'first_outlet' => $outlets->first(),
            'outlet_keys' => $outlets->first() ? array_keys($outlets->first()->toArray()) : []
        ]);
        
        $dropdownData = [
            'divisis' => Divisi::active()->orderBy('nama_divisi')->get(),
            'jabatans' => Jabatan::active()->orderBy('nama_jabatan')->get(),
            'outlets' => $outlets,
        ];

        return Inertia::render('ManPowerOutlet/Index', [
            'users' => $users,
            'totalMP' => $totalMP,
            'divisiSummary' => $divisiSummary,
            'dropdownData' => $dropdownData,
            'filters' => $request->only(['search', 'divisi_id', 'jabatan_id', 'outlet_id']),
        ]);
    }
} 