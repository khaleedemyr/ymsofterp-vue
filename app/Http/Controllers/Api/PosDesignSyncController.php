<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PosDesignSyncController extends Controller
{
    private function writeSyncLog(array $data): void
    {
        DB::table('pos_design_sync_logs')->insert([
            'kode_outlet' => $data['kode_outlet'] ?? 'unknown',
            'status' => $data['status'] ?? 'failed',
            'sections_count' => (int) ($data['sections_count'] ?? 0),
            'tables_count' => (int) ($data['tables_count'] ?? 0),
            'accessories_count' => (int) ($data['accessories_count'] ?? 0),
            'message' => $data['message'] ?? null,
            'synced_at' => $data['synced_at'] ?? null,
            'request_payload' => isset($data['request_payload']) ? json_encode($data['request_payload']) : null,
            'response_payload' => isset($data['response_payload']) ? json_encode($data['response_payload']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Sync snapshot POS Design dari outlet ke server pusat (1 arah).
     */
    public function syncSnapshot(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string|max:50',
                'sections' => 'required|array',
                'sections.*.id' => 'required|integer',
                'sections.*.nama' => 'required|string|max:100',
                'tables' => 'required|array',
                'tables.*.id' => 'required|integer',
                'tables.*.section_id' => 'required|integer',
                'tables.*.nama' => 'required|string|max:50',
                'tables.*.tipe' => 'nullable|in:biasa,takeaway,ojol',
                'tables.*.bentuk' => 'nullable|in:round,square',
                'tables.*.orientasi' => 'nullable|in:horizontal,vertical',
                'tables.*.jumlah_kursi' => 'nullable|integer',
                'tables.*.warna' => 'nullable|string|max:20',
                'tables.*.x' => 'required|integer',
                'tables.*.y' => 'required|integer',
                'accessories' => 'required|array',
                'accessories.*.id' => 'required|integer',
                'accessories.*.section_id' => 'required|integer',
                'accessories.*.type' => 'required|in:divider,lemari,pot,pos,kasir',
                'accessories.*.x' => 'required|integer',
                'accessories.*.y' => 'required|integer',
                'accessories.*.panjang' => 'nullable|integer',
                'accessories.*.orientasi' => 'nullable|in:horizontal,vertical',
                'synced_at' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                $this->writeSyncLog([
                    'kode_outlet' => $request->input('kode_outlet', 'unknown'),
                    'status' => 'validation_failed',
                    'sections_count' => count($request->input('sections', [])),
                    'tables_count' => count($request->input('tables', [])),
                    'accessories_count' => count($request->input('accessories', [])),
                    'message' => 'Validation failed',
                    'synced_at' => now()->format('Y-m-d H:i:s'),
                    'request_payload' => $request->all(),
                    'response_payload' => ['errors' => $validator->errors()->toArray()],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');
            $sections = $request->input('sections', []);
            $tables = $request->input('tables', []);
            $accessories = $request->input('accessories', []);
            $syncedAt = $request->input('synced_at')
                ? Carbon::parse($request->input('synced_at'))->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s');

            DB::transaction(function () use ($kodeOutlet, $sections, $tables, $accessories, $syncedAt) {
                // Snapshot sync: replace seluruh data outlet agar selalu konsisten dengan local POS.
                DB::table('pos_design_accessories_sync')->where('kode_outlet', $kodeOutlet)->delete();
                DB::table('pos_design_tables_sync')->where('kode_outlet', $kodeOutlet)->delete();
                DB::table('pos_design_sections_sync')->where('kode_outlet', $kodeOutlet)->delete();

                if (!empty($sections)) {
                    $sectionRows = array_map(function ($section) use ($kodeOutlet, $syncedAt) {
                        return [
                            'kode_outlet' => $kodeOutlet,
                            'source_section_id' => (int) $section['id'],
                            'nama' => (string) $section['nama'],
                            'synced_at' => $syncedAt,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }, $sections);

                    DB::table('pos_design_sections_sync')->insert($sectionRows);
                }

                if (!empty($tables)) {
                    $tableRows = array_map(function ($table) use ($kodeOutlet, $syncedAt) {
                        return [
                            'kode_outlet' => $kodeOutlet,
                            'source_table_id' => (int) $table['id'],
                            'source_section_id' => (int) $table['section_id'],
                            'nama' => (string) $table['nama'],
                            'tipe' => $table['tipe'] ?? 'biasa',
                            'bentuk' => $table['bentuk'] ?? 'round',
                            'orientasi' => $table['orientasi'] ?? 'horizontal',
                            'jumlah_kursi' => isset($table['jumlah_kursi']) ? (int) $table['jumlah_kursi'] : 4,
                            'warna' => $table['warna'] ?? '#2563eb',
                            'x' => (int) $table['x'],
                            'y' => (int) $table['y'],
                            'synced_at' => $syncedAt,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }, $tables);

                    DB::table('pos_design_tables_sync')->insert($tableRows);
                }

                if (!empty($accessories)) {
                    $accessoryRows = array_map(function ($accessory) use ($kodeOutlet, $syncedAt) {
                        return [
                            'kode_outlet' => $kodeOutlet,
                            'source_accessory_id' => (int) $accessory['id'],
                            'source_section_id' => (int) $accessory['section_id'],
                            'type' => (string) $accessory['type'],
                            'x' => (int) $accessory['x'],
                            'y' => (int) $accessory['y'],
                            'panjang' => isset($accessory['panjang']) ? (int) $accessory['panjang'] : null,
                            'orientasi' => $accessory['orientasi'] ?? 'horizontal',
                            'synced_at' => $syncedAt,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }, $accessories);

                    DB::table('pos_design_accessories_sync')->insert($accessoryRows);
                }
            });

            $responsePayload = [
                'kode_outlet' => $kodeOutlet,
                'sections_count' => count($sections),
                'tables_count' => count($tables),
                'accessories_count' => count($accessories),
                'synced_at' => $syncedAt,
            ];

            $this->writeSyncLog([
                'kode_outlet' => $kodeOutlet,
                'status' => 'success',
                'sections_count' => count($sections),
                'tables_count' => count($tables),
                'accessories_count' => count($accessories),
                'message' => 'POS Design synced successfully',
                'synced_at' => $syncedAt,
                'request_payload' => $request->all(),
                'response_payload' => $responsePayload,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'POS Design synced successfully',
                'data' => $responsePayload,
            ]);
        } catch (\Throwable $e) {
            Log::error('POS Design Sync Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            try {
                $this->writeSyncLog([
                    'kode_outlet' => $request->input('kode_outlet', 'unknown'),
                    'status' => 'failed',
                    'sections_count' => count($request->input('sections', [])),
                    'tables_count' => count($request->input('tables', [])),
                    'accessories_count' => count($request->input('accessories', [])),
                    'message' => 'Failed to sync POS Design: ' . $e->getMessage(),
                    'synced_at' => now()->format('Y-m-d H:i:s'),
                    'request_payload' => $request->all(),
                    'response_payload' => ['error' => $e->getMessage()],
                ]);
            } catch (\Throwable $loggingError) {
                Log::error('POS Design Sync Log Error', [
                    'message' => $loggingError->getMessage(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync POS Design: ' . $e->getMessage(),
            ], 500);
        }
    }
}
