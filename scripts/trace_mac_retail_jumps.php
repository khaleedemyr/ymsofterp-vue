<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('outlet_food_inventory_cost_histories as h')
    ->join('outlet_food_inventory_items as fi', 'fi.id', '=', 'h.inventory_item_id')
    ->join('items as i', 'i.id', '=', 'fi.item_id')
    ->leftJoin('retail_food as rf', 'rf.id', '=', 'h.reference_id')
    ->where('h.reference_type', 'retail_food')
    ->where('h.date', '>=', '2026-01-01')
    ->whereRaw('h.old_cost > 0 and (h.new_cost / h.old_cost >= 5 or h.new_cost / h.old_cost <= 0.2)')
    ->orderByDesc('h.id')
    ->limit(20)
    ->get([
        'h.date',
        'h.id_outlet',
        'rf.retail_number',
        'h.reference_id',
        'i.name as item',
        'h.old_cost',
        'h.new_cost',
        'h.mac',
    ]);

foreach ($rows as $r) {
    echo "{$r->date} | outlet {$r->id_outlet} | {$r->retail_number} (#{$r->reference_id}) | {$r->item} | old={$r->old_cost} new={$r->new_cost} mac={$r->mac}\n";
}

