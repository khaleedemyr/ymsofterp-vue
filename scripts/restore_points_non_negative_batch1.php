<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MemberAppsMember;
use Illuminate\Support\Facades\DB;

$targets = [
    'JT2604IJ5I' => 230,
    'JT260376TM' => 202,
];

try {
    DB::beginTransaction();

    foreach ($targets as $memberId => $restoredPoints) {
        $member = MemberAppsMember::where('member_id', $memberId)->first();
        if (!$member) {
            echo "Member not found: {$memberId}\n";
            continue;
        }

        $old = (int)($member->just_points ?? 0);
        $member->just_points = $restoredPoints;
        $member->save();

        echo "{$memberId} | old={$old} | restored={$restoredPoints}\n";
    }

    DB::commit();
    echo "SUCCESS\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
