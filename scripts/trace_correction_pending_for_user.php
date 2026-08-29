<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ScheduleAttendanceCorrectionController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$userId = (int) ($argv[1] ?? 1089); // Rizal Taufiq Anwar
$user = User::find($userId);
if (!$user) {
    echo "User not found\n";
    exit(1);
}

Auth::login($user);

$controller = app(ScheduleAttendanceCorrectionController::class);
$response = $controller->getPendingApprovals(new Request());
$data = json_decode($response->getContent(), true);

echo "Pending for {$user->nama_lengkap} (id={$userId}):\n";
$approvals = $data['approvals'] ?? [];
echo "Count: " . count($approvals) . "\n";
foreach ($approvals as $a) {
    $arr = (array) $a;
    echo sprintf(
        "  #%s type=%s status=%s stage=%s employee=%s\n",
        $arr['id'] ?? '?',
        $arr['type'] ?? '?',
        $arr['status'] ?? '?',
        $arr['approval_stage'] ?? '?',
        $arr['employee_name'] ?? '?'
    );
}

$has12094 = collect($approvals)->contains(fn ($a) => (int) ((array) $a)['id'] === 12094);
echo "\nContains #12094: " . ($has12094 ? 'YES (BUG!)' : 'NO (correct)') . "\n";
