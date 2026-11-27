<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$outletId = 18;
$whId = 41;
$itemId = 52984;
$invItemId = 5080;

echo "=== 1) Stock row outlet_food_inventory_stocks for tenderloin ===\n";
$stock = DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->first();
echo json_encode($stock, JSON_PRETTY_PRINT) . "\n";

echo "\n=== 2) Soft-delete columns on cards / details? ===\n";
foreach (['outlet_food_inventory_cards', 'stock_cut_details', 'stock_cut_logs', 'outlet_food_inventory_stocks'] as $t) {
    $cols = Schema::getColumnListing($t);
    $soft = array_values(array_filter($cols, fn ($c) => stripos($c, 'delete') !== false || $c === 'deleted_at'));
    echo "{$t}: soft=" . json_encode($soft) . "\n";
}

echo "\n=== 3) Activity logs CREATE/UPDATE around stock cut outlet 18 food 19 Nov ===\n";
$acts = DB::table('activity_logs')
    ->where('module', 'stock_cut')
    ->whereBetween('created_at', ['2026-11-19 21:50:00', '2026-11-19 23:00:00'])
    ->where(function ($q) {
        $q->where('description', 'like', '%outlet 18%')
            ->orWhere('old_data', 'like', '%"outlet_id": 18%')
            ->orWhere('old_data', 'like', '%"outlet_id":18%')
            ->orWhere('new_data', 'like', '%"outlet_id": 18%')
            ->orWhere('new_data', 'like', '%"outlet_id":18%')
            ->orWhere('description', 'like', '%12015%')
            ->orWhere('description', 'like', '%12032%');
    })
    ->orderBy('id')
    ->get();
foreach ($acts as $a) {
    echo "{$a->id} {$a->created_at} user={$a->user_id} {$a->activity_type} :: {$a->description}\n";
    echo "  old={$a->old_data}\n";
    echo "  new={$a->new_data}\n\n";
}

echo "\n=== 4) All activity_logs mentioning 12015 anywhere ===\n";
$a12015 = DB::table('activity_logs')
    ->where(function ($q) {
        $q->where('description', 'like', '%12015%')
            ->orWhere('old_data', 'like', '%12015%')
            ->orWhere('new_data', 'like', '%12015%');
    })
    ->orderBy('id')
    ->get();
echo "hits=" . $a12015->count() . "\n";
foreach ($a12015 as $a) {
    echo "{$a->created_at} module={$a->module} {$a->activity_type} :: {$a->description}\n";
}

echo "\n=== 5) Card chain tenderloin WH41 around gap ===\n";
$cards = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereBetween('created_at', ['2026-11-18 20:00:00', '2026-11-20 02:00:00'])
    ->orderBy('id')
    ->get([
        'id', 'date', 'created_at', 'reference_type', 'reference_id', 'stock_cut_log_id',
        'in_qty_small', 'out_qty_small', 'saldo_qty_small', 'description',
    ]);
foreach ($cards as $c) {
    echo sprintf(
        "id=%d created=%s ref=%s#%s log=%s in=%s out=%s saldo=%s | %s\n",
        $c->id,
        $c->created_at,
        $c->reference_type,
        $c->reference_id,
        $c->stock_cut_log_id,
        $c->in_qty_small,
        $c->out_qty_small,
        $c->saldo_qty_small,
        substr((string) $c->description, 0, 60)
    );
}

echo "\n=== 6) Detail for tenderloin on surviving food cut 12032 ===\n";
$d = DB::table('stock_cut_details')
    ->where('stock_cut_log_id', 12032)
    ->where('item_id', $itemId)
    ->first();
echo json_encode($d, JSON_PRETTY_PRINT) . "\n";

$cutCard = DB::table('outlet_food_inventory_cards')
    ->where('stock_cut_log_id', 12032)
    ->where('inventory_item_id', $invItemId)
    ->first();
echo "cut card: " . json_encode($cutCard) . "\n";

