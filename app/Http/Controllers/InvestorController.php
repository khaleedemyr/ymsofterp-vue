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

    public function apiMasterCreateData()
    {
        try {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();

            return response()->json([
                'success' => true,
                'outlets' => $outlets,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch outlets: '.$e->getMessage(),
            ], 500);
        }
    }

    public function apiMasterIndex(Request $request)
    {
        try {
            $query = DB::table('investors');
            if ($request->filled('search')) {
                $search = trim((string) $request->query('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $perPage = (int) ($request->query('per_page') ?? 10);
            $perPage = max(1, min(100, $perPage));
            $investors = $query->orderByDesc('id')->paginate($perPage);

            $data = collect($investors->items())->map(function ($inv) {
                $outletObjs = DB::table('investor_outlet')
                    ->join('tbl_data_outlet', 'investor_outlet.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                    ->where('investor_outlet.investor_id', $inv->id)
                    ->where('tbl_data_outlet.status', 'A')
                    ->select('tbl_data_outlet.id_outlet as id', 'tbl_data_outlet.nama_outlet as name')
                    ->get()
                    ->toArray();

                $inv->outlets = $outletObjs;
                $inv->outlet_ids = array_map(fn ($o) => $o->id, $outletObjs);
                return $inv;
            })->values();

            return response()->json([
                'success' => true,
                'investors' => [
                    'current_page' => $investors->currentPage(),
                    'data' => $data,
                    'last_page' => $investors->lastPage(),
                    'per_page' => $investors->perPage(),
                    'total' => $investors->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch investors: '.$e->getMessage(),
            ], 500);
        }
    }

    public function apiMasterStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'integer|exists:tbl_data_outlet,id_outlet',
        ]);

        try {
            $id = DB::table('investors')->insertGetId([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (($validated['outlet_ids'] ?? []) as $outletId) {
                DB::table('investor_outlet')->insert([
                    'investor_id' => $id,
                    'outlet_id' => $outletId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Investor berhasil ditambahkan',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create investor: '.$e->getMessage(),
            ], 500);
        }
    }

    public function apiMasterUpdate(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'integer|exists:tbl_data_outlet,id_outlet',
        ]);

        try {
            DB::table('investors')->where('id', $id)->update([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'updated_at' => now(),
            ]);

            DB::table('investor_outlet')->where('investor_id', $id)->delete();
            foreach (($validated['outlet_ids'] ?? []) as $outletId) {
                DB::table('investor_outlet')->insert([
                    'investor_id' => $id,
                    'outlet_id' => $outletId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Investor berhasil diupdate',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update investor: '.$e->getMessage(),
            ], 500);
        }
    }

    public function apiMasterDestroy(int $id)
    {
        try {
            DB::table('investor_outlet')->where('investor_id', $id)->delete();
            DB::table('investors')->where('id', $id)->delete();
            return response()->json([
                'success' => true,
                'message' => 'Investor berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete investor: '.$e->getMessage(),
            ], 500);
        }
    }
} 