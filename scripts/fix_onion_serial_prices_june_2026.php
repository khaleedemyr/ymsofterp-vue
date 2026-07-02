<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);
$targetCostSmall = 36.96;
$itemId = 53111;
$outletName = 'Justus Steakhouse SMB';
$from = '2026-06-01';
$to = '2026-06-30';

$rows = DB::table('outlet_serial_receive_items as si')
    ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
    ->join('items as it', 'it.id', '=', 'si.item_id')
    ->leftJoin('units as u', 'u.id', '=', 'si.unit_id')
    ->where('si.item_id', $itemId)
    ->where('o.nama_outlet', $outletName)
    ->whereBetween('h.receive_date', [$from, $to])
    ->whereRaw('ABS(COALESCE(si.cost_small,0) - ?) > 0.0001', [$targetCostSmall])
    ->select(
        'si.id',
        'si.header_id',
        'h.receive_date',
        'o.nama_outlet',
        'it.name as item_name',
        DB::raw("COALESCE(u.name, '-') as unit_name"),
        'si.qty',
        'si.cost_small'
    )
    ->orderBy('h.receive_date')
    ->get();

echo "=== Fix Onion Serial Price (June 2026, SMB) ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo "Target cost_small: {$targetCostSmall}\n";
echo 'Rows affected: ' . $rows->count() . "\n\n";

foreach ($rows as $r) {
    echo "{$r->receive_date} header={$r->header_id} si_id={$r->id} qty={$r->qty} unit={$r->unit_name} cost_small={$r->cost_small} -> {$targetCostSmall}\n";
}

if (! $apply || $rows->isEmpty()) {
    exit(0);
}

DB::beginTransaction();
try {
    $updated = DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
        ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
        ->where('si.item_id', $itemId)
        ->where('o.nama_outlet', $outletName)
        ->whereBetween('h.receive_date', [$from, $to])
        ->whereRaw('ABS(COALESCE(si.cost_small,0) - ?) > 0.0001', [$targetCostSmall])
        ->update([
            'si.cost_small' => $targetCostSmall,
            'si.updated_at' => now(),
        ]);

    DB::commit();
    echo "\nUpdated rows: {$updated}\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo "\nERROR: {$e->getMessage()}\n";
    exit(1);
}
