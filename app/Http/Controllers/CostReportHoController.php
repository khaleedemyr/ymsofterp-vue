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
     * Trace baris begin inventory terbesar — JSON (saldo kartu × MAC).
     * Query: bulan (Y-m), warehouse_id (opsional), limit (default 50, max 200).
     */
    public function traceBegin(Request $request)
    {
        $validated = $request->validate([
            'bulan' => ['required', 'date_format:Y-m'],
            'warehouse_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $payload = $this->buildHoBeginTracePayload($validated);

        return response()->json(['success' => true] + $payload);
    }

    /**
     * Halaman Vue: tabel trace begin (sumber data sama dengan traceBegin JSON).
     */
    public function traceBeginView(Request $request)
    {
        $validated = $request->validate([
            'bulan' => ['required', 'date_format:Y-m'],
            'warehouse_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $payload = $this->buildHoBeginTracePayload($validated);
        $warehouseOptions = DB::table('warehouses')
            ->where('status', 'active')
            ->orderBy('name')
            ->select('id', 'name', 'code')
            ->get();

        return Inertia::render('CostReportHo/TraceBegin', [
            'trace' => $payload,
            'warehouseOptions' => $warehouseOptions,
            'filters' => [
                'bulan' => $validated['bulan'],
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'limit' => (int) ($validated['limit'] ?? 100),
            ],
        ]);
    }

    /**
     * @param  array{bulan: string, warehouse_id?: int|null, limit?: int|null}  $validated
     * @return array{bulan: string, cutoff_date: string, formula: string, lines: array<int, array<string, mixed>>}
     */
    private function buildHoBeginTracePayload(array $validated): array
    {
        $bulan = $validated['bulan'];
        $limit = (int) ($validated['limit'] ?? 50);
        $limit = min(200, max(1, $limit));

        $bulanCarbon = Carbon::parse($bulan . '-01');
        $tanggalAkhirBulanSebelumnya = $bulanCarbon->copy()->subMonth()->format('Y-m-t');
        $formula = 'begin_line = saldo_qty_small (kartu terakhir s/d cutoff) × mac_efektif (histori terakhir s/d cutoff; fallback cost_per_small kartu)';

        $warehousesQuery = DB::table('warehouses')->where('status', 'active');
        if (! empty($validated['warehouse_id'])) {
            $warehousesQuery->where('id', (int) $validated['warehouse_id']);
        }
        $warehouses = $warehousesQuery->orderBy('name')->select('id', 'name', 'code')->get();
        $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($warehouseIds)) {
            return [
                'bulan' => $bulan,
                'cutoff_date' => $tanggalAkhirBulanSebelumnya,
                'formula' => $formula,
                'lines' => [],
            ];
        }

        $components = $this->fetchHoBeginInventoryComponents($warehouseIds, $tanggalAkhirBulanSebelumnya);
        if ($components === null || empty($components['line_by_key'])) {
            return [
                'bulan' => $bulan,
                'cutoff_date' => $tanggalAkhirBulanSebelumnya,
                'formula' => $formula,
                'lines' => [],
            ];
        }

        $whName = $warehouses->keyBy('id')->map(fn ($w) => $w->name)->all();

        $inventoryItemIds = array_unique(array_map(
            fn ($k) => (int) explode('|', $k, 2)[1],
            array_keys($components['line_by_key'])
        ));

        $itemMeta = [];
        if (! empty($inventoryItemIds)) {
            $metaRows = DB::table('food_inventory_items as fi')
                ->join('items as i', 'fi.item_id', '=', 'i.id')
                ->whereIn('fi.id', $inventoryItemIds)
                ->select('fi.id as inventory_item_id', 'fi.item_id', 'i.name as item_name', 'i.sku as item_sku')
                ->get();
            foreach ($metaRows as $m) {
                $itemMeta[(int) $m->inventory_item_id] = $m;
            }
        }

        $lines = [];
        foreach ($components['line_by_key'] as $k => $lineVal) {
            $parts = explode('|', $k, 2);
            $wid = (int) ($parts[0] ?? 0);
            $iid = (int) ($parts[1] ?? 0);
            $meta = $itemMeta[$iid] ?? null;
            $lines[] = [
                'warehouse_id' => $wid,
                'warehouse_name' => $whName[$wid] ?? null,
                'inventory_item_id' => $iid,
                'item_id' => $meta ? (int) $meta->item_id : null,
                'item_name' => $meta?->item_name,
                'item_sku' => $meta?->item_sku,
                'saldo_qty_small' => round((float) ($components['saldo_by_key'][$k] ?? 0), 6),
                'mac_effective' => round((float) ($components['mac_effective_by_key'][$k] ?? 0), 6),
                'mac_source' => (string) ($components['mac_source_by_key'][$k] ?? 'none'),
                'hist_mac_raw' => isset($components['hist_mac_by_key'][$k])
                    ? round((float) $components['hist_mac_by_key'][$k], 6)
                    : null,
                'hist_new_cost_raw' => isset($components['hist_new_cost_by_key'][$k])
                    ? round((float) $components['hist_new_cost_by_key'][$k], 6)
                    : null,
                'hist_id' => $components['hist_id_by_key'][$k] ?? null,
                'hist_date' => $components['hist_date_by_key'][$k] ?? null,
                'card_cost_per_small' => isset($components['card_cost_by_key'][$k])
                    ? round((float) $components['card_cost_by_key'][$k], 6)
                    : null,
                'card_id' => $components['card_id_by_key'][$k] ?? null,
                'card_date' => $components['card_date_by_key'][$k] ?? null,
                'begin_line_value' => round((float) $lineVal, 2),
            ];
        }

        usort($lines, fn ($a, $b) => abs($b['begin_line_value']) <=> abs($a['begin_line_value']));
        $lines = array_slice($lines, 0, $limit);

        return [
            'bulan' => $bulan,
            'cutoff_date' => $tanggalAkhirBulanSebelumnya,
            'formula' => $formula,
            'lines' => $lines,
        ];
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
     * Ambil komponen begin (saldo kartu, MAC histori / fallback) per key gudang|inventory_item_id.
     *
     * @return array<string, array<string, mixed>>|null null jika warehouseIds kosong
     */
    private function fetchHoBeginInventoryComponents(array $warehouseIds, string $tanggalAkhirBulanSebelumnya): ?array
    {
        if (empty($warehouseIds)) {
            return null;
        }

        $warehouseIdsSql = implode(',', array_map('intval', $warehouseIds));

        $pairRows = DB::table('food_inventory_cards')
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereRaw('DATE(`date`) <= ?', [$tanggalAkhirBulanSebelumnya])
            ->select('warehouse_id', 'inventory_item_id')
            ->distinct()
            ->get();

        $empty = [
            'saldo_by_key' => [],
            'card_cost_by_key' => [],
            'card_id_by_key' => [],
            'card_date_by_key' => [],
            'hist_mac_by_key' => [],
            'hist_new_cost_by_key' => [],
            'hist_id_by_key' => [],
            'hist_date_by_key' => [],
            'mac_effective_by_key' => [],
            'mac_source_by_key' => [],
            'line_by_key' => [],
        ];

        if ($pairRows->isEmpty()) {
            return $empty;
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
                'c.id as card_id',
                DB::raw('DATE(c.date) as card_date'),
                'c.warehouse_id',
                'c.inventory_item_id',
                'c.saldo_qty_small',
                'c.cost_per_small'
            )
            ->get();

        $saldoByKey = [];
        $cardCostByKey = [];
        $cardIdByKey = [];
        $cardDateByKey = [];
        foreach ($cardRows as $r) {
            $k = (int) $r->warehouse_id . '|' . (int) $r->inventory_item_id;
            $saldoByKey[$k] = (float) ($r->saldo_qty_small ?? 0);
            $cardCostByKey[$k] = (float) ($r->cost_per_small ?? 0);
            $cardIdByKey[$k] = (int) $r->card_id;
            $cardDateByKey[$k] = $r->card_date !== null ? (string) $r->card_date : null;
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
                'h.id as history_id',
                DB::raw('DATE(COALESCE(h.date, \'1970-01-01\')) as hist_date'),
                'h.warehouse_id',
                'h.inventory_item_id',
                'h.mac',
                'h.new_cost'
            )
            ->get();

        $histMacByKey = [];
        $histNewCostByKey = [];
        $histIdByKey = [];
        $histDateByKey = [];
        foreach ($histRows as $r) {
            $k = (int) $r->warehouse_id . '|' . (int) $r->inventory_item_id;
            $histMacByKey[$k] = (float) ($r->mac ?? 0);
            $histNewCostByKey[$k] = (float) ($r->new_cost ?? 0);
            $histIdByKey[$k] = (int) $r->history_id;
            $histDateByKey[$k] = $r->hist_date !== null ? (string) $r->hist_date : null;
        }

        $macEffectiveByKey = [];
        $macSourceByKey = [];
        $lineByKey = [];
        foreach ($saldoByKey as $k => $qty) {
            $hm = (float) ($histMacByKey[$k] ?? 0);
            $hn = (float) ($histNewCostByKey[$k] ?? 0);
            $cc = (float) ($cardCostByKey[$k] ?? 0);
            if ($hm > 0) {
                $eff = $hm;
                $src = 'history_mac';
            } elseif ($hn > 0) {
                $eff = $hn;
                $src = 'history_new_cost';
            } elseif ($cc > 0) {
                $eff = $cc;
                $src = 'card_cost_per_small';
            } else {
                $eff = 0.0;
                $src = 'none';
            }
            $macEffectiveByKey[$k] = $eff;
            $macSourceByKey[$k] = $src;
            $lineByKey[$k] = (float) $qty * $eff;
        }

        return [
            'saldo_by_key' => $saldoByKey,
            'card_cost_by_key' => $cardCostByKey,
            'card_id_by_key' => $cardIdByKey,
            'card_date_by_key' => $cardDateByKey,
            'hist_mac_by_key' => $histMacByKey,
            'hist_new_cost_by_key' => $histNewCostByKey,
            'hist_id_by_key' => $histIdByKey,
            'hist_date_by_key' => $histDateByKey,
            'mac_effective_by_key' => $macEffectiveByKey,
            'mac_source_by_key' => $macSourceByKey,
            'line_by_key' => $lineByKey,
        ];
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
        $c = $this->fetchHoBeginInventoryComponents($warehouseIds, $tanggalAkhirBulanSebelumnya);

        return $c === null ? [] : $c['line_by_key'];
    }
}
