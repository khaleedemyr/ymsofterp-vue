<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$nums = ['GSR-20260719-0136', 'GSR-20260719-0126'];

foreach ($nums as $num) {
    $h = DB::table('outlet_serial_receive_headers')->where('number', $num)->first();
    if (!$h) {
        echo "{$num} NOT FOUND\n";
        continue;
    }

    echo "=== {$num} id={$h->id} outlet={$h->outlet_id} ===\n";

    $rows = DB::table('outlet_serial_receive_items as si')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->leftJoin('warehouse_outlets as wo', 'si.warehouse_outlet_id', '=', 'wo.id')
        ->where('si.header_id', $h->id)
        ->select(
            'si.id',
            'it.id as item_id',
            'it.name as item_name',
            'it.warehouse_division_id',
            'wd.name as div_name',
            'w.id as wh_id',
            'w.name as wh_name',
            'wo.name as wo_name'
        )
        ->orderBy('si.id')
        ->get();

    echo 'items: ' . $rows->count() . "\n";
    $pairs = [];
    foreach ($rows as $r) {
        $pair = ($r->wh_name ?: '-') . ' | ' . ($r->div_name ?: '-');
        $pairs[$pair] = ($pairs[$pair] ?? 0) + 1;
        echo "  si={$r->id} item={$r->item_id} {$r->item_name} | div={$r->div_name} wh={$r->wh_name} | outlet_wh={$r->wo_name}\n";
    }

    echo "unique wh|div pairs:\n";
    foreach ($pairs as $pair => $cnt) {
        echo "  {$pair} => {$cnt}\n";
    }

    $w1 = DB::select(
        'SELECT w.name FROM outlet_serial_receive_items si
         INNER JOIN items it ON si.item_id = it.id
         LEFT JOIN warehouse_division wd ON it.warehouse_division_id = wd.id
         LEFT JOIN warehouses w ON wd.warehouse_id = w.id
         WHERE si.header_id = ? LIMIT 1',
        [$h->id]
    );
    $d1 = DB::select(
        'SELECT wd.name FROM outlet_serial_receive_items si
         INNER JOIN items it ON si.item_id = it.id
         LEFT JOIN warehouse_division wd ON it.warehouse_division_id = wd.id
         WHERE si.header_id = ? LIMIT 1',
        [$h->id]
    );

    echo 'report subquery => ' . ($w1[0]->name ?? 'null') . ' - ' . ($d1[0]->name ?? 'null') . "\n\n";
}

echo "warehouses with Perishable in name:\n";
$lit = DB::table('warehouses')
    ->where('name', 'like', '%Perishable%')
    ->orWhere('name', 'like', '%MK2 Cold Kitchen -%')
    ->get(['id', 'name']);
foreach ($lit as $w) {
    echo "  {$w->id} {$w->name}\n";
}

echo "\nwarehouse_division named Perishable:\n";
$divs = DB::table('warehouse_division as wd')
    ->leftJoin('warehouses as w', 'w.id', '=', 'wd.warehouse_id')
    ->where('wd.name', 'Perishable')
    ->select('wd.id', 'wd.name', 'w.name as wh')
    ->get();
foreach ($divs as $d) {
    echo "  div={$d->id} {$d->name} under {$d->wh}\n";
}
