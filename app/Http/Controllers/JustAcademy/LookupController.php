<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function searchUsers(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['users' => []]);
        }

        $users = User::active()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'id_jabatan', 'id_outlet']);

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
}
