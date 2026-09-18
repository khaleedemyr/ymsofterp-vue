<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\OutletRejectionController;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

$ctrl = app(OutletRejectionController::class);
$ref = new ReflectionClass($ctrl);

$resolve = $ref->getMethod('resolveMacSmallForRejection');
$resolve->setAccessible(true);
$convert = $ref->getMethod('convertMacSmallToLineUnit');
$convert->setAccessible(true);
$sanitize = $ref->getMethod('sanitizeMacLineForStorage');
$sanitize->setAccessible(true);

$serial = DB::table('inventory_item_serials')->where('id', 298822)->first();
$item = DB::table('items')->where('id', 53235)->first();
$inv = DB::table('food_inventory_items')->where('item_id', 53235)->first();
$warehouseId = 5; // assume MK related

$macSmall = $resolve->invoke($ctrl, (float)$serial->cost_small, $inv, $warehouseId);
$macLine = $convert->invoke($ctrl, $macSmall, $item, 5);
$macSafe = $sanitize->invoke($ctrl, $macLine, $serial, $item, 5, $warehouseId);

echo "serial_cost_small={$serial->cost_small}\n";
echo "resolved_mac_small={$macSmall}\n";
echo "mac_line={$macLine}\n";
echo "mac_safe={$macSafe}\n";
echo "fits_decimal15_4=".($macSafe <= 99999999999.9999 ? 'YES' : 'NO')."\n";
