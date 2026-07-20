<?php

/**
 * Smoke checks for AssetOwnership helper (no DB writes).
 * Run: php scripts/smoke_asset_ownership.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\AssetOwnership;

$fail = 0;
function assert_true(bool $cond, string $msg): void
{
    global $fail;
    if ($cond) {
        echo "OK  {$msg}\n";
    } else {
        echo "FAIL {$msg}\n";
        $fail++;
    }
}

assert_true(AssetOwnership::PAK_YUDI_ID === 90001, 'PAK_YUDI_ID=90001');
assert_true(AssetOwnership::PT_BAA_ID === 90002, 'PT_BAA_ID=90002');
assert_true(AssetOwnership::isVirtual(90001), 'isVirtual(90001)');
assert_true(AssetOwnership::isVirtual(90002), 'isVirtual(90002)');
assert_true(! AssetOwnership::isVirtual(1), 'not virtual for id=1');
assert_true(AssetOwnership::name(90001) === 'Pak Yudi', 'name(90001)');
assert_true(AssetOwnership::name(90002) === 'PT BAA', 'name(90002)');
assert_true(AssetOwnership::isValidOwnerId(90001), 'isValidOwnerId virtual');

$sql = AssetOwnership::ownerNameSql('s.owner_outlet_id', 'oo.nama_outlet');
assert_true(str_contains($sql, '90001'), 'ownerNameSql contains 90001');
assert_true(str_contains($sql, 'Pak Yudi'), 'ownerNameSql contains Pak Yudi');
assert_true(str_contains($sql, 'ELSE oo.nama_outlet'), 'ownerNameSql ELSE outlet');

$options = AssetOwnership::options();
$ids = $options->pluck('id_outlet')->all();
assert_true(in_array(90001, $ids, true) || in_array(90001, array_map('intval', $ids), true), 'options includes 90001');
assert_true(in_array(90002, $ids, true) || in_array(90002, array_map('intval', $ids), true), 'options includes 90002');

$loc = AssetOwnership::locationOptions();
$locIds = $loc->pluck('id_outlet')->map(fn ($v) => (int) $v)->all();
assert_true(! in_array(90001, $locIds, true), 'locationOptions excludes 90001');
assert_true(! in_array(90002, $locIds, true), 'locationOptions excludes 90002');

echo $fail === 0 ? "\nSMOKE PASS\n" : "\nSMOKE FAIL ({$fail})\n";
exit($fail === 0 ? 0 : 1);
