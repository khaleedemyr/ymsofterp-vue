<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CostReportHoController extends Controller
{
    private function cacheKeyCostRows(string $bulan): string
    {
        return 'cost_report_ho:cost_rows:' . $bulan;
    }

    public function index(Request $request)
    {
        $bulan = $request->input('bulan', date('Y-m'));
        $shouldLoad = $request->boolean('load') || $request->input('load') === '1';

        if (! $shouldLoad) {
            return Inertia::render('CostReportHo/Index', [
                'costRows' => [],
                'filters' => ['bulan' => $bulan],
            ]);
        }

        $request->validate([
            'bulan' => 'required|date_format:Y-m',
        ]);
        $bulan = $request->input('bulan', date('Y-m'));

        $costRows = Cache::remember(
            $this->cacheKeyCostRows($bulan),
            now()->addMinutes(10),
            fn () => $this->buildCostHoRows($bulan)
        );

        return Inertia::render('CostReportHo/Index', [
            'costRows' => $costRows,
            'filters' => ['bulan' => $bulan],
        ]);
    }

    public function tabData(Request $request)
    {
        $request->validate([
            'bulan' => 'nullable|date_format:Y-m',
            'tab' => 'nullable|string|in:cost,cogs_ideal',
        ]);

        $bulan = $request->input('bulan', date('Y-m'));
        $tab = $request->input('tab', 'cost');

        if (! in_array($tab, ['cost', 'cogs_ideal'], true)) {
            return response()->json(['success' => false, 'message' => 'Tab tidak valid.'], 422);
        }

        if ($tab === 'cost') {
            $costRows = Cache::remember(
                $this->cacheKeyCostRows($bulan),
                now()->addMinutes(10),
                fn () => $this->buildCostHoRows($bulan)
            );

            return response()->json([
                'success' => true,
                'tab' => $tab,
                'costRows' => $costRows,
            ]);
        }

        $comparisonRows = $this->buildCogsIdealHoSkeletonRows($bulan);

        return response()->json([
            'success' => true,
            'tab' => $tab,
            'comparisonRows' => $comparisonRows,
        ]);
    }

    public function clearCache(Request $request)
    {
        $validated = $request->validate([
            'bulan' => ['required', 'date_format:Y-m'],
        ]);
        Cache::forget($this->cacheKeyCostRows($validated['bulan']));

        return response()->json([
            'success' => true,
            'message' => 'Cache Cost Report HO berhasil dibersihkan.',
            'bulan' => $validated['bulan'],
        ]);
    }

    /**
     * Pohon gudang + divisi (master), tanpa angka — placeholder tab Perbandingan COGS Ideal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCogsIdealHoSkeletonRows(string $bulan): array
    {
        $warehouses = DB::table('warehouses')
            ->where('status', 'active')
            ->orderBy('name')
            ->select('id', 'name', 'code')
            ->get();

        $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all();
        $divisionsByWarehouse = [];
        if (! empty($warehouseIds)) {
            $divisionsByWarehouse = DB::table('warehouse_division')
                ->whereIn('warehouse_id', $warehouseIds)
                ->where('status', 'active')
                ->orderBy('name')
                ->select('id', 'name', 'warehouse_id')
                ->get()
                ->groupBy('warehouse_id');
        }

        $rows = [];
        foreach ($warehouses as $wh) {
            $wid = (int) $wh->id;
            $divs = [];
            foreach (($divisionsByWarehouse[$wid] ?? collect()) as $div) {
                $divs[] = [
                    'division_id' => (int) $div->id,
                    'division_name' => $div->name,
                ];
            }
            $rows[] = [
                'warehouse_id' => $wid,
                'warehouse_name' => $wh->name,
                'warehouse_code' => $wh->code ?? null,
                'divisions' => $divs,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCostHoRows(string $bulan): array
    {
        $bulanCarbon = Carbon::parse($bulan . '-01');
        $tanggal1BulanIni = $bulanCarbon->format('Y-m-01');

        $warehouses = DB::table('warehouses')
            ->where('status', 'active')
            ->orderBy('name')
            ->select('id', 'name', 'code')
            ->get();

        $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (empty($warehouseIds)) {
            return [];
        }

        $stockRows = $this->loadHoStockRows($warehouseIds);
        $beginByWhItem = $this->computeHoBeginInventoryMacByWarehouseItem(
            $stockRows,
            $warehouseIds,
            $tanggal1BulanIni
        );

        $beginByWarehouseDivision = [];
        foreach ($stockRows->unique(fn ($row) => (int) $row->warehouse_id . '|' . (int) $row->inventory_item_id) as $row) {
            $wh = (int) $row->warehouse_id;
            $ii = (int) $row->inventory_item_id;
            $k = $wh . '|' . $ii;
            $val = (float) ($beginByWhItem[$k] ?? 0);
            $divId = $row->warehouse_division_id !== null && $row->warehouse_division_id !== ''
                ? (int) $row->warehouse_division_id
                : null;
            $divKey = $divId !== null ? (string) $divId : 'none';
            $aggKey = $wh . '|' . $divKey;
            $beginByWarehouseDivision[$aggKey] = ($beginByWarehouseDivision[$aggKey] ?? 0) + $val;
        }

        $divisionsByWarehouse = DB::table('warehouse_division')
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('status', 'active')
            ->orderBy('name')
            ->select('id', 'name', 'warehouse_id')
            ->get()
            ->groupBy('warehouse_id');

        $rows = [];
        foreach ($warehouses as $wh) {
            $wid = (int) $wh->id;
            $divs = [];
            $total = 0.0;
            foreach (($divisionsByWarehouse[$wid] ?? collect()) as $div) {
                $ak = $wid . '|' . (string) $div->id;
                $amt = round((float) ($beginByWarehouseDivision[$ak] ?? 0), 2);
                $total += $amt;
                $divs[] = [
                    'division_id' => (int) $div->id,
                    'division_name' => $div->name,
                    'begin_inventory' => $amt,
                ];
            }
            $noneKey = $wid . '|none';
            $noneAmt = round((float) ($beginByWarehouseDivision[$noneKey] ?? 0), 2);
            if ($noneAmt !== 0.0) {
                $total += $noneAmt;
                $divs[] = [
                    'division_id' => null,
                    'division_name' => 'Tanpa divisi (item)',
                    'begin_inventory' => $noneAmt,
                ];
            }

            $rows[] = [
                'warehouse_id' => $wid,
                'warehouse_name' => $wh->name,
                'warehouse_code' => $wh->code ?? null,
                'begin_inventory' => round($total, 2),
                'divisions' => $divs,
            ];
        }

        return $rows;
    }

    private function loadHoStockRows(array $warehouseIds)
    {
        if (empty($warehouseIds)) {
            return collect();
        }

        return DB::table('food_inventory_stocks as s')
            ->join('food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->whereIn('s.warehouse_id', $warehouseIds)
            ->select(
                's.warehouse_id',
                's.inventory_item_id',
                's.qty_small',
                's.last_cost_small',
                'i.warehouse_division_id'
            )
            ->get();
    }

    /**
     * Begin inventory (nilai MAC) per (warehouse_id, inventory_item_id).
     * Logika selaras Cost Report outlet: jika gudang punya kartu initial_balance tanggal 1 bulan laporan,
     * pakai saldo_value snapshot terbaru per item; jika tidak, pakai qty_small * last_cost_small dari stok.
     *
     * @return array<string, float> key "warehouse_id|inventory_item_id"
     */
    private function computeHoBeginInventoryMacByWarehouseItem($stockRows, array $warehouseIds, string $tanggal1BulanIni): array
    {
        if ($stockRows->isEmpty() || empty($warehouseIds)) {
            return [];
        }

        $inventoryItemIds = $stockRows->pluck('inventory_item_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (empty($inventoryItemIds)) {
            return [];
        }

        $warehouseIdsSql = implode(',', array_map('intval', $warehouseIds));
        $inventoryIdsSql = implode(',', array_map('intval', $inventoryItemIds));

        $warehousesWithDay1Initial = DB::table('food_inventory_cards as c')
            ->whereIn('c.warehouse_id', $warehouseIds)
            ->whereIn('c.inventory_item_id', $inventoryItemIds)
            ->where('c.reference_type', 'initial_balance')
            ->whereDate('c.date', $tanggal1BulanIni)
            ->distinct()
            ->pluck('c.warehouse_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $warehousesWithDay1InitialSet = array_fill_keys($warehousesWithDay1Initial, true);

        $subInit = "
            SELECT
                card.warehouse_id,
                card.inventory_item_id,
                MAX(
                    CONCAT(
                        DATE(card.date),
                        ' ',
                        LPAD(card.id, 20, '0')
                    )
                ) AS mx
            FROM food_inventory_cards card
            WHERE card.warehouse_id IN ({$warehouseIdsSql})
              AND card.inventory_item_id IN ({$inventoryIdsSql})
              AND card.reference_type = 'initial_balance'
              AND DATE(card.date) = '{$tanggal1BulanIni}'
            GROUP BY card.warehouse_id, card.inventory_item_id
        ";

        $saldoRowsInit = DB::table('food_inventory_cards as card')
            ->join(DB::raw("({$subInit}) t"), function ($join) {
                $join->on('t.warehouse_id', '=', 'card.warehouse_id')
                    ->on('t.inventory_item_id', '=', 'card.inventory_item_id');
            })
            ->whereRaw("t.mx = CONCAT(DATE(card.date), ' ', LPAD(card.id, 20, '0'))")
            ->select(
                'card.warehouse_id',
                'card.inventory_item_id',
                'card.saldo_value'
            )
            ->get();

        $saldoValueInitMap = [];
        foreach ($saldoRowsInit as $r) {
            $k = (int) $r->warehouse_id . '|' . (int) $r->inventory_item_id;
            $saldoValueInitMap[$k] = (float) ($r->saldo_value ?? 0);
        }

        $stockValueMap = [];
        foreach ($stockRows->unique(fn ($row) => (int) $row->warehouse_id . '|' . (int) $row->inventory_item_id) as $row) {
            $k = (int) $row->warehouse_id . '|' . (int) $row->inventory_item_id;
            if (! isset($stockValueMap[$k])) {
                $qtySmall = (float) ($row->qty_small ?? 0);
                $macSmall = (float) ($row->last_cost_small ?? 0);
                $stockValueMap[$k] = $qtySmall * $macSmall;
            }
        }

        $uniqueKeys = [];
        foreach ($stockRows->unique(fn ($row) => (int) $row->warehouse_id . '|' . (int) $row->inventory_item_id) as $row) {
            $k = (int) $row->warehouse_id . '|' . (int) $row->inventory_item_id;
            $uniqueKeys[$k] = true;
        }

        $totalsByKey = [];
        foreach (array_keys($uniqueKeys) as $k) {
            $parts = explode('|', $k, 2);
            $whIdKey = (int) ($parts[0] ?? 0);
            $useInitOnly = isset($warehousesWithDay1InitialSet[$whIdKey]);
            $totalsByKey[$k] = (float) (
                $useInitOnly
                    ? ($saldoValueInitMap[$k] ?? 0)
                    : ($stockValueMap[$k] ?? 0)
            );
        }

        return $totalsByKey;
    }
}
