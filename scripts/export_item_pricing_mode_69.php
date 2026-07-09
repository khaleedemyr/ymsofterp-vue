<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FoodGrLastPurchaseForItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 69 item names — exact list from user (pecahan gambar)
$list = [
    'Beras Lokal',
    'Beras Pandan Wangi',
    'Black Pepper',
    'Buah Lychee Can',
    'Buah Peach Can',
    'Cheese Powder',
    'Chicken Powder',
    'Chilli Flake',
    'Chocolate Compound',
    'Coca Cola Can',
    'Coca Cola Zero Can',
    'Cokelat Powder',
    'Cream Soup Powder',
    'Fryall',
    'Garam Kapal',
    'Garam Refina',
    'Gas Whipped',
    'Gerkin',
    'Guacamole',
    'Gula Pasir',
    'Gula Putih Sachet SH',
    'Kecap Asin Angsa',
    'Kecap Manis',
    'Kerupuk Emping',
    'Madu',
    'Marjan Lychee',
    'Marjan Strawberry',
    'Marjan Vanilla',
    'Mayonaise',
    'Milk Evaporate',
    'Minyak Klentik',
    'Nestea Powder',
    'Olive Oil 500Mili liter',
    'Oyster Sauce',
    'Pasta Fettucine',
    'Pasta Lasagna',
    'Pasta Spaghetti',
    'Plastik Roll Buah 30x45',
    'Plastik Wrap',
    'Salad Bowl',
    'Sauce Chilli Botol',
    'Sauce Cup',
    'Sauce Cup Dessert',
    'Sauce Tomat Botol',
    'Skewer Rotan Simpul',
    'Soda Water',
    'Soun',
    'Sunquick',
    'Syrup Gula Aren',
    'Syrup Kawista',
    'Syrup Mint',
    'Tabasco Sauce',
    'Teh Celup',
    'Tepung Easy Mix',
    'Tepung Roti Kasar',
    'Thyme',
    'Tissue Dinner',
    'Tissue Pop Up',
    'Tissue Roll',
    'Tissue Towel',
    'Truffle Oil',
    'Dishwash',
    'General Cleaner',
    'Hairnet',
    'Hands Glove Rubber',
    'Masker 3 Ply',
    'HVS 1 Ply 75x65',
    'Label Preparation Roll',
    'Thermal Roll Steak house',
];

$hasPricingMode = Schema::hasColumn('item_prices', 'pricing_mode');

/**
 * @return array{
 *   gr_number: ?string,
 *   gr_receive_date: ?string,
 *   po_number: ?string,
 *   po_price: ?float,
 *   po_unit: ?string,
 *   cost_large_last: ?float,
 *   suggested_sell: ?float,
 *   price_reason: string
 * }
 */
