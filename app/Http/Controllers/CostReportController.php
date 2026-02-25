<?php

namespace App\Http\Controllers;

use App\Exports\CostReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class CostReportController extends Controller
{
    private array $internalUseWasteAggregatesCache = [];

    /**
     * Cost Report: kolom Outlet (is_outlet=1, status=A) dan Begin Inventory (Total MAC).
     * Begin inventory logic sama seperti Outlet Stock Report, tapi nilai yang ditampilkan
     * adalah total MAC (nilai rupiah) = sum(begin_qty_small * mac_per_small) per outlet.
     */
    /**
     * Lazy load: data tidak di-load saat pertama masuk. Load hanya saat user klik "Load Data".
     */
    public function index(Request $request)
    {
        $bulan = $request->input('bulan', date('Y-m'));
        $shouldLoadData = $request->boolean('load') || $request->input('load') === '1';

        if (!$shouldLoadData) {
            $outlets = \Illuminate\Support\Facades\DB::table('tbl_data_outlet')
                ->where('is_outlet', 1)
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();
            return Inertia::render('CostReport/Index', [
                'outlets' => $outlets,
                'reportRows' => [],
                'cogsRows' => [],
                'categoryCostRows' => [],
                'filters' => ['bulan' => $bulan],
            ]);
        }

        $data = $this->getReportData($bulan);
        return Inertia::render('CostReport/Index', [
            'outlets' => $data['outlets'],
            'reportRows' => $data['reportRows'],
            'cogsRows' => $data['cogsRows'],
            'categoryCostRows' => $data['categoryCostRows'],
            'filters' => ['bulan' => $bulan],
        ]);
    }

    /**
     * Export Cost Report to Excel (3 sheets: Cost Inventory, COGS, Category Cost).
     */
    public function export(Request $request)
    {
        $bulan = $request->input('bulan', date('Y-m'));
        $data = $this->getReportData($bulan);
        $fileName = 'cost_report_' . $bulan . '.xlsx';
        return Excel::download(
            new CostReportExport($data['reportRows'], $data['cogsRows'], $data['categoryCostRows'], $bulan),
            $fileName
        );
    }

    /**
     * Load data per tab (AJAX) to avoid heavy full-report computation on every request.
     */
    public function tabData(Request $request)
    {
        $bulan = $request->input('bulan', date('Y-m'));
        $tab = $request->input('tab', 'cost_inventory');

        if (!in_array($tab, ['cost_inventory', 'cogs', 'category_cost'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Tab tidak valid.',
            ], 422);
        }

        $bulanCarbon = Carbon::parse($bulan . '-01');
        $bulanSebelumnya = $bulanCarbon->copy()->subMonth();
        $tanggalAkhirBulanSebelumnya = $bulanSebelumnya->format('Y-m-t');
        $tanggal1BulanIni = $bulanCarbon->format('Y-m-01');
        $tanggalAwalBulan = $bulanCarbon->format('Y-m-01');
        $tanggalAkhirBulan = $bulanCarbon->format('Y-m-t');

        $outlets = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        if ($tab === 'cost_inventory') {
            $reportRows = Cache::remember(
                $this->getReportRowsCacheKey($bulan),
                now()->addMinutes(10),
                fn () => $this->buildCostInventoryRows(
                    $outlets,
                    $bulanSebelumnya,
                    $tanggalAkhirBulanSebelumnya,
                    $tanggal1BulanIni,
                    $tanggalAwalBulan,
                    $tanggalAkhirBulan
                )
            );

            return response()->json([
                'success' => true,
                'tab' => $tab,
                'reportRows' => $reportRows,
            ]);
        }

        $reportRows = Cache::remember(
            $this->getReportRowsCacheKey($bulan),
            now()->addMinutes(10),
            fn () => $this->buildCostInventoryRows(
                $outlets,
                $bulanSebelumnya,
                $tanggalAkhirBulanSebelumnya,
                $tanggal1BulanIni,
                $tanggalAwalBulan,
                $tanggalAkhirBulan
            )
        );

        if ($tab === 'cogs') {
            $cogsRows = $this->buildCogsRows($outlets, $reportRows, $tanggalAwalBulan, $tanggalAkhirBulan);

            return response()->json([
                'success' => true,
                'tab' => $tab,
                'cogsRows' => $cogsRows,
            ]);
        }

        $categoryCostRows = $this->buildCategoryCostRows($outlets, $reportRows, $tanggalAwalBulan, $tanggalAkhirBulan);

        return response()->json([
            'success' => true,
            'tab' => $tab,
            'categoryCostRows' => $categoryCostRows,
        ]);
    }

    /**
     * Clear cached Cost Report rows for selected month.
     */
    public function clearCache(Request $request)
    {
        $validated = $request->validate([
            'bulan' => ['required', 'date_format:Y-m'],
        ]);

        $bulan = $validated['bulan'];
        Cache::forget($this->getReportRowsCacheKey($bulan));

        return response()->json([
            'success' => true,
            'message' => 'Cache cost report berhasil dibersihkan.',
            'bulan' => $bulan,
        ]);
    }

    private function getReportRowsCacheKey(string $bulan): string
    {
        return 'cost_report:report_rows:' . $bulan;
    }

    private function buildCostInventoryRows($outlets, Carbon $bulanSebelumnya, string $tanggalAkhirBulanSebelumnya, string $tanggal1BulanIni, string $tanggalAwalBulan, string $tanggalAkhirBulan): array
    {
        $reportRows = [];
        $outletIds = collect($outlets)->pluck('id_outlet')->map(fn ($id) => (int) $id)->all();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->whereIn('outlet_id', $outletIds)
            ->where('status', 'active')
            ->select('id', 'outlet_id', 'name')
            ->orderBy('name')
            ->get();

        $warehouseOutletIds = $warehouseOutlets->pluck('id')->map(fn ($id) => (int) $id)->all();
        $stockRows = $this->loadOutletWarehouseStockRows($outletIds, $warehouseOutletIds);
        $beginMacByWarehouse = $this->computeBeginInventoryTotalMacByWarehouse(
            $stockRows,
            $outletIds,
            $warehouseOutletIds,
            $bulanSebelumnya,
            $tanggalAkhirBulanSebelumnya,
            $tanggal1BulanIni
        );
        $endingMacByWarehouse = $this->computeEndingInventoryTotalMacByWarehouse(
            $stockRows,
            $outletIds,
            $warehouseOutletIds,
            $tanggalAkhirBulan
        );

        $warehouseIdsByOutlet = [];
        foreach ($warehouseOutlets as $warehouseOutlet) {
            $warehouseIdsByOutlet[(int) $warehouseOutlet->outlet_id][] = (int) $warehouseOutlet->id;
        }

        foreach ($outlets as $outlet) {
            $outletId = $outlet->id_outlet;
            $totalBeginMacOutlet = 0;
            $totalEndingMacOutlet = 0;

            foreach (($warehouseIdsByOutlet[$outletId] ?? []) as $warehouseOutletId) {
                $totalBeginMacOutlet += (float) ($beginMacByWarehouse[$warehouseOutletId] ?? 0);
                $totalEndingMacOutlet += (float) ($endingMacByWarehouse[$warehouseOutletId] ?? 0);
            }

            $reportRows[] = [
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => $outlet->name,
                'total_begin_mac' => round($totalBeginMacOutlet, 2),
                'ending_inventory' => round($totalEndingMacOutlet, 2),
                'official_cost' => 0,
                'cost_rnd' => 0,
                'outlet_transfer' => 0,
                'sales_before_discount' => 0,
                'discount' => 0,
            ];
        }

        $officialCostByOutlet = $this->computeOfficialCostByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $costRndByOutlet = $this->computeCostRndByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $outletTransferByOutlet = $this->computeOutletTransferByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $salesBeforeDiscountByOutlet = $this->computeSalesBeforeDiscountByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $discountByOutlet = $this->computeDiscountByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);

        foreach ($reportRows as &$row) {
            $row['official_cost'] = round($officialCostByOutlet[$row['outlet_id']] ?? 0, 2);
            $row['cost_rnd'] = round($costRndByOutlet[$row['outlet_id']] ?? 0, 2);
            $row['outlet_transfer'] = round($outletTransferByOutlet[$row['outlet_id']] ?? 0, 2);
            $row['sales_before_discount'] = round($salesBeforeDiscountByOutlet[$row['outlet_id']] ?? 0, 2);
            $row['discount'] = round($discountByOutlet[$row['outlet_id']] ?? 0, 2);
            $row['sales_after_discount'] = round(($row['sales_before_discount'] ?? 0) - ($row['discount'] ?? 0), 2);

            $salesBefore = (float) ($row['sales_before_discount'] ?? 0);
            $row['pct_discount'] = $salesBefore > 0
                ? round(((float) ($row['discount'] ?? 0) / $salesBefore) * 100, 2)
                : 0;

            $row['total_barang_tersedia'] = round(
                ($row['total_begin_mac'] ?? 0) + ($row['official_cost'] ?? 0) - ($row['cost_rnd'] ?? 0) - ($row['outlet_transfer'] ?? 0),
                2
            );
            $row['cogs_aktual'] = round(($row['total_barang_tersedia'] ?? 0) - ($row['ending_inventory'] ?? 0), 2);
        }
        unset($row);

        return $reportRows;
    }

    private function loadOutletWarehouseStockRows(array $outletIds, array $warehouseOutletIds)
    {
        if (empty($outletIds) || empty($warehouseOutletIds)) {
            return collect();
        }

        return DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->whereIn('s.id_outlet', $outletIds)
            ->whereIn('s.warehouse_outlet_id', $warehouseOutletIds)
            ->select(
                's.id_outlet',
                's.warehouse_outlet_id',
                'fi.id as inventory_item_id',
                'fi.item_id',
                's.last_cost_small'
            )
            ->distinct()
            ->get();
    }

    private function computeBeginInventoryTotalMacByWarehouse(
        $stockRows,
        array $outletIds,
        array $warehouseOutletIds,
        Carbon $bulanSebelumnya,
        string $tanggalAkhirBulanSebelumnya,
        string $tanggal1BulanIni
    ): array {
        if ($stockRows->isEmpty() || empty($outletIds) || empty($warehouseOutletIds)) {
            return [];
        }

        $beginInventoryFromOpname = [];
        $beginOpnameRows = DB::table('outlet_stock_opname_items as soi')
            ->join('outlet_stock_opnames as so', 'soi.stock_opname_id', '=', 'so.id')
            ->join('outlet_food_inventory_items as fi', 'soi.inventory_item_id', '=', 'fi.id')
            ->whereIn('so.outlet_id', $outletIds)
            ->whereIn('so.warehouse_outlet_id', $warehouseOutletIds)
            ->whereIn('so.status', ['APPROVED', 'COMPLETED'])
            ->where(function ($q) use ($bulanSebelumnya, $tanggal1BulanIni) {
                $q->where(function ($q2) use ($bulanSebelumnya) {
                    $q2->whereYear('so.opname_date', $bulanSebelumnya->year)
                        ->whereMonth('so.opname_date', $bulanSebelumnya->month);
                })->orWhereDate('so.opname_date', $tanggal1BulanIni);
            })
            ->select(
                'so.outlet_id',
                'so.warehouse_outlet_id',
                'fi.item_id',
                'soi.qty_physical_small',
                'soi.mac_after',
                'soi.mac_before',
                'so.opname_date',
                'so.id as so_id'
            )
            ->orderByDesc('so.opname_date')
            ->orderByDesc('so.id')
            ->get();

        foreach ($beginOpnameRows as $row) {
            $key = (int) $row->outlet_id . '|' . (int) $row->warehouse_outlet_id . '|' . (int) $row->item_id;
            if (isset($beginInventoryFromOpname[$key])) {
                continue;
            }

            $beginInventoryFromOpname[$key] = [
                'qty_small' => (float) ($row->qty_physical_small ?? 0),
                'mac' => (float) ($row->mac_after ?? $row->mac_before ?? 0),
            ];
        }

        $inventoryItemIds = $stockRows->pluck('inventory_item_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $beginFromUpload = [];
        if (!empty($inventoryItemIds)) {
            $uploadRows = DB::table('outlet_food_inventory_cards as card')
                ->where('card.reference_type', 'initial_balance')
                ->whereIn('card.id_outlet', $outletIds)
                ->whereIn('card.warehouse_outlet_id', $warehouseOutletIds)
                ->where('card.date', '<=', $tanggalAkhirBulanSebelumnya)
                ->whereIn('card.inventory_item_id', $inventoryItemIds)
                ->orderByDesc('card.date')
                ->orderByDesc('card.created_at')
                ->select(
                    'card.id_outlet',
                    'card.warehouse_outlet_id',
                    'card.inventory_item_id',
                    'card.saldo_qty_small'
                )
                ->get();

            foreach ($uploadRows as $row) {
                $key = (int) $row->id_outlet . '|' . (int) $row->warehouse_outlet_id . '|' . (int) $row->inventory_item_id;
                if (!isset($beginFromUpload[$key])) {
                    $beginFromUpload[$key] = [
                        'qty_small' => (float) ($row->saldo_qty_small ?? 0),
                    ];
                }
            }
        }

        $totalsByWarehouse = [];
        foreach ($stockRows as $row) {
            $warehouseId = (int) $row->warehouse_outlet_id;
            $itemKey = (int) $row->id_outlet . '|' . $warehouseId . '|' . (int) $row->item_id;
            $inventoryItemKey = (int) $row->id_outlet . '|' . $warehouseId . '|' . (int) $row->inventory_item_id;

            $fromOpname = $beginInventoryFromOpname[$itemKey] ?? null;
            $fromUpload = $beginFromUpload[$inventoryItemKey] ?? null;

            $beginQtySmall = 0.0;
            $mac = 0.0;
            if ($fromOpname !== null) {
                $beginQtySmall = (float) ($fromOpname['qty_small'] ?? 0);
                $mac = (float) ($fromOpname['mac'] ?? 0);
            } elseif ($fromUpload !== null) {
                $beginQtySmall = (float) ($fromUpload['qty_small'] ?? 0);
                $mac = (float) ($row->last_cost_small ?? 0);
            }

            if (!isset($totalsByWarehouse[$warehouseId])) {
                $totalsByWarehouse[$warehouseId] = 0;
            }

            $totalsByWarehouse[$warehouseId] += $beginQtySmall * $mac;
        }

        return $totalsByWarehouse;
    }

    private function computeEndingInventoryTotalMacByWarehouse(
        $stockRows,
        array $outletIds,
        array $warehouseOutletIds,
        string $tanggalAkhirBulan
    ): array {
        if ($stockRows->isEmpty() || empty($outletIds) || empty($warehouseOutletIds)) {
            return [];
        }

        $endingFromOpname = [];
        $endingRows = DB::table('outlet_stock_opname_items as soi')
            ->join('outlet_stock_opnames as so', 'soi.stock_opname_id', '=', 'so.id')
            ->join('outlet_food_inventory_items as fi', 'soi.inventory_item_id', '=', 'fi.id')
            ->whereIn('so.outlet_id', $outletIds)
            ->whereIn('so.warehouse_outlet_id', $warehouseOutletIds)
            ->whereIn('so.status', ['APPROVED', 'COMPLETED'])
            ->whereDate('so.opname_date', '=', $tanggalAkhirBulan)
            ->select(
                'so.outlet_id',
                'so.warehouse_outlet_id',
                'fi.item_id',
                'soi.qty_physical_small',
                'soi.mac_after',
                'soi.mac_before',
                'so.id as so_id'
            )
            ->orderByDesc('so.id')
            ->get();

        foreach ($endingRows as $row) {
            $key = (int) $row->outlet_id . '|' . (int) $row->warehouse_outlet_id . '|' . (int) $row->item_id;
            if (isset($endingFromOpname[$key])) {
                continue;
            }

            $endingFromOpname[$key] = [
                'qty_small' => (float) ($row->qty_physical_small ?? 0),
                'mac' => (float) ($row->mac_after ?? $row->mac_before ?? 0),
            ];
        }

        $totalsByWarehouse = [];
        foreach ($stockRows as $row) {
            $warehouseId = (int) $row->warehouse_outlet_id;
            $key = (int) $row->id_outlet . '|' . $warehouseId . '|' . (int) $row->item_id;
            $opname = $endingFromOpname[$key] ?? null;
            if ($opname === null) {
                continue;
            }

            if (!isset($totalsByWarehouse[$warehouseId])) {
                $totalsByWarehouse[$warehouseId] = 0;
            }

            $totalsByWarehouse[$warehouseId] += (float) ($opname['qty_small'] ?? 0) * (float) ($opname['mac'] ?? 0);
        }

        return $totalsByWarehouse;
    }

    private function buildCogsRows($outlets, array $reportRows, string $tanggalAwalBulan, string $tanggalAkhirBulan): array
    {
        $cogsByOutlet = $this->computeCogsStockCutByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $categoryCostByOutlet = $this->computeCategoryCostByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $mealEmployeesByOutlet = $this->computeMealEmployeesByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $cogsAktualByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['cogs_aktual'] ?? 0))->all();
        $salesBeforeDiscountByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['sales_before_discount'] ?? 0))->all();
        $salesAfterDiscountByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['sales_after_discount'] ?? 0))->all();

        $cogsRows = [];
        foreach ($outlets as $outlet) {
            $cogs = round($cogsByOutlet[$outlet->id_outlet] ?? 0, 2);
            $categoryCost = round($categoryCostByOutlet[$outlet->id_outlet] ?? 0, 2);
            $mealEmployees = round($mealEmployeesByOutlet[$outlet->id_outlet] ?? 0, 2);
            $cogsPembanding = round($cogs + $categoryCost + $mealEmployees, 2);
            $cogsAktual = $cogsAktualByOutlet[$outlet->id_outlet] ?? 0;
            $deviasi = round($cogsPembanding - $cogsAktual, 2);
            $toleransi2Pct = round($cogsAktual * 0.02, 2);
            $salesBeforeDiscount = $salesBeforeDiscountByOutlet[$outlet->id_outlet] ?? 0;
            $salesAfterDiscount = $salesAfterDiscountByOutlet[$outlet->id_outlet] ?? 0;
            $pctCogsPembanding = $salesBeforeDiscount > 0 ? round(($cogsPembanding / $salesBeforeDiscount) * 100, 2) : null;
            $pctCogsActualBeforeDisc = $salesBeforeDiscount > 0 ? round(($cogsAktual / $salesBeforeDiscount) * 100, 2) : null;
            $pctCogsActualAfterDisc = $salesAfterDiscount > 0 ? round(($cogsAktual / $salesAfterDiscount) * 100, 2) : null;
            $pctCogsFoods = $salesBeforeDiscount > 0 ? round(($cogs / $salesBeforeDiscount) * 100, 2) : null;
            $pctDeviasi = $cogsPembanding > 0 ? round(($deviasi / $cogsPembanding) * 100, 2) : null;
            $pctCategoryCost = $cogsAktual > 0 ? round(($categoryCost / $cogsAktual) * 100, 2) : null;

            $cogsRows[] = [
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => $outlet->name,
                'cogs' => $cogs,
                'category_cost' => $categoryCost,
                'meal_employees' => $mealEmployees,
                'cogs_pembanding' => $cogsPembanding,
                'deviasi' => $deviasi,
                'toleransi_2_pct' => $toleransi2Pct,
                'pct_cogs_pembanding' => $pctCogsPembanding,
                'pct_cogs_actual_before_disc' => $pctCogsActualBeforeDisc,
                'pct_cogs_actual_after_disc' => $pctCogsActualAfterDisc,
                'pct_cogs_foods' => $pctCogsFoods,
                'pct_deviasi' => $pctDeviasi,
                'pct_category_cost' => $pctCategoryCost,
            ];
        }

        return $cogsRows;
    }

    private function buildCategoryCostRows($outlets, array $reportRows, string $tanggalAwalBulan, string $tanggalAkhirBulan): array
    {
        $guestSuppliesByOutlet = $this->computeGuestSuppliesByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $spoilageByOutlet = $this->computeSpoilageByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $wasteByOutlet = $this->computeWasteByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $nonCommodityByOutlet = $this->computeNonCommodityByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $cogsAktualByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['cogs_aktual'] ?? 0))->all();

        $categoryCostRows = [];
        foreach ($outlets as $outlet) {
            $cogsAktual = $cogsAktualByOutlet[$outlet->id_outlet] ?? 0;
            $guestSupplies = round($guestSuppliesByOutlet[$outlet->id_outlet] ?? 0, 2);
            $spoilage = round($spoilageByOutlet[$outlet->id_outlet] ?? 0, 2);
            $waste = round($wasteByOutlet[$outlet->id_outlet] ?? 0, 2);
            $nonCommodity = round($nonCommodityByOutlet[$outlet->id_outlet] ?? 0, 2);
            $categoryCostTotal = round($guestSupplies + $spoilage + $waste + $nonCommodity, 2);
            $pctGuestSupplies = $cogsAktual > 0 ? round(($guestSupplies / $cogsAktual) * 100, 2) : null;
            $pctSpoilage = $cogsAktual > 0 ? round(($spoilage / $cogsAktual) * 100, 2) : null;
            $pctWaste = $cogsAktual > 0 ? round(($waste / $cogsAktual) * 100, 2) : null;
            $pctNonCommodity = $cogsAktual > 0 ? round(($nonCommodity / $cogsAktual) * 100, 2) : null;
            $pctCategoryCost = $cogsAktual > 0 ? round(($categoryCostTotal / $cogsAktual) * 100, 2) : null;

            $categoryCostRows[] = [
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => $outlet->name,
                'guest_supplies' => $guestSupplies,
                'pct_guest_supplies' => $pctGuestSupplies,
                'spoilage' => $spoilage,
                'pct_spoilage' => $pctSpoilage,
                'waste' => $waste,
                'pct_waste' => $pctWaste,
                'non_commodity' => $nonCommodity,
                'pct_non_commodity' => $pctNonCommodity,
                'category_cost' => $categoryCostTotal,
                'pct_category_cost' => $pctCategoryCost,
            ];
        }

        return $categoryCostRows;
    }

    /**
     * Build report data for the given month (shared by index and export).
     */
    private function getReportData(string $bulan): array
    {
        $bulanCarbon = Carbon::parse($bulan . '-01');
        $bulanSebelumnya = $bulanCarbon->copy()->subMonth();
        $tanggalAkhirBulanSebelumnya = $bulanSebelumnya->format('Y-m-t');
        $tanggal1BulanIni = $bulanCarbon->format('Y-m-01');
        $tanggalAwalBulan = $bulanCarbon->format('Y-m-01');
        $tanggalAkhirBulan = $bulanCarbon->format('Y-m-t');

        // 1. Outlets: is_outlet=1, status='A'
        $outlets = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        $reportRows = Cache::remember(
            $this->getReportRowsCacheKey($bulan),
            now()->addMinutes(10),
            fn () => $this->buildCostInventoryRows(
                $outlets,
                $bulanSebelumnya,
                $tanggalAkhirBulanSebelumnya,
                $tanggal1BulanIni,
                $tanggalAwalBulan,
                $tanggalAkhirBulan
            )
        );

        // Tab COGS: outlet sama, kolom COGS + Category Cost + Meal Employees + COGS Pembanding + Deviasi
        $cogsByOutlet = $this->computeCogsStockCutByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $categoryCostByOutlet = $this->computeCategoryCostByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $mealEmployeesByOutlet = $this->computeMealEmployeesByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $cogsAktualByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['cogs_aktual'] ?? 0))->all();
        $salesBeforeDiscountByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['sales_before_discount'] ?? 0))->all();
        $salesAfterDiscountByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['sales_after_discount'] ?? 0))->all();
        $cogsRows = [];
        foreach ($outlets as $outlet) {
            $cogs = round($cogsByOutlet[$outlet->id_outlet] ?? 0, 2);
            $categoryCost = round($categoryCostByOutlet[$outlet->id_outlet] ?? 0, 2);
            $mealEmployees = round($mealEmployeesByOutlet[$outlet->id_outlet] ?? 0, 2);
            $cogsPembanding = round($cogs + $categoryCost + $mealEmployees, 2);
            $cogsAktual = $cogsAktualByOutlet[$outlet->id_outlet] ?? 0;
            $deviasi = round($cogsPembanding - $cogsAktual, 2);
            $toleransi2Pct = round($cogsAktual * 0.02, 2);
            $salesBeforeDiscount = $salesBeforeDiscountByOutlet[$outlet->id_outlet] ?? 0;
            $salesAfterDiscount = $salesAfterDiscountByOutlet[$outlet->id_outlet] ?? 0;
            $pctCogsPembanding = $salesBeforeDiscount > 0
                ? round(($cogsPembanding / $salesBeforeDiscount) * 100, 2)
                : null;
            $pctCogsActualBeforeDisc = $salesBeforeDiscount > 0
                ? round(($cogsAktual / $salesBeforeDiscount) * 100, 2)
                : null;
            $pctCogsActualAfterDisc = $salesAfterDiscount > 0
                ? round(($cogsAktual / $salesAfterDiscount) * 100, 2)
                : null;
            // % COGS Foods = cogs / sales before discount
            $pctCogsFoods = $salesBeforeDiscount > 0
                ? round(($cogs / $salesBeforeDiscount) * 100, 2)
                : null;
            // % Deviasi = deviasi / cogs pembanding (persentase dari COGS Pembanding)
            $pctDeviasi = $cogsPembanding > 0
                ? round(($deviasi / $cogsPembanding) * 100, 2)
                : null;
            // % Category Cost = category cost / cogs actual
            $pctCategoryCost = $cogsAktual > 0
                ? round(($categoryCost / $cogsAktual) * 100, 2)
                : null;
            $cogsRows[] = [
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => $outlet->name,
                'cogs' => $cogs,
                'category_cost' => $categoryCost,
                'meal_employees' => $mealEmployees,
                'cogs_pembanding' => $cogsPembanding,
                'deviasi' => $deviasi,
                'toleransi_2_pct' => $toleransi2Pct,
                'pct_cogs_pembanding' => $pctCogsPembanding,
                'pct_cogs_actual_before_disc' => $pctCogsActualBeforeDisc,
                'pct_cogs_actual_after_disc' => $pctCogsActualAfterDisc,
                'pct_cogs_foods' => $pctCogsFoods,
                'pct_deviasi' => $pctDeviasi,
                'pct_category_cost' => $pctCategoryCost,
            ];
        }

        // Tab Category Cost: outlet + Guest Supplies, Spoilage, Waste, Non Commodity (masing-masing + %), Category Cost (total + %)
        $guestSuppliesByOutlet = $this->computeGuestSuppliesByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $spoilageByOutlet = $this->computeSpoilageByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $wasteByOutlet = $this->computeWasteByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $nonCommodityByOutlet = $this->computeNonCommodityByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $categoryCostRows = [];
        foreach ($outlets as $outlet) {
            $cogsAktual = $cogsAktualByOutlet[$outlet->id_outlet] ?? 0;
            $guestSupplies = round($guestSuppliesByOutlet[$outlet->id_outlet] ?? 0, 2);
            $spoilage = round($spoilageByOutlet[$outlet->id_outlet] ?? 0, 2);
            $waste = round($wasteByOutlet[$outlet->id_outlet] ?? 0, 2);
            $nonCommodity = round($nonCommodityByOutlet[$outlet->id_outlet] ?? 0, 2);
            $categoryCostTotal = round($guestSupplies + $spoilage + $waste + $nonCommodity, 2);
            $pctGuestSupplies = $cogsAktual > 0 ? round(($guestSupplies / $cogsAktual) * 100, 2) : null;
            $pctSpoilage = $cogsAktual > 0 ? round(($spoilage / $cogsAktual) * 100, 2) : null;
            $pctWaste = $cogsAktual > 0 ? round(($waste / $cogsAktual) * 100, 2) : null;
            $pctNonCommodity = $cogsAktual > 0 ? round(($nonCommodity / $cogsAktual) * 100, 2) : null;
            $pctCategoryCost = $cogsAktual > 0 ? round(($categoryCostTotal / $cogsAktual) * 100, 2) : null;
            $categoryCostRows[] = [
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => $outlet->name,
                'guest_supplies' => $guestSupplies,
                'pct_guest_supplies' => $pctGuestSupplies,
                'spoilage' => $spoilage,
                'pct_spoilage' => $pctSpoilage,
                'waste' => $waste,
                'pct_waste' => $pctWaste,
                'non_commodity' => $nonCommodity,
                'pct_non_commodity' => $pctNonCommodity,
                'category_cost' => $categoryCostTotal,
                'pct_category_cost' => $pctCategoryCost,
            ];
        }

        return [
            'outlets' => $outlets,
            'reportRows' => $reportRows,
            'cogsRows' => $cogsRows,
            'categoryCostRows' => $categoryCostRows,
        ];
    }

    /**
     * Hitung total MAC begin inventory untuk satu (outlet, warehouse).
     * Logic mengikuti OutletStockReportController: begin = last stock opname bulan lalu
     * atau fallback initial_balance. Nilai MAC = qty_small * mac (per small unit).
     */
    private function computeBeginInventoryTotalMac(
        int $outletId,
        int $warehouseOutletId,
        Carbon $bulanSebelumnya,
        string $tanggalAkhirBulanSebelumnya,
        string $tanggal1BulanIni
    ): float {
        // Inventory items di outlet+warehouse ini
        $inventoryItems = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->where('s.id_outlet', $outletId)
            ->where('s.warehouse_outlet_id', $warehouseOutletId)
            ->select('fi.id as inventory_item_id', 'fi.item_id')
            ->distinct()
            ->get();

        if ($inventoryItems->isEmpty()) {
            return 0;
        }

        $inventoryItemIds = $inventoryItems->pluck('inventory_item_id')->toArray();

        // Begin from opname bulan lalu (dengan mac_after untuk nilai MAC)
        $beginInventoryFromOpname = [];
        $beginOpnamePrevMonth = DB::table('outlet_stock_opname_items as soi')
            ->join('outlet_stock_opnames as so', 'soi.stock_opname_id', '=', 'so.id')
            ->join('outlet_food_inventory_items as fi', 'soi.inventory_item_id', '=', 'fi.id')
            ->where('so.outlet_id', $outletId)
            ->where('so.warehouse_outlet_id', $warehouseOutletId)
            ->whereIn('so.status', ['APPROVED', 'COMPLETED'])
            ->where(function ($q) use ($bulanSebelumnya, $tanggal1BulanIni) {
                $q->where(function ($q2) use ($bulanSebelumnya) {
                    $q2->whereYear('so.opname_date', $bulanSebelumnya->year)
                       ->whereMonth('so.opname_date', $bulanSebelumnya->month);
                })->orWhereDate('so.opname_date', $tanggal1BulanIni);
            })
            ->select(
                'fi.item_id',
                'soi.qty_physical_small',
                'soi.qty_physical_medium',
                'soi.qty_physical_large',
                'soi.mac_after',
                'soi.mac_before',
                'so.opname_date',
                'so.id as so_id'
            )
            ->orderBy('so.opname_date', 'desc')
            ->orderBy('so.id', 'desc')
            ->get();

        foreach ($beginOpnamePrevMonth as $d) {
            if (isset($beginInventoryFromOpname[$d->item_id])) {
                continue;
            }
            $mac = (float) ($d->mac_after ?? $d->mac_before ?? 0);
            $beginInventoryFromOpname[$d->item_id] = [
                'qty_small' => (float) ($d->qty_physical_small ?? 0),
                'mac' => $mac,
            ];
        }

        // Fallback: initial_balance dari outlet_food_inventory_cards
        $beginFromUpload = [];
        $uploadCards = DB::table('outlet_food_inventory_cards as card')
            ->where('card.reference_type', 'initial_balance')
            ->where('card.id_outlet', $outletId)
            ->where('card.warehouse_outlet_id', $warehouseOutletId)
            ->where('card.date', '<=', $tanggalAkhirBulanSebelumnya)
            ->whereIn('card.inventory_item_id', $inventoryItemIds)
            ->orderBy('card.date', 'desc')
            ->orderBy('card.created_at', 'desc')
            ->select('card.inventory_item_id', 'card.saldo_qty_small', 'card.saldo_qty_medium', 'card.saldo_qty_large')
            ->get();

        foreach ($uploadCards as $r) {
            if (!isset($beginFromUpload[$r->inventory_item_id])) {
                $beginFromUpload[$r->inventory_item_id] = [
                    'qty_small' => (float) ($r->saldo_qty_small ?? 0),
                ];
            }
        }

        // Last cost (MAC) untuk item yang begin-nya dari upload - dari outlet_food_inventory_stocks
        $lastCostByInventoryItem = [];
        if (!empty($inventoryItemIds)) {
            $stocks = DB::table('outlet_food_inventory_stocks')
                ->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->whereIn('inventory_item_id', $inventoryItemIds)
                ->select('inventory_item_id', 'last_cost_small')
                ->get();
            foreach ($stocks as $s) {
                $lastCostByInventoryItem[$s->inventory_item_id] = (float) ($s->last_cost_small ?? 0);
            }
        }

        $totalMac = 0;
        foreach ($inventoryItems as $row) {
            $itemId = $row->item_id;
            $inventoryItemId = $row->inventory_item_id;

            $fromOpname = $beginInventoryFromOpname[$itemId] ?? null;
            $fromUpload = $beginFromUpload[$inventoryItemId] ?? null;

            $beginQtySmall = 0;
            $mac = 0;

            if ($fromOpname !== null) {
                $beginQtySmall = $fromOpname['qty_small'];
                $mac = $fromOpname['mac'];
            } elseif ($fromUpload !== null) {
                $beginQtySmall = $fromUpload['qty_small'];
                $mac = $lastCostByInventoryItem[$inventoryItemId] ?? 0;
            }

            $totalMac += $beginQtySmall * $mac;
        }

return $totalMac;
        }

    /**
     * Hitung total MAC ending inventory untuk satu (outlet, warehouse).
     * Hanya dari stock opname yang opname_date = tanggal terakhir bulan laporan (opname akhir bulan).
     * Jika belum ada stock opname akhir bulan, ending = 0 (tidak pakai opname tengah bulan).
     * Nilai MAC = qty_small * mac (per small unit).
     */
    private function computeEndingInventoryTotalMac(
        int $outletId,
        int $warehouseOutletId,
        string $tanggalAwalBulan,
        string $tanggalAkhirBulan
    ): float {
        $inventoryItems = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->where('s.id_outlet', $outletId)
            ->where('s.warehouse_outlet_id', $warehouseOutletId)
            ->select('fi.id as inventory_item_id', 'fi.item_id')
            ->distinct()
            ->get();

        if ($inventoryItems->isEmpty()) {
            return 0;
        }

        // Hanya dari opname yang tanggalnya = akhir bulan laporan (stock opname akhir bulan)
        $endingFromOpname = [];
        $endingOpnameMonth = DB::table('outlet_stock_opname_items as soi')
            ->join('outlet_stock_opnames as so', 'soi.stock_opname_id', '=', 'so.id')
            ->join('outlet_food_inventory_items as fi', 'soi.inventory_item_id', '=', 'fi.id')
            ->where('so.outlet_id', $outletId)
            ->where('so.warehouse_outlet_id', $warehouseOutletId)
            ->whereIn('so.status', ['APPROVED', 'COMPLETED'])
            ->where('so.opname_date', '=', $tanggalAkhirBulan)
            ->select(
                'fi.item_id',
                'soi.qty_physical_small',
                'soi.mac_after',
                'soi.mac_before'
            )
            ->orderBy('so.id', 'desc')
            ->get();

        foreach ($endingOpnameMonth as $d) {
            if (isset($endingFromOpname[$d->item_id])) {
                continue;
            }
            $mac = (float) ($d->mac_after ?? $d->mac_before ?? 0);
            $endingFromOpname[$d->item_id] = [
                'qty_small' => (float) ($d->qty_physical_small ?? 0),
                'mac' => $mac,
            ];
        }

        $totalMac = 0;
        foreach ($inventoryItems as $row) {
            $fromOpname = $endingFromOpname[$row->item_id] ?? null;
            if ($fromOpname === null) {
                continue; // Belum ada opname bulan ini → item ini tidak masuk ending
            }
            $totalMac += $fromOpname['qty_small'] * $fromOpname['mac'];
        }

        return $totalMac;
    }

    /**
     * Official Cost = nilai GR + Retail Food untuk bulan tertentu,
     * EXCLUDE barang dengan sub_category: Stationary, Marketing, Chemical.
     * Return array [ outlet_id => total_official_cost ].
     */
    private function computeOfficialCostByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        // 1) GR mengikuti pola Report Rekap FJ: group per outlet + item dulu, lalu dijumlah per outlet.
        //    Ini menjaga konsistensi perhitungan dengan report rekap FJ.
        $grItems = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->whereDate('gr.receive_date', '>=', $tanggalAwal)
            ->whereDate('gr.receive_date', '<=', $tanggalAkhir)
            ->whereNull('gr.deleted_at')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', [strtoupper('Stationary'), strtoupper('Marketing'), strtoupper('Chemical')])
            ->groupBy('gr.outlet_id', 'it.id', 'sc.name')
            ->select(
                'gr.outlet_id',
                DB::raw('SUM(i.received_qty * COALESCE(fo.price, 0)) as item_subtotal')
            )
            ->get();

        $grByOutlet = [];
        foreach ($grItems as $item) {
            $outletId = (int) $item->outlet_id;
            if (!isset($grByOutlet[$outletId])) {
                $grByOutlet[$outletId] = 0;
            }
            $grByOutlet[$outletId] += (float) ($item->item_subtotal ?? 0);
        }

        // 2) Tambahan Retail Food (sesuai permintaan):
        //    gunakan mapping nama item -> satu item master agar tidak double count jika nama item kembar.
        $itemNameMap = DB::table('items as im')
            ->select(DB::raw('MIN(im.id) as item_id'), DB::raw('TRIM(im.name) as item_name_key'))
            ->groupBy(DB::raw('TRIM(im.name)'));

        $retailRows = DB::table('retail_food as rf')
            ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
            ->joinSub($itemNameMap, 'map_item', function ($join) {
                $join->on(DB::raw('TRIM(rfi.item_name)'), '=', DB::raw('map_item.item_name_key'));
            })
            ->join('items as it', 'map_item.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->whereDate('rf.transaction_date', '>=', $tanggalAwal)
            ->whereDate('rf.transaction_date', '<=', $tanggalAkhir)
            ->where('rf.status', 'approved')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', [strtoupper('Stationary'), strtoupper('Marketing'), strtoupper('Chemical')])
            ->groupBy('rf.outlet_id')
            ->select('rf.outlet_id', DB::raw('SUM(rfi.subtotal) as total_retail'))
            ->get();

        $retailByOutlet = [];
        foreach ($retailRows as $row) {
            $retailByOutlet[(int) $row->outlet_id] = (float) ($row->total_retail ?? 0);
        }

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $gr = (float) ($grByOutlet[(int) $oid] ?? 0);
            $retail = (float) ($retailByOutlet[(int) $oid] ?? 0);
            $result[$oid] = $gr + $retail;
        }
        return $result;
    }

    /**
     * Cost RND = total dari menu Category Cost Outlet dengan type RnD dan Marketing.
     * Sumber: outlet_internal_use_waste_headers (type IN r_and_d, marketing), status APPROVED,
     * date dalam bulan laporan. Nilai = SUM(subtotal_mac).
     * Return array [ outlet_id => total_cost_rnd ].
     */
    private function computeCostRndByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $aggregates = $this->getInternalUseWasteAggregates($tanggalAwal, $tanggalAkhir);

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = (float) (($aggregates['r_and_d'][$oid] ?? 0) + ($aggregates['marketing'][$oid] ?? 0));
        }

        return $result;
    }

    /**
     * Category Cost = total dari menu Category Cost Outlet dengan type spoil, waste, guest_supplies, non_commodity.
     * Sumber: outlet_internal_use_waste_headers, status APPROVED, date dalam bulan laporan. Nilai = SUM(subtotal_mac).
     * Return array [ outlet_id => total ].
     */
    private function computeCategoryCostByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $aggregates = $this->getInternalUseWasteAggregates($tanggalAwal, $tanggalAkhir);

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = (float) (
                ($aggregates['spoil'][$oid] ?? 0)
                + ($aggregates['waste'][$oid] ?? 0)
                + ($aggregates['guest_supplies'][$oid] ?? 0)
                + ($aggregates['non_commodity'][$oid] ?? 0)
            );
        }

        return $result;
    }

    /**
     * Meal Employees = total dari menu Category Cost Outlet dengan type internal_use.
     * Sumber: outlet_internal_use_waste_headers, status APPROVED, date dalam bulan laporan. Nilai = SUM(subtotal_mac).
     * Return array [ outlet_id => total ].
     */
    private function computeMealEmployeesByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        return $this->computeCategoryCostTypeByOutlet('internal_use', $tanggalAwal, $tanggalAkhir);
    }

    /**
     * Guest Supplies = total dari menu Category Cost Outlet dengan type guest_supplies.
     * Sumber: outlet_internal_use_waste_headers, status APPROVED/PROCESSED, date dalam bulan laporan. Nilai = SUM(subtotal_mac).
     * Return array [ outlet_id => total ].
     */
    private function computeGuestSuppliesByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        return $this->computeCategoryCostTypeByOutlet('guest_supplies', $tanggalAwal, $tanggalAkhir);
    }

    /**
     * Spoilage = total dari menu Category Cost Outlet dengan type spoil.
     * Return array [ outlet_id => total ].
     */
    private function computeSpoilageByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        return $this->computeCategoryCostTypeByOutlet('spoil', $tanggalAwal, $tanggalAkhir);
    }

    /**
     * Waste = total dari menu Category Cost Outlet dengan type waste.
     * Return array [ outlet_id => total ].
     */
    private function computeWasteByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        return $this->computeCategoryCostTypeByOutlet('waste', $tanggalAwal, $tanggalAkhir);
    }

    /**
     * Non Commodity = total dari menu Category Cost Outlet dengan type non_commodity.
     * Return array [ outlet_id => total ].
     */
    private function computeNonCommodityByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        return $this->computeCategoryCostTypeByOutlet('non_commodity', $tanggalAwal, $tanggalAkhir);
    }

    /**
     * Helper: total per outlet untuk satu type dari outlet_internal_use_waste_headers.
     * Return array [ outlet_id => total ].
     */
    private function computeCategoryCostTypeByOutlet(string $type, string $tanggalAwal, string $tanggalAkhir): array
    {
        $aggregates = $this->getInternalUseWasteAggregates($tanggalAwal, $tanggalAkhir);

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = (float) ($aggregates[$type][$oid] ?? 0);
        }

        return $result;
    }

    /**
     * Hitung sekali nilai detail+MAC untuk semua type Category Cost yang dipakai Cost Report.
     * Cache per periode untuk menghindari query berat berulang saat build tab.
     *
     * Rule status:
     * - r_and_d, marketing: APPROVED saja
     * - internal_use, spoil, waste, guest_supplies, non_commodity: APPROVED/PROCESSED
     *
     * @return array<string, array<int, float>>
     */
    private function getInternalUseWasteAggregates(string $tanggalAwal, string $tanggalAkhir): array
    {
        $cacheKey = $tanggalAwal . '|' . $tanggalAkhir;
        if (isset($this->internalUseWasteAggregatesCache[$cacheKey])) {
            return $this->internalUseWasteAggregatesCache[$cacheKey];
        }

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $trackedTypes = ['r_and_d', 'marketing', 'internal_use', 'spoil', 'waste', 'guest_supplies', 'non_commodity'];

        $aggregates = [];
        foreach ($trackedTypes as $trackedType) {
            $aggregates[$trackedType] = [];
            foreach ($outletIds as $oid) {
                $aggregates[$trackedType][$oid] = 0;
            }
        }

        $headers = DB::table('outlet_internal_use_waste_headers as h')
            ->whereIn('h.type', $trackedTypes)
            ->whereBetween('h.date', [$tanggalAwal, $tanggalAkhir]);

        $headers->where(function ($query) {
            $query->where(function ($sub) {
                $sub->whereIn('h.type', ['r_and_d', 'marketing'])
                    ->where('h.status', 'APPROVED');
            })->orWhere(function ($sub) {
                $sub->whereIn('h.type', ['internal_use', 'spoil', 'waste', 'guest_supplies', 'non_commodity'])
                    ->whereIn('h.status', ['APPROVED', 'PROCESSED']);
            });
        });

        $headers = $headers
            ->select('h.id', 'h.type', 'h.outlet_id', 'h.warehouse_outlet_id', 'h.date')
            ->get();

        if ($headers->isEmpty()) {
            $this->internalUseWasteAggregatesCache[$cacheKey] = $aggregates;
            return $aggregates;
        }

        $headerIds = $headers->pluck('id')->all();
        $headersById = $headers->keyBy('id');

        $details = DB::table('outlet_internal_use_waste_details as d')
            ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
            ->whereIn('d.header_id', $headerIds)
            ->select(
                'd.header_id',
                'd.item_id',
                'd.unit_id',
                'd.qty',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            )
            ->get();

        if ($details->isEmpty()) {
            $this->internalUseWasteAggregatesCache[$cacheKey] = $aggregates;
            return $aggregates;
        }

        $itemIds = $details->pluck('item_id')->filter()->unique()->values()->all();
        $inventoryItems = [];
        if (!empty($itemIds)) {
            $inventoryItems = DB::table('outlet_food_inventory_items')
                ->whereIn('item_id', $itemIds)
                ->select('id', 'item_id')
                ->get()
                ->keyBy('item_id')
                ->toArray();
        }

        $macQueryConditions = [];
        foreach ($details as $detail) {
            $header = $headersById->get($detail->header_id);
            if (!$header || !isset($inventoryItems[$detail->item_id])) {
                continue;
            }

            $inventoryItemId = $inventoryItems[$detail->item_id]->id;
            $key = "{$inventoryItemId}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
            if (!isset($macQueryConditions[$key])) {
                $macQueryConditions[$key] = [
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $header->outlet_id,
                    'warehouse_outlet_id' => $header->warehouse_outlet_id,
                    'date' => $header->date,
                ];
            }
        }

        $macHistories = [];
        foreach ($macQueryConditions as $key => $condition) {
            $macHistories[$key] = null;
        }

        if (!empty($macQueryConditions)) {
            $inventoryItemIdsForMac = [];
            $outletIdsForMac = [];
            $warehouseIdsForMac = [];
            $maxDate = null;

            foreach ($macQueryConditions as $condition) {
                $inventoryItemIdsForMac[] = (int) $condition['inventory_item_id'];
                $outletIdsForMac[] = (int) $condition['id_outlet'];
                $warehouseIdsForMac[] = (int) $condition['warehouse_outlet_id'];
                $maxDate = $maxDate === null ? $condition['date'] : max($maxDate, $condition['date']);
            }

            $historyRows = DB::table('outlet_food_inventory_cost_histories')
                ->whereIn('inventory_item_id', array_values(array_unique($inventoryItemIdsForMac)))
                ->whereIn('id_outlet', array_values(array_unique($outletIdsForMac)))
                ->whereIn('warehouse_outlet_id', array_values(array_unique($warehouseIdsForMac)))
                ->where('date', '<=', $maxDate)
                ->select('inventory_item_id', 'id_outlet', 'warehouse_outlet_id', 'date', 'id', 'mac')
                ->orderBy('inventory_item_id')
                ->orderBy('id_outlet')
                ->orderBy('warehouse_outlet_id')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get();

            $historiesByTuple = [];
            foreach ($historyRows as $historyRow) {
                $tupleKey = (int) $historyRow->inventory_item_id . '|'
                    . (int) $historyRow->id_outlet . '|'
                    . (int) $historyRow->warehouse_outlet_id;
                $historiesByTuple[$tupleKey][] = $historyRow;
            }

            foreach ($macQueryConditions as $key => $condition) {
                $tupleKey = (int) $condition['inventory_item_id'] . '|'
                    . (int) $condition['id_outlet'] . '|'
                    . (int) $condition['warehouse_outlet_id'];
                $targetDate = $condition['date'];

                foreach (($historiesByTuple[$tupleKey] ?? []) as $historyRow) {
                    if ($historyRow->date <= $targetDate) {
                        $macHistories[$key] = (float) ($historyRow->mac ?? 0);
                        break;
                    }
                }
            }
        }

        foreach ($details as $detail) {
            $header = $headersById->get($detail->header_id);
            if (!$header || !isset($inventoryItems[$detail->item_id])) {
                continue;
            }

            $inventoryItemId = $inventoryItems[$detail->item_id]->id;
            $macKey = "{$inventoryItemId}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
            $mac = $macHistories[$macKey] ?? null;
            if ($mac === null) {
                continue;
            }

            $macConverted = $mac;
            if ((int) $detail->unit_id === (int) $detail->medium_unit_id && (float) $detail->small_conversion_qty > 0) {
                $macConverted = $mac * (float) $detail->small_conversion_qty;
            } elseif (
                (int) $detail->unit_id === (int) $detail->large_unit_id
                && (float) $detail->small_conversion_qty > 0
                && (float) $detail->medium_conversion_qty > 0
            ) {
                $macConverted = $mac * (float) $detail->small_conversion_qty * (float) $detail->medium_conversion_qty;
            }

            $subtotalMac = $macConverted * (float) ($detail->qty ?? 0);
            $headerType = $header->type ?? null;

            if ($headerType && isset($aggregates[$headerType][$header->outlet_id])) {
                $aggregates[$headerType][$header->outlet_id] += $subtotalMac;
            }
        }

        $this->internalUseWasteAggregatesCache[$cacheKey] = $aggregates;
        return $aggregates;
    }

    /**
     * Outlet Transfer = net per outlet dari menu Outlet Transfer (status approved).
     * Outlet yang mengirim stock: nilai minus (pengurangan).
     * Outlet yang menerima stock: nilai plus (penambahan).
     * Return array [ outlet_id => net_value ] dengan net = received - sent.
     */
    private function computeOutletTransferByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');
        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = 0;
        }

        // 1. Nilai diterima (receiver): dari outlet_food_inventory_cost_histories (reference_type=outlet_transfer)
        //    join transfer items → sum(qty_small * mac) per id_outlet
        $receivedRows = DB::table('outlet_food_inventory_cost_histories as h')
            ->join('outlet_transfers as t', 't.id', '=', 'h.reference_id')
            ->join('outlet_transfer_items as d', function ($j) {
                $j->on('d.outlet_transfer_id', '=', 't.id');
            })
            ->join('outlet_food_inventory_items as ofii', function ($j) {
                $j->on('ofii.id', '=', 'h.inventory_item_id')->on('ofii.item_id', '=', 'd.item_id');
            })
            ->where('h.reference_type', 'outlet_transfer')
            ->where('t.status', 'approved')
            ->whereBetween('h.date', [$tanggalAwal, $tanggalAkhir])
            ->groupBy('h.id_outlet')
            ->select('h.id_outlet', DB::raw('SUM(d.qty_small * COALESCE(h.mac, 0)) as total_received'))
            ->get();
        foreach ($receivedRows as $r) {
            $result[$r->id_outlet] = (float) ($r->total_received ?? 0);
        }

        // 2. Nilai dikirim (sender): per transfer approved, outlet asal = warehouse_outlet_from → outlet_id
        //    nilai = sum over items (qty_small * MAC di outlet asal pada transfer_date)
        $transfers = DB::table('outlet_transfers as t')
            ->join('warehouse_outlets as wo_from', 'wo_from.id', '=', 't.warehouse_outlet_from_id')
            ->where('t.status', 'approved')
            ->whereBetween('t.transfer_date', [$tanggalAwal, $tanggalAkhir])
            ->select('t.id', 't.transfer_date', 't.warehouse_outlet_from_id', 'wo_from.outlet_id as from_outlet_id')
            ->get();

        foreach ($transfers as $t) {
            $items = DB::table('outlet_transfer_items')->where('outlet_transfer_id', $t->id)->get();
            foreach ($items as $item) {
                $qtySmall = (float) ($item->qty_small ?? 0);
                if ($qtySmall <= 0) {
                    continue;
                }
                $ofii = DB::table('outlet_food_inventory_items')->where('item_id', $item->item_id)->first();
                if (!$ofii) {
                    continue;
                }
                $mac = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $ofii->id)
                    ->where('id_outlet', $t->from_outlet_id)
                    ->where('warehouse_outlet_id', $t->warehouse_outlet_from_id)
                    ->where('date', '<=', $t->transfer_date)
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->value('mac');
                $valueSent = $qtySmall * (float) ($mac ?? 0);
                $result[$t->from_outlet_id] -= $valueSent;
            }
        }

        return $result;
    }

    /**
     * Sales Before Discount per outlet: sumber sama seperti menu Engineering (Item Engineering).
     * orders + order_items, SUM(qty * price) per outlet, filter tanggal created_at dalam bulan laporan.
     * Mapping outlet via orders.kode_outlet = tbl_data_outlet.qr_code.
     * Return array [ outlet_id => total_sales_before_discount ].
     */
    private function computeSalesBeforeDiscountByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $rows = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
            ->where('o.is_outlet', 1)
            ->where('o.status', 'A')
            ->whereDate('orders.created_at', '>=', $tanggalAwal)
            ->whereDate('orders.created_at', '<=', $tanggalAkhir)
            ->groupBy('o.id_outlet')
            ->select('o.id_outlet', DB::raw('SUM(order_items.qty * order_items.price) as total_sales'))
            ->get();

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = 0;
        }
        foreach ($rows as $r) {
            $result[$r->id_outlet] = (float) ($r->total_sales ?? 0);
        }
        return $result;
    }

    /**
     * Total discount per outlet: discount promo + manual discount (sumber: report rekap discount).
     * Promo = orders.discount, manual = orders.manual_discount_amount.
     * Filter: orders.created_at dalam bulan laporan, outlet via kode_outlet = qr_code (is_outlet=1, status=A).
     * Return array [ outlet_id => total_discount ].
     */
    private function computeDiscountByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $rows = DB::table('orders')
            ->join('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
            ->where('o.is_outlet', 1)
            ->where('o.status', 'A')
            ->whereDate('orders.created_at', '>=', $tanggalAwal)
            ->whereDate('orders.created_at', '<=', $tanggalAkhir)
            ->groupBy('o.id_outlet')
            ->select(
                'o.id_outlet',
                DB::raw('COALESCE(SUM(orders.discount), 0) + COALESCE(SUM(orders.manual_discount_amount), 0) as total_discount')
            )
            ->get();

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = 0;
        }
        foreach ($rows as $r) {
            $result[$r->id_outlet] = (float) ($r->total_discount ?? 0);
        }
        return $result;
    }

    /**
     * COGS dari hasil stock cut bulan tersebut (menu Stock Cut).
     * Sumber: stock_cut_details join stock_cut_logs, SUM(value_out) per outlet.
     * value_out = nilai cost yang keluar saat potong stock (qty * cost per small).
     * Filter: scl.tanggal dalam bulan laporan, scl.status = 'success'.
     * Return array [ outlet_id => total_cogs ].
     */
    private function computeCogsStockCutByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $rows = DB::table('stock_cut_details as scd')
            ->join('stock_cut_logs as scl', 'scd.stock_cut_log_id', '=', 'scl.id')
            ->whereBetween('scl.tanggal', [$tanggalAwal, $tanggalAkhir])
            ->where('scl.status', 'success')
            ->groupBy('scl.outlet_id')
            ->select('scl.outlet_id', DB::raw('SUM(COALESCE(scd.value_out, 0)) as total_cogs'))
            ->get();

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = 0;
        }
        foreach ($rows as $r) {
            $result[$r->outlet_id] = (float) ($r->total_cogs ?? 0);
        }
        return $result;
    }
}
