<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$from = '2026-06-01';
$to = '2026-06-30';

$ctrl = new class {
    use \App\Http\Traits\ReportHelperTrait;
    public function allDetail(string $from, string $to, string $warehouse): array {
        $out = [];
        $outlets = DB::table('tbl_data_outlet')->pluck('nama_outlet');
        foreach ($outlets as $outlet) {
            $food = $this->rekapFjFetchFoodGrDetailRows($outlet, $from, $to, $warehouse);
            $serial = $this->rekapFjFetchSerialGrDetailRows($outlet, $from, $to, $warehouse);
            foreach ($this->rekapFjMergeFjDetailRows($food, $serial) as $row) {
                $name = (string) $row->item_name;
                if (!isset($out[$name])) {
                    $out[$name] = ['qty' => 0, 'subtotal' => 0];
                }
                $out[$name]['qty'] += (float) $row->received_qty;
                $out[$name]['subtotal'] += (float) $row->subtotal;
            }
        }
        return $out;
    }
};

$agg = $ctrl->allDetail($from, $to, 'Main Store');

foreach (['Kacang Tanah', 'Vetcin Powder'] as $name) {
    $item = DB::table('items')->where('name', $name)->first();
    $medium = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
    $expected = FloorOrderItemPriceResolver::resolveLineUnitPrice((int) $item->id, (string) $medium);
    $ip = DB::table('item_prices')->where('item_id', $item->id)->where('availability_price_type', 'all')->orderByDesc('id')->first();

    $a = $agg[$name] ?? ['qty' => 0, 'subtotal' => 0];
    $report = $a['qty'] > 0 ? $a['subtotal'] / $a['qty'] : 0;

    echo "{$name}:\n";
    echo "  ALL outlets Main Store report_price=" . round($report, 2) . " qty={$a['qty']}\n";
    echo "  item_prices stored={$ip->price} expected_medium={$expected}\n";
    echo "  selisih vs expected=" . round($report - $expected, 2) . "\n";
    echo "  selisih vs stored=" . round($report - (float) $ip->price, 2) . "\n\n";
}