function resolveGrReference(int $itemId, string $pricingMode, ?float $storedPrice, ?float $suggested): array
{
    $line = DB::table('food_good_receive_items as gri')
        ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
        ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
        ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
        ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
        ->where('gri.item_id', $itemId)
        ->orderByDesc('gr.receive_date')
        ->orderByDesc('gr.id')
        ->orderByDesc('gri.id')
        ->select(
            'gr.gr_number',
            'gr.receive_date',
            'po.number as po_number',
            'poi.price as po_price',
            'u.name as po_unit'
        )
        ->first();

    $lastGr = FoodGrLastPurchaseForItem::lastLine($itemId);
    $costLarge = $lastGr ? (float) ($lastGr['cost_large'] ?? 0) : null;
    $suggestedSell = $suggested ?? FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);

    $grNumber = $line?->gr_number ?? ($lastGr['gr_number'] ?? null);
    $grDate = $line?->receive_date ? (string) $line->receive_date : ($lastGr['receive_date'] ?? null);
    $poNumber = $line?->po_number ?? null;
    $poPrice = $line && $line->po_price !== null ? (float) $line->po_price : ($lastGr['cost_po_unit'] ?? null);
    $poUnit = $line?->po_unit ?? null;

    if ($pricingMode === 'NO_PRICE') {
        $reason = 'Tidak ada baris item_prices';
    } elseif ($pricingMode === 'auto') {
        if ($costLarge && $costLarge > 0 && $grNumber) {
            $reason = sprintf(
                'Auto GR+12%% — HPP large Rp %s dari %s (%s)',
                number_format($costLarge, 0, ',', '.'),
                $grNumber,
                $grDate ?? '-'
            );
            if ($poNumber) {
                $reason .= sprintf(
                    '; referensi PO %s harga beli Rp %s/%s',
                    $poNumber,
                    number_format((float) $poPrice, 0, ',', '.'),
                    $poUnit ?? '-'
                );
            }
            if ($storedPrice !== null && $suggestedSell !== null && abs($storedPrice - $suggestedSell) > 0.01) {
                $reason .= sprintf(
                    '; item_prices (Rp %s) belum sama dengan hitungan GR terbaru (Rp %s)',
                    number_format($storedPrice, 0, ',', '.'),
                    number_format($suggestedSell, 0, ',', '.')
                );
            }
        } else {
            $reason = 'Auto GR+12% — belum ada GR Food / HPP untuk item ini';
        }
    } elseif ($pricingMode === 'manual') {
        $reason = 'Manual — harga diset manual, tidak mengikuti GR terakhir';
        if ($grNumber && $costLarge) {
            $reason .= sprintf(
                '; acuan GR terakhir %s (%s) HPP large Rp %s',
                $grNumber,
                $grDate ?? '-',
                number_format($costLarge, 0, ',', '.')
            );
            if ($poNumber) {
                $reason .= sprintf(
                    ', PO %s Rp %s/%s',
                    $poNumber,
                    number_format((float) $poPrice, 0, ',', '.'),
                    $poUnit ?? '-'
                );
            }
        }
    } else {
        $reason = 'Mode harga tidak diketahui';
    }

    return [
        'gr_number' => $grNumber,
        'gr_receive_date' => $grDate,
        'po_number' => $poNumber,
        'po_price' => $poPrice,
        'po_unit' => $poUnit,
        'cost_large_last' => $costLarge,
        'suggested_sell' => $suggestedSell,
        'price_reason' => $reason,
    ];
}

$summaryPath = __DIR__ . '/item_pricing_mode_summary_69_v2.csv';
$detailPath = __DIR__ . '/item_pricing_mode_list_69_v2.csv';

$sfh = fopen($summaryPath, 'w');
$dfh = fopen($detailPath, 'w');

fputcsv($sfh, [
    'no',
    'item_name',
    'item_id',
    'item_name_db',
    'sku',
    'category_db',
    'match_status',
    'pricing_mode',
    'scope',
    'item_prices_price',
    'suggested_sell_gr_plus_12',
    'cost_large_last',
    'gr_number',
    'gr_receive_date',
    'po_number',
    'po_price',
    'po_unit',
    'price_reason',
]);

fputcsv($dfh, [
    'no',
    'item_name',
    'item_id',
    'item_name_db',
    'sku',
    'pricing_mode',
    'availability_price_type',
    'region_id',
    'outlet_id',
    'item_prices_price',
    'suggested_sell_gr_plus_12',
    'cost_large_last',
    'gr_number',
    'gr_receive_date',
    'po_number',
    'po_price',
    'po_unit',
    'price_reason',
    'is_picked_by_resolver',
]);

$stats = ['auto' => 0, 'manual' => 0, 'no_price' => 0, 'not_found' => 0];

