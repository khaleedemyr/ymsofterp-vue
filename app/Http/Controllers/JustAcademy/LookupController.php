<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\Jabatan;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function searchUsers(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $jabatanId = $request->input('jabatan_id');
        $divisionId = $request->input('division_id');
        $outletId = $request->input('outlet_id');
        $sortBy = (string) $request->input('sort_by', 'name');

        $hasFilter = $jabatanId || $divisionId || $outletId;
        if (strlen($q) < 1 && !$hasFilter) {
            return response()->json(['users' => []]);
        }

        $query = User::query()
            ->select([
                'users.id',
                'users.nama_lengkap',
                'users.email',
                'users.id_jabatan',
                'users.id_outlet',
                'users.division_id',
                'tbl_data_jabatan.nama_jabatan as jabatan',
                'tbl_data_divisi.nama_divisi as divisi',
                'tbl_data_outlet.nama_outlet as outlet',
            ])
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->where('users.status', 'A');

        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('users.nama_lengkap', 'like', "%{$q}%")
                    ->orWhere('users.nama_panggilan', 'like', "%{$q}%")
                    ->orWhere('users.email', 'like', "%{$q}%")
                    ->orWhere('users.nik', 'like', "%{$q}%")
                    ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$q}%")
                    ->orWhere('tbl_data_divisi.nama_divisi', 'like', "%{$q}%")
                    ->orWhere('tbl_data_outlet.nama_outlet', 'like', "%{$q}%");
            });
        }

        if ($jabatanId) {
            $query->where('users.id_jabatan', $jabatanId);
        }
        if ($divisionId) {
            $query->where('users.division_id', $divisionId);
        }
        if ($outletId) {
            $query->where('users.id_outlet', $outletId);
        }

        match ($sortBy) {
            'jabatan' => $query->orderBy('tbl_data_jabatan.nama_jabatan')->orderBy('users.nama_lengkap'),
            'divisi' => $query->orderBy('tbl_data_divisi.nama_divisi')->orderBy('users.nama_lengkap'),
            'outlet' => $query->orderBy('tbl_data_outlet.nama_outlet')->orderBy('users.nama_lengkap'),
            default => $query->orderBy('users.nama_lengkap'),
        };

        $users = $query
            ->limit(40)
            ->get()
            ->map(fn ($user) => $this->formatUserOption($user));

        return response()->json(['users' => $users]);
    }

    public function jabatan()
    {
        $items = Jabatan::orderBy('nama_jabatan')->get(['id_jabatan', 'nama_jabatan']);

        return response()->json([
            'jabatan' => $items->map(fn ($j) => [
                'id' => $j->id_jabatan,
                'name' => $j->nama_jabatan,
            ]),
        ]);
    }

    public function divisi()
    {
        $items = Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']);

        return response()->json([
            'divisi' => $items->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->nama_divisi,
            ]),
        ]);
    }

    public function outlets()
    {
        $items = Outlet::orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);

        return response()->json([
            'outlets' => $items->map(fn ($o) => [
                'id' => $o->id_outlet,
                'name' => $o->nama_outlet,
            ]),
        ]);
    }

    protected function formatUserOption($user): array
    {
        $name = $user->nama_lengkap ?: "User #{$user->id}";

        return [
            'id' => $user->id,
            'name' => $name,
            'nama_lengkap' => $name,
            'email' => $user->email,
            'jabatan' => $user->jabatan,
            'divisi' => $user->divisi,
            'outlet' => $user->outlet,
            'id_jabatan' => $user->id_jabatan,
            'division_id' => $user->division_id,
            'id_outlet' => $user->id_outlet,
        ];
    }
}
