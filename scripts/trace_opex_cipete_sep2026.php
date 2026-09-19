<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\OpexOutletDashboardService;
use Illuminate\Support\Facades\DB;

$outlet = DB::table('tbl_data_outlet')->where('nama_outlet', 'Justus Steak House Cipete')->first();
if (! $outlet) {
    echo "Outlet not found\n";
    exit(1);
}

$oid = (int) $outlet->id_outlet;
echo "Outlet: {$outlet->nama_outlet} id={$oid}\n\n";

$svc = app(OpexOutletDashboardService::class);

$fmt = fn ($n) => number_format((float) $n, 0, ',', '.');

$periods = [
    'MTD filter 01-18' => ['2026-09-01', '2026-09-18'],
    'Full month 01-30' => ['2026-09-01', '2026-09-30'],
];

foreach ($periods as $label => [$from, $to]) {
    echo "===== {$label} {$from} s/d {$to} =====\n";
    $gsr = $svc->sumGsrRo($oid, $from, $to);
    $rws = $svc->sumRws($oid, $from, $to);
    $rf = $svc->sumRetailFood($oid, $from, $to);
    $rnf = $svc->sumRetailNonFood($oid, $from, $to);
    $forecast = $svc->buildRoForecastSummary($oid, $from, $to);

    $cardSum = $gsr['total'] + $rws['total'] + $rf['total'] + $rnf['total'];
    $purchasedSum = $forecast['kitchen']['purchased'] + $forecast['bar']['purchased'] + $forecast['service']['purchased'];

    echo "Cards:\n";
    echo "  GSR/RO total={$fmt($gsr['total'])} (GR={$fmt($gsr['gr_total'])} GSR={$fmt($gsr['gsr_total'])})\n";
    echo "  RWS={$fmt($rws['total'])} ({$rws['count']} trx)\n";
    echo "  Retail Food={$fmt($rf['total'])} ({$rf['count']} trx)\n";
    echo "  Retail Non Food={$fmt($rnf['total'])} ({$rnf['count']} trx)\n";
    echo "  Card sum GSR+RWS+RF+RNF={$fmt($cardSum)}\n";
    echo "Budget vs Purchase (forced full month):\n";
    echo "  period={$forecast['period_from']} s/d {$forecast['period_to']}\n";
    echo "  Forecast={$fmt($forecast['forecast'])}\n";
    echo "  Pool {$forecast['budget_pool_ratio_pct']}%={$fmt($forecast['budget_pool'])}\n";
    echo "  Kitchen purchased={$fmt($forecast['kitchen']['purchased'])} budget={$fmt($forecast['kitchen']['budget'])}\n";
    echo "  Bar purchased={$fmt($forecast['bar']['purchased'])} budget={$fmt($forecast['bar']['budget'])}\n";
    echo "  Service purchased={$fmt($forecast['service']['purchased'])} budget={$fmt($forecast['service']['budget'])}\n";
    echo "  Purchased sum={$fmt($purchasedSum)}\n";
    echo "  Diff purchased - card sum={$fmt($purchasedSum - $cardSum)}\n\n";
}

// Detailed RO purchased breakdown for full month
$from = '2026-09-01';
$to = '2026-09-30';
echo "===== RO purchased breakdown (full month, mirror sumRoPurchasedByBucket) =====\n";

$bucketExpr = "CASE
    WHEN LOWER(TRIM(wo.name)) IN ('kitchen', 'bar') THEN 'kitchen_bar'
    WHEN LOWER(TRIM(wo.name)) = 'service' THEN 'service'
    ELSE 'other'
END";

$receivedQtyByRoItem = DB::table('outlet_food_good_receive_items as gri')
    ->join('outlet_food_good_receives as gr', function ($join) {
        $join->on('gri.outlet_food_good_receive_id', '=', 'gr.id')
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'completed');
    })
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->join('food_floor_orders as ffo_r', 'do.floor_order_id', '=', 'ffo_r.id')
    ->where('ffo_r.id_outlet', $oid)
    ->whereNotNull('ffo_r.arrival_date')
    ->whereBetween(DB::raw('DATE(ffo_r.arrival_date)'), [$from, $to])
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