foreach ($list as $idx => $reportName) {
    $no = $idx + 1;

    $exact = DB::table('items as i')
        ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
        ->where('i.name', $reportName)
        ->where('i.status', 'active')
        ->select('i.id', 'i.name', 'i.sku', 'c.name as category_name')
        ->get();

    if ($exact->count() === 1) {
        $item = $exact->first();
        $matchStatus = 'exact';
    } elseif ($exact->count() > 1) {
        $item = $exact->first();
        $matchStatus = 'exact_multiple';
    } else {
        $fuzzy = DB::table('items as i')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->where('i.name', 'like', '%' . $reportName . '%')
            ->where('i.status', 'active')
            ->orderByRaw('LENGTH(i.name)')
            ->limit(1)
            ->select('i.id', 'i.name', 'i.sku', 'c.name as category_name')
            ->first();

        if (! $fuzzy) {
            $stats['not_found']++;
            fputcsv($sfh, [$no, $reportName, '', '', '', '', 'not_found', 'NO_PRICE', '', '', '', '', '', '', '', '', 'Item tidak ditemukan di master']);
            fputcsv($dfh, [$no, $reportName, '', '', '', 'NO_PRICE', '', '', '', '', '', '', '', '', '', '', 'Item tidak ditemukan di master', '']);
            continue;
        }
        $item = $fuzzy;
        $matchStatus = 'fuzzy';
    }

    $picked = FloorOrderItemPriceResolver::resolvePriceRow((int) $item->id, null, null);
    $suggested = FoodGrLastPurchaseForItem::suggestedSellingPrice((int) $item->id);

    if (! $picked) {
        $stats['no_price']++;
        $mode = 'NO_PRICE';
        $scope = '';
        $price = '';
        $storedPrice = null;
    } else {
        $mode = $hasPricingMode
            ? ((($picked->pricing_mode ?? 'manual') === 'auto') ? 'auto' : 'manual')
            : 'n/a';
        $scope = $picked->availability_price_type ?? '';
        $storedPrice = (float) $picked->price;
        $price = number_format($storedPrice, 2, '.', '');
        if ($mode === 'auto') {
            $stats['auto']++;
        } else {
            $stats['manual']++;
        }
    }

    $grRef = resolveGrReference(
        (int) $item->id,
        $mode,
        $storedPrice,
        $suggested !== null ? (float) $suggested : null
    );

    fputcsv($sfh, [
        $no,
        $reportName,
        $item->id,
        $item->name,
        $item->sku,
        $item->category_name,
        $matchStatus,
        $mode,
        $scope,
        $price,
        $grRef['suggested_sell'] !== null ? number_format($grRef['suggested_sell'], 2, '.', '') : '',
        $grRef['cost_large_last'] !== null ? number_format($grRef['cost_large_last'], 2, '.', '') : '',
        $grRef['gr_number'] ?? '',
        $grRef['gr_receive_date'] ?? '',
        $grRef['po_number'] ?? '',
        $grRef['po_price'] !== null ? number_format($grRef['po_price'], 2, '.', '') : '',
        $grRef['po_unit'] ?? '',
        $grRef['price_reason'],
    ]);

    $priceRows = DB::table('item_prices')->where('item_id', $item->id)->orderByDesc('id')->get();
    if ($priceRows->isEmpty()) {
        fputcsv($dfh, [
            $no, $reportName, $item->id, $item->name, $item->sku,
            'NO_PRICE', '', '', '', '',
            $grRef['suggested_sell'] !== null ? number_format($grRef['suggested_sell'], 2, '.', '') : '',
            $grRef['cost_large_last'] !== null ? number_format($grRef['cost_large_last'], 2, '.', '') : '',
            $grRef['gr_number'] ?? '',
            $grRef['gr_receive_date'] ?? '',
            $grRef['po_number'] ?? '',
            $grRef['po_price'] !== null ? number_format($grRef['po_price'], 2, '.', '') : '',
            $grRef['po_unit'] ?? '',
            $grRef['price_reason'],
            '',
        ]);
    } else {
        foreach ($priceRows as $pr) {
            $m = $hasPricingMode
                ? ((($pr->pricing_mode ?? 'manual') === 'auto') ? 'auto' : 'manual')
                : 'n/a';
            $isPicked = $picked && (int) $picked->id === (int) $pr->id ? 'yes' : 'no';
            $rowGrRef = resolveGrReference(
                (int) $item->id,
                $m,
                (float) $pr->price,
                $grRef['suggested_sell']
            );
            fputcsv($dfh, [
                $no,
                $reportName,
                $item->id,
                $item->name,
                $item->sku,
                $m,
                $pr->availability_price_type,
                $pr->region_id,
                $pr->outlet_id,
                number_format((float) $pr->price, 2, '.', ''),
                $rowGrRef['suggested_sell'] !== null ? number_format($rowGrRef['suggested_sell'], 2, '.', '') : '',
                $rowGrRef['cost_large_last'] !== null ? number_format($rowGrRef['cost_large_last'], 2, '.', '') : '',
                $rowGrRef['gr_number'] ?? '',
                $rowGrRef['gr_receive_date'] ?? '',
                $rowGrRef['po_number'] ?? '',
                $rowGrRef['po_price'] !== null ? number_format($rowGrRef['po_price'], 2, '.', '') : '',
                $rowGrRef['po_unit'] ?? '',
                $rowGrRef['price_reason'],
                $isPicked,
            ]);
        }
    }
}

fclose($sfh);
fclose($dfh);

echo "Summary CSV : {$summaryPath}\n";
echo "Detail CSV  : {$detailPath}\n";
echo "Total       : " . count($list) . "\n";
echo "auto={$stats['auto']} | manual={$stats['manual']} | no_price={$stats['no_price']} | not_found={$stats['not_found']}\n";