echo "\n=== 7) Drift signature at cut 12032: stock_before vs last card saldo ===\n";
$details = DB::table('stock_cut_details as d')
    ->join('items as i', 'i.id', '=', 'd.item_id')
    ->where('d.stock_cut_log_id', 12032)
    ->where('d.warehouse_outlet_id', $whId)
    ->select('d.*', 'i.name')
    ->get();

$negExactNeed = [];
$negOther = [];
$pos = [];
$ok = 0;
foreach ($details as $row) {
    $prev = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $row->inventory_item_id)
        ->where('id_outlet', $outletId)
        ->where('warehouse_outlet_id', $whId)
        ->where(function ($q) use ($row) {
            // cards that existed before this cut's cards: either created before cut start or not belonging to 12032
            $q->where('created_at', '<', '2026-11-19 22:35:59')
                ->orWhere(function ($q2) {
                    $q2->whereNull('stock_cut_log_id')->orWhere('stock_cut_log_id', '!=', 12032);
                });
        })
        ->where(function ($q) {
            $q->whereNull('stock_cut_log_id')->orWhere('stock_cut_log_id', '!=', 12032);
        })
        ->orderByDesc('id')
        ->first();

    if (!$prev) {
        continue;
    }
    $drift = (float) $row->stock_before_small - (float) $prev->saldo_qty_small;
    $need = (float) $row->qty_small;
    if (abs($drift) < 0.01) {
        $ok++;
        continue;
    }
    $entry = [
        'name' => $row->name,
        'item_id' => $row->item_id,
        'inv' => $row->inventory_item_id,
        'stock_before' => (float) $row->stock_before_small,
        'card_saldo' => (float) $prev->saldo_qty_small,
        'drift' => $drift,
        'needed' => $need,
        'prev_card' => $prev->id,
        'prev_ref' => $prev->reference_type,
        'exact_neg_need' => abs($drift + $need) < 0.01,
    ];
    if ($drift < -0.01) {
        if ($entry['exact_neg_need']) {
            $negExactNeed[] = $entry;
        } else {
            $negOther[] = $entry;
        }
    } else {
        $pos[] = $entry;
    }
}

echo "ok_match={$ok} neg_exact_need=" . count($negExactNeed) . " neg_other=" . count($negOther) . " pos=" . count($pos) . "\n";
echo "-- tenderloin and top negative drifts --\n";
$allNeg = array_merge($negExactNeed, $negOther);
usort($allNeg, fn ($a, $b) => abs($b['drift']) <=> abs($a['drift']));
foreach (array_slice($allNeg, 0, 40) as $r) {
    echo sprintf(
        "  %s(%d) stock=%s card=%s drift=%s need=%s exact_neg_need=%s prev=#%d/%s\n",
        $r['name'],
        $r['item_id'],
        $r['stock_before'],
        $r['card_saldo'],
        $r['drift'],
        $r['needed'],
        $r['exact_neg_need'] ? 'Y' : 'N',
        $r['prev_card'],
        $r['prev_ref']
    );
}
echo "-- top positive drifts --\n";
usort($pos, fn ($a, $b) => abs($b['drift']) <=> abs($a['drift']));
foreach (array_slice($pos, 0, 15) as $r) {
    echo sprintf(
        "  %s(%d) stock=%s card=%s drift=%s need=%s prev=#%d/%s\n",
        $r['name'],
        $r['item_id'],
        $r['stock_before'],
        $r['card_saldo'],
        $r['drift'],
        $r['needed'],
        $r['prev_card'],
        $r['prev_ref']
    );
}

echo "\n=== 8) Transfers / waste / CIU involving item 52984 on 19 Nov ===\n";
$xferTables = [
    'outlet_food_transfers',
    'outlet_transfers',
    'warehouse_outlet_transfers',
    'outlet_food_inventory_transfers',
];
foreach ($xferTables as $t) {
    if (Schema::hasTable($t)) {
        echo "has {$t} cols=" . implode(',', array_slice(Schema::getColumnListing($t), 0, 15)) . "\n";
    }
}

