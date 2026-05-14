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
        // v2: agregasi per gudang saja (tanpa divisi / tanpa join GR bobot).
        return 'cost_report_ho:cost_rows_v2:' . $bulan;
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
     * Struktur tab Perbandingan COGS Ideal: gudang saja (tanpa divisi).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCogsIdealHoSkeletonRows(string $_bulan): array
    {
        $warehouses = DB::table('warehouses')
            ->where('status', 'active')
            ->orderBy('name')
            ->select('id', 'name', 'code')
            ->get();

        $rows = [];
        foreach ($warehouses as $wh) {
            $rows[] = [
                'warehouse_id' => (int) $wh->id,
                'warehouse_name' => $wh->name,
                'warehouse_code' => $wh->code ?? null,
                'divisions' => [],
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

        $beginByWarehouse = [];
        foreach ($beginByWhItem as $k => $v) {
            $parts = explode('|', $k, 2);
            $wid = (int) ($parts[0] ?? 0);
            $beginByWarehouse[$wid] = ($beginByWarehouse[$wid] ?? 0) + (float) $v;
        }

        $rows = [];
        foreach ($warehouses as $wh) {
            $wid = (int) $wh->id;
            $rows[] = [
                'warehouse_id' => $wid,
                'warehouse_name' => $wh->name,
                'warehouse_code' => $wh->code ?? null,
                'begin_inventory' => round((float) ($beginByWarehouse[$wid] ?? 0), 2),
                'divisions' => [],
            ];
        }

        return $rows;
    }

    /**
     * Satu baris per (warehouse_id, inventory_item_id) dari food_inventory_stocks — tanpa join master item
     * agar tidak ada duplikasi baris stok.
     */
    private function loadHoStockRows(array $warehouseIds)
    {
        if (empty($warehouseIds)) {
            return collect();
        }

        return DB::table('food_inventory_stocks as s')
            ->whereIn('s.warehouse_id', $warehouseIds)
            ->select(
                's.warehouse_id',
                's.inventory_item_id',
                's.qty_small',
                's.last_cost_small'
            )
            ->get()
            ->unique(fn ($r) => (int) $r->warehouse_id . '|' . (int) $r->inventory_item_id)
            ->values();
    }

    /**
     * Begin inventory (nilai MAC) per (warehouse_id, inventory_item_id).
     * Logika disamakan dengan CostReportController::computeBeginInventoryTotalMacByWarehouse:
     * id_outlet + warehouse_outlet_id → warehouse_id; outlet_food_inventory_cards → food_inventory_cards.
     *
     * @return array<string, float> key "warehouse_id|inventory_item_id"
     */
    private function computeHoBeginInventoryMacByWarehouseItem(
        $stockRows,
        array $warehouseIds,
        string $tanggal1BulanIni
    ): array {
        if ($stockRows->isEmpty() || empty($warehouseIds)) {
            return [];
        }

        $inventoryItemIds = $stockRows->pluck('inventory_item_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (empty($inventoryItemIds)) {
            return [];
        }

        /**
         * BEGIN INVENTORY (AUTO MODE) — selaras Cost Report outlet:
         * - Jika gudang punya upload saldo awal (reference_type=initial_balance) pada tanggal 1 bulan laporan,
         *   maka untuk gudang tsb nilai per item HANYA dari initial_balance day-1 (latest per item+gudang).
         * - Jika gudang TIDAK punya initial_balance day-1 sama sekali, maka dari stok sistem:
         *   qty_small * last_cost_small (sama seperti Cost Report).
         */
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

        // (A) initial_balance day-1 snapshot (latest per warehouse+item)
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

        // (B) last stock from system (stocks table): qty_small * last_cost_small (per warehouse+item)
        $stockValueMap = [];
        foreach ($stockRows as $row) {
            $k = (int) $row->warehouse_id . '|' . (int) $row->inventory_item_id;
            if (! isset($stockValueMap[$k])) {
                $qtySmall = (float) ($row->qty_small ?? 0);
                $macSmall = (float) ($row->last_cost_small ?? 0);
                $stockValueMap[$k] = $qtySmall * $macSmall;
            }
        }

        $uniqueKeys = [];
        foreach ($stockRows as $row) {
            $k = (int) $row->warehouse_id . '|' . (int) $row->inventory_item_id;
            $uniqueKeys[$k] = true;
        }

        $totalsByKey = [];
        foreach (array_keys($uniqueKeys) as $k) {
            [$whIdKey] = array_map('intval', explode('|', $k, 2));
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
