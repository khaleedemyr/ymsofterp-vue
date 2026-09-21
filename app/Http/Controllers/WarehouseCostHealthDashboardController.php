<?php

namespace App\Http\Controllers;

use App\Services\WarehouseDashboardOpsService;
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
        private WarehouseDashboardOpsService $opsService,
    ) {}

    public function index()
    {
        $warehouses = DB::table('warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $period = WarehouseDashboardOpsService::resolvePeriod(Carbon::now()->format('Y-m'));

        return Inertia::render('WarehouseCostHealthDashboard/Index', [
            'warehouses' => $warehouses,
            'historyCutoffDate' => MacAnomalyHistoryCutoff::DATE,
            'defaultFilters' => [
                'warehouse_id' => null,
                'period' => $period['month'],
                'max_mac' => 10_000_000,
            ],
            'shortcuts' => $this->shortcutCatalog(),
        ]);
    }

    public function snapshot(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'min_spike_percent' => ['nullable', 'numeric', 'min:0'],
            'spike_multiplier' => ['nullable', 'numeric', 'min:1.1'],
            'max_mac' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $periodMeta = WarehouseDashboardOpsService::resolvePeriod($validated['period'] ?? null);
            $dateFrom = $validated['date_from'] ?? $periodMeta['date_from'];
            $dateTo = $validated['date_to'] ?? $periodMeta['date_to'];
            $warehouseId = (int) ($validated['warehouse_id'] ?? 0);

            $cost = $this->anomalyDetection->dashboardSnapshot([
                'warehouse_id' => $warehouseId ?: null,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'min_spike_percent' => $validated['min_spike_percent'] ?? null,
                'spike_multiplier' => $validated['spike_multiplier'] ?? null,
                'max_mac' => $validated['max_mac'] ?? null,
            ]);

            $transactions = $this->opsService->build($dateFrom, $dateTo, $warehouseId);

            return response()->json([
                'status' => 'success',
                'period' => $periodMeta,
                ...$cost,
                'transactions' => $transactions,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat snapshot kesehatan cost gudang: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function transactions(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'warehouse_id' => ['nullable', 'integer'],
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        try {
            $periodMeta = WarehouseDashboardOpsService::resolvePeriod($validated['period'] ?? null);
            $result = $this->opsService->listTransactions(
                $validated['type'],
                $periodMeta['date_from'],
                $periodMeta['date_to'],
                (int) ($validated['warehouse_id'] ?? 0),
                (string) ($validated['search'] ?? ''),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );

            return response()->json([
                'status' => 'success',
                'period' => $periodMeta,
                ...$result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat daftar transaksi: ' . $e->getMessage(),
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
