<?php

namespace App\Http\Controllers;

use App\Models\ExternalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExternalReportUserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status', 'A');

        $query = ExternalUser::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nama_outlet', 'like', "%{$search}%");
            });
        }

        $users = $query
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ExternalUsers/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        $outlets = DB::table('tbl_data_outlet')
            ->select('qr_code', 'nama_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('ExternalUsers/Create', [
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:external_report_users,email'],
            'password' => ['required', 'string', 'min:6'],
            'kode_outlet' => ['required', 'string', 'max:50'],
            'status' => ['required', 'in:A,N'],
        ]);

        $outlet = DB::table('tbl_data_outlet')
            ->select('qr_code', 'nama_outlet')
            ->where('qr_code', $validated['kode_outlet'])
            ->first();

        if (!$outlet) {
            return back()->withErrors([
                'kode_outlet' => 'Outlet tidak ditemukan.',
            ]);
        }

        ExternalUser::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'kode_outlet' => $outlet->qr_code,
            'nama_outlet' => $outlet->nama_outlet,
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('external-report-users.index')
            ->with('success', 'User external berhasil ditambahkan.');
    }
}
