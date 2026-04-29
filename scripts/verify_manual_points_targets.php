<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MemberAppsMember;

$targets = ['JT2604IJ5I', 'JT260376TM'];

foreach ($targets as $memberId) {
    $member = MemberAppsMember::where('member_id', $memberId)->first();

    if (!$member) {
        echo "{$memberId} | not found\n";
        continue;
    }

    echo "{$memberId} | points=" . ((int)($member->just_points ?? 0)) . "\n";
}
