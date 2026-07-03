<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$rows = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->join('items as i', 'i.id', '=', 'ffoi.item_id')
    ->where('i.name', 'Salad Oil')
    ->where('ffoi.price', 896100)
    ->select('ffoi.id', 'ffoi.price', 'ffo.tanggal', 'ffo.fo_mode', 'ffo.status', 'ffo.order_number')
    ->get();
foreach ($rows as $r) {
    echo "id={$r->id} price={$r->price} tanggal={$r->tanggal} mode={$r->fo_mode} status={$r->status} order={$r->order_number}\n";
}

$khusus = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->whereIn('ffoi.item_id', DB::table('items')->whereIn('name', [
        'Artisan Tea Ceylon Green Tea','Artisan Tea Ginger & Mint','Butter Salted','Cup 12 Logo SH',
        'Gas Whipped','Kacang Arab','Kacang Tanah','Kecap Asin Angsa','Salad Oil','Vetcin Powder',
    ])->pluck('id'))
    ->whereIn('ffo.fo_mode', ['RO Khusus', 'RO Supplier'])
    ->join('outlet_food_good_receives as gr', function ($j) {
        $j->on('gr.outlet_id', '=', 'ffo.id_outlet');
    })
    ->whereBetween('gr.receive_date', ['2026-06-01', '2026-06-30'])
    ->select('ffoi.id', 'ffoi.item_id', 'ffoi.price', 'ffo.fo_mode', 'ffo.tanggal')
    ->limit(5)
    ->get();
echo "\nRO Khusus/Supplier sample (bad join): " . $khusus->count() . "\n";