$roRows = DB::table('food_floor_orders as ffo')
    ->join('warehouse_outlets as wo', 'wo.id', '=', 'ffo.warehouse_outlet_id')
    ->join('food_floor_order_items as ffoi', 'ffoi.floor_order_id', '=', 'ffo.id')
    ->leftJoinSub($receivedQtyByRoItem, 'recv', function ($join) {
        $join->on('recv.floor_order_id', '=', 'ffo.id')
            ->on('recv.item_id', '=', 'ffoi.item_id');
    })
    ->where('ffo.id_outlet', $oid)
    ->whereNotNull('ffo.arrival_date')
    ->whereBetween(DB::raw('DATE(ffo.arrival_date)'), [$from, $to])
    ->whereNotIn('ffo.status', ['draft', 'rejected'])
    ->selectRaw("
        {$bucketExpr} as bucket,
        wo.name as warehouse_name,
        SUM(CASE WHEN recv.qty_received IS NOT NULL AND recv.qty_received > 0 THEN recv.qty_received * COALESCE(ffoi.price, 0) ELSE 0 END) as from_gr,
        SUM(CASE WHEN recv.qty_received IS NULL OR recv.qty_received <= 0 THEN COALESCE(ffoi.subtotal, 0) ELSE 0 END) as from_ro_fallback,
        SUM({$lineValueSql}) as total,
        COUNT(DISTINCT ffo.id) as ro_count
    ")
    ->groupBy(DB::raw($bucketExpr), 'wo.name')
    ->orderBy('wo.name')
    ->get();

$roFromGr = 0.0;
$roFallback = 0.0;
$roTotal = 0.0;
foreach ($roRows as $row) {
    echo sprintf(
        "  RO %-12s warehouse=%-10s total=%s from_GR=%s fallback_RO_subtotal=%s ro_count=%d\n",
        $row->bucket,
        $row->warehouse_name,
        $fmt($row->total),
        $fmt($row->from_gr),
        $fmt($row->from_ro_fallback),
        $row->ro_count
    );
    $roFromGr += (float) $row->from_gr;
    $roFallback += (float) $row->from_ro_fallback;
    $roTotal += (float) $row->total;
}
echo "  RO TOTAL total={$fmt($roTotal)} from_GR={$fmt($roFromGr)} fallback={$fmt($roFallback)}\n\n";

$rfByWh = DB::table('retail_food as rf')
    ->leftJoin('warehouse_outlets as wo', 'wo.id', '=', 'rf.warehouse_outlet_id')
    ->where('rf.outlet_id', $oid)
    ->where('rf.status', 'approved')
    ->whereNull('rf.deleted_at')
    ->whereBetween(DB::raw('DATE(rf.transaction_date)'), [$from, $to])
    ->selectRaw("
        COALESCE(wo.name, '(null)') as warehouse_name,
        CASE
            WHEN LOWER(TRIM(COALESCE(wo.name,''))) IN ('kitchen','bar') THEN 'kitchen_bar'
            WHEN LOWER(TRIM(COALESCE(wo.name,''))) = 'service' THEN 'service'
            ELSE 'other'
        END as bucket,
        SUM(rf.total_amount) as total,
        COUNT(*) as cnt
    ")
    ->groupBy('wo.name')
    ->orderBy('wo.name')
    ->get();

echo "===== Retail Food by warehouse_outlet (full month) =====\n";
$rfInScope = 0.0;
$rfOther = 0.0;
foreach ($rfByWh as $row) {
    echo sprintf(
        "  RF %-12s warehouse=%-12s total=%s cnt=%d\n",
        $row->bucket,
        $row->warehouse_name,
        $fmt($row->total),
        $row->cnt
    );
    if (in_array($row->bucket, ['kitchen_bar', 'service'], true)) {
        $rfInScope += (float) $row->total;
    } else {
        $rfOther += (float) $row->total;
    }
}
echo "  RF in F&B/Service buckets={$fmt($rfInScope)} other={$fmt($rfOther)}\n\n";

// GSR vs RO arrival_date overlap note
$gsr = $svc->sumGsrRo($oid, $from, $to);
echo "===== Interpretation helpers =====\n";
echo "  Card GSR (receive_date cost)={$fmt($gsr['gsr_total'])}\n";
echo "  Card GR (receive_date RO price)={$fmt($gsr['gr_total'])}\n";
echo "  Budget RO component (arrival_date + GR qty OR RO subtotal)={$fmt($roTotal)}\n";
echo "  Budget RF component (kitchen/bar/service only)={$fmt($rfInScope)}\n";
echo "  Budget purchased expected={$fmt($roTotal + $rfInScope)}\n";
echo "  Cards include RWS+RNF which Budget excludes\n";
echo "  Budget RO fallback uses RO subtotal when no completed outlet GR — GSR receive does NOT reduce this fallback\n";
