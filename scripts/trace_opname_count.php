<?php

/**
 * One-off: count outlet_stock_opname_items for a given opname_number.
 * Usage: php scripts/trace_opname_count.php [opname_number]
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$num = $argv[1] ?? 'SO-20260402-023';

$row = Illuminate\Support\Facades\DB::table('outlet_stock_opnames')
    ->where('opname_number', $num)
    ->first();

if (!$row) {
    echo "NOT_FOUND: no outlet_stock_opnames row for opname_number={$num}\n";
    exit(1);
}

$count = Illuminate\Support\Facades\DB::table('outlet_stock_opname_items')
    ->where('stock_opname_id', $row->id)
    ->count();

$withDiff = Illuminate\Support\Facades\DB::table('outlet_stock_opname_items')
    ->where('stock_opname_id', $row->id)
    ->where(function ($q) {
        $q->where('qty_diff_small', '!=', 0)
            ->orWhere('qty_diff_medium', '!=', 0)
            ->orWhere('qty_diff_large', '!=', 0);
    })
    ->count();

$withReason = Illuminate\Support\Facades\DB::table('outlet_stock_opname_items')
    ->where('stock_opname_id', $row->id)
    ->whereNotNull('reason')
    ->where('reason', '!=', '')
    ->count();

echo "opname_number: {$num}\n";
echo "outlet_stock_opnames.id: {$row->id}\n";
echo "outlet_stock_opname_items total rows: {$count}\n";
echo "rows with qty_diff (any unit) != 0: {$withDiff}\n";
echo "rows with non-empty reason: {$withReason}\n";
