<?php

namespace App\Http\Controllers;

use App\Services\WarehouseMacAnomalyDetectionService;
use App\Support\MacAnomalyHistoryCutoff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WarehouseCostHealthDashboardController extends Controller
{
    public function __construct(
        private WarehouseMacAnomalyDetectionService $anomalyDetection,
    ) {}

    public function index()
    {
        $warehouses = DB::table('warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('WarehouseCostHealthDashboard/Index', [
            'warehouses' => $warehouses,
            'historyCutoffDate' => MacAnomalyHistoryCutoff::DATE,
            'defaultFilters' => [
                'warehouse_id' => null,
                'date_from' => Carbon::now()->subDays(30)->format('Y-m-d'),
                'date_to' => Carbon::now()->format('Y-m-d'),
                'max_mac' => 10_000_000,
            ],
            'shortcuts' => $this->shortcutCatalog(),
        ]);
    }

    public function snapshot(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'min_spike_percent' => ['nullable', 'numeric', 'min:0'],
            'spike_multiplier' => ['nullable', 'numeric', 'min:1.1'],
            'max_mac' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $result = $this->anomalyDetection->dashboardSnapshot($validated);

            return response()->json([
                'status' => 'success',
                ...$result,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat snapshot kesehatan cost gudang: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return list<array{label: string, route: string, icon: string, group: string}>
     */
    private function shortcutCatalog(): array
    {
        return [
            [
                'label' => 'Warehouse MAC Anomaly',
                'route' => '/warehouse-mac-anomaly-tracking',
                'icon' => 'fa-solid fa-triangle-exclamation',
                'group' => 'cost',
            ],
            [
                'label' => 'Warehouse MAC Tracking',
                'route' => '/warehouse-mac-tracking',
                'icon' => 'fa-solid fa-warehouse',
                'group' => 'cost',
            ],
            [
                'label' => 'MAC Report',
                'route' => '/mac-report',
                'icon' => 'fa-solid fa-chart-line',
                'group' => 'cost',
            ],
            [
                'label' => 'Cost Report',
                'route' => '/cost-report',
                'icon' => 'fa-solid fa-coins',
                'group' => 'cost',
            ],
            [
                'label' => 'Penerimaan Barang',
                'route' => '/food-good-receive',
                'icon' => 'fa-solid fa-truck',
                'group' => 'ops',
            ],
            [
                'label' => 'Pindah Gudang',
                'route' => '/warehouse-transfer',
                'icon' => 'fa-solid fa-right-left',
                'group' => 'ops',
            ],
            [
                'label' => 'Warehouse Retail Food',
                'route' => '/retail-warehouse-food',
                'icon' => 'fa-solid fa-warehouse',
                'group' => 'ops',
            ],
            [
                'label' => 'Penyesuaian Stok',
                'route' => '/food-inventory-adjustment',
                'icon' => 'fa-solid fa-boxes-stacked',
                'group' => 'ops',
            ],
            [
                'label' => 'Pemakaian Internal & Sampah',
                'route' => '/internal-use-waste',
                'icon' => 'fa-solid fa-recycle',
                'group' => 'ops',
            ],
        ];
    }
}
