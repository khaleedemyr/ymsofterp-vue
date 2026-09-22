<?php
/**
 * Repair WIP finished-goods cards: value_in was incorrectly posted as qty × blended MAC
 * instead of batch material cost (Σ value_out). Makes Cost Bahan = Barang Jadi per production.
 *
 * Does NOT change saldo_value / stock MAC (those correctly use WAC).
 *
 * Safety (default): only repair "typical WAC drift" headers —
 *   |mat-fin|/max(mat,fin) <= 25% AND max(mat,fin) <= 100jt
 * Extreme/poisoned productions (cost meledak) di-skip — butuh repair cost terpisah.
 * Use --aggressive to equalize semua header tanpa filter.
 *
 * Usage:
 *   php scripts/repair_wip_value_in_batch_cost.php --dry-run
 *   php scripts/repair_wip_value_in_batch_cost.php
 *   php scripts/repair_wip_value_in_batch_cost.php --outlet=23 --from=2026-09-01
 *   php scripts/repair_wip_value_in_batch_cost.php --aggressive --dry-run
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$aggressive = in_array('--aggressive', $argv ?? [], true);
$outletOpt = null;
$from = null;
$to = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--outlet=')) {
        $outletOpt = (int) substr($arg, 9);
    }
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, 7);
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
    }
}

const MAX_RATIO = 0.25;          // |gap|/max <= 25%
const MAX_PRODUCTION_VALUE = 100_000_000.0; // skip absurd single production

$fmt = static fn (float $n): string => number_format($n, 2, '.', ',');

echo "=== Repair WIP value_in → batch material cost ===\n";
echo 'Mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo 'Filter: ' . ($aggressive ? 'AGGRESSIVE (all unequal)' : 'SAFE (WAC drift only, ratio<=25%, max<=100jt)') . "\n";
echo 'Outlet: ' . ($outletOpt ?: 'ALL') . "\n";
echo 'From: ' . ($from ?: '(all)') . '  To: ' . ($to ?: '(all)') . "\n\n";

$base = DB::table('outlet_food_inventory_cards')
    ->where('reference_type', 'outlet_wip_production');
if ($outletOpt) {
    $base->where('id_outlet', $outletOpt);
}
if ($from) {
    $base->whereDate('date', '>=', $from);
}
if ($to) {
    $base->whereDate('date', '<=', $to);
}

$headers = (clone $base)
    ->groupBy('id_outlet', 'warehouse_outlet_id', 'reference_id')
    ->selectRaw('
        id_outlet,
        warehouse_outlet_id,
        reference_id,
        SUM(COALESCE(value_out,0)) as mat,
        SUM(COALESCE(value_in,0)) as fin
    ')
    ->havingRaw('ABS(SUM(COALESCE(value_out,0)) - SUM(COALESCE(value_in,0))) >= 0.5')
    ->get();

echo "Headers with |mat-fin|>=0.5: {$headers->count()}\n";

if ($headers->isEmpty()) {
    echo "Nothing to repair.\n";
    exit(0);
}

// Index headers
$headerMap = [];
$skippedExtreme = 0;
$sumAbsGapSkipped = 0.0;
foreach ($headers as $h) {
    $mat = (float) $h->mat;
    $fin = (float) $h->fin;
    $mx = max(abs($mat), abs($fin));
    $gap = abs($mat - $fin);
    if (! $aggressive) {
        $ratio = $mx > 0 ? ($gap / $mx) : 0.0;
        if ($ratio > MAX_RATIO || $mx > MAX_PRODUCTION_VALUE) {
            $skippedExtreme++;
            $sumAbsGapSkipped += $gap;
            continue;
        }
    }
    $key = $h->id_outlet.'|'.$h->warehouse_outlet_id.'|'.$h->reference_id;
    $headerMap[$key] = $h;
}
echo "Headers eligible after filter: ".count($headerMap)." (skipped extreme: {$skippedExtreme}, Σ|gap| skipped=".$fmt($sumAbsGapSkipped).")\n";

if ($headerMap === []) {
    echo "Nothing to repair after filter.\n";
    exit(0);
}

// Load FG cards once (filter to header keys in PHP)
$outletIds = $headers->pluck('id_outlet')->unique()->all();
echo "Loading FG cards for ".count($outletIds)." outlets...\n";
$finQuery = DB::table('outlet_food_inventory_cards')
    ->where('reference_type', 'outlet_wip_production')
    ->where('value_in', '>', 0)
    ->whereIn('id_outlet', $outletIds);
if ($from) {
    $finQuery->whereDate('date', '>=', $from);
}
if ($to) {
    $finQuery->whereDate('date', '<=', $to);
}
$allFin = $finQuery->get([
    'id', 'id_outlet', 'warehouse_outlet_id', 'reference_id',
    'value_in', 'in_qty_small', 'cost_per_small', 'cost_per_medium', 'cost_per_large',
]);
echo "FG cards loaded: {$allFin->count()}\n";

$finByHeader = [];
foreach ($allFin as $row) {
    $key = $row->id_outlet.'|'.$row->warehouse_outlet_id.'|'.$row->reference_id;
    if (! isset($headerMap[$key])) {
        continue;
    }
    $finByHeader[$key][] = $row;
}
echo "FG cards matched to unequal headers: ".array_sum(array_map('count', $finByHeader))."\n";

$updates = []; // id => [value_in, cost_per_small, cost_per_medium, cost_per_large]
$sumAbsGapBefore = 0.0;
$byOutlet = [];
$skipped = 0;

foreach ($headerMap as $key => $h) {
    $mat = (float) $h->mat;
    $fin = (float) $h->fin;
    $sumAbsGapBefore += abs($mat - $fin);
    $outletId = (int) $h->id_outlet;

    if (! isset($byOutlet[$outletId])) {
        $byOutlet[$outletId] = ['headers' => 0, 'cards' => 0, 'gap' => 0.0];
    }
    $byOutlet[$outletId]['headers']++;
    $byOutlet[$outletId]['gap'] += abs($mat - $fin);

    $finRows = $finByHeader[$key] ?? [];
    if ($finRows === []) {
        $skipped++;
        continue;
    }

    $sumFin = 0.0;
    foreach ($finRows as $row) {
        $sumFin += (float) $row->value_in;
    }

    $n = count($finRows);
    $allocated = 0.0;
    foreach ($finRows as $i => $row) {
        if ($n === 1) {
            $newVin = round($mat, 2);
        } elseif ($sumFin > 0) {
            if ($i === $n - 1) {
                $newVin = round($mat - $allocated, 2);
            } else {
                $newVin = round($mat * ((float) $row->value_in / $sumFin), 2);
                $allocated += $newVin;
            }
        } else {
            $newVin = ($i === 0) ? round($mat, 2) : 0.0;
        }

        $oldVin = round((float) $row->value_in, 2);
        if (abs($newVin - $oldVin) < 0.005) {
            continue;
        }

        $qty = (float) $row->in_qty_small;
        $newCps = $qty > 0.0000001 ? ($newVin / $qty) : 0.0;
        $oldCps = (float) $row->cost_per_small;
        $ratioM = ($oldCps > 0 && (float) $row->cost_per_medium > 0)
            ? ((float) $row->cost_per_medium / $oldCps)
            : 1.0;
        $ratioL = ($oldCps > 0 && (float) $row->cost_per_large > 0)
            ? ((float) $row->cost_per_large / $oldCps)
            : 1.0;

        $updates[(int) $row->id] = [
            'value_in' => $newVin,
            'cost_per_small' => $newCps,
            'cost_per_medium' => $newCps * $ratioM,
            'cost_per_large' => $newCps * $ratioL,
        ];
        $byOutlet[$outletId]['cards']++;
    }
}

echo 'FG cards to update: '.count($updates)."\n";
echo 'Skipped (no FG card): '.$skipped."\n";
echo 'Σ|mat-fin| before: '.$fmt($sumAbsGapBefore)."\n\n";

echo "=== Top outlets by |gap| ===\n";
$names = DB::table('tbl_data_outlet')->whereIn('id_outlet', array_keys($byOutlet))->pluck('nama_outlet', 'id_outlet');
uasort($byOutlet, fn ($a, $b) => $b['gap'] <=> $a['gap']);
$n = 0;
foreach ($byOutlet as $oid => $info) {
    if ($n++ >= 15) {
        break;
    }
    echo sprintf(
        "  #%d %-40s headers=%5d cards=%5d |gap|=%s\n",
        $oid,
        mb_substr((string) ($names[$oid] ?? $oid), 0, 40),
        $info['headers'],
        $info['cards'],
        $fmt($info['gap'])
    );
}

if ($dryRun) {
    echo "\nDRY-RUN done. Re-run without --dry-run to apply ".count($updates)." card updates.\n";
    exit(0);
}

echo "\nApplying value_in (+ cost_per_small) updates...\n";
$ids = array_keys($updates);
$batch = 100;
$done = 0;
for ($i = 0; $i < count($ids); $i += $batch) {
    $slice = array_slice($ids, $i, $batch);
    $vinCases = [];
    $cpsCases = [];
    $idList = [];
    foreach ($slice as $id) {
        $u = $updates[$id];
        $idList[] = $id;
        $vinCases[] = 'WHEN '.$id.' THEN '.sprintf('%.4F', $u['value_in']);
        $cpsCases[] = 'WHEN '.$id.' THEN '.sprintf('%.8F', $u['cost_per_small']);
    }
    $idCsv = implode(',', $idList);
    $sql = 'UPDATE outlet_food_inventory_cards SET '
        .'value_in = CASE id '.implode(' ', $vinCases).' END, '
        .'cost_per_small = CASE id '.implode(' ', $cpsCases).' END, '
        ."updated_at = NOW() WHERE id IN ({$idCsv})";

    $attempts = 0;
    while (true) {
        try {
            DB::statement($sql);
            break;
        } catch (\Throwable $e) {
            $attempts++;
            if ($attempts >= 5 || ! str_contains($e->getMessage(), 'Lock wait timeout')) {
                throw $e;
            }
            echo "  lock timeout at {$done}, retry {$attempts}...\n";
            usleep(500_000 * $attempts);
        }
    }
    $done += count($slice);
    if ($done % 2000 === 0 || $done >= count($ids)) {
        echo "  updated {$done}/".count($ids)."\n";
    }
}

$leftQ = DB::table('outlet_food_inventory_cards')
    ->where('reference_type', 'outlet_wip_production');
if ($outletOpt) {
    $leftQ->where('id_outlet', $outletOpt);
}
if ($from) {
    $leftQ->whereDate('date', '>=', $from);
}
if ($to) {
    $leftQ->whereDate('date', '<=', $to);
}
$left = $leftQ
    ->groupBy('id_outlet', 'warehouse_outlet_id', 'reference_id')
    ->selectRaw('id_outlet, warehouse_outlet_id, reference_id, SUM(COALESCE(value_out,0)) mat, SUM(COALESCE(value_in,0)) fin')
    ->havingRaw('ABS(SUM(COALESCE(value_out,0)) - SUM(COALESCE(value_in,0))) >= 0.5')
    ->get()
    ->count();

echo "\n=== DONE ===\n";
echo 'Cards fixed: '.count($updates)."\n";
echo "Remaining unequal headers (safe-eligible window): {$left}\n";
