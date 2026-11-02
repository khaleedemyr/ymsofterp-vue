<?php
/**
 * Trace selisih Rekap FJ (SH ALSUT RETAIL) vs Outlet Payment retail — 2026-10-30
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$date = $argv[1] ?? '2026-10-30';
$outletId = (int) ($argv[2] ?? 22);
$fmt = static fn (float $n): string => number_format($n, 0, ',', '.');

echo "=== Trace SH ALSUT RETAIL vs OP retail | {$date} | outlet {$outletId} ===\n\n";

$rows = DB::table('retail_warehouse_sales as rws')
    ->join('customers as c', 'rws.customer_id', '=', 'c.id')
    ->leftJoin('outlet_payments as op', function ($j) {
        $j->on('rws.id', '=', 'op.retail_sales_id')->where('op.status', '!=', 'cancelled');
    })
    ->where(function ($q) use ($outletId) {
        $q->where('c.id_outlet', (string) $outletId)
            ->orWhere('c.name', 'like', '%ALSUT RETAIL%');
    })
    ->where(function ($q) use ($date) {
        $q->whereDate('rws.sale_date', $date)
            ->orWhereDate('rws.created_at', $date)
            ->orWhereRaw('COALESCE(rws.sale_date, DATE(rws.created_at)) = ?', [$date]);
    })
    ->select(
        'rws.id',
        'rws.number',
        'rws.status',
        'rws.sale_date',
        'rws.created_at',
        'rws.total_amount',
        'c.name as customer',
        'c.id_outlet',
        'c.type',
        'op.id as payment_id',
        'op.status as pay_status',
        'op.total_amount as pay_amt'
    )
    ->orderBy('rws.number')
    ->get();

$sumHdr = 0.0;
$sumItems = 0.0;
$sumSerial = 0.0;
$hasSerial = Schema::hasTable('retail_warehouse_sale_serial_items');

foreach ($rows as $r) {
    $items = (float) DB::table('retail_warehouse_sale_items')
        ->where('retail_warehouse_sale_id', $r->id)
        ->sum('subtotal');
    $serial = 0.0;
    if ($hasSerial) {
        $serial = (float) DB::table('retail_warehouse_sale_serial_items')
            ->where('retail_warehouse_sale_id', $r->id)
            ->sum('subtotal');
    }
    $sumHdr += (float) $r->total_amount;
    $sumItems += $items;
    $sumSerial += $serial;

    $pay = $r->payment_id
        ? ('#' . $r->payment_id . ' ' . $r->pay_status . ' ' . $fmt((float) $r->pay_amt))
        : 'UNPAID';

    echo sprintf(
        "%s | status=%s | sale=%s | created=%s | hdr=%s | items=%s | serial=%s | cust=%s (out=%s type=%s) | %s\n",
        $r->number,
        $r->status,
        $r->sale_date,
        $r->created_at,
        $fmt((float) $r->total_amount),
        $fmt($items),
        $fmt($serial),
        $r->customer,
        $r->id_outlet,
        $r->type,
        $pay
    );
}

echo "\nALL matching rows count=" . $rows->count()
    . " hdr=" . $fmt($sumHdr)
    . " items=" . $fmt($sumItems)
    . " serial=" . $fmt($sumSerial)
    . " items+serial=" . $fmt($sumItems + $sumSerial) . "\n";

echo "\n=== Rekap FJ retail (COALESCE sale_date, item subtotal, NO status/paid filter) ===\n";
$rekap = DB::table('retail_warehouse_sales as rws')
    ->join('retail_warehouse_sale_items as rwsi', 'rws.id', '=', 'rwsi.retail_warehouse_sale_id')
    ->join('customers as c', 'rws.customer_id', '=', 'c.id')
    ->join('items as it', 'rwsi.item_id', '=', 'it.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('c.name', 'SH ALSUT RETAIL')
    ->whereNotNull('w.name')
    ->whereRaw('COALESCE(rws.sale_date, DATE(rws.created_at)) = ?', [$date])
    ->select(
        'rws.id',
        'rws.number',
        'rws.status',
        'rws.sale_date',
        'rws.created_at',
        'rws.total_amount',
        DB::raw('SUM(rwsi.subtotal) as subtotal')
    )
    ->groupBy('rws.id', 'rws.number', 'rws.status', 'rws.sale_date', 'rws.created_at', 'rws.total_amount')
    ->get();

foreach ($rekap as $r) {
    echo sprintf(
        "%s status=%s sale=%s created=%s hdr=%s items_sub=%s\n",
        $r->number,
        $r->status,
        $r->sale_date,
        $r->created_at,
        $fmt((float) $r->total_amount),
        $fmt((float) $r->subtotal)
    );
}
$rekapItems = (float) $rekap->sum('subtotal');
echo 'REKAP normal items SUM: ' . $fmt($rekapItems) . "\n";

$rekapSerialSum = 0.0;
if ($hasSerial) {
    $rekapS = DB::table('retail_warehouse_sales as rws')
        ->join('retail_warehouse_sale_serial_items as rwss', 'rws.id', '=', 'rwss.retail_warehouse_sale_id')
        ->join('customers as c', 'rws.customer_id', '=', 'c.id')
        ->join('items as it', 'rwss.item_id', '=', 'it.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->where('c.name', 'SH ALSUT RETAIL')
        ->whereNotNull('w.name')
        ->whereRaw('COALESCE(rws.sale_date, DATE(rws.created_at)) = ?', [$date])
        ->select('rws.number', 'rws.status', DB::raw('SUM(rwss.subtotal) as subtotal'))
        ->groupBy('rws.number', 'rws.status')
        ->get();
    foreach ($rekapS as $r) {
        echo sprintf("SERIAL %s status=%s subtotal=%s\n", $r->number, $r->status, $fmt((float) $r->subtotal));
    }
    $rekapSerialSum = (float) $rekapS->sum('subtotal');
    echo 'REKAP serial SUM: ' . $fmt($rekapSerialSum) . "\n";
}
echo 'REKAP TOTAL (items+serial): ' . $fmt($rekapItems + $rekapSerialSum) . "\n";

echo "\n=== OP filter (sale_date COALESCE, completed, unpaid, branch, id_outlet={$outletId}) ===\n";
$op = DB::table('retail_warehouse_sales as rws')
    ->join('customers as c', 'rws.customer_id', '=', 'c.id')
    ->leftJoin('outlet_payments as op', function ($j) {
        $j->on('rws.id', '=', 'op.retail_sales_id')->where('op.status', '!=', 'cancelled');
    })
    ->where('c.id_outlet', (string) $outletId)
    ->where('c.type', 'branch')
    ->where('rws.status', 'completed')
    ->whereNull('op.id')
    ->whereRaw('COALESCE(rws.sale_date, DATE(rws.created_at)) = ?', [$date])
    ->select('rws.id', 'rws.number', 'rws.total_amount', 'rws.sale_date', 'rws.created_at', 'rws.status')
    ->get();

foreach ($op as $r) {
    echo sprintf(
        "%s hdr=%s sale=%s created=%s\n",
        $r->number,
        $fmt((float) $r->total_amount),
        $r->sale_date,
        $r->created_at
    );
}
$opSum = (float) $op->sum('total_amount');
echo 'OP SUM: ' . $fmt($opSum) . "\n";

$rekapTotal = $rekapItems + $rekapSerialSum;
$gap = $rekapTotal - $opSum;
echo "\n=== GAP Rekap retail - OP retail = " . $fmt($gap) . " ===\n";

$rekapIds = $rekap->pluck('id')->map(fn ($id) => (int) $id)->all();
$opIds = $op->pluck('id')->map(fn ($id) => (int) $id)->all();
$onlyRekap = array_values(array_diff($rekapIds, $opIds));
$onlyOp = array_values(array_diff($opIds, $rekapIds));

echo 'Hanya di Rekap (tidak di OP unpaid): ' . implode(', ', $onlyRekap) . "\n";
echo 'Hanya di OP (tidak di Rekap items): ' . implode(', ', $onlyOp) . "\n";

foreach ($onlyRekap as $id) {
    $r = $rows->firstWhere('id', $id) ?? DB::table('retail_warehouse_sales as rws')
        ->join('customers as c', 'rws.customer_id', '=', 'c.id')
        ->leftJoin('outlet_payments as op', function ($j) {
            $j->on('rws.id', '=', 'op.retail_sales_id')->where('op.status', '!=', 'cancelled');
        })
        ->where('rws.id', $id)
        ->select('rws.*', 'c.name as customer', 'c.id_outlet', 'c.type', 'op.id as payment_id', 'op.status as pay_status')
        ->first();
    if (!$r) {
        continue;
    }
    $reasons = [];
    if (($r->status ?? '') !== 'completed') {
        $reasons[] = 'status=' . ($r->status ?? 'null');
    }
    if (!empty($r->payment_id)) {
        $reasons[] = 'sudah dibayar payment#' . $r->payment_id;
    }
    $createdDate = substr((string) ($r->created_at ?? ''), 0, 10);
    if ($createdDate !== $date) {
        $reasons[] = "created_at={$createdDate} (OP filter created_at)";
    }
    $saleDate = (string) ($r->sale_date ?? '');
    if ($saleDate === $date && $createdDate !== $date) {
        $reasons[] = 'masuk Rekap via sale_date, bukan created_at';
    }
    if (($r->type ?? '') !== 'branch') {
        $reasons[] = 'type=' . ($r->type ?? 'null');
    }
    if ((string) ($r->id_outlet ?? '') !== (string) $outletId) {
        $reasons[] = 'id_outlet=' . ($r->id_outlet ?? 'null');
    }
    echo "  -> #{$id} {$r->number} reasons: " . (empty($reasons) ? '(cek item warehouse/subtotal)' : implode('; ', $reasons)) . "\n";
}

// Also check hdr vs items mismatch on OP rows
echo "\n=== Header total_amount vs item subtotal (OP rows) ===\n";
foreach ($op as $r) {
    $items = (float) DB::table('retail_warehouse_sale_items')
        ->where('retail_warehouse_sale_id', $r->id)
        ->sum('subtotal');
    $serial = $hasSerial
        ? (float) DB::table('retail_warehouse_sale_serial_items')->where('retail_warehouse_sale_id', $r->id)->sum('subtotal')
        : 0.0;
    $diff = (float) $r->total_amount - ($items + $serial);
    if (abs($diff) > 0.5) {
        echo sprintf(
            "%s HDR=%s items+serial=%s DIFF=%s\n",
            $r->number,
            $fmt((float) $r->total_amount),
            $fmt($items + $serial),
            $fmt($diff)
        );
    }
}

echo "\nDone.\n";
