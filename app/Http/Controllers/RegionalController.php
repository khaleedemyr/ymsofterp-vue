<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\UserRegional;
use App\Models\User;
use App\Models\Outlet;

class RegionalController extends Controller
{
    public function index()
    {
        // Get only users who have regional assignments with jabatan and divisi
        $users = DB::table('users as u')
            ->join('user_regional as ur', 'u.id', '=', 'ur.user_id')
            ->join('tbl_data_outlet as o', 'ur.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->select(
                'u.id',
                'u.nama_lengkap as name',
                'u.email',
                'u.status',
                'u.avatar',
                'j.nama_jabatan',
                'd.nama_divisi',
                DB::raw('GROUP_CONCAT(DISTINCT o.nama_outlet ORDER BY o.nama_outlet SEPARATOR ", ") as outlets'),
                DB::raw('COUNT(DISTINCT ur.outlet_id) as outlet_count')
            )
            ->groupBy('u.id', 'u.nama_lengkap', 'u.email', 'u.status', 'u.avatar', 'j.nama_jabatan', 'd.nama_divisi')
            ->orderBy('u.nama_lengkap')
            ->get();

        return inertia('Regional/Index', [
            'users' => $users
        ]);
    }

    public function create()
    {
        // Get all outlets
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        // Debug: Log outlets
        \Log::info('Create outlets result', [
            'count' => $outlets->count(),
            'outlets' => $outlets->toArray()
        ]);

        return inertia('Regional/Create', [
            'outlets' => $outlets
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => 'exists:tbl_data_outlet,id_outlet'
        ]);

        try {
            DB::beginTransaction();

            // Delete existing regional assignments for this user
            UserRegional::where('user_id', $request->user_id)->delete();

            // Insert new regional assignments
            $insertData = [];
            foreach ($request->outlet_ids as $outletId) {
                $insertData[] = [
                    'user_id' => $request->user_id,
                    'outlet_id' => $outletId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            UserRegional::insert($insertData);

            DB::commit();

            return redirect()->route('regional.index')
                ->with('success', 'Regional assignment berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Gagal menyimpan regional assignment: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        
        // Get current regional assignments
        $currentOutlets = UserRegional::where('user_id', $id)
            ->pluck('outlet_id')
            ->toArray();

        // Get all outlets
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return inertia('Regional/Edit', [
            'user' => $user,
            'currentOutlets' => $currentOutlets,
            'outlets' => $outlets
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => 'exists:tbl_data_outlet,id_outlet'
        ]);

        try {
            DB::beginTransaction();

            // Delete existing regional assignments for this user
            UserRegional::where('user_id', $id)->delete();

            // Insert new regional assignments
            $insertData = [];
            foreach ($request->outlet_ids as $outletId) {
                $insertData[] = [
                    'user_id' => $id,
                    'outlet_id' => $outletId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            UserRegional::insert($insertData);

            DB::commit();

            return redirect()->route('regional.index')
                ->with('success', 'Regional assignment berhasil diupdate!');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Gagal mengupdate regional assignment: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            UserRegional::where('user_id', $id)->delete();

            return redirect()->route('regional.index')
                ->with('success', 'Regional assignment berhasil dihapus!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menghapus regional assignment: ' . $e->getMessage());
        }
    }

    public function searchUsers(Request $request)
    {
        $search = $request->get('search', '');
        
        // Debug: Log the search request
        \Log::info('Search users request', [
            'search' => $search,
            'request_params' => $request->all()
        ]);
        
        // Debug: Check total users in database
        $totalUsers = DB::table('users')->count();
        $activeUsers = DB::table('users')->where('status', 'A')->count();
        \Log::info('Total users in database', [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers
        ]);
        
        // Get all users with status='A' (no limit)
        $users = DB::table('users')
            ->where('status', 'A')
            ->where(function($query) use ($search) {
                if ($search) {
                    $query->where('nama_lengkap', 'like', '%' . $search . '%')
                          ->orWhere('email', 'like', '%' . $search . '%');
                }
            })
            ->select('id', 'nama_lengkap as name', 'email')
            ->orderBy('nama_lengkap')
            ->get();

        // Debug: Log the results
        \Log::info('Search users result', [
            'count' => $users->count(),
            'users' => $users->toArray()
        ]);
        
        // Debug: Show sample users from database
        $sampleUsers = DB::table('users')
            ->select('id', 'nama_lengkap', 'email', 'status', 'id_outlet')
            ->limit(5)
            ->get();
        \Log::info('Sample users from database', [
            'sample_users' => $sampleUsers->toArray()
        ]);

        return response()->json($users);
    }

    public function getUserOutlets($userId)
    {
        $outlets = DB::table('user_regional as ur')
            ->join('tbl_data_outlet as o', 'ur.outlet_id', '=', 'o.id_outlet')
            ->where('ur.user_id', $userId)
            ->select('o.id_outlet', 'o.nama_outlet')
            ->orderBy('o.nama_outlet')
            ->get();

        return response()->json($outlets);
    }
}
