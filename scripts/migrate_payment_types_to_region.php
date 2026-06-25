<?php

/**
 * Konversi payment type dari assign per-outlet → per-region (selaras Gopay/Cash/dll).
 *
 * Usage:
 *   php scripts/migrate_payment_types_to_region.php          # dry-run
 *   php scripts/migrate_payment_types_to_region.php --apply  # eksekusi
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PaymentType;
use Illuminate\Support\Facades\DB;

/**
 * Tabel payment_type_regions punya kolom id/name manual (bukan AI).
 * POS sync cek ptr.id IS NOT NULL — wajib diisi agar ikut sync.
 */
function repairPaymentTypeRegionPivotRows(int $paymentTypeId, array $regionIds): void
{
    $regionNames = DB::table('regions')
        ->whereIn('id', $regionIds)
        ->pluck('name', 'id');

    foreach ($regionIds as $regionId) {
        DB::table('payment_type_regions')
            ->where('payment_type_id', $paymentTypeId)
            ->where('region_id', $regionId)
            ->update([
                'id' => (int) $regionId,
                'name' => $regionNames[$regionId] ?? null,
            ]);
    }
}

$apply = in_array('--apply', $argv, true);
$targets = ['BCA MERCHANT(QRIS)', 'Gopay', 'Grabfood'];

echo $apply ? "=== APPLY MODE ===\n\n" : "=== DRY-RUN (tambah --apply untuk eksekusi) ===\n\n";

foreach ($targets as $name) {
    $pt = PaymentType::where('name', $name)->first();
    if (! $pt) {
        echo "[SKIP] Payment type tidak ditemukan: {$name}\n\n";
        continue;
    }

    echo "--- {$pt->name} (id={$pt->id}) ---\n";

    $currentOutlets = DB::table('payment_type_outlets')
        ->where('payment_type_id', $pt->id)
        ->pluck('outlet_id')
        ->all();
    $currentRegions = DB::table('payment_type_regions')
        ->where('payment_type_id', $pt->id)
        ->pluck('region_id')
        ->all();

    echo 'Outlet links sekarang: '.count($currentOutlets)." → ".json_encode(array_map('intval', $currentOutlets))."\n";
    echo 'Region links sekarang: '.count($currentRegions)." → ".json_encode(array_map('intval', $currentRegions))."\n";

    // Region dari outlet yang saat ini ter-assign (BCA/Grabfood) atau region yang sudah ada (Gopay)
    $regionIdsFromOutlets = DB::table('payment_type_outlets as pto')
        ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'pto.outlet_id')
        ->where('pto.payment_type_id', $pt->id)
        ->whereNotNull('o.region_id')
        ->distinct()
        ->pluck('o.region_id')
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->sort()
        ->values()
        ->all();

    $regionIdsExisting = collect($currentRegions)
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->sort()
        ->values()
        ->all();

    $targetRegionIds = ! empty($regionIdsFromOutlets)
        ? $regionIdsFromOutlets
        : $regionIdsExisting;

    if (empty($targetRegionIds)) {
        echo "[WARN] Tidak ada region yang bisa ditentukan, lewati.\n\n";
        continue;
    }

    $regionNames = DB::table('regions')
        ->whereIn('id', $targetRegionIds)
        ->orderBy('id')
        ->pluck('name', 'id');

    echo 'Target regions: ';
    foreach ($targetRegionIds as $rid) {
        echo "[{$rid}] ".($regionNames[$rid] ?? '?').'  ';
    }
    echo "\n";

    if ($apply) {
        DB::transaction(function () use ($pt, $targetRegionIds) {
            $pt->regions()->sync($targetRegionIds);
            $pt->outlets()->detach();
            repairPaymentTypeRegionPivotRows((int) $pt->id, $targetRegionIds);
        });
        echo "✓ Diupdate: regions synced, outlet links dihapus, pivot id/name diperbaiki\n";
    } else {
        echo "(dry-run) Akan sync regions + hapus outlet links\n";
    }

    // Verifikasi hasil / simulasi
    if ($apply) {
        $afterOutlets = DB::table('payment_type_outlets')->where('payment_type_id', $pt->id)->count();
        $afterRegions = DB::table('payment_type_regions')
            ->where('payment_type_id', $pt->id)
            ->get(['id', 'payment_type_id', 'region_id']);
        echo "Setelah update: outlets={$afterOutlets}, regions=".$afterRegions->count()."\n";
        foreach ($afterRegions as $row) {
            $nullId = $row->id === null ? ' (id NULL!)' : '';
            echo "  region_id={$row->region_id}{$nullId}\n";
        }
    }

    echo "\n";
}

if ($apply) {
    echo "=== Perbaiki pivot id NULL (semua payment type) ===\n";
    $broken = DB::table('payment_type_regions')->whereNull('id')->get();
    foreach ($broken as $row) {
        $name = DB::table('regions')->where('id', $row->region_id)->value('name');
        DB::table('payment_type_regions')
            ->where('payment_type_id', $row->payment_type_id)
            ->where('region_id', $row->region_id)
            ->update(['id' => (int) $row->region_id, 'name' => $name]);
        echo "  pt={$row->payment_type_id} region={$row->region_id} → id={$row->region_id}\n";
    }
    echo "\n";

    echo "=== Verifikasi sync POS outlet 18 (BCA MERCHANT) ===\n";
    $outletId = 18;
    $regionId = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('region_id');
    $inSync = DB::selectOne("
        SELECT pt.id, pt.name
        FROM payment_types pt
        LEFT JOIN payment_type_regions ptr ON pt.id = ptr.payment_type_id AND ptr.region_id = ?
        LEFT JOIN payment_type_outlets pto ON pt.id = pto.payment_type_id AND pto.outlet_id = ?
        WHERE pt.status = 'active'
          AND pt.name = 'BCA MERCHANT(QRIS)'
          AND (ptr.id IS NOT NULL OR pto.id IS NOT NULL)
    ", [$regionId, $outletId]);
    echo $inSync
        ? "BCA MERCHANT ikut sync outlet {$outletId}: YES (id={$inSync->id})\n"
        : "BCA MERCHANT ikut sync outlet {$outletId}: NO — cek query POS\n";

    $fixedQuery = DB::selectOne("
        SELECT pt.id, pt.name
        FROM payment_types pt
        LEFT JOIN payment_type_regions ptr ON pt.id = ptr.payment_type_id AND ptr.region_id = ?
        LEFT JOIN payment_type_outlets pto ON pt.id = pto.payment_type_id AND pto.outlet_id = ?
        WHERE pt.status = 'active'
          AND pt.name = 'BCA MERCHANT(QRIS)'
          AND (ptr.region_id IS NOT NULL OR pto.outlet_id IS NOT NULL)
    ", [$regionId, $outletId]);
    echo $fixedQuery
        ? "Dengan kondisi sync yang benar (region_id/outlet_id): YES\n"
        : "Dengan kondisi sync yang benar: NO\n";
} else {
    echo "Jalankan: php scripts/migrate_payment_types_to_region.php --apply\n";
}
