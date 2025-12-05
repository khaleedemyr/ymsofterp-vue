<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvestorController extends Controller
{
    public function index()
    {
        try {
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
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch investors: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create investor: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
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
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update investor: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::table('investor_outlet')->where('investor_id', $id)->delete();
            DB::table('investors')->where('id', $id)->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete investor: ' . $e->getMessage()], 500);
        }
    }

    public function outlets()
    {
        try {
            // First check if table exists and has data
            $totalOutlets = DB::table('tbl_data_outlet')->count();
            
            if ($totalOutlets == 0) {
                return response()->json([
                    'error' => 'No outlets found in database',
                    'outlets' => []
                ], 404);
            }

            // Get active outlets
            $activeOutlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->get();

            // If no active outlets, get all outlets and show warning
            if ($activeOutlets->count() == 0) {
                $allOutlets = DB::table('tbl_data_outlet')
                    ->select('id_outlet as id', 'nama_outlet as name', 'status')
                    ->get();
                
                return response()->json([
                    'warning' => 'No outlets with status "A" found. Showing all outlets.',
                    'outlets' => $allOutlets->map(function($outlet) {
                        return [
                            'id' => $outlet->id,
                            'name' => $outlet->name . ' (Status: ' . $outlet->status . ')'
                        ];
                    })
                ]);
            }

            return response()->json($activeOutlets);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch outlets: ' . $e->getMessage()], 500);
        }
    }
} 