<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OutletMapDashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Outlets/OutletMapDashboard');
    }

    public function activeOutlets()
    {
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet', 'lat', 'long', 'lokasi', 'region_id', 'qr_code', 'keterangan')
            ->get();
        $outlets = $outlets->map(function($o) {
            $o->omzet = rand(10000000, 100000000);
            $o->omzet_today = rand(500000, 5000000);
            $o->omzet_mtd = rand(10000000, 100000000);
            return $o;
        });
        return response()->json($outlets);
    }
} 