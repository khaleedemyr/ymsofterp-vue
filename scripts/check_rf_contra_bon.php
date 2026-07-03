<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$retailNumber = $argv[1] ?? 'RF202607010033';

$rf = DB::table('retail_food')->where('retail_number', $retailNumber)->first();
if (!$rf) {
    echo "NOT_FOUND: {$retailNumber}\n";
    exit(1);
}

$id = (int) $rf->id;
$cbSources = DB::table('food_contra_bon_sources')
    ->where('source_type', 'retail_food')
    ->where('source_id', $id)
    ->get();

$cbHeaderQuery = DB::table('food_contra_bons')
    ->where('source_type', 'retail_food')
    ->where('source_id', $id);
if (Schema::hasColumn('food_contra_bons', 'deleted_at')) {
    $cbHeaderQuery->whereNull('deleted_at');
}
$cbHeader = $cbHeaderQuery->get(['id', 'number', 'status']);

$reasons = [];
if ($rf->payment_method !== 'contra_bon') {
    $reasons[] = "payment_method bukan contra_bon (sekarang: {$rf->payment_method})";
}
if ($rf->status !== 'approved') {
    $reasons[] = "status bukan approved (sekarang: {$rf->status})";
}
if (DB::table('retail_food_items')->where('retail_food_id', $id)->count() === 0) {
    $reasons[] = 'tidak punya retail_food_items';
}
if (!DB::table('suppliers')->where('id', $rf->supplier_id)->exists()) {
    $reasons[] = "supplier_id {$rf->supplier_id} tidak ada di suppliers";
}
if (!DB::table('users')->where('id', $rf->created_by)->exists()) {
    $reasons[] = "created_by {$rf->created_by} tidak ada di users";
}
if ($cbSources->isNotEmpty()) {
    $reasons[] = 'sudah punya food_contra_bon_sources (dianggap sudah dipakai contra bon)';
}
if ($cbHeader->isNotEmpty()) {
    $reasons[] = 'sudah punya food_contra_bons header';
}

$eligible = $reasons === [];

echo json_encode([
    'retail_food' => $rf,
    'items_count' => DB::table('retail_food_items')->where('retail_food_id', $id)->count(),
    'contra_bon_sources' => $cbSources,
    'contra_bon_header' => $cbHeader,
    'eligible_for_contra_bon_list' => $eligible,
    'blocking_reasons' => $reasons,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if ($cbHeader->isNotEmpty()) {
    $cbId = (int) $cbHeader->first()->id;
    $cb = DB::table('food_contra_bons')->where('id', $cbId)->first();
    echo "\n--- linked contra bon ---\n";
    echo json_encode([
        'contra_bon' => $cb,
        'items_count' => DB::table('food_contra_bon_items')->where('contra_bon_id', $cbId)->count(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
