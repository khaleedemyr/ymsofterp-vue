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
        // v4: begin bulan laporan = saldo_qty_small (kartu stok terakhir s/d akhir bulan lalu) × mac (histori terakhir s/d tanggal sama).
        return 'cost_report_ho:cost_rows_v4:' . $bulan;
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
        $tanggalAkhirBulanSebelumnya = $bulanCarbon->copy()->subMonth()->format('Y-m-t');

        $warehouses = DB::table('warehouses')
            ->where('status', 'active')
            ->orderBy('name')
            ->select('id', 'name', 'code')
            ->get();

        $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (empty($warehouseIds)) {
            return [];
        }

        $beginByWhItem = $this->computeHoBeginInventoryFromCardsAndHistory(
            $warehouseIds,
            $tanggalAkhirBulanSebelumnya
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
     * Begin inventory per (warehouse_id, inventory_item_id) untuk bulan laporan $bulan:
     * saldo_qty_small dari kartu food_inventory_cards terakhir s/d tanggal cutoff (akhir bulan sebelum bulan laporan),
     * dikalikan mac dari food_inventory_cost_histories terakhir s/d cutoff yang sama.
     * Tanpa kartu di rentang tersebut → nilai 0 untuk pasangan itu. Tanpa mac → fallback cost_per_small kartu, lalu new_cost histori.
     *
     * @return array<string, float> key "warehouse_id|inventory_item_id"
     */
    private function computeHoBeginInventoryFromCardsAndHistory(
        array $warehouseIds,
        string $tanggalAkhirBulanSebelumnya
    ): array {
        if (empty($warehouseIds)) {
            return [];
        }

        $warehouseIdsSql = implode(',', array_map('intval', $warehouseIds));

        $pairRows = DB::table('food_inventory_cards')
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereRaw('DATE(`date`) <= ?', [$tanggalAkhirBulanSebelumnya])
            ->select('warehouse_id', 'inventory_item_id')
            ->distinct()
            ->get();

        if ($pairRows->isEmpty()) {
            return [];
        }

        $inventoryItemIds = $pairRows->pluck('inventory_item_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $inventoryIdsSql = implode(',', array_map('intval', $inventoryItemIds));

        $subCard = "
            SELECT
                c.warehouse_id,
                c.inventory_item_id,
                MAX(
                    CONCAT(
                        DATE(c.date),
                        ' ',
                        LPAD(c.id, 20, '0')
                    )
                ) AS mx
            FROM food_inventory_cards c
            WHERE c.warehouse_id IN ({$warehouseIdsSql})
              AND c.inventory_item_id IN ({$inventoryIdsSql})
              AND DATE(c.date) <= '{$tanggalAkhirBulanSebelumnya}'
            GROUP BY c.warehouse_id, c.inventory_item_id
        ";

        $cardRows = DB::table('food_inventory_cards as c')
            ->join(DB::raw("({$subCard}) t"), function ($join) {
                $join->on('t.warehouse_id', '=', 'c.warehouse_id')
                    ->on('t.inventory_item_id', '=', 'c.inventory_item_id');
            })
            ->whereRaw("t.mx = CONCAT(DATE(c.date), ' ', LPAD(c.id, 20, '0'))")
            ->select(
                'c.warehouse_id',
                'c.inventory_item_id',
                'c.saldo_qty_small',
                'c.cost_per_small'
            )
            ->get();

        $saldoByKey = [];
        $cardCostByKey = [];
        foreach ($cardRows as $r) {
            $k = (int) $r->warehouse_id . '|' . (int) $r->inventory_item_id;
            $saldoByKey[$k] = (float) ($r->saldo_qty_small ?? 0);
            $cardCostByKey[$k] = (float) ($r->cost_per_small ?? 0);
        }

        $subHist = "
            SELECT
                h.warehouse_id,
                h.inventory_item_id,
                MAX(
                    CONCAT(
                        DATE(COALESCE(h.date, '1970-01-01')),
                        ' ',
                        LPAD(h.id, 20, '0')
                    )
                ) AS mx
            FROM food_inventory_cost_histories h
            WHERE h.warehouse_id IN ({$warehouseIdsSql})
              AND h.inventory_item_id IN ({$inventoryIdsSql})
              AND DATE(COALESCE(h.date, '1970-01-01')) <= '{$tanggalAkhirBulanSebelumnya}'
            GROUP BY h.warehouse_id, h.inventory_item_id
        ";

        $histRows = DB::table('food_inventory_cost_histories as h')
            ->join(DB::raw("({$subHist}) t"), function ($join) {
                $join->on('t.warehouse_id', '=', 'h.warehouse_id')
                    ->on('t.inventory_item_id', '=', 'h.inventory_item_id');
            })
            ->whereRaw(
                "t.mx = CONCAT(DATE(COALESCE(h.date, '1970-01-01')), ' ', LPAD(h.id, 20, '0'))"
            )
            ->select(
                'h.warehouse_id',
                'h.inventory_item_id',
                'h.mac',
                'h.new_cost'
            )
            ->get();

        $macByKey = [];
        foreach ($histRows as $r) {
            $k = (int) $r->warehouse_id . '|' . (int) $r->inventory_item_id;
            $mac = (float) ($r->mac ?? 0);
            if ($mac <= 0) {
                $mac = (float) ($r->new_cost ?? 0);
            }
            $macByKey[$k] = $mac;
        }

        $totalsByKey = [];
        foreach ($saldoByKey as $k => $qty) {
            $mac = (float) ($macByKey[$k] ?? 0);
            if ($mac <= 0) {
                $mac = (float) ($cardCostByKey[$k] ?? 0);
            }
            $totalsByKey[$k] = (float) $qty * $mac;
        }

        return $totalsByKey;
    }
}
