<?php

/**
 * Diagnosa kode scan GR Outlet untuk satu DO.
 * Usage: php database/scripts/diagnose_gr_do_barcode.php DO2605280090
 *        php database/scripts/diagnose_gr_do_barcode.php DO2605280090 98950090
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$doNumber = $argv[1] ?? null;
$scanCode = $argv[2] ?? null;

if (! $doNumber) {
    echo "Usage: php database/scripts/diagnose_gr_do_barcode.php <DO_NUMBER> [SCAN_CODE]\n";
    exit(1);
}

$do = DB::table('delivery_orders')->where('number', $doNumber)->first();
if (! $do) {
    echo "DO tidak ditemukan: {$doNumber}\n";
    exit(1);
}

echo "DO: {$do->number} (id={$do->id})\n\n";

$lines = DB::table('delivery_order_items as doi')
    ->join('items as i', 'doi.item_id', '=', 'i.id')
    ->where('doi.delivery_order_id', $do->id)
    ->select('doi.id as doi_id', 'doi.item_id', 'doi.barcode as do_barcode', 'i.name', 'i.sku')
    ->get();

foreach ($lines as $line) {
    echo "Item: {$line->name} (item_id={$line->item_id})\n";
    echo "  SKU (items.sku / Code label): " . ($line->sku ?: '-') . "\n";
    echo "  DO barcode snapshot: " . ($line->do_barcode ?: '-') . "\n";

    $barcodes = DB::table('item_barcodes')->where('item_id', $line->item_id)->pluck('barcode');
    echo '  item_barcodes: ' . ($barcodes->isEmpty() ? '-' : $barcodes->implode(', ')) . "\n\n";
}

if ($scanCode) {
    echo "Test scan code: {$scanCode}\n";
    $controller = app(\App\Http\Controllers\OutletFoodGoodReceiveController::class);
    $request = Illuminate\Http\Request::create('/test', 'POST', [
        'delivery_order_id' => $do->id,
        'code' => $scanCode,
    ]);
    $response = $controller->resolveBarcode($request);
    echo $response->getContent() . "\n";
}
