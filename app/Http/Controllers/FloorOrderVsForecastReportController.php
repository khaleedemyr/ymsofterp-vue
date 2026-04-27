<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FloorOrderVsForecastReportController extends Controller
{
    private const KITCHEN_BAR_RATIO = 0.40;

    private const SERVICE_RATIO = 0.05;

    public function index(Request $request)
    {
        return Inertia::render('Reports/FloorOrderVsForecast', $this->buildReportPayload($request));
    }

    /**
     * GET /api/approval-app/floor-order-vs-forecast — payload sama seperti halaman web (mobile app).
     */
    public function apiIndex(Request $request)
    {
        return response()->json(array_merge([
            'success' => true,
        ], $this->buildReportPayload($request)));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportPayload(Request $request): array
    {
        $user = auth()->user();
        /** Hanya user dengan id_outlet = 1 (HO) yang boleh memilih outlet; lainnya dikunci ke outlet user. */
        $isAdminOutlet = (int) ($user->id_outlet ?? 0) === 1;

        $month = $request->input('month', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $monthDate = $monthStart->toDateString();

        $selectedOutletId = (int) ($request->input('outlet_id') ?: 0);
        if (!$isAdminOutlet) {
            $selectedOutletId = (int) ($user->id_outlet ?? 0);
        } elseif ($selectedOutletId <= 0) {
            $selectedOutletId = 1;
        }

        $outletsQuery = DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        if (!$isAdminOutlet) {
            $outletsQuery->where('id_outlet', $selectedOutletId);
        }

        $outlets = $outletsQuery->get();

        $forecastByDate = [];
        $monthlyTarget = null;
        $revenueTargetHeaderExists = false;

        if ($selectedOutletId > 0) {
            $header = DB::table('outlet_revenue_target_headers')
                ->where('outlet_id', $selectedOutletId)
                ->where('target_month', $monthDate)
                ->first();

            if ($header) {
                $revenueTargetHeaderExists = true;
                $monthlyTarget = (float) $header->monthly_target;
                $details = DB::table('outlet_revenue_target_details')
                    ->where('header_id', $header->id)
                    ->orderBy('forecast_date')
                    ->get(['forecast_date', 'forecast_revenue']);

                foreach ($details as $d) {
                    $key = Carbon::parse($d->forecast_date)->toDateString();
                    $forecastByDate[$key] = (float) $d->forecast_revenue;
                }
            }
        }

        $roKitchenBar = [];
        $roService = [];
        $roOther = [];
        $revenueByDate = [];
        $stockOnHandKitchenBarByDate = [];
        $stockOnHandServiceByDate = [];
        $stockOnHandTotalByDate = [];

        if ($selectedOutletId > 0) {
            $rangeStart = $monthStart->toDateString();
            $rangeEnd = $monthEnd->toDateString();

            $warehouseBucketById = DB::table('warehouse_outlets')
                ->select('id', 'name')
                ->get()
                ->mapWithKeys(function ($w) {
                    $name = strtolower(trim((string) ($w->name ?? '')));
                    $bucket = 'other';
                    if (in_array($name, ['kitchen', 'bar'], true)) {
                        $bucket = 'kitchen_bar';
                    } elseif ($name === 'service') {
                        $bucket = 'service';
                    }

                    return [(int) $w->id => $bucket];
                })
                ->all();

            $outletQr = DB::table('tbl_data_outlet')
                ->where('id_outlet', $selectedOutletId)
                ->value('qr_code');

            if ($outletQr) {
                $revenueRows = DB::table('orders')
                    ->where('kode_outlet', $outletQr)
                    ->whereBetween(DB::raw('DATE(created_at)'), [$rangeStart, $rangeEnd])
                    ->selectRaw('DATE(created_at) as d, SUM(grand_total) as revenue')
                    ->groupBy(DB::raw('DATE(created_at)'))
                    ->get();

                foreach ($revenueRows as $rv) {
                    $revenueByDate[Carbon::parse($rv->d)->toDateString()] = round((float) $rv->revenue, 2);
                }
            }

            // Stock On Hand harian (nilai harta stok): saldo_value end-of-day per item+warehouse,
            // lalu dikelompokkan Kitchen+Bar, Service, dan Total.
            $baselineLatestKeyByTuple = DB::table('outlet_food_inventory_cards as c')
                ->where('c.id_outlet', $selectedOutletId)
                ->whereDate('c.date', '<', $rangeStart)
                ->groupBy('c.warehouse_outlet_id', 'c.inventory_item_id')
                ->selectRaw("c.warehouse_outlet_id, c.inventory_item_id, MAX(CONCAT(DATE(c.date), ' ', LPAD(c.id, 20, '0'))) as mx");

            $baselineSaldoByTuple = [];
            $baselineRows = DB::table('outlet_food_inventory_cards as c')
                ->joinSub($baselineLatestKeyByTuple, 'b', function ($join) {
                    $join->on('b.warehouse_outlet_id', '=', 'c.warehouse_outlet_id')
                        ->on('b.inventory_item_id', '=', 'c.inventory_item_id')
                        ->whereRaw("CONCAT(DATE(c.date), ' ', LPAD(c.id, 20, '0')) = b.mx");
                })
                ->where('c.id_outlet', $selectedOutletId)
                ->select('c.warehouse_outlet_id', 'c.inventory_item_id', 'c.saldo_value')
                ->get();

            foreach ($baselineRows as $row) {
                $tupleKey = (int) $row->warehouse_outlet_id.'|'.(int) $row->inventory_item_id;
                $baselineSaldoByTuple[$tupleKey] = (float) ($row->saldo_value ?? 0);
            }

            $monthCardRows = DB::table('outlet_food_inventory_cards as c')
                ->where('c.id_outlet', $selectedOutletId)
                ->whereBetween(DB::raw('DATE(c.date)'), [$rangeStart, $rangeEnd])
                ->selectRaw('c.warehouse_outlet_id, c.inventory_item_id, DATE(c.date) as d, c.id, c.saldo_value')
                ->orderBy('c.warehouse_outlet_id')
                ->orderBy('c.inventory_item_id')
                ->orderBy(DB::raw('DATE(c.date)'))
                ->orderBy('c.id')
                ->get();

            $entriesByTuple = [];
            foreach ($monthCardRows as $row) {
                $tupleKey = (int) $row->warehouse_outlet_id.'|'.(int) $row->inventory_item_id;
                if (!isset($entriesByTuple[$tupleKey])) {
                    $entriesByTuple[$tupleKey] = [];
                }
                $entriesByTuple[$tupleKey][] = [
                    'date' => Carbon::parse($row->d)->toDateString(),
                    'saldo_value' => (float) ($row->saldo_value ?? 0),
                ];
            }

            $tupleKeys = array_values(array_unique(array_merge(array_keys($baselineSaldoByTuple), array_keys($entriesByTuple))));
            $tupleStates = [];
            foreach ($tupleKeys as $tupleKey) {
                $warehouseId = (int) explode('|', $tupleKey, 2)[0];
                $tupleStates[$tupleKey] = [
                    'saldo' => (float) ($baselineSaldoByTuple[$tupleKey] ?? 0),
                    'next_index' => 0,
                    'entries' => $entriesByTuple[$tupleKey] ?? [],
                    'bucket' => $warehouseBucketById[$warehouseId] ?? 'other',
                ];
            }

            $sohCursor = $monthStart->copy();
            while ($sohCursor->lte($monthEnd)) {
                $dayKey = $sohCursor->toDateString();
                $dayKitchenBar = 0.0;
                $dayService = 0.0;
                $dayTotal = 0.0;

                foreach ($tupleStates as &$state) {
                    $entries = $state['entries'];
                    $entriesCount = count($entries);
                    while ($state['next_index'] < $entriesCount && $entries[$state['next_index']]['date'] <= $dayKey) {
                        $state['saldo'] = (float) $entries[$state['next_index']]['saldo_value'];
                        $state['next_index']++;
                    }

                    $saldo = (float) $state['saldo'];
                    $dayTotal += $saldo;
                    if ($state['bucket'] === 'kitchen_bar') {
                        $dayKitchenBar += $saldo;
                    } elseif ($state['bucket'] === 'service') {
                        $dayService += $saldo;
                    }
                }
                unset($state);

                $stockOnHandKitchenBarByDate[$dayKey] = round($dayKitchenBar, 2);
                $stockOnHandServiceByDate[$dayKey] = round($dayService, 2);
                $stockOnHandTotalByDate[$dayKey] = round($dayTotal, 2);
                $sohCursor->addDay();
            }

            $bucketExpr = 'CASE
                WHEN LOWER(TRIM(wo.name)) IN (\'kitchen\', \'bar\') THEN \'kitchen_bar\'
                WHEN LOWER(TRIM(wo.name)) = \'service\' THEN \'service\'
                ELSE \'other\'
            END';

            /*
             * Nilai RO per baris: jika sudah ada GR completed (Outlet Food Good Receive), pakai
             * SUM(received_qty) × harga baris RO — sama seperti detail GR di laporan Invoice Outlet.
             * Jika belum ada penerimaan tercatat di GR, fallback ke subtotal baris FO.
             */
            $receivedQtyByRoItem = DB::table('outlet_food_good_receive_items as gri')
                ->join('outlet_food_good_receives as gr', function ($join) {
                    $join->on('gri.outlet_food_good_receive_id', '=', 'gr.id')
                        ->whereNull('gr.deleted_at')
                        ->where('gr.status', 'completed');
                })
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->join('food_floor_orders as ffo_r', 'do.floor_order_id', '=', 'ffo_r.id')
                ->where('ffo_r.id_outlet', $selectedOutletId)
                ->whereNotNull('ffo_r.arrival_date')
                ->whereBetween(DB::raw('DATE(ffo_r.arrival_date)'), [$rangeStart, $rangeEnd])
                ->whereNotIn('ffo_r.status', ['draft', 'rejected'])
                ->groupBy('do.floor_order_id', 'gri.item_id')
                ->select(
                    'do.floor_order_id as floor_order_id',
                    'gri.item_id as item_id',
                    DB::raw('SUM(gri.received_qty) as qty_received')
                );

            $lineValueSql = '(CASE
                WHEN recv.qty_received IS NOT NULL AND recv.qty_received > 0
                THEN recv.qty_received * COALESCE(ffoi.price, 0)
                ELSE COALESCE(ffoi.subtotal, 0)
            END)';

            $aggregates = DB::table('food_floor_orders as ffo')
                ->join('warehouse_outlets as wo', 'wo.id', '=', 'ffo.warehouse_outlet_id')
                ->join('food_floor_order_items as ffoi', 'ffoi.floor_order_id', '=', 'ffo.id')
                ->leftJoinSub($receivedQtyByRoItem, 'recv', function ($join) {
                    $join->on('recv.floor_order_id', '=', 'ffo.id')
                        ->on('recv.item_id', '=', 'ffoi.item_id');
                })
                ->where('ffo.id_outlet', $selectedOutletId)
                ->whereNotNull('ffo.arrival_date')
                ->whereBetween(DB::raw('DATE(ffo.arrival_date)'), [$rangeStart, $rangeEnd])
                ->whereNotIn('ffo.status', ['draft', 'rejected'])
                ->selectRaw('DATE(ffo.arrival_date) as d, '.$bucketExpr.' as bucket, SUM('.$lineValueSql.') as total')
                ->groupBy(DB::raw('DATE(ffo.arrival_date)'), DB::raw($bucketExpr))
                ->get();

            foreach ($aggregates as $row) {
                $dateKey = Carbon::parse($row->d)->toDateString();
                $total = round((float) $row->total, 2);
                if ($row->bucket === 'kitchen_bar') {
                    $roKitchenBar[$dateKey] = ($roKitchenBar[$dateKey] ?? 0) + $total;
                } elseif ($row->bucket === 'service') {
                    $roService[$dateKey] = ($roService[$dateKey] ?? 0) + $total;
                } else {
                    $roOther[$dateKey] = ($roOther[$dateKey] ?? 0) + $total;
                }
            }
        }

        $rows = [];
        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $ds = $cursor->toDateString();
            $forecast = (float) ($forecastByDate[$ds] ?? 0);
            $capKb = round($forecast * self::KITCHEN_BAR_RATIO, 2);
            $capSvc = round($forecast * self::SERVICE_RATIO, 2);
            $kb = round((float) ($roKitchenBar[$ds] ?? 0), 2);
            $svc = round((float) ($roService[$ds] ?? 0), 2);
            $other = round((float) ($roOther[$ds] ?? 0), 2);

            $diffKb = round($kb - $capKb, 2);
            $diffSvc = round($svc - $capSvc, 2);

            $pctKb = $capKb > 0 ? round(($kb / $capKb) * 100, 1) : null;
            $pctSvc = $capSvc > 0 ? round(($svc / $capSvc) * 100, 1) : null;

            $revenue = round((float) ($revenueByDate[$ds] ?? 0), 2);
            $stockOnHandKitchenBar = round((float) ($stockOnHandKitchenBarByDate[$ds] ?? 0), 2);
            $stockOnHandService = round((float) ($stockOnHandServiceByDate[$ds] ?? 0), 2);
            $stockOnHandTotal = round((float) ($stockOnHandTotalByDate[$ds] ?? 0), 2);

            $rows[] = [
                'date' => $ds,
                'day_name' => $cursor->locale('id')->isoFormat('dddd'),
                'forecast_revenue' => $forecast,
                'revenue' => $revenue,
                'stock_on_hand_kitchen_bar' => $stockOnHandKitchenBar,
                'stock_on_hand_service' => $stockOnHandService,
                'stock_on_hand_total' => $stockOnHandTotal,
                'cap_kitchen_bar' => $capKb,
                'cap_service' => $capSvc,
                'ro_kitchen_bar' => $kb,
                'ro_service' => $svc,
                'ro_other' => $other,
                'diff_kitchen_bar' => $diffKb,
                'diff_service' => $diffSvc,
                'pct_kitchen_bar_vs_cap' => $pctKb,
                'pct_service_vs_cap' => $pctSvc,
            ];
            $cursor->addDay();
        }

        $totals = [
            'forecast_revenue' => 0,
            'revenue' => 0,
            'stock_on_hand_kitchen_bar_end' => 0,
            'stock_on_hand_service_end' => 0,
            'stock_on_hand_total_end' => 0,
            'cap_kitchen_bar' => 0,
            'cap_service' => 0,
            'ro_kitchen_bar' => 0,
            'ro_service' => 0,
            'ro_other' => 0,
            'diff_kitchen_bar' => 0,
            'diff_service' => 0,
        ];
        foreach ($rows as $r) {
            $totals['forecast_revenue'] += $r['forecast_revenue'];
            $totals['revenue'] += $r['revenue'];
            $totals['cap_kitchen_bar'] += $r['cap_kitchen_bar'];
            $totals['cap_service'] += $r['cap_service'];
            $totals['ro_kitchen_bar'] += $r['ro_kitchen_bar'];
            $totals['ro_service'] += $r['ro_service'];
            $totals['ro_other'] += $r['ro_other'];
            $totals['diff_kitchen_bar'] += $r['diff_kitchen_bar'];
            $totals['diff_service'] += $r['diff_service'];
        }
        if (!empty($rows)) {
            $last = $rows[count($rows) - 1];
            $totals['stock_on_hand_kitchen_bar_end'] = round((float) ($last['stock_on_hand_kitchen_bar'] ?? 0), 2);
            $totals['stock_on_hand_service_end'] = round((float) ($last['stock_on_hand_service'] ?? 0), 2);
            $totals['stock_on_hand_total_end'] = round((float) ($last['stock_on_hand_total'] ?? 0), 2);
        }
        foreach ($totals as $k => $v) {
            $totals[$k] = round($v, 2);
        }

        return [
            'outlets' => $outlets,
            'selectedOutletId' => $selectedOutletId,
            'selectedMonth' => $month,
            'month_label' => $monthStart->locale('id')->translatedFormat('F Y'),
            'monthlyTarget' => $monthlyTarget,
            'kitchen_bar_ratio_pct' => (int) round(self::KITCHEN_BAR_RATIO * 100),
            'service_ratio_pct' => (int) round(self::SERVICE_RATIO * 100),
            'rows' => $rows,
            'totals' => $totals,
            'has_forecast_header' => $revenueTargetHeaderExists || count($forecastByDate) > 0,
            'canSelectOutlet' => $isAdminOutlet,
        ];
    }
}
