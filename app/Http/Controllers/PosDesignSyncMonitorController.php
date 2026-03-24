<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PosDesignSyncMonitorController extends Controller
{
    public function index(Request $request)
    {
        $outletFilter = $request->query('kode_outlet');
        $statusFilter = $request->query('status');

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

        return Inertia::render('Admin/PosDesignSyncMonitor', [
            'summary' => $summary,
            'logs' => $logs,
            'filters' => [
                'kode_outlet' => $outletFilter,
                'status' => $statusFilter,
            ],
        ]);
    }
}
