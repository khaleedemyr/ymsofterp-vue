<?php

/**
 * Scan Main Store stock-card drift from 2026-09-16 for steak/dairy/seafood items.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fmt = fn ($n) => number_format((float) $n, 3, '.', '');
$whId = 1;
$fromDate = '2026-09-16';

$queries = [
    ['key' => 'rib eye blue label 250gr', 'selisih_note' => '5 pcs', 'patterns' => ['%rib eye%blue%250%', '%Rib Eye%Blue%250%']],
    ['key' => 'sirloin aussie 150gr', 'selisih_note' => '10 pcs', 'patterns' => ['%sirloin%aussie%150%', '%Sirloin%Aussie%150%']],
    ['key' => 'sirloin aussie 250gr', 'selisih_note' => '5 pcs', 'patterns' => ['%sirloin%aussie%250%', '%Sirloin%Aussie%250%']],
    ['key' => 'sirloin blue label', 'selisih_note' => '10 pcs', 'patterns' => ['%sirloin%blue%label%', '%Sirloin%Blue%Label%']],
    ['key' => 'stroganof', 'selisih_note' => '1 pck', 'patterns' => ['%stroganof%', '%Stroganof%', '%stroganoff%']],
    ['key' => 'tenderloin aussie 150gr', 'selisih_note' => '10 pcs', 'patterns' => ['%tenderloin%aussie%150%', '%Tenderloin%Aussie%150%']],
    ['key' => 'tenderloin blue label', 'selisih_note' => '10 pcs', 'patterns' => ['%tenderloin%blue%label%', '%Tenderloin%Blue%Label%']],
    ['key' => 'tenderloin meltique 250gr', 'selisih_note' => '4 pcs', 'patterns' => ['%tenderloin%meltique%250%', '%Tenderloin%Meltique%250%']],
    ['key' => 'butter salted', 'selisih_note' => '5 pck', 'patterns' => ['%butter%salted%', '%Butter%Salted%']],
    ['key' => 'chicken breast', 'selisih_note' => '2 kg', 'patterns' => ['%chicken breast%', '%Chicken Breast%']],
    ['key' => 'chicken leg skin', 'selisih_note' => '1 kg', 'patterns' => ['%chicken leg%skin%', '%Chicken Leg%Skin%']],
    ['key' => 'cooking cream', 'selisih_note' => '3 pck', 'patterns' => ['%cooking cream%', '%Cooking Cream%']],
    ['key' => 'cooking cream gourmant', 'selisih_note' => '2 pcs CLEAR', 'patterns' => ['%cooking cream%gourmant%', '%Cooking Cream%Gourmant%', '%Gourmant%']],
    ['key' => 'freshmilk gf', 'selisih_note' => '2 pck', 'patterns' => ['%fresh%milk%gf%', '%Freshmilk%GF%', '%Fresh Milk%GF%']],
    ['key' => 'ikan cumi', 'selisih_note' => '1 kg', 'patterns' => ['%cumi%', '%Cumi%']],
    ['key' => 'ikan dory', 'selisih_note' => '2 kg', 'patterns' => ['%dory%', '%Dory%']],
    ['key' => 'salmon fillet', 'selisih_note' => '2 pcs', 'patterns' => ['%salmon%fillet%', '%Salmon%Fillet%']],
    ['key' => 'salmon steak', 'selisih_note' => '6 pcs', 'patterns' => ['%salmon%steak%', '%Salmon%Steak%']],
    ['key' => 'straightcut', 'selisih_note' => '2 pck', 'patterns' => ['%straight%cut%', '%Straightcut%', '%Straight Cut%']],
    ['key' => 'sosis', 'selisih_note' => '1 pck', 'patterns' => ['%sosis%', '%Sosis%', '%sausage%']],
];

function findItems(array $patterns)
{
    $q = DB::table('items')->select('id', 'name', 'sku');
    $q->where(function ($w) use ($patterns) {
        foreach ($patterns as $p) {
            $w->orWhere('name', 'like', $p);
        }
    });

    return $q->orderBy('name')->limit(30)->get();
}

function analyzeItem(int $itemId, int $invId, int $whId, string $fromDate): array
{
    $fmt = fn ($n) => number_format((float) $n, 3, '.', '');

    $stock = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->first();

    $prev = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->whereDate('date', '<', $fromDate)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();

    $running = $prev ? (float) $prev->saldo_qty_small : 0.0;
    $cards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->whereDate('date', '>=', $fromDate)
        ->orderBy('date')
        ->orderBy('id')
        ->get();

    $firstBad = null;
    $badCount = 0;
    $do37 = [];
    foreach ($cards as $c) {
        $expected = $running + (float) $c->in_qty_small - (float) $c->out_qty_small;
        $actual = (float) $c->saldo_qty_small;
        $delta = $actual - $expected;
        if (abs($delta) > 0.01) {
            $badCount++;
            if (! $firstBad) {
                $firstBad = [
                    'id' => $c->id,
                    'date' => $c->date,
                    'out' => (float) $c->out_qty_small,
                    'expected' => $expected,
                    'actual' => $actual,
                    'delta' => $delta,
                    'desc' => $c->description,
                ];
            }
        }
        if (stripos((string) $c->description, 'DO2609160037') !== false) {
            $do37[] = [
                'id' => $c->id,
                'ref' => $c->reference_id,
                'out_s' => (float) $c->out_qty_small,
                'out_m' => (float) $c->out_qty_medium,
                'out_l' => (float) $c->out_qty_large,
                'saldo_s' => (float) $c->saldo_qty_small,
                'saldo_m' => (float) $c->saldo_qty_medium,
                'desc' => $c->description,
                'created' => $c->created_at,
            ];
        }
        $running = $expected; // continue correct chain
    }

    $last = $cards->last();
    $endCorrect = $running;
    $stockSmall = $stock ? (float) $stock->qty_small : null;
    $lastSaldo = $last ? (float) $last->saldo_qty_small : null;
    $driftStockVsCorrect = ($stockSmall !== null) ? ($stockSmall - $endCorrect) : null;
    $driftCardVsCorrect = ($lastSaldo !== null) ? ($lastSaldo - $endCorrect) : null;

    // Festival lost-update estimate from DO37 pair
    $lost = null;
    if (count($do37) >= 2) {
        $a = $do37[0];
        $b = $do37[1];
        // expected second saldo = first_saldo_correct? use outs: if same saldo race
        $sameSaldo = abs($a['saldo_s'] - $b['saldo_s']) < 0.01;
        // shortfall on second card vs sequential
        $shouldSecond = $a['saldo_s'] - $b['out_s']; // if first was correct
        $shortfall = $b['saldo_s'] - $shouldSecond;
        $lost = [
            'same_saldo' => $sameSaldo,
            'shortfall_small' => $shortfall,
            'festival_out_s' => $b['out_s'],
            'festival_out_m' => $b['out_m'],
            'festival_out_l' => $b['out_l'],
            'jawa_out_s' => $a['out_s'],
            'jawa_out_m' => $a['out_m'],
            'jawa_out_l' => $a['out_l'],
        ];
    }

    return [
        'stock' => $stock,
        'bad_count' => $badCount,
        'first_bad' => $firstBad,
        'do37' => $do37,
        'end_correct' => $endCorrect,
        'stock_small' => $stockSmall,
        'last_saldo' => $lastSaldo,
        'drift_stock' => $driftStockVsCorrect,
        'drift_card' => $driftCardVsCorrect,
        'lost' => $lost,
        'last_id' => $last->id ?? null,
    ];
}

echo "===== RESOLVE + ANALYZE =====\n";
$targets = [];

foreach ($queries as $q) {
    echo "\n## {$q['key']} (reported {$q['selisih_note']})\n";
    $items = findItems($q['patterns']);
    if ($items->isEmpty()) {
        echo "  NOT FOUND\n";
        continue;
    }
    foreach ($items as $it) {
        $inv = DB::table('food_inventory_items')->where('item_id', $it->id)->first();
        if (! $inv) {
            echo "  item={$it->id} {$it->name} — no inventory item\n";
            continue;
        }
        $a = analyzeItem((int) $it->id, (int) $inv->id, $whId, $fromDate);
        $drift = $a['drift_stock'];
        $flag = ($drift !== null && abs($drift) > 0.01) || $a['bad_count'] > 0 ? 'NEEDS_FIX' : 'OK';
        echo "  [{$flag}] item={$it->id} inv={$inv->id} {$it->name}\n";
        echo "    stock={$fmt($a['stock_small'] ?? 0)} last_card={$fmt($a['last_saldo'] ?? 0)} correct_end={$fmt($a['end_correct'])}\n";
        echo "    drift_stock={$fmt($a['drift_stock'] ?? 0)} bad_cards={$a['bad_count']} do37_cards=".count($a['do37'])."\n";
        if ($a['lost']) {
            $l = $a['lost'];
            echo "    DO37 same_saldo=".($l['same_saldo'] ? 'Y' : 'N')." shortfall_s={$fmt($l['shortfall_small'])} fest_out={$fmt($l['festival_out_s'])}/{$fmt($l['festival_out_m'])}\n";
        }
        if ($a['first_bad']) {
            $fb = $a['first_bad'];
            echo "    first_bad id={$fb['id']} delta={$fmt($fb['delta'])} out={$fmt($fb['out'])} ".substr((string) $fb['desc'], 0, 70)."\n";
        }
        foreach ($a['do37'] as $c) {
            echo "    DO37 card={$c['id']} ref={$c['ref']} out_s={$fmt($c['out_s'])} saldo_s={$fmt($c['saldo_s'])}\n";
        }

        if ($flag === 'NEEDS_FIX') {
            $targets[] = [
                'key' => $q['key'],
                'reported' => $q['selisih_note'],
                'item_id' => (int) $it->id,
                'inv_id' => (int) $inv->id,
                'name' => $it->name,
                'drift_stock' => $a['drift_stock'],
                'lost' => $a['lost'],
                'do37' => $a['do37'],
                'bad_count' => $a['bad_count'],
            ];
        }
    }
}

echo "\n===== SUMMARY NEEDS_FIX (".count($targets).") =====\n";
foreach ($targets as $t) {
    $lostS = $t['lost']['shortfall_small'] ?? $t['drift_stock'];
    echo sprintf(
        "- %s | %s | item=%d inv=%d | drift=%s | do37_shortfall=%s | reported=%s\n",
        $t['key'],
        $t['name'],
        $t['item_id'],
        $t['inv_id'],
        $fmt($t['drift_stock'] ?? 0),
        $fmt($lostS ?? 0),
        $t['reported']
    );
}

// write json for fix script
file_put_contents(__DIR__.'/sep16_fix_targets.json', json_encode($targets, JSON_PRETTY_PRINT));
echo "\nWrote scripts/sep16_fix_targets.json\n";
