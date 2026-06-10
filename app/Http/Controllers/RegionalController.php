<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRegional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RegionalController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('users as u')
            ->join('user_regional as ur', 'u.id', '=', 'ur.user_id')
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
                'ur.area',
                'ur.target_outlet_visits',
                'ur.updated_at as assigned_at',
            )
            ->orderBy('u.nama_lengkap');

        if ($request->filled('status')) {
            $query->where('u.status', $request->get('status'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('u.nama_lengkap', 'like', '%' . $search . '%')
                    ->orWhere('u.email', 'like', '%' . $search . '%');
            });
        }

        return inertia('Regional/Index', [
            'users' => $query->get(),
            'filters' => [
                'status' => $request->get('status', ''),
                'search' => $request->get('search', ''),
            ],
        ]);
    }

    public function create()
    {
        return inertia('Regional/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
                Rule::unique('user_regional', 'user_id'),
            ],
            'area' => ['required', Rule::in(UserRegional::AREAS)],
            'target_outlet_visits' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        try {
            UserRegional::create([
                'user_id' => $request->user_id,
                'area' => $request->area,
                'target_outlet_visits' => $request->input('target_outlet_visits'),
            ]);

            return redirect()->route('regional.index')
                ->with('success', 'Regional assignment berhasil disimpan!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menyimpan regional assignment: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $assignment = UserRegional::where('user_id', $id)->first();

        return inertia('Regional/Edit', [
            'user' => $user,
            'currentArea' => $assignment?->area,
            'targetOutletVisits' => $assignment?->target_outlet_visits,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'area' => ['required', Rule::in(UserRegional::AREAS)],
            'target_outlet_visits' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        try {
            UserRegional::updateOrCreate(
                ['user_id' => $id],
                [
                    'area' => $request->area,
                    'target_outlet_visits' => $request->input('target_outlet_visits'),
                ],
            );

            return redirect()->route('regional.index')
                ->with('success', 'Regional assignment berhasil diupdate!');
        } catch (\Exception $e) {
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

        $users = DB::table('users')
            ->where('status', 'A')
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('nama_lengkap', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                }
            })
            ->select('id', 'nama_lengkap as name', 'email')
            ->orderBy('nama_lengkap')
            ->get();

        return response()->json($users);
    }

    public function getUserRegional($userId)
    {
        $assignment = UserRegional::where('user_id', $userId)->first();

        return response()->json([
            'user_id' => (int) $userId,
            'area' => $assignment?->area,
            'target_outlet_visits' => $assignment?->target_outlet_visits,
        ]);
    }
}
