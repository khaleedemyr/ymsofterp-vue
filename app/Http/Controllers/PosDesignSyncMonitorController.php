<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class PosDesignSyncMonitorController extends Controller
{
    public function index(Request $request)
    {
        $outletFilter = $request->query('kode_outlet');
        $statusFilter = $request->query('status');
        $hasSyncLogTable = Schema::hasTable('pos_design_sync_logs');

        $sectionCounts = DB::table('pos_design_sections_sync')
            ->select('kode_outlet', DB::raw('COUNT(*) as total_sections'))
            ->groupBy('kode_outlet')
            ->pluck('total_sections', 'kode_outlet');

        $tableCounts = DB::table('pos_design_tables_sync')
            ->select('kode_outlet', DB::raw('COUNT(*) as total_tables'))
            ->groupBy('kode_outlet')
            ->pluck('total_tables', 'kode_outlet');

        $accessoryCounts = DB::table('pos_design_accessories_sync')
            ->select('kode_outlet', DB::raw('COUNT(*) as total_accessories'))
            ->groupBy('kode_outlet')
            ->pluck('total_accessories', 'kode_outlet');

        $lastSuccessSync = collect();
        $lastStatusByOutlet = collect();

        if ($hasSyncLogTable) {
            $lastSuccessSync = DB::table('pos_design_sync_logs')
                ->select('kode_outlet', DB::raw('MAX(synced_at) as last_synced_at'))
                ->where('status', 'success')
                ->groupBy('kode_outlet')
                ->pluck('last_synced_at', 'kode_outlet');

            $latestLogIds = DB::table('pos_design_sync_logs')
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('kode_outlet');

            $lastStatusByOutlet = DB::table('pos_design_sync_logs as logs')
                ->joinSub($latestLogIds, 'latest', function ($join) {
                    $join->on('logs.id', '=', 'latest.id');
                })
                ->select('logs.kode_outlet', 'logs.status', 'logs.message')
                ->get()
                ->keyBy('kode_outlet');
        }

        $allOutletCodes = collect()
            ->merge($sectionCounts->keys())
            ->merge($tableCounts->keys())
            ->merge($accessoryCounts->keys())
            ->merge($lastSuccessSync->keys())
            ->merge($lastStatusByOutlet->keys())
            ->unique()
            ->sort()
            ->values();

        $summary = $allOutletCodes
            ->map(function ($kodeOutlet) use ($sectionCounts, $tableCounts, $accessoryCounts, $lastSuccessSync, $lastStatusByOutlet) {
                $lastStatus = $lastStatusByOutlet->get($kodeOutlet);

                return [
                    'kode_outlet' => $kodeOutlet,
                    'sections_count' => (int) ($sectionCounts[$kodeOutlet] ?? 0),
                    'tables_count' => (int) ($tableCounts[$kodeOutlet] ?? 0),
                    'accessories_count' => (int) ($accessoryCounts[$kodeOutlet] ?? 0),
                    'last_synced_at' => $lastSuccessSync[$kodeOutlet] ?? null,
                    'last_status' => $lastStatus->status ?? null,
                    'last_message' => $lastStatus->message ?? null,
                ];
            })
            ->when($outletFilter, function ($collection) use ($outletFilter) {
                return $collection->filter(function ($item) use ($outletFilter) {
                    return stripos($item['kode_outlet'], $outletFilter) !== false;
                })->values();
            });

        $logs = DB::table('pos_design_sections_sync')
            ->select(
                DB::raw('NULL as id'),
                DB::raw('NULL as kode_outlet'),
                DB::raw('NULL as status'),
                DB::raw('0 as sections_count'),
                DB::raw('0 as tables_count'),
                DB::raw('0 as accessories_count'),
                DB::raw('NULL as message'),
                DB::raw('NULL as synced_at'),
                DB::raw('NULL as created_at')
            )
            ->whereRaw('1 = 0')
            ->paginate(50)
            ->withQueryString();

        if ($hasSyncLogTable) {
            $logsQuery = DB::table('pos_design_sync_logs')
                ->select(
                    'id',
                    'kode_outlet',
                    'status',
                    'sections_count',
                    'tables_count',
                    'accessories_count',
                    'message',
                    'synced_at',
                    'created_at'
                )
                ->orderByDesc('id');

            if ($outletFilter) {
                $logsQuery->where('kode_outlet', 'like', '%' . $outletFilter . '%');
            }

            if ($statusFilter) {
                $logsQuery->where('status', $statusFilter);
            }

            $logs = $logsQuery->paginate(50)->withQueryString();
        }

        return Inertia::render('Admin/PosDesignSyncMonitor', [
            'summary' => $summary,
            'logs' => $logs,
            'filters' => [
                'kode_outlet' => $outletFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function layout(Request $request)
    {
        $outletCodes = collect()
            ->merge(DB::table('pos_design_sections_sync')->distinct()->pluck('kode_outlet'))
            ->merge(DB::table('pos_design_tables_sync')->distinct()->pluck('kode_outlet'))
            ->merge(DB::table('pos_design_accessories_sync')->distinct()->pluck('kode_outlet'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $outletRows = DB::table('tbl_data_outlet')
            ->select('qr_code', 'nama_outlet')
            ->whereNotNull('qr_code')
            ->where('qr_code', '!=', '')
            ->get();

        $outletNameMapExact = $outletRows
            ->mapWithKeys(function ($row) {
                return [trim((string) $row->qr_code) => $row->nama_outlet];
            });

        $outletNameMapLower = $outletRows
            ->mapWithKeys(function ($row) {
                return [strtolower(trim((string) $row->qr_code)) => $row->nama_outlet];
            });

        $outletOptions = $outletCodes->map(function ($code) use ($outletNameMapExact, $outletNameMapLower) {
            $normalizedCode = trim((string) $code);
            $name = $outletNameMapExact[$normalizedCode] ?? $outletNameMapLower[strtolower($normalizedCode)] ?? null;

            return [
                'code' => $code,
                'name' => $name,
                'label' => $name ? ($code . '-' . $name) : $code,
            ];
        })->values();

        $selectedOutlet = $request->query('kode_outlet');
        if (!$selectedOutlet && $outletCodes->isNotEmpty()) {
            $selectedOutlet = $outletCodes->first();
        }

        $sections = collect();
        $tablesBySection = collect();
        $accessoriesBySection = collect();
        $reservationSettings = collect();
        $hasSmokingTypeColumn = Schema::hasTable('pos_design_table_reservation_settings')
            && Schema::hasColumn('pos_design_table_reservation_settings', 'smoking_type');

        if ($selectedOutlet && Schema::hasTable('pos_design_table_reservation_settings')) {
            $settingColumns = ['source_table_id', 'allow_reservation'];
            if ($hasSmokingTypeColumn) {
                $settingColumns[] = 'smoking_type';
            }

            $reservationSettings = DB::table('pos_design_table_reservation_settings')
                ->where('kode_outlet', $selectedOutlet)
                ->get($settingColumns)
                ->keyBy('source_table_id');
        }

        if ($selectedOutlet) {
            $sections = DB::table('pos_design_sections_sync')
                ->where('kode_outlet', $selectedOutlet)
                ->orderBy('source_section_id')
                ->get(['source_section_id', 'nama']);

            $tablesBySection = DB::table('pos_design_tables_sync')
                ->where('kode_outlet', $selectedOutlet)
                ->orderBy('source_table_id')
                ->get([
                    'source_table_id',
                    'source_section_id',
                    'nama',
                    'tipe',
                    'bentuk',
                    'orientasi',
                    'jumlah_kursi',
                    'warna',
                    'x',
                    'y',
                ])
                ->map(function ($table) use ($reservationSettings) {
                    $setting = $reservationSettings[(int) $table->source_table_id] ?? $reservationSettings[(string) $table->source_table_id] ?? null;
                    $table->allow_reservation = $setting ? ((int) $setting->allow_reservation === 1) : true;
                    $table->smoking_type = $setting && $setting->smoking_type ? $setting->smoking_type : 'non_smoking';
                    return $table;
                })
                ->groupBy('source_section_id')
                ->map(function ($items) {
                    return $items->values();
                });

            $accessoriesBySection = DB::table('pos_design_accessories_sync')
                ->where('kode_outlet', $selectedOutlet)
                ->orderBy('source_accessory_id')
                ->get([
                    'source_accessory_id',
                    'source_section_id',
                    'type',
                    'x',
                    'y',
                    'panjang',
                    'orientasi',
                ])
                ->groupBy('source_section_id')
                ->map(function ($items) {
                    return $items->values();
                });
        }

        return Inertia::render('Admin/PosDesignLayoutViewer', [
            'outletCodes' => $outletCodes,
            'outletOptions' => $outletOptions,
            'selectedOutlet' => $selectedOutlet,
            'sections' => $sections,
            'tablesBySection' => $tablesBySection,
            'accessoriesBySection' => $accessoriesBySection,
        ]);
    }

    public function updateReservationSetting(Request $request)
    {
        if (!Schema::hasTable('pos_design_table_reservation_settings')) {
            return response()->json([
                'success' => false,
                'message' => 'Table setting reservasi belum tersedia. Jalankan SQL create table terlebih dahulu.',
            ], 500);
        }

        $validator = Validator::make($request->all(), [
            'kode_outlet' => 'required|string|max:50',
            'source_table_id' => 'required|integer',
            'allow_reservation' => 'required|boolean',
            'smoking_type' => 'required|in:smoking,non_smoking',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $kodeOutlet = $request->input('kode_outlet');
        $sourceTableId = (int) $request->input('source_table_id');
        $allowReservation = (bool) $request->input('allow_reservation');
        $smokingType = $request->input('smoking_type');
        $hasSmokingTypeColumn = Schema::hasColumn('pos_design_table_reservation_settings', 'smoking_type');

        $existing = DB::table('pos_design_table_reservation_settings')
            ->where('kode_outlet', $kodeOutlet)
            ->where('source_table_id', $sourceTableId)
            ->first();

        if ($existing) {
            $updatePayload = [
                'allow_reservation' => $allowReservation ? 1 : 0,
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ];
            if ($hasSmokingTypeColumn) {
                $updatePayload['smoking_type'] = $smokingType;
            }

            DB::table('pos_design_table_reservation_settings')
                ->where('id', $existing->id)
                ->update($updatePayload);
        } else {
            $insertPayload = [
                'kode_outlet' => $kodeOutlet,
                'source_table_id' => $sourceTableId,
                'allow_reservation' => $allowReservation ? 1 : 0,
                'updated_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasSmokingTypeColumn) {
                $insertPayload['smoking_type'] = $smokingType;
            }

            DB::table('pos_design_table_reservation_settings')->insert($insertPayload);
        }

        return response()->json([
            'success' => true,
            'message' => 'Setting reservasi berhasil disimpan.',
            'data' => [
                'kode_outlet' => $kodeOutlet,
                'source_table_id' => $sourceTableId,
                'allow_reservation' => $allowReservation,
                'smoking_type' => $smokingType,
            ],
        ]);
    }
}
