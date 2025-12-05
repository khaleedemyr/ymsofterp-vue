<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Jabatan;
use App\Models\Outlet;

class OrganizationChartController extends Controller
{
    public function __construct()
    {
        // Middleware akan dihandle di routes
    }

    public function index()
    {
        // Get all outlets for selection
        $outlets = Outlet::active()
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return inertia('OrganizationChart/Index', [
            'outlets' => $outlets
        ]);
    }

    public function getOrganizationData(Request $request)
    {
        try {
            $outletId = $request->get('outlet_id', 1);
            
            \Log::info("Debug Organization Chart - Outlet ID: {$outletId}");
            
            // Get simple organization data
            $organizationData = DB::table('users as u')
                ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
                ->leftJoin('tbl_data_divisi as d', 'j.id_divisi', '=', 'd.id')
                ->leftJoin('tbl_data_jabatan as atasan', 'j.id_atasan', '=', 'atasan.id_jabatan')
                ->where('u.id_outlet', $outletId)
                ->where('u.status', 'A')
                ->where('j.status', 'A')
                ->where('atasan.status', 'A') // Filter atasan yang aktif juga
                ->select([
                    'u.id',
                    'u.nama_lengkap',
                    'u.id_jabatan',
                    'u.avatar',
                    'j.nama_jabatan',
                    'j.id_atasan',
                    'j.id_level',
                    'j.id_divisi',
                    'l.nama_level',
                    'l.nilai_level',
                    'd.nama_divisi',
                    'atasan.nama_jabatan as atasan_jabatan'
                ])
                ->orderBy('j.id_atasan', 'asc')
                ->orderBy('u.nama_lengkap', 'asc')
                ->get();
            
            \Log::info("Debug Organization Chart - Data count: " . $organizationData->count());
            \Log::info("Debug Organization Chart - Sample data: " . json_encode($organizationData->take(2)));
            
            // Get outlet info
            $outlet = Outlet::find($outletId);
            
            return response()->json([
                'success' => true,
                'data' => $organizationData,
                'outlet' => $outlet ? [
                    'id_outlet' => $outlet->id_outlet,
                    'nama_outlet' => $outlet->nama_outlet
                ] : null,
                'message' => 'Data struktur organisasi berhasil dimuat',
                'debug' => [
                    'outlet_id' => $outletId,
                    'data_count' => $organizationData->count(),
                    'sample_data' => $organizationData->take(2)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Organization Chart Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data struktur organisasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process organization tree to include additional information
     */
    private function processOrganizationTree($tree)
    {
        return $tree->map(function ($jabatan) {
            $jabatanData = [
                'id_jabatan' => $jabatan->id_jabatan,
                'nama_jabatan' => $jabatan->nama_jabatan,
                'id_atasan' => $jabatan->id_atasan,
                'id_level' => $jabatan->id_level,
                'id_divisi' => $jabatan->id_divisi,
                'id_sub_divisi' => $jabatan->id_sub_divisi,
                'level' => $jabatan->level ? [
                    'id' => $jabatan->level->id,
                    'nama_level' => $jabatan->level->nama_level,
                    'nilai_level' => $jabatan->level->nilai_level
                ] : null,
                'divisi' => $jabatan->divisi ? [
                    'id' => $jabatan->divisi->id,
                    'nama_divisi' => $jabatan->divisi->nama_divisi
                ] : null,
                'sub_divisi' => $jabatan->subDivisi ? [
                    'id' => $jabatan->subDivisi->id,
                    'nama_sub_divisi' => $jabatan->subDivisi->nama_sub_divisi
                ] : null,
                'atasan' => $jabatan->atasan ? [
                    'id_jabatan' => $jabatan->atasan->id_jabatan,
                    'nama_jabatan' => $jabatan->atasan->nama_jabatan
                ] : null,
                'employees' => $jabatan->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'avatar' => $user->avatar,
                        'email' => $user->email
                    ];
                }),
                'employee_count' => $jabatan->users->count(),
                'children' => $this->processOrganizationTree($jabatan->children)
            ];

            return $jabatanData;
        });
    }

    /**
     * Get all outlets for organization chart
     */
    public function getOutlets()
    {
        try {
            $outlets = Outlet::active()
                ->select('id_outlet', 'nama_outlet', 'lokasi')
                ->orderBy('nama_outlet')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $outlets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data outlet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get organization structure for specific outlet (alternative method)
     */
    public function getOrganizationByOutlet($outletId)
    {
        try {
            $outlet = Outlet::find($outletId);
            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet tidak ditemukan'
                ], 404);
            }

            // Get root jabatans (top level positions)
            $rootJabatans = Jabatan::getRootJabatans($outletId);
            
            // Build complete tree structure
            $tree = $this->buildCompleteTree($rootJabatans, $outletId);
            
            return response()->json([
                'success' => true,
                'data' => $tree,
                'outlet' => [
                    'id_outlet' => $outlet->id_outlet,
                    'nama_outlet' => $outlet->nama_outlet,
                    'lokasi' => $outlet->lokasi
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat struktur organisasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build complete tree structure recursively
     */
    private function buildCompleteTree($jabatans, $outletId)
    {
        return $jabatans->map(function ($jabatan) use ($outletId) {
            // Get employees for this position in this outlet
            $employees = $jabatan->users()
                ->where('status', 'A')
                ->where('id_outlet', $outletId)
                ->get();

            // Get subordinates (bawahan)
            $subordinates = Jabatan::active()
                ->where('id_atasan', $jabatan->id_jabatan)
                ->where('status', 'A')
                ->with(['level', 'divisi', 'subDivisi'])
                ->get();

            return [
                'id_jabatan' => $jabatan->id_jabatan,
                'nama_jabatan' => $jabatan->nama_jabatan,
                'id_atasan' => $jabatan->id_atasan,
                'level' => $jabatan->level ? [
                    'id' => $jabatan->level->id,
                    'nama_level' => $jabatan->level->nama_level,
                    'nilai_level' => $jabatan->level->nilai_level
                ] : null,
                'divisi' => $jabatan->divisi ? [
                    'id' => $jabatan->divisi->id,
                    'nama_divisi' => $jabatan->divisi->nama_divisi
                ] : null,
                'sub_divisi' => $jabatan->subDivisi ? [
                    'id' => $jabatan->subDivisi->id,
                    'nama_sub_divisi' => $jabatan->subDivisi->nama_sub_divisi
                ] : null,
                'employees' => $employees->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'avatar' => $user->avatar,
                        'email' => $user->email
                    ];
                }),
                'employee_count' => $employees->count(),
                'children' => $this->buildCompleteTree($subordinates, $outletId)
            ];
        });
    }

    public function debugData()
    {
        try {
            // Check users table
            $users = DB::table('users')->where('status', 'A')->get();
            $usersOutlet1 = DB::table('users')->where('id_outlet', 1)->where('status', 'A')->get();
            
            // Check jabatan table
            $jabatan = DB::table('tbl_data_jabatan')->where('status', 'A')->get();
            
            // Check join result without outlet filter
            $joinResultAll = DB::table('users as u')
                ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->where('u.status', 'A')
                ->where('j.status', 'A')
                ->select(['u.id', 'u.nama_lengkap', 'u.id_jabatan', 'u.id_outlet', 'j.nama_jabatan', 'j.id_atasan'])
                ->get();
                
            // Check join result with outlet filter
            $joinResultOutlet1 = DB::table('users as u')
                ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
                ->where('u.id_outlet', 1)
                ->where('u.status', 'A')
                ->where('j.status', 'A')
                ->select(['u.id', 'u.nama_lengkap', 'u.id_jabatan', 'u.id_outlet', 'j.nama_jabatan', 'j.id_atasan', 'j.id_level', 'l.nama_level', 'l.nilai_level'])
                ->get();

            // Analyze hierarchy structure
            $atasanIds = $joinResultOutlet1->pluck('id_atasan')->filter()->unique()->values();
            $jabatanIds = $joinResultOutlet1->pluck('id_jabatan')->unique()->values();
            $topLevel = $joinResultOutlet1->filter(function($emp) use ($jabatanIds) {
                return !$jabatanIds->contains($emp->id_atasan);
            });
            
            // Check specifically for jabatan 149
            $jabatan149 = $joinResultOutlet1->where('id_jabatan', 149);
            $jabatan149Count = $jabatan149->count();

            // Analyze complete hierarchy structure from all jabatan
            $allJabatan = DB::table('tbl_data_jabatan')
                ->where('status', 'A')
                ->select('id_jabatan', 'nama_jabatan', 'id_atasan')
                ->get();

            // Find root jabatan (those not referenced as atasan)
            $allAtasanIds = $allJabatan->pluck('id_atasan')->filter()->unique();
            $rootJabatan = $allJabatan->whereNotIn('id_jabatan', $allAtasanIds);

            // Calculate hierarchy depth
            $maxDepth = 0;
            $processed = [];
            
            $calculateDepth = function($id, $jabatan, $depth = 0) use (&$maxDepth, &$processed, &$calculateDepth) {
                if (in_array($id, $processed)) return $depth;
                $processed[] = $id;
                
                $children = $jabatan->where('id_atasan', $id);
                if ($children->count() > 0) {
                    foreach($children as $child) {
                        $childDepth = $calculateDepth($child->id_jabatan, $jabatan, $depth + 1);
                        $maxDepth = max($maxDepth, $childDepth);
                    }
                }
                return $depth;
            };

            foreach($rootJabatan as $root) {
                $calculateDepth($root->id_jabatan, $allJabatan);
            }

            return response()->json([
                'success' => true,
                'debug' => [
                    'users_count' => $users->count(),
                    'users_outlet_1_count' => $usersOutlet1->count(),
                    'jabatan_count' => $jabatan->count(),
                    'join_result_all_count' => $joinResultAll->count(),
                    'join_result_outlet_1_count' => $joinResultOutlet1->count(),
                    'users_outlet_1_sample' => $usersOutlet1->take(5),
                    'join_result_outlet_1_sample' => $joinResultOutlet1->take(5),
                    'hierarchy_analysis' => [
                        'unique_atasan_ids' => $atasanIds,
                        'unique_jabatan_ids' => $jabatanIds,
                        'top_level_employees' => $topLevel->take(5),
                        'top_level_count' => $topLevel->count(),
                        'jabatan_149_found' => $jabatan149Count > 0,
                        'jabatan_149_count' => $jabatan149Count,
                        'jabatan_149_data' => $jabatan149->take(3)
                    ],
                    'complete_hierarchy_analysis' => [
                        'total_jabatan_in_db' => $allJabatan->count(),
                        'root_jabatan_count' => $rootJabatan->count(),
                        'root_jabatan_list' => $rootJabatan->map(function($j) {
                            return ['id' => $j->id_jabatan, 'nama' => $j->nama_jabatan];
                        })->toArray(),
                        'max_hierarchy_depth' => $maxDepth,
                        'sample_jabatan_with_atasan' => $allJabatan->take(30)->map(function($j) {
                            return ['id' => $j->id_jabatan, 'nama' => $j->nama_jabatan, 'atasan' => $j->id_atasan];
                        })->toArray()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Debug error: ' . $e->getMessage()
            ], 500);
        }
    }
}

