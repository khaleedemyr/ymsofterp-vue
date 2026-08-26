<?php

declare(strict_types=1);

/**
 * Remediasi SN yang keluar di 2 DO: SN asli tetap di outlet yang sudah GSR,
 * DO yang belum GR diganti SN baru (append "2") + row inventory_item_serials baru.
 *
 * Usage:
 *   php scripts/fix_duplicate_do_serials_append2.php
 *   php scripts/fix_duplicate_do_serials_append2.php --apply
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv ?? [], true);
$suffix = '2';

$serials = [
    'F2608201203IXCH',
    'F2608201203OMLU',
    'F2608201203SU7Z',
    'F2608201203UAY0',
    'F2608201203YYW0',
];

echo "=== Fix duplicate DO serials (append {$suffix}) ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n\n";

$rows = DB::table('inventory_item_serials as s')
    ->leftJoin('delivery_orders as d', 'd.id', '=', 's.out_delivery_order_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 's.out_outlet_id')
    ->whereIn('s.serial_number', $serials)
    ->select(
        's.*',
        'd.number as out_do_number',
        'o.nama_outlet as out_outlet_name'
    )
    ->get()
    ->keyBy('serial_number');

if ($rows->count() !== count($serials)) {
    $missing = array_diff($serials, $rows->keys()->all());
    echo "SN tidak ditemukan: " . implode(', ', $missing) . "\n";
    exit(1);
}

$movements = DB::table('inventory_serial_movements')
    ->whereIn('serial_number', $serials)
    ->where('movement_type', 'out')
    ->orderBy('id')
    ->get()
    ->groupBy('serial_number');

$doIds = $movements->flatten(1)->pluck('delivery_order_id')->unique()->filter()->values()->all();
if (empty($doIds)) {
    echo "Tidak ada movement out untuk SN tersebut.\n";
    exit(1);
}

$doNumbers = DB::table('delivery_orders')->whereIn('id', $doIds)->pluck('number', 'id');

$doiByDoItem = DB::table('delivery_order_items')
    ->whereIn('delivery_order_id', $doIds)
    ->whereNotNull('serial_numbers')
    ->get()
    ->keyBy(fn ($r) => $r->delivery_order_id . ':' . $r->item_id);

$plan = [];

foreach ($serials as $sn) {
    $serial = $rows->get($sn);
    $newSn = $sn . $suffix;

    if (DB::table('inventory_item_serials')->where('serial_number', $newSn)->exists()) {
        echo "ABORT: SN baru sudah ada: {$newSn}\n";
        exit(1);
    }

    $outs = $movements->get($sn) ?? collect();
    $otherOuts = $outs->filter(fn ($m) => (int) $m->delivery_order_id !== (int) $serial->out_delivery_order_id);

    if ($otherOuts->isEmpty()) {
        echo "SKIP {$sn}: hanya 1 DO out (current={$serial->out_do_number}, received=" . ((int) $serial->is_received) . ")\n";
        continue;
    }

    echo "PLAN {$sn}\n";
    echo "  KEEP (sudah GR): DO {$serial->out_do_number} / {$serial->out_outlet_name} / received=" . ((int) $serial->is_received) . "\n";

    foreach ($otherOuts as $m) {
        $key = $m->delivery_order_id . ':' . $serial->item_id;
        $doi = $doiByDoItem->get($key);
        if (! $doi) {
            echo "  WARN: DOI tidak ketemu untuk DO {$doNumbers[$m->delivery_order_id]} item {$serial->item_id}\n";
            continue;
        }

        $decoded = json_decode($doi->serial_numbers, true) ?: [];
        if (! in_array($sn, $decoded, true)) {
            echo "  WARN: SN tidak ada di JSON DOI#{$doi->id} DO {$doNumbers[$m->delivery_order_id]}\n";
            continue;
        }

        $plan[] = [
            'old_sn' => $sn,
            'new_sn' => $newSn,
            'serial' => $serial,
            'doi' => $doi,
            'do_number' => $doNumbers[$m->delivery_order_id] ?? (string) $m->delivery_order_id,
            'movement' => $m,
        ];

        echo "  RENAME on DO {$doNumbers[$m->delivery_order_id]} (DOI#{$doi->id}) → {$newSn}\n";
        echo "    outlet_id={$m->outlet_id} warehouse_outlet_id={$m->warehouse_outlet_id}\n";
    }
    echo "\n";
}

if (empty($plan)) {
    echo "Tidak ada perubahan.\n";
    exit(0);
}

if (! $apply) {
    echo 'Dry-run OK. Total replace: ' . count($plan) . "\n";
    echo "Jalankan: php scripts/fix_duplicate_do_serials_append2.php --apply\n";
    exit(0);
}

$allowed = Schema::getColumnListing('inventory_item_serials');

DB::beginTransaction();
try {
    $now = now();
    $doneNew = [];

    foreach ($plan as $step) {
        $sn = $step['old_sn'];
        $newSn = $step['new_sn'];
        $serial = $step['serial'];
        $doi = $step['doi'];
        $movement = $step['movement'];
        $doNumber = $step['do_number'];

        if (! isset($doneNew[$newSn])) {
            $insert = [];
            foreach ($allowed as $col) {
                if ($col === 'id') {
                    continue;
                }
                if (property_exists($serial, $col)) {
                    $insert[$col] = $serial->{$col};
                }
            }

            $insert['serial_number'] = $newSn;
            $insert['is_out'] = 1;
            $insert['out_at'] = $movement->moved_at ?? $now;
            $insert['out_delivery_order_id'] = $doi->delivery_order_id;
            $insert['out_outlet_id'] = $movement->outlet_id;
            $insert['out_warehouse_outlet_id'] = $movement->warehouse_outlet_id;
            $insert['is_received'] = 0;
            if (in_array('received_at', $allowed, true)) {
                $insert['received_at'] = null;
            }
            if (in_array('received_by', $allowed, true)) {
                $insert['received_by'] = null;
            }
            if (in_array('received_outlet_gr_id', $allowed, true)) {
                $insert['received_outlet_gr_id'] = null;
            }
            $insert['created_at'] = $now;
            $insert['updated_at'] = $now;

            $newId = DB::table('inventory_item_serials')->insertGetId($insert);

            DB::table('inventory_serial_movements')->insert([
                'serial_id' => $newId,
                'serial_number' => $newSn,
                'movement_type' => 'out',
                'delivery_order_id' => $doi->delivery_order_id,
                'delivery_order_number' => $doNumber,
                'outlet_id' => $movement->outlet_id,
                'warehouse_outlet_id' => $movement->warehouse_outlet_id,
                'item_id' => $serial->item_id,
                'qty' => $movement->qty ?? 1,
                'unit_id' => $serial->unit_id,
                'moved_by' => $movement->moved_by,
                'moved_at' => $movement->moved_at ?? $now,
                'notes' => "Remediasi duplikat DO: clone dari {$sn}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $doneNew[$newSn] = $newId;
            echo "INSERT {$newSn} id={$newId} for DO {$doNumber}\n";
        }

        // Reload DOI JSON in case multiple SN on same DOI
        $fresh = DB::table('delivery_order_items')->where('id', $doi->id)->value('serial_numbers');
        $decoded = json_decode($fresh, true) ?: [];
        $replaced = false;
        foreach ($decoded as $i => $value) {
            if ($value === $sn) {
                $decoded[$i] = $newSn;
                $replaced = true;
            }
        }
        if ($replaced) {
            DB::table('delivery_order_items')
                ->where('id', $doi->id)
                ->update(['serial_numbers' => json_encode(array_values($decoded))]);
            echo "UPDATE DOI#{$doi->id} {$sn} → {$newSn}\n";
        }
    }

    DB::commit();
    echo "\nAPPLY sukses. Outlet belum GR bisa terima dengan SN berakhiran {$suffix}.\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo 'GAGAL: ' . $e->getMessage() . "\n";
    exit(1);
}
