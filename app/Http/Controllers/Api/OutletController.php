<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->input('q');
        $query = DB::table('tbl_data_outlet')->where('status', 'A');
        if ($q) {
            $query->where('nama_outlet', 'like', "%$q%") ;
        }
        return $query->limit(15)->get(['id_outlet', 'nama_outlet']);
    }
} 