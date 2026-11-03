<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('food_good_receives')
    ->whereDate('receive_date', '2026-11-03')
    ->orderBy('id')
    ->get(['id', 'gr_number', 'created_at', 'receive_date', 'po_id', 'received_by']);

echo 'count=' . $rows->count() . PHP_EOL;

$seqs = [];
foreach ($rows as $r) {
    $seq = (int) substr((string) $r->gr_number, -4);
    $seqs[] = $seq;
    echo "{$r->id}|{$r->gr_number}|seq={$seq}|{$r->created_at}|po={$r->po_id}|by={$r->received_by}\n";
}

sort($seqs);
$unique = array_values(array_unique($seqs));
echo 'min=' . ($unique[0] ?? 0) . ' max=' . (end($unique) ?: 0) . ' unique=' . count($unique) . PHP_EOL;

$expected = range(1, max($seqs ?: [0]));
$missing = array_diff($expected, $unique);
echo 'missing seqs: ' . (empty($missing) ? '(none)' : implode(',', $missing)) . PHP_EOL;

$dups = $rows->groupBy('gr_number')->filter(fn ($g) => $g->count() > 1);
foreach ($dups as $n => $g) {
    echo "DUP {$n} => ids " . $g->pluck('id')->implode(',') . PHP_EOL;
}
