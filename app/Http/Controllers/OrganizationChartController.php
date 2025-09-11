<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationChartController extends Controller
{
    public function index()
    {
        return inertia('OrganizationChart/Index');
    }

    public function getOrganizationData()
    {
        try {
            // Debug: Check if there's data in users table
            $usersCount = DB::table('users')->where('status', 'A')->count();
            $usersOutlet1Count = DB::table('users')->where('id_outlet', 1)->where('status', 'A')->count();
            $jabatanCount = DB::table('tbl_data_jabatan')->where('status', 'A')->count();
            
            \Log::info("Debug Organization Chart - Users count: {$usersCount}, Users outlet 1 count: {$usersOutlet1Count}, Jabatan count: {$jabatanCount}");
            
            // Query untuk mengambil data organisasi dengan join
            $organizationData = DB::table('users as u')
                ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
                ->leftJoin('tbl_data_jabatan as atasan', 'j.id_atasan', '=', 'atasan.id_jabatan')
                ->leftJoin('users as atasan_user', 'atasan.id_jabatan', '=', 'atasan_user.id_jabatan')
                ->where('u.id_outlet', 1)
                ->where('u.status', 'A')
                ->where('j.status', 'A')
                ->select([
                    'u.id',
                    'u.nama_lengkap',
                    'u.id_jabatan',
                    'u.avatar',
                    'j.nama_jabatan',
                    'j.id_atasan',
                    'j.id_level',
                    'l.nama_level',
                    'l.nilai_level',
                    'atasan.nama_jabatan as atasan_jabatan',
                    'atasan_user.nama_lengkap as atasan_nama',
                    'atasan_user.avatar as atasan_avatar'
                ])
                ->orderBy('j.id_atasan', 'asc')
                ->orderBy('u.nama_lengkap', 'asc')
                ->get();
                
            // Get all jabatan that are referenced in hierarchy but might not have employees in outlet 1
            $allJabatanInHierarchy = DB::table('tbl_data_jabatan as j')
                ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
                ->where('j.status', 'A')
                ->select([
                    'j.id_jabatan',
                    'j.nama_jabatan',
                    'j.id_atasan',
                    'j.id_level',
                    'l.nama_level',
                    'l.nilai_level'
                ])
                ->get();
                
            // Add missing jabatan to organization data
            $existingJabatanIds = $organizationData->pluck('id_jabatan')->unique();
            $missingJabatan = $allJabatanInHierarchy->whereNotIn('id_jabatan', $existingJabatanIds);
            
            foreach($missingJabatan as $jabatan) {
                // Add as empty jabatan (no employees in outlet 1)
                $organizationData->push((object)[
                    'id' => null,
                    'nama_lengkap' => null,
                    'id_jabatan' => $jabatan->id_jabatan,
                    'avatar' => null,
                    'nama_jabatan' => $jabatan->nama_jabatan,
                    'id_atasan' => $jabatan->id_atasan,
                    'id_level' => $jabatan->id_level,
                    'nama_level' => $jabatan->nama_level,
                    'nilai_level' => $jabatan->nilai_level,
                    'atasan_jabatan' => null,
                    'atasan_nama' => null,
                    'atasan_avatar' => null
                ]);
            }
                
            \Log::info("Debug Organization Chart - Query result count: " . $organizationData->count());

            // Hitung jumlah bawahan untuk setiap jabatan
            $subordinatesCount = [];
            foreach ($organizationData as $employee) {
                $superiorId = $employee->id_atasan;
                if ($superiorId) {
                    if (!isset($subordinatesCount[$superiorId])) {
                        $subordinatesCount[$superiorId] = 0;
                    }
                    $subordinatesCount[$superiorId]++;
                }
            }

            // Tambahkan informasi atasan dan jumlah bawahan
            $processedData = $organizationData->map(function ($employee) use ($subordinatesCount) {
                $employee->subordinates_count = $subordinatesCount[$employee->id_jabatan] ?? 0;
                
                // Tambahkan informasi atasan jika ada
                if ($employee->id_atasan && $employee->atasan_nama) {
                    $employee->atasan = [
                        'nama_lengkap' => $employee->atasan_nama,
                        'nama_jabatan' => $employee->atasan_jabatan,
                        'avatar' => $employee->atasan_avatar
                    ];
                } else {
                    $employee->atasan = null;
                }

                // Hapus field yang tidak diperlukan
                unset($employee->atasan_nama, $employee->atasan_jabatan, $employee->atasan_avatar);

                return $employee;
            });

            \Log::info("Debug Organization Chart - Final processed data count: " . $processedData->count());
            \Log::info("Debug Organization Chart - Sample processed data: " . json_encode($processedData->take(2)));

            return response()->json([
                'success' => true,
                'data' => $processedData,
                'message' => 'Data organisasi berhasil dimuat',
                'debug' => [
                    'total_count' => $processedData->count(),
                    'sample_data' => $processedData->take(3)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data organisasi: ' . $e->getMessage()
            ], 500);
        }
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

