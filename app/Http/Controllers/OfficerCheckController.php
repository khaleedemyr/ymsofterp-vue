<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OfficerCheckController extends Controller
{
    public function index()
    {
        return Inertia::render('OfficerCheck/Index');
    }

    public function getOCs()
    {
        $ocs = DB::table('officer_checks')
            ->join('users', 'officer_checks.user_id', '=', 'users.id')
            ->select('officer_checks.id', 'officer_checks.nilai', 'officer_checks.user_id', 'users.nama_lengkap as user_name')
            ->orderBy('officer_checks.id', 'desc')
            ->get();
        return response()->json($ocs);
    }

    public function store(Request $request)
    {
        // Ambil nama user
        $user = DB::table('users')->where('id', $request->user_id)->first();
        $userName = $user->nama_lengkap ?? $user->name ?? '';
        $id = DB::table('officer_checks')->insertGetId([
            'user_id' => $request->user_id,
            'user_name' => $userName,
            'nilai' => $request->nilai,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'id' => $id]);
    }

    public function update(Request $request, $id)
    {
        // Ambil nama user
        $user = DB::table('users')->where('id', $request->user_id)->first();
        $userName = $user->nama_lengkap ?? $user->name ?? '';
        DB::table('officer_checks')->where('id', $id)->update([
            'user_id' => $request->user_id,
            'user_name' => $userName,
            'nilai' => $request->nilai,
            'updated_at' => now(),
        ]);
        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        DB::table('officer_checks')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function users()
    {
        $users = DB::table('users')
            ->where('status', 'A')
            ->select('id', 'nama_lengkap as name')
            ->orderBy('nama_lengkap')
            ->get();
        return response()->json($users);
    }

    public function apiMasterCreateData()
    {
        $users = DB::table('users')
            ->where('status', 'A')
            ->select('id', 'nama_lengkap as name')
            ->orderBy('nama_lengkap')
            ->get();

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }

    public function apiMasterIndex(Request $request)
    {
        $query = DB::table('officer_checks')
            ->join('users', 'officer_checks.user_id', '=', 'users.id')
            ->select(
                'officer_checks.id',
                'officer_checks.nilai',
                'officer_checks.user_id',
                'users.nama_lengkap as user_name'
            );

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where('users.nama_lengkap', 'like', "%{$search}%");
        }

        $perPage = (int) ($request->query('per_page') ?? 10);
        $perPage = max(1, min(100, $perPage));
        $officerChecks = $query->orderByDesc('officer_checks.id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'officerChecks' => $officerChecks,
        ]);
    }

    public function apiMasterStore(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'nilai' => 'required|numeric',
        ]);

        $user = DB::table('users')->where('id', $validated['user_id'])->first();
        $userName = $user->nama_lengkap ?? $user->name ?? '';
        $id = DB::table('officer_checks')->insertGetId([
            'user_id' => $validated['user_id'],
            'user_name' => $userName,
            'nilai' => $validated['nilai'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Officer check berhasil ditambahkan',
            'id' => $id,
        ]);
    }

    public function apiMasterUpdate(Request $request, int $id)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'nilai' => 'required|numeric',
        ]);

        $user = DB::table('users')->where('id', $validated['user_id'])->first();
        $userName = $user->nama_lengkap ?? $user->name ?? '';
        DB::table('officer_checks')->where('id', $id)->update([
            'user_id' => $validated['user_id'],
            'user_name' => $userName,
            'nilai' => $validated['nilai'],
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Officer check berhasil diupdate',
        ]);
    }

    public function apiMasterDestroy(int $id)
    {
        DB::table('officer_checks')->where('id', $id)->delete();
        return response()->json([
            'success' => true,
            'message' => 'Officer check berhasil dihapus',
        ]);
    }
} 