<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo 'rws cols: '.implode(', ', Schema::getColumnListing('retail_warehouse_sales')).PHP_EOL;

$outletId = 20;
$rows = DB::table('retail_warehouse_sales as rws')
    ->join('customers as c', 'rws.customer_id', '=', 'c.id')
    ->leftJoin('warehouse_division as wd', 'rws.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', function ($join) {
        $join->on('w.id', '=', DB::raw('COALESCE(wd.warehouse_id, rws.warehouse_id)'));
    })
    ->where('rws.status', 'completed')
    ->where('c.type', 'branch')
    ->where('c.id_outlet', $outletId)
    ->whereBetween(DB::raw('DATE(rws.sale_date)'), ['2026-09-01', '2026-09-30'])
    ->selectRaw('COALESCE(w.name, "(null)") as wh, SUM(rws.total_amount) as total, COUNT(*) as cnt')
    ->groupBy('w.name')
    ->get();

foreach ($rows as $r) {
    echo "wh={$r->wh} total={$r->total} cnt={$r->cnt}\n";
}

// check item warehouse_outlet if any
if (Schema::hasColumn('retail_warehouse_sale_items', 'warehouse_outlet_id')) {
    echo "items have warehouse_outlet_id\n";
} else {
    echo 'item cols: '.implode(', ', Schema::getColumnListing('retail_warehouse_sale_items')).PHP_EOL;
}
