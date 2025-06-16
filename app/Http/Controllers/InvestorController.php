<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvestorController extends Controller
{
    public function index()
    {
        $investors = DB::table('investors')
            ->select('id', 'name', 'email', 'phone')
            ->get()
            ->map(function($inv) {
                $outletObjs = DB::table('investor_outlet')
                    ->join('tbl_data_outlet', 'investor_outlet.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                    ->where('investor_outlet.investor_id', $inv->id)
                    ->where('tbl_data_outlet.status', 'A')
                    ->select('tbl_data_outlet.id_outlet as id', 'tbl_data_outlet.nama_outlet as name')
                    ->get()
                    ->toArray();
                $inv->outlets = $outletObjs;
                $inv->outlet_ids = array_map(fn($o) => $o->id, $outletObjs);
                return $inv;
            });
        return response()->json($investors);
    }

    public function store(Request $request)
    {
        $id = DB::table('investors')->insertGetId([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $outletIds = $request->outlet_ids ?? [];
        foreach ($outletIds as $outlet_id) {
            DB::table('investor_outlet')->insert([
                'investor_id' => $id,
                'outlet_id' => $outlet_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json(['success' => true]);
    }

    public function update(Request $request, $id)
    {
        DB::table('investors')->where('id', $id)->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'updated_at' => now(),
        ]);
        DB::table('investor_outlet')->where('investor_id', $id)->delete();
        $outletIds = $request->outlet_ids ?? [];
        foreach ($outletIds as $outlet_id) {
            DB::table('investor_outlet')->insert([
                'investor_id' => $id,
                'outlet_id' => $outlet_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        DB::table('investor_outlet')->where('investor_id', $id)->delete();
        DB::table('investors')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function outlets()
    {
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->get();

        return $outlets;
    }
} 