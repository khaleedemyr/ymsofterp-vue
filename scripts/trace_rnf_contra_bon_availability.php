<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$number = 'RNF202606100008';
$row = DB::table('retail_non_food')->where('retail_number', $number)->first();

if (!$row) {
    echo "NOT FOUND\n";
    exit(0);
}

$inContraBonSources = DB::table('food_contra_bon_sources')
    ->where('source_type', 'retail_non_food')
    ->where('source_id', $row->id)
    ->get();

$inContraBonLegacy = DB::table('food_contra_bons')
    ->where('source_type', 'retail_non_food')
    ->where('source_id', $row->id)
    ->get(['id', 'number', 'status']);

$rankQuery = DB::table('retail_non_food as rnf')
    ->where('rnf.payment_method', 'contra_bon')
    ->where('rnf.status', 'approved')
    ->whereNull('rnf.deleted_at')
    ->whereNotExists(function ($q) {
        $q->select(DB::raw(1))
            ->from('food_contra_bon_sources as cbs')
            ->whereColumn('cbs.source_id', 'rnf.id')
            ->where('cbs.source_type', 'retail_non_food');
    })
    ->orderByDesc('rnf.transaction_date')
    ->orderByDesc('rnf.id')
    ->pluck('id');

$position = $rankQuery->search($row->id);
$totalAvailable = $rankQuery->count();

$inTop100 = $position !== false && $position < 100;
$inTop50AfterFilter = $position !== false && $position < 50;

echo json_encode([
    'id' => $row->id,
    'retail_number' => $row->retail_number,
    'transaction_date' => $row->transaction_date,
    'payment_method' => $row->payment_method,
    'status' => $row->status,
    'already_in_food_contra_bon_sources' => $inContraBonSources,
    'already_in_food_contra_bons_legacy' => $inContraBonLegacy,
    'available_rank_by_date_desc' => $position === false ? null : $position + 1,
    'total_available_contra_bon_rnf' => $totalAvailable,
    'in_initial_top_100_query' => $inTop100,
    'in_initial_top_50_after_filter' => $inTop50AfterFilter,
    'reasons_hidden' => array_values(array_filter([
        $row->payment_method !== 'contra_bon' ? 'payment_method bukan contra_bon' : null,
        $row->status !== 'approved' ? 'status bukan approved' : null,
        $inContraBonSources->isNotEmpty() ? 'sudah ada di food_contra_bon_sources' : null,
        !$inTop100 ? 'tidak masuk top 100 saat load halaman (rank ' . (($position ?? -1) + 1) . " dari $totalAvailable)" : null,
    ])),
], JSON_PRETTY_PRINT) . "\n";
