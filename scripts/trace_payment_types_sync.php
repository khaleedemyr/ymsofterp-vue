<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$outletId = (int) ($argv[1] ?? 18);

echo "=== TRACE PAYMENT TYPES SYNC (outlet_id={$outletId}) ===\n\n";

$outlet = DB::table('tbl_data_outlet')
    ->where('id_outlet', $outletId)
    ->first(['id_outlet', 'nama_outlet', 'region_id', 'qr_code', 'status']);

if (! $outlet) {
    echo "Outlet tidak ditemukan\n";
    exit(1);
}

echo "--- Outlet ---\n";
echo json_encode($outlet, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n";

$regionId = $outlet->region_id;

// Cari BCA MERCHANT / QRIS
echo "--- Payment types (BCA / QRIS) di ERP ---\n";
$bcaTypes = DB::table('payment_types')
    ->where(function ($q) {
        $q->where('name', 'like', '%BCA%MERCHANT%')
            ->orWhere('name', 'like', '%BCA MERCHANT%')
            ->orWhere('code', 'like', '%QRIS%');
    })
    ->get();

foreach ($bcaTypes as $pt) {
    $outlets = DB::table('payment_type_outlets')->where('payment_type_id', $pt->id)->pluck('outlet_id')->all();
    $regions = DB::table('payment_type_regions')->where('payment_type_id', $pt->id)->pluck('region_id')->all();
    $inOutlet = in_array($outletId, array_map('intval', $outlets), true);
    $inRegion = $regionId && in_array((int) $regionId, array_map('intval', $regions), true);

    echo "ID={$pt->id} | {$pt->name} | code={$pt->code} | status={$pt->status}\n";
    echo "  outlets: ".json_encode($outlets)."\n";
    echo "  regions: ".json_encode($regions)."\n";
    echo "  linked to outlet {$outletId}? ".($inOutlet ? 'YES' : 'NO')."\n";
    echo "  linked to region {$regionId}? ".($inRegion ? 'YES' : 'NO')."\n";
    echo "  would sync to POS? ".(($pt->status === 'active' && ($inOutlet || $inRegion)) ? 'YES' : 'NO')."\n\n";
}

// Simulasi query syncPaymentTypes.js (ymsoftpos)
echo "--- Simulasi query POS syncPaymentTypes.js ---\n";
$syncRows = DB::select("
    SELECT DISTINCT pt.id, pt.name, pt.code, pt.status
    FROM payment_types pt
    LEFT JOIN payment_type_regions ptr ON pt.id = ptr.payment_type_id AND ptr.region_id = ?
    LEFT JOIN payment_type_outlets pto ON pt.id = pto.payment_type_id AND pto.outlet_id = ?
    WHERE pt.status = 'active'
      AND (ptr.id IS NOT NULL OR pto.id IS NOT NULL)
    ORDER BY pt.name
", [$regionId, $outletId]);

echo 'Total payment types untuk outlet ini: '.count($syncRows)."\n";

$bcaInSync = collect($syncRows)->first(fn ($r) => stripos($r->name, 'BCA MERCHANT') !== false);
echo 'BCA MERCHANT(QRIS) ada di hasil sync? '.($bcaInSync ? "YES (id={$bcaInSync->id})" : 'NO — INI PENYEBAB HILANG SETELAH SYNC')."\n\n";

if (! $bcaInSync) {
    echo "--- Analisis penyebab ---\n";
    $bca = DB::table('payment_types')->where('name', 'like', '%BCA MERCHANT%')->first();
    if (! $bca) {
        echo "- Payment type BCA MERCHANT tidak ada di tabel payment_types ERP\n";
    } elseif ($bca->status !== 'active') {
        echo "- BCA MERCHANT status={$bca->status} (bukan active)\n";
    } else {
        $outlets = DB::table('payment_type_outlets')->where('payment_type_id', $bca->id)->pluck('outlet_id')->all();
        $regions = DB::table('payment_type_regions')->where('payment_type_id', $bca->id)->pluck('region_id')->all();
        if (empty($outlets) && empty($regions)) {
            echo "- BCA MERCHANT tidak punya mapping outlet/region (payment_type_outlets & payment_type_regions kosong)\n";
        } elseif (! in_array($outletId, array_map('intval', $outlets), true) && ! in_array((int) $regionId, array_map('intval', $regions), true)) {
            echo "- BCA MERCHANT tidak di-assign ke outlet {$outletId} ({$outlet->nama_outlet}) maupun region {$regionId}\n";
            echo "  Assign saat ini: outlets=".json_encode($outlets).", regions=".json_encode($regions)."\n";
        }
    }

    // Debug join untuk payment_type id=18 (kemungkinan bentrok id dengan outlet_id)
    $joinDebug = DB::select('
        SELECT pt.id, pt.name, ptr.id AS ptr_id, pto.id AS pto_row_id, pto.outlet_id AS pto_outlet_id
        FROM payment_types pt
        LEFT JOIN payment_type_regions ptr ON pt.id = ptr.payment_type_id AND ptr.region_id = ?
        LEFT JOIN payment_type_outlets pto ON pt.id = pto.payment_type_id AND pto.outlet_id = ?
        WHERE pt.id = 18
    ', [$regionId, $outletId]);
    echo "\nDebug JOIN payment_type id=18:\n";
    echo json_encode($joinDebug, JSON_PRETTY_PRINT)."\n";

    $ptoRow = DB::table('payment_type_outlets')
        ->where('payment_type_id', 18)
        ->where('outlet_id', $outletId)
        ->first();
    echo "Row payment_type_outlets (pt=18, outlet={$outletId}): ".json_encode($ptoRow)."\n";

    $allPto18 = DB::table('payment_type_outlets')->where('payment_type_id', 18)->get();
    echo "All payment_type_outlets for pt=18: count=".$allPto18->count()."\n";
    foreach ($allPto18 as $row) {
        if ((int) $row->outlet_id === $outletId) {
            echo "  MATCH outlet {$outletId}: ".json_encode($row)."\n";
        }
    }

    $cols = DB::select('SHOW COLUMNS FROM payment_type_outlets');
    echo "Columns payment_type_outlets: ".implode(', ', array_map(fn ($c) => $c->Field, $cols))."\n";
    echo "\nCatatan: POS sync menonaktifkan (status=inactive) payment type lokal yang TIDAK ada di hasil query di atas.\n";
}

echo "\n--- Semua payment types outlet {$outletId} ---\n";
foreach ($syncRows as $r) {
    echo "  [{$r->id}] {$r->name} ({$r->code})\n";
}

// Cek data corrupt: payment_type_outlets.id NULL
$nullIdCount = DB::table('payment_type_outlets')->whereNull('id')->count();
$nullIdFor18 = DB::table('payment_type_outlets')
    ->where('payment_type_id', 18)
    ->whereNull('id')
    ->count();
echo "\n--- Data integrity payment_type_outlets ---\n";
echo "Rows dengan id NULL (total): {$nullIdCount}\n";
echo "Rows pt=18 dengan id NULL: {$nullIdFor18}\n";

$sampleOk = DB::table('payment_type_outlets as pto')
    ->join('payment_types as pt', 'pt.id', '=', 'pto.payment_type_id')
    ->where('pto.outlet_id', $outletId)
    ->where('pt.id', 6)
    ->first(['pto.id as pto_id', 'pto.payment_type_id', 'pto.outlet_id', 'pt.name']);
echo "Sample Bank BCA (pt=6) pivot row: ".json_encode($sampleOk)."\n";

$sampleBad = DB::table('payment_type_outlets as pto')
    ->join('payment_types as pt', 'pt.id', '=', 'pto.payment_type_id')
    ->where('pto.outlet_id', $outletId)
    ->where('pt.id', 18)
    ->first(['pto.id as pto_id', 'pto.payment_type_id', 'pto.outlet_id', 'pt.name']);
echo "Sample BCA MERCHANT (pt=18) pivot row: ".json_encode($sampleBad)."\n";

echo "\n--- Sumber sync per payment type (region vs outlet pivot) ---\n";
foreach ($syncRows as $r) {
    $viaRegion = DB::table('payment_type_regions')
        ->where('payment_type_id', $r->id)
        ->where('region_id', $regionId)
        ->exists();
    $viaOutlet = DB::table('payment_type_outlets')
        ->where('payment_type_id', $r->id)
        ->where('outlet_id', $outletId)
        ->exists();
    $ptoId = DB::table('payment_type_outlets')
        ->where('payment_type_id', $r->id)
        ->where('outlet_id', $outletId)
        ->value('id');
    echo "  [{$r->id}] {$r->name}: region=".($viaRegion ? 'Y' : 'N')." outlet=".($viaOutlet ? 'Y' : 'N')." pto.id=".json_encode($ptoId)."\n";
}

// Kenapa BCA tidak masuk: cek kondisi WHERE pto.id IS NOT NULL
$bcaViaOutlet = DB::table('payment_type_outlets')
    ->where('payment_type_id', 18)
    ->where('outlet_id', $outletId)
    ->whereNotNull('id')
    ->exists();
$bcaViaRegion = DB::table('payment_type_regions')
    ->where('payment_type_id', 18)
    ->where('region_id', $regionId)
    ->whereNotNull('id')
    ->exists();
$ptrBca = DB::table('payment_type_regions')
    ->where('payment_type_id', 6)
    ->where('region_id', $regionId)
    ->first();
echo "ptr row Bank BCA region {$regionId}: ".json_encode($ptrBca)."\n";

$ptrQris = DB::table('payment_type_regions')
    ->where('payment_type_id', 18)
    ->where('region_id', $regionId)
    ->first();
echo "ptr row BCA MERCHANT region {$regionId}: ".json_encode($ptrQris)."\n";