// cards any warehouse for this item 19 Nov
$anyCards = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->whereDate('date', '2026-11-19')
    ->orderBy('id')
    ->get(['id', 'warehouse_outlet_id', 'created_at', 'reference_type', 'reference_id', 'in_qty_small', 'out_qty_small', 'saldo_qty_small', 'stock_cut_log_id']);
echo "all WH cards 19 Nov for tenderloin:\n";
foreach ($anyCards as $c) {
    echo json_encode($c) . "\n";
}

$waste = DB::table('outlet_internal_use_waste_details as d')
    ->join('outlet_internal_use_waste_headers as h', 'h.id', '=', 'd.header_id')
    ->where('h.outlet_id', $outletId)
    ->whereDate('h.date', '2026-11-19')
    ->where('d.item_id', $itemId)
    ->get();
echo "waste lines tenderloin: " . $waste->count() . "\n";

echo "\n=== 9) Engineering BOM need 19 Nov = cut detail qty? ===\n";
$qr = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('qr_code');
$parents = DB::table('item_bom as b')
    ->join('items as i', 'i.id', '=', 'b.item_id')
    ->where('b.material_item_id', $itemId)
    ->where(function ($q) {
        $q->whereNull('b.stock_cut')->orWhere('b.stock_cut', 0);
    })
    ->get(['b.item_id', 'b.qty', 'i.name', 'i.type']);
$totalNeed = 0;
foreach ($parents as $p) {
    if (!in_array($p->type, ['Food Asian', 'Food Western', 'Food'], true)) {
        continue;
    }
    $sold = (float) DB::table('order_items')
        ->where('kode_outlet', $qr)
        ->whereDate('created_at', '2026-11-19')
        ->where('item_id', $p->item_id)
        ->sum('qty');
    $need = $sold * (float) $p->qty;
    $totalNeed += $need;
    if ($sold > 0) {
        echo sprintf("  %s sold=%s bom=%s need=%s\n", $p->name, $sold, $p->qty, $need);
    }
}
echo "TOTAL food BOM need grams={$totalNeed} pcs=" . ($totalNeed / 150) . "\n";
echo "detail qty_small=" . ($d->qty_small ?? 'null') . "\n";

echo "\n=== 10) ID gap proof: 12015 deleted; neighbors timestamps ===\n";
$neighbors = DB::table('stock_cut_logs')->whereBetween('id', [12014, 12016])->orderBy('id')->get();
foreach ($neighbors as $l) {
    echo json_encode($l) . "\n";
}
$exists = DB::table('stock_cut_logs')->where('id', 12015)->exists();
echo "12015 exists? " . ($exists ? 'YES' : 'NO') . "\n";

echo "\n=== 11) Laravel log files mentioning 12015 / tenderloin / outlet 18 potong ===\n";
$logDir = storage_path('logs');
$files = glob($logDir . '/laravel*.log');
rsort($files);
$checked = 0;
foreach (array_slice($files, 0, 30) as $f) {
    $checked++;
    $fh = fopen($f, 'r');
    if (!$fh) {
        continue;
    }
    $lineNo = 0;
    $hits = 0;
    while (($line = fgets($fh)) !== false) {
        $lineNo++;
        if (
            strpos($line, '12015') !== false
            || (strpos($line, 'Potong Stock') !== false && strpos($line, '52984') !== false)
            || (strpos($line, 'inventory_item_id') !== false && strpos($line, '5080') !== false && strpos($line, '2026-11-19') !== false)
        ) {
            // date filter rough
            if (strpos($line, '2026-11-19') === false && strpos($line, '12015') === false) {
                continue;
            }
            $hits++;
            if ($hits <= 8) {
                echo basename($f) . ":{$lineNo} " . substr(trim($line), 0, 300) . "\n";
            }
        }
    }
    fclose($fh);
    if ($hits > 0) {
        echo "  ... total hits in " . basename($f) . "={$hits}\n";
    }
}
echo "checked_log_files={$checked}\n";

echo "\nDONE\n";
