<?php

declare(strict_types=1);

/**
 * Trace harga Cabe Rawit Merah: item master, item_prices, PO/PR/GR/FO, cari anomali satuan.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function money($n): string
{
    return number_format((float) $n, 2, ',', '.');
}

function unitName($id): string
{
    static $cache = [];
    if ($id === null) {
        return '-';
    }
    $id = (int) $id;
    if (! isset($cache[$id])) {
        $cache[$id] = DB::table('units')->where('id', $id)->value('name') ?: (string) $id;
    }

    return $cache[$id];
}

function toSmallCost(object $item, float $price, ?int $unitId): float
{
    $smallConv = (float) ($item->small_conversion_qty ?: 1);
    $mediumConv = (float) ($item->medium_conversion_qty ?: 1);
    if ($unitId && $unitId === (int) $item->large_unit_id) {
        return $price / max($smallConv * $mediumConv, 0.0001);
    }
    if ($unitId && $unitId === (int) $item->medium_unit_id) {
        return $price / max($smallConv, 0.0001);
    }

    return $price;
}

function toKgCost(object $item, float $costSmall): float
{
    $smallConv = (float) ($item->small_conversion_qty ?: 1);
    $mediumConv = (float) ($item->medium_conversion_qty ?: 1);
    $smallName = strtolower(unitName($item->small_unit_id));
    $largeName = strtolower(unitName($item->large_unit_id));

    if (str_contains($smallName, 'gram') && $smallConv > 1) {
        return $costSmall * $smallConv * (str_contains($largeName, 'kg') ? $mediumConv : 1);
    }

    return $costSmall * $smallConv * $mediumConv;
}

$items = DB::table('items')
    ->where('name', 'like', '%Cabe Rawit Merah%')
    ->orWhere('name', 'like', '%cabe rawit merah%')
    ->get();

if ($items->isEmpty()) {
    $items = DB::table('items')->where('name', 'like', '%Rawit Merah%')->get();
}

echo "=== ITEMS ===\n";
if ($items->isEmpty()) {
    echo "Item tidak ditemukan.\n";
    exit(1);
}

foreach ($items as $item) {
    echo sprintf(
        "id=%d sku=%s name=%s status=%s | small=%s conv=%s | medium=%s conv=%s | large=%s\n",
        $item->id,
        $item->sku,
        $item->name,
        $item->status ?? '-',
        unitName($item->small_unit_id),
        $item->small_conversion_qty,
        unitName($item->medium_unit_id),
        $item->medium_conversion_qty,
        unitName($item->large_unit_id)
    );
}

foreach ($items as $item) {
    $itemId = (int) $item->id;
    echo "\n\n########## {$item->name} (id={$itemId} sku={$item->sku}) ##########\n";

    echo "\n=== item_prices ===\n";
    if (Schema::hasTable('item_prices')) {
        $prices = DB::table('item_prices')->where('item_id', $itemId)->orderByDesc('id')->get();
        if ($prices->isEmpty()) {
            echo "(kosong)\n";
        } else {
            foreach ($prices as $p) {
                $mode = $p->pricing_mode ?? '-';
                $type = $p->availability_price_type ?? '-';
                $region = $p->region_id ?? '-';
                $outlet = $p->outlet_id ?? '-';
                echo sprintf(
                    "id=%s type=%s region=%s outlet=%s mode=%s price=%s updated=%s\n",
                    $p->id,
                    $type,
                    $region,
                    $outlet,
                    $mode,
                    money($p->price),
                    $p->updated_at ?? '-'
                );
            }
        }
    }

    echo "\n=== PO (purchase_order_food_items) last 40 ===\n";
    $poLines = DB::table('purchase_order_food_items as poi')
        ->join('purchase_order_foods as po', 'po.id', '=', 'poi.purchase_order_food_id')
        ->where('poi.item_id', $itemId)
        ->orderByDesc('po.date')
        ->orderByDesc('po.id')
        ->limit(40)
        ->get([
            'poi.id as poi_id',
            'po.id as po_id',
            'po.number',
            'po.date',
            'po.status',
            'poi.quantity',
            'poi.unit_id',
            'poi.price',
            'poi.total',
        ]);

    $poCostSmalls = [];
    if ($poLines->isEmpty()) {
        echo "(kosong)\n";
    } else {
        printf("%-12s %-14s %-10s %8s %-8s %14s %14s %12s %12s\n",
            'date', 'PO', 'status', 'qty', 'unit', 'price', 'total', 'cost/small', 'cost/kg');
        foreach ($poLines as $row) {
            $costSmall = toSmallCost($item, (float) $row->price, $row->unit_id ? (int) $row->unit_id : null);
            $costKg = toKgCost($item, $costSmall);
            $poCostSmalls[] = $costSmall;
            printf(
                "%-12s %-14s %-10s %8s %-8s %14s %14s %12s %12s\n",
                substr((string) $row->date, 0, 10),
                $row->number,
                $row->status,
                money($row->quantity),
                unitName($row->unit_id),
                money($row->price),
                money($row->total),
                money($costSmall),
                money($costKg)
            );
        }
    }

    echo "\n=== PO distinct price+unit ===\n";
    $poDistinct = DB::table('purchase_order_food_items as poi')
        ->join('purchase_order_foods as po', 'po.id', '=', 'poi.purchase_order_food_id')
        ->where('poi.item_id', $itemId)
        ->select('poi.price', 'poi.unit_id', DB::raw('count(*) as cnt'), DB::raw('min(po.date) as first_date'), DB::raw('max(po.date) as last_date'))
        ->groupBy('poi.price', 'poi.unit_id')
        ->orderByDesc('cnt')
        ->get();
    foreach ($poDistinct as $d) {
        $costSmall = toSmallCost($item, (float) $d->price, $d->unit_id ? (int) $d->unit_id : null);
        echo sprintf(
            "price=%s unit=%s cnt=%s first=%s last=%s cost/small=%s cost/kg=%s\n",
            money($d->price),
            unitName($d->unit_id),
            $d->cnt,
            $d->first_date,
            $d->last_date,
            money($costSmall),
            money(toKgCost($item, $costSmall))
        );
    }

    if (count($poCostSmalls) > 0) {
        sort($poCostSmalls);
        $n = count($poCostSmalls);
        $median = $poCostSmalls[(int) floor(($n - 1) / 2)];
        $min = $poCostSmalls[0];
        $max = $poCostSmalls[$n - 1];
        echo "\nPO cost/small stats: min=" . money($min) . ' median=' . money($median) . ' max=' . money($max) . "\n";
    }

    echo "\n=== ANOMALI PO (cost/small 10x di luar median, atau >1000/gram, atau <5/gram) ===\n";
    $median = 0;
    if (count($poCostSmalls) > 0) {
        $sorted = $poCostSmalls;
        sort($sorted);
        $median = $sorted[(int) floor((count($sorted) - 1) / 2)];
    }
    $allPo = DB::table('purchase_order_food_items as poi')
        ->join('purchase_order_foods as po', 'po.id', '=', 'poi.purchase_order_food_id')
        ->where('poi.item_id', $itemId)
        ->orderByDesc('po.date')
        ->get([
            'poi.id as poi_id',
            'po.id as po_id',
            'po.number',
            'po.date',
            'po.status',
            'poi.quantity',
            'poi.unit_id',
            'poi.price',
            'poi.total',
            'po.created_by',
        ]);

    $anomCount = 0;
    foreach ($allPo as $row) {
        $costSmall = toSmallCost($item, (float) $row->price, $row->unit_id ? (int) $row->unit_id : null);
        $ratio = $median > 0 ? $costSmall / $median : 0;
        $isAnom = $costSmall > 1000 || ($costSmall > 0 && $costSmall < 5) || ($median > 0 && ($ratio >= 10 || $ratio <= 0.1));
        if (! $isAnom) {
            continue;
        }
        $anomCount++;
        echo sprintf(
            "ANOMALI poi=%s %s %s qty=%s unit=%s price=%s total=%s cost/small=%s cost/kg=%s vs median %sx created_by=%s\n",
            $row->poi_id,
            substr((string) $row->date, 0, 10),
            $row->number,
            money($row->quantity),
            unitName($row->unit_id),
            money($row->price),
            money($row->total),
            money($costSmall),
            money(toKgCost($item, $costSmall)),
            money($ratio),
            $row->created_by ?? '-'
        );
    }
    if ($anomCount === 0) {
        echo "(tidak ada anomali PO dengan filter 10x / >1000 / <5 per small)\n";
    } else {
        echo "\n=== Detail GR untuk PO anomali ===\n";
        $anomPois = $allPo->filter(function ($row) use ($item, $median) {
            $costSmall = toSmallCost($item, (float) $row->price, $row->unit_id ? (int) $row->unit_id : null);
            $ratio = $median > 0 ? $costSmall / $median : 0;

            return $costSmall > 1000 || ($costSmall > 0 && $costSmall < 5) || ($median > 0 && ($ratio >= 10 || $ratio <= 0.1));
        });
        foreach ($anomPois as $row) {
            $grs = DB::table('food_good_receive_items as gri')
                ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
                ->where('gri.po_item_id', $row->poi_id)
                ->get(['gr.id', 'gr.gr_number', 'gr.receive_date', 'gri.qty_received', 'gri.unit_id']);
            echo "PO {$row->number} poi={$row->poi_id} GR count={$grs->count()}\n";
            foreach ($grs as $g) {
                echo sprintf("  GR %s date=%s qty=%s unit=%s\n", $g->gr_number, $g->receive_date, money($g->qty_received), unitName($g->unit_id));
            }
        }
    }

    echo "\n=== PR (pr_food_items) last 20 ===\n";
    try {
        if (Schema::hasTable('pr_food_items')) {
            $dateCol = Schema::hasColumn('pr_foods', 'tanggal') ? 'tanggal' : 'date';
            $select = ['pri.id as pri_id', 'pr.pr_number', "pr.{$dateCol} as pr_date", 'pri.qty'];
            if (Schema::hasColumn('pr_food_items', 'unit')) {
                $select[] = 'pri.unit';
            }
            if (Schema::hasColumn('pr_food_items', 'price')) {
                $select[] = 'pri.price';
            }
            $prLines = DB::table('pr_food_items as pri')
                ->join('pr_foods as pr', 'pr.id', '=', 'pri.pr_food_id')
                ->where('pri.item_id', $itemId)
                ->orderByDesc("pr.{$dateCol}")
                ->limit(20)
                ->get($select);
            foreach ($prLines as $row) {
                echo sprintf(
                    "%s %s qty=%s unit=%s price=%s\n",
                    substr((string) ($row->pr_date ?? ''), 0, 10),
                    $row->pr_number ?? '-',
                    money($row->qty ?? 0),
                    $row->unit ?? '-',
                    money($row->price ?? 0)
                );
            }
            if ($prLines->isEmpty()) {
                echo "(kosong)\n";
            }
        }
    } catch (\Throwable $e) {
        echo "PR skip: " . $e->getMessage() . "\n";
    }

    echo "\n=== GR last 25 (harga dari PO line) ===\n";
    $grLines = DB::table('food_good_receive_items as gri')
        ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
        ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
        ->where('gri.item_id', $itemId)
        ->orderByDesc('gr.receive_date')
        ->orderByDesc('gr.id')
        ->limit(25)
        ->get([
            'gr.gr_number',
            'gr.receive_date',
            'gri.qty_received',
            'gri.unit_id',
            'poi.price as po_price',
            'poi.id as poi_id',
        ]);
    foreach ($grLines as $g) {
        $costSmall = toSmallCost($item, (float) ($g->po_price ?? 0), $g->unit_id ? (int) $g->unit_id : null);
        echo sprintf(
            "%s %s qty=%s unit=%s po_price=%s cost/small=%s cost/kg=%s poi=%s\n",
            $g->receive_date,
            $g->gr_number,
            money($g->qty_received),
            unitName($g->unit_id),
            money($g->po_price ?? 0),
            money($costSmall),
            money(toKgCost($item, $costSmall)),
            $g->poi_id ?? '-'
        );
    }
    if ($grLines->isEmpty()) {
        echo "(kosong)\n";
    }

    echo "\n=== FO distinct price+unit (last year) ===\n";
    $foPrices = DB::table('food_floor_order_items as foi')
        ->join('food_floor_orders as fo', 'fo.id', '=', 'foi.floor_order_id')
        ->where('foi.item_id', $itemId)
        ->where('fo.tanggal', '>=', '2026-01-01')
        ->select('foi.price', 'foi.unit', DB::raw('count(*) as cnt'), DB::raw('min(fo.tanggal) as first_date'), DB::raw('max(fo.tanggal) as last_date'))
        ->groupBy('foi.price', 'foi.unit')
        ->orderByDesc('cnt')
        ->get();
    foreach ($foPrices as $p) {
        echo sprintf(
            "price=%s unit=%s cnt=%s first=%s last=%s\n",
            money($p->price),
            $p->unit,
            $p->cnt,
            $p->first_date,
            $p->last_date
        );
    }
    if ($foPrices->isEmpty()) {
        echo "(kosong)\n";
    }

    echo "\n=== FO anomali (price > 1000 atau < 5, 2026) sample ===\n";
    $foAnom = DB::table('food_floor_order_items as foi')
        ->join('food_floor_orders as fo', 'fo.id', '=', 'foi.floor_order_id')
        ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'fo.id_outlet')
        ->where('foi.item_id', $itemId)
        ->where('fo.tanggal', '>=', '2026-01-01')
        ->where(function ($q) {
            $q->where('foi.price', '>', 1000)->orWhere('foi.price', '<', 5);
        })
        ->orderByDesc('fo.tanggal')
        ->limit(30)
        ->get(['fo.order_number', 'fo.tanggal', 'fo.status', 'foi.price', 'foi.unit', 'o.nama_outlet']);
    foreach ($foAnom as $n) {
        echo sprintf(
            "%s %s status=%s outlet=%s unit=%s price=%s\n",
            $n->tanggal,
            $n->order_number,
            $n->status,
            $n->nama_outlet ?? '-',
            $n->unit,
            money($n->price)
        );
    }
    if ($foAnom->isEmpty()) {
        echo "(tidak ada FO 2026 dengan price >1000 atau <5)\n";
    }

    echo "\n=== Retail food last 20 (by item_name) ===\n";
    if (Schema::hasTable('retail_food_items') && Schema::hasTable('retail_food')) {
        $rf = DB::table('retail_food_items as rfi')
            ->join('retail_food as rf', 'rf.id', '=', 'rfi.retail_food_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'rf.outlet_id')
            ->where('rfi.item_name', $item->name)
            ->orderByDesc('rf.transaction_date')
            ->limit(20)
            ->get(['rf.id as rf_id', 'rf.retail_number', 'rf.transaction_date', 'rf.status', 'rfi.qty', 'rfi.unit', 'rfi.price', 'rfi.subtotal', 'o.nama_outlet']);
        $rfCosts = [];
        foreach ($rf as $row) {
            echo sprintf(
                "%s %s status=%s outlet=%s qty=%s unit=%s price=%s subtotal=%s rf_id=%s\n",
                $row->transaction_date,
                $row->retail_number,
                $row->status ?? '-',
                $row->nama_outlet ?? '-',
                money($row->qty ?? 0),
                $row->unit ?? '-',
                money($row->price ?? 0),
                money($row->subtotal ?? 0),
                $row->rf_id
            );
            $rfCosts[] = (float) $row->price;
        }
        if ($rf->isEmpty()) {
            echo "(kosong)\n";
        }

        echo "\n=== Retail food distinct price+unit 2026 ===\n";
        $rfDistinct = DB::table('retail_food_items as rfi')
            ->join('retail_food as rf', 'rf.id', '=', 'rfi.retail_food_id')
            ->where('rfi.item_name', $item->name)
            ->where('rf.transaction_date', '>=', '2026-01-01')
            ->select('rfi.price', 'rfi.unit', DB::raw('count(*) as cnt'), DB::raw('min(rf.transaction_date) as first_date'), DB::raw('max(rf.transaction_date) as last_date'))
            ->groupBy('rfi.price', 'rfi.unit')
            ->orderByDesc('cnt')
            ->get();
        foreach ($rfDistinct as $d) {
            echo sprintf("price=%s unit=%s cnt=%s first=%s last=%s\n", money($d->price), $d->unit, $d->cnt, $d->first_date, $d->last_date);
        }

        echo "\n=== ANOMALI retail food 2026 (price > 1000/gram atau < 5/gram, atau Kg <5000 / >300000) ===\n";
        $rfAll = DB::table('retail_food_items as rfi')
            ->join('retail_food as rf', 'rf.id', '=', 'rfi.retail_food_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'rf.outlet_id')
            ->where('rfi.item_name', $item->name)
            ->where('rf.transaction_date', '>=', '2026-01-01')
            ->orderByDesc('rf.transaction_date')
            ->get(['rf.id as rf_id', 'rf.retail_number', 'rf.transaction_date', 'rf.status', 'rfi.qty', 'rfi.unit', 'rfi.price', 'rfi.subtotal', 'o.nama_outlet']);
        $rfAnom = 0;
        foreach ($rfAll as $row) {
            $price = (float) $row->price;
            $unit = strtolower((string) $row->unit);
            $isGram = str_contains($unit, 'gram') && ! str_contains($unit, 'kg');
            $isKg = str_contains($unit, 'kg') || $unit === 'kilogram';
            $isAnom = false;
            if ($isGram && ($price > 1000 || ($price > 0 && $price < 5))) {
                $isAnom = true;
            }
            if ($isKg && ($price < 5000 || $price > 300000)) {
                $isAnom = true;
            }
            if (! $isAnom) {
                continue;
            }
            $rfAnom++;
            echo sprintf(
                "ANOMALI %s %s outlet=%s qty=%s unit=%s price=%s subtotal=%s status=%s rf=%s\n",
                $row->transaction_date,
                $row->retail_number,
                $row->nama_outlet ?? '-',
                money($row->qty),
                $row->unit,
                money($row->price),
                money($row->subtotal),
                $row->status,
                $row->rf_id
            );
        }
        if ($rfAnom === 0) {
            echo "(tidak ada)\n";
        }
    }

    echo "\n=== GROS (good_receive_outlet_supplier_items) last 20 + anomali 2026 ===\n";
    if (Schema::hasTable('good_receive_outlet_supplier_items')) {
        $hasGrNumber = Schema::hasColumn('good_receive_outlet_suppliers', 'gr_number');
        $grosSelect = [
            'gr.id as gr_id',
            'gr.receive_date',
            'gri.qty_received',
            'gri.unit_id',
            'gri.price',
            'o.nama_outlet',
        ];
        if ($hasGrNumber) {
            $grosSelect[] = 'gr.gr_number';
        }

        $gros = DB::table('good_receive_outlet_supplier_items as gri')
            ->join('good_receive_outlet_suppliers as gr', 'gr.id', '=', 'gri.good_receive_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'gr.outlet_id')
            ->where('gri.item_id', $itemId)
            ->orderByDesc('gr.receive_date')
            ->limit(20)
            ->get($grosSelect);
        foreach ($gros as $row) {
            $costSmall = toSmallCost($item, (float) $row->price, $row->unit_id ? (int) $row->unit_id : null);
            echo sprintf(
                "%s %s outlet=%s qty=%s unit=%s price=%s cost/small=%s cost/kg=%s\n",
                $row->receive_date,
                $row->gr_number ?? $row->gr_id,
                $row->nama_outlet ?? '-',
                money($row->qty_received),
                unitName($row->unit_id),
                money($row->price),
                money($costSmall),
                money(toKgCost($item, $costSmall))
            );
        }
        if ($gros->isEmpty()) {
            echo "(kosong last 20)\n";
        }

        $grosAll = DB::table('good_receive_outlet_supplier_items as gri')
            ->join('good_receive_outlet_suppliers as gr', 'gr.id', '=', 'gri.good_receive_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'gr.outlet_id')
            ->where('gri.item_id', $itemId)
            ->where('gr.receive_date', '>=', '2026-01-01')
            ->get($grosSelect);
        echo "\n=== ANOMALI GROS 2026 ===\n";
        $gAnom = 0;
        foreach ($grosAll as $row) {
            $costSmall = toSmallCost($item, (float) $row->price, $row->unit_id ? (int) $row->unit_id : null);
            if (! ($costSmall > 1000 || ($costSmall > 0 && $costSmall < 5))) {
                continue;
            }
            $gAnom++;
            echo sprintf(
                "ANOMALI %s %s outlet=%s qty=%s unit=%s price=%s cost/small=%s cost/kg=%s\n",
                $row->receive_date,
                $row->gr_number ?? $row->gr_id,
                $row->nama_outlet ?? '-',
                money($row->qty_received),
                unitName($row->unit_id),
                money($row->price),
                money($costSmall),
                money(toKgCost($item, $costSmall))
            );
        }
        if ($gAnom === 0) {
            echo "(tidak ada)\n";
        }
    }

    echo "\n=== Kartu stok MK (warehouse 5) last 10 + min/max cost ===\n";
    $inv = DB::table('food_inventory_items')->where('item_id', $itemId)->first();
    if ($inv && Schema::hasTable('food_inventory_cards')) {
        $cards = DB::table('food_inventory_cards')
            ->where('inventory_item_id', $inv->id)
            ->where('warehouse_id', 5)
            ->orderByDesc('id')
            ->limit(10)
            ->get();
            foreach ($cards as $c) {
            echo sprintf(
                "id=%s date=%s type=%s in=%s cost/small=%s qty=%s val=%s\n",
                $c->id,
                $c->date ?? '-',
                $c->reference_type ?? '-',
                money($c->in_qty_small ?? 0),
                money($c->cost_per_small ?? 0),
                money($c->saldo_qty_small ?? 0),
                money($c->saldo_value ?? 0)
            );
        }

        if (Schema::hasTable('food_inventory_cost_histories')) {
            $stats = DB::table('food_inventory_cost_histories')
                ->where('inventory_item_id', $inv->id)
                ->where('warehouse_id', 5)
                ->where('date', '>=', '2026-01-01')
                ->selectRaw('min(mac) as min_mac, max(mac) as max_mac, avg(mac) as avg_mac, min(new_cost) as min_cost, max(new_cost) as max_cost')
                ->first();
            if ($stats) {
                echo sprintf(
                    "MAC 2026 WH5: min=%s avg=%s max=%s | new_cost min=%s max=%s\n",
                    money($stats->min_mac),
                    money($stats->avg_mac),
                    money($stats->max_mac),
                    money($stats->min_cost),
                    money($stats->max_cost)
                );
            }
            $costAnom = DB::table('food_inventory_cost_histories')
                ->where('inventory_item_id', $inv->id)
                ->where('warehouse_id', 5)
                ->where('date', '>=', '2026-01-01')
                ->where(function ($q) {
                    $q->where('new_cost', '>', 1000)->orWhere('mac', '>', 1000)->orWhere('new_cost', '<', 5);
                })
                ->orderByDesc('date')
                ->limit(20)
                ->get();
            echo "\n=== Cost history anomali WH5 2026 (new_cost/mac >1000 atau <5) ===\n";
            foreach ($costAnom as $c) {
                echo sprintf(
                    "%s type=%s new_cost=%s mac=%s ref=%s#%s\n",
                    $c->date,
                    $c->type ?? '-',
                    money($c->new_cost),
                    money($c->mac),
                    $c->reference_type ?? '-',
                    $c->reference_id ?? '-'
                );
            }
            if ($costAnom->isEmpty()) {
                echo "(tidak ada)\n";
            }
        }
    } else {
        echo "inventory item / cards tidak ada\n";
    }
}

echo "\nDONE\n";
