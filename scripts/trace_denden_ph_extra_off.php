<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\HolidayAttendanceService;

function fmt($n): string
{
    return number_format((float) $n, 2, ',', '.');
}

$users = DB::table('users')
    ->where('nama_lengkap', 'like', '%DENDEN%')
    ->orWhere('nama_lengkap', 'like', '%RACHDIANA%')
    ->orWhere('nama_lengkap', 'like', '%Tamtam%')
    ->get();

echo "=== USER MATCH ===\n";
foreach ($users as $u) {
    echo "id={$u->id} nik=" . ($u->nik ?? '-') . " nama={$u->nama_lengkap} status={$u->status} outlet={$u->id_outlet} jabatan={$u->id_jabatan} cuti=" . ($u->cuti ?? 0) . "\n";
}

if ($users->isEmpty()) {
    echo "Tidak ketemu. Coba LIKE Denden...\n";
    $alts = DB::table('users')->where('nama_lengkap', 'like', '%Denden%')->orWhere('nama_lengkap', 'like', '%denden%')->limit(20)->get(['id', 'nama_lengkap', 'status']);
    foreach ($alts as $a) {
        echo "alt: {$a->id} {$a->nama_lengkap} {$a->status}\n";
    }
    exit(1);
}

$user = $users->first(fn ($u) => stripos($u->nama_lengkap, 'DENDEN') !== false)
    ?? $users->first();

echo "\n>>> TRACE: {$user->nama_lengkap} (id={$user->id})\n\n";

$jabatan = DB::table('tbl_data_jabatan as j')
    ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
    ->where('j.id_jabatan', $user->id_jabatan)
    ->select('j.nama_jabatan', 'l.nama_level', 'l.nilai_public_holiday')
    ->first();
$outlet = DB::table('tbl_data_outlet')->where('id_outlet', $user->id_outlet)->first();

echo "=== PROFIL ===\n";
echo 'Outlet: ' . ($outlet->nama_outlet ?? $user->id_outlet) . "\n";
echo 'Jabatan: ' . ($jabatan->nama_jabatan ?? '-') . "\n";
echo 'Level: ' . ($jabatan->nama_level ?? '-') . "\n";
echo 'nilai_public_holiday (master): ' . ($jabatan->nilai_public_holiday ?? '-') . "\n";
echo 'tanggal_masuk: ' . ($user->tanggal_masuk ?? '-') . "\n";
echo 'cuti tahunan (users.cuti): ' . ($user->cuti ?? 0) . "\n\n";

$eob = DB::table('extra_off_balance')->where('user_id', $user->id)->first();
echo "=== EXTRA OFF BALANCE TABLE ===\n";
echo $eob
    ? "balance={$eob->balance} updated_at={$eob->updated_at} created_at={$eob->created_at}\n"
    : "TIDAK ADA ROW extra_off_balance\n";

$tx = DB::table('extra_off_transactions')
    ->where('user_id', $user->id)
    ->orderBy('source_date')
    ->orderBy('id')
    ->get();

echo "\n=== EXTRA OFF TRANSACTIONS ({$tx->count()}) ===\n";
$earnedDays = 0.0;
$usedDays = 0.0;
$byType = [];
$dupDates = [];
foreach ($tx as $t) {
    $key = ($t->source_type ?? '-') . '|' . ($t->transaction_type ?? '-') . '|' . ($t->source_date ?? '');
    $dupDates[$key] = ($dupDates[$key] ?? 0) + 1;
    $byType[$t->transaction_type . '/' . ($t->source_type ?? '-')] = ($byType[$t->transaction_type . '/' . ($t->source_type ?? '-')] ?? 0) + (float) $t->amount;
    if ($t->transaction_type === 'earned') {
        $earnedDays += (float) $t->amount;
    } else {
        $usedDays += abs((float) $t->amount);
    }
    echo sprintf(
        "  #%s %s/%s amt=%s date=%s used=%s status=%s | %s\n",
        $t->id,
        $t->transaction_type,
        $t->source_type ?? '-',
        $t->amount,
        $t->source_date ?? '-',
        $t->used_date ?? '-',
        $t->status,
        substr((string) ($t->description ?? ''), 0, 90)
    );
}

echo "\nRingkasan extra off tx:\n";
foreach ($byType as $k => $v) {
    echo "  {$k}: amount sum=" . fmt($v) . "\n";
}
$recalc = $earnedDays - $usedDays;
echo 'SUM earned=' . fmt($earnedDays) . ' used=' . fmt($usedDays) . ' recalc balance=' . fmt($recalc)
    . ' vs table=' . fmt($eob->balance ?? 0)
    . ' selisih=' . fmt(($eob->balance ?? 0) - $recalc) . "\n";

$dupes = array_filter($dupDates, fn ($n) => $n > 1);
if ($dupes) {
    echo "\nDUPLIKAT extra_off_transactions (source_type+type+date):\n";
    foreach ($dupes as $k => $n) {
        echo "  {$k} x{$n}\n";
    }
} else {
    echo "Tidak ada duplikat extra_off per (type+source_date).\n";
}

echo "\n=== Cek extra off: apakah tanggal punya SHIFT / LIBUR? ===\n";
$earnedUnsched = $tx->where('transaction_type', 'earned')->where('source_type', 'unscheduled_work');
$suspiciousEo = 0;
foreach ($earnedUnsched as $t) {
    $d = $t->source_date ? substr((string) $t->source_date, 0, 10) : null;
    if (!$d) {
        continue;
    }
    $shift = DB::table('user_shifts')
        ->where('user_id', $user->id)
        ->whereDate('tanggal', $d)
        ->first();
    $holiday = DB::table('tbl_kalender_perusahaan')->whereDate('tgl_libur', $d)->first();
    $ph = DB::table('holiday_attendance_compensations')
        ->where('user_id', $user->id)
        ->whereDate('holiday_date', $d)
        ->first();
    $shiftLabel = $shift
        ? ('SHIFT id=' . $shift->shift_id)
        : 'NO SHIFT';
    $holLabel = $holiday ? 'LIBUR' : 'bukan libur';
    $phLabel = $ph ? "PH {$ph->compensation_type}/{$ph->compensation_amount}" : 'no PH row';
    $flag = '';
    if ($shift && $shift->shift_id) {
        $flag = ' [?] ADA SHIFT tapi dapat extra off';
        $suspiciousEo++;
    }
    if ($holiday) {
        $flag .= ' [?] HARI LIBUR — seharusnya jalur PH, bukan extra off';
        $suspiciousEo++;
    }
    echo "  {$d} | {$shiftLabel} | {$holLabel} | {$phLabel}{$flag}\n";
}

$phService = app(HolidayAttendanceService::class);
$availPh = $phService->getAvailablePublicHolidayBonusDays((int) $user->id);
$totalPh = $phService->getTotalPublicHolidayBonusBalance((int) $user->id);

echo "\n=== PUBLIC HOLIDAY (service) ===\n";
echo 'available_leave_days (modal): ' . fmt($availPh) . "\n";
echo 'total_bonus_balance (bruto): ' . fmt($totalPh) . "\n";

$cols = Schema::getColumnListing('holiday_attendance_compensations');
echo 'kolom compensations: ' . implode(', ', $cols) . "\n";

$comps = DB::table('holiday_attendance_compensations')
    ->where('user_id', $user->id)
    ->orderBy('holiday_date')
    ->orderBy('id')
    ->get();

echo "\n=== HOLIDAY COMPENSATIONS ({$comps->count()}) ===\n";
$phEarned = 0.0;
$phUsed = 0.0;
$phDup = [];
foreach ($comps as $c) {
    $usedAmt = (float) ($c->used_amount ?? 0);
    $avail = max(0, (float) $c->compensation_amount - $usedAmt);
    $key = ($c->compensation_type ?? '') . '|' . substr((string) $c->holiday_date, 0, 10);
    $phDup[$key] = ($phDup[$key] ?? 0) + 1;
    if ($c->compensation_type === 'bonus' && $c->status === 'approved') {
        $phEarned += (float) $c->compensation_amount;
        $phUsed += $usedAmt;
    }
    $isHoliday = DB::table('tbl_kalender_perusahaan')->whereDate('tgl_libur', $c->holiday_date)->exists();
    $manual = stripos((string) ($c->compensation_description ?? ''), 'Manual') !== false;
    echo sprintf(
        "  #%s %s %s amt=%s used=%s sisa=%s status=%s libur=%s%s | %s\n",
        $c->id,
        substr((string) $c->holiday_date, 0, 10),
        $c->compensation_type,
        fmt($c->compensation_amount),
        fmt($usedAmt),
        fmt($avail),
        $c->status,
        $isHoliday ? 'YA' : 'TIDAK',
        $manual ? ' MANUAL' : '',
        substr((string) ($c->compensation_description ?? ''), 0, 80)
    );
}

$phDupes = array_filter($phDup, fn ($n) => $n > 1);
if ($phDupes) {
    echo "\nDUPLIKAT PH per (type+date):\n";
    foreach ($phDupes as $k => $n) {
        echo "  {$k} x{$n}\n";
    }
} else {
    echo "Tidak ada duplikat PH per (type+date).\n";
}

echo 'PH bonus earned=' . fmt($phEarned) . ' used_amount sum=' . fmt($phUsed) . ' sisa calc=' . fmt($phEarned - $phUsed) . "\n";

echo "\n=== PENGAJUAN IZIN PH / EXTRA OFF ===\n";
$leaveTypes = DB::table('leave_types')->get(['id', 'name']);
echo 'leave_types: ' . $leaveTypes->map(fn ($t) => "{$t->id}={$t->name}")->implode(', ') . "\n";

$phTypeIds = $leaveTypes->filter(fn ($t) => stripos($t->name, 'public') !== false || strcasecmp($t->name, 'PH') === 0)->pluck('id');
$eoTypeIds = $leaveTypes->filter(fn ($t) => stripos($t->name, 'extra') !== false)->pluck('id');

$reqs = DB::table('approval_requests as ar')
    ->leftJoin('leave_types as lt', 'lt.id', '=', 'ar.leave_type_id')
    ->where('ar.user_id', $user->id)
    ->select('ar.id', 'ar.date_from', 'ar.date_to', 'ar.status', 'ar.leave_type_id', 'lt.name as leave_type', 'ar.created_at')
    ->orderBy('ar.date_from')
    ->get();

echo 'approval_requests: ' . $reqs->count() . "\n";
foreach ($reqs as $r) {
    $days = (strtotime((string) $r->date_to) - strtotime((string) $r->date_from)) / 86400 + 1;
    $mark = '';
    if ($phTypeIds->contains($r->leave_type_id)) {
        $mark = ' [PH]';
    }
    if ($eoTypeIds->contains($r->leave_type_id)) {
        $mark = ' [EXTRA OFF]';
    }
    echo sprintf(
        "  #%s %s s/d %s (%s hari) type=%s status=%s%s\n",
        $r->id,
        substr((string) $r->date_from, 0, 10),
        substr((string) $r->date_to, 0, 10),
        (int) $days,
        $r->leave_type ?? $r->leave_type_id,
        $r->status,
        $mark
    );
}

echo "\n=== PEER SAMA OUTLET (saldo extra off & PH sisa) ===\n";
$peers = DB::table('users as u')
    ->leftJoin('extra_off_balance as eob', 'eob.user_id', '=', 'u.id')
    ->where('u.id_outlet', $user->id_outlet)
    ->where('u.status', 'A')
    ->select('u.id', 'u.nama_lengkap', 'eob.balance as eo')
    ->orderByDesc('eob.balance')
    ->limit(15)
    ->get();

foreach ($peers as $p) {
    $phAvail = $phService->getAvailablePublicHolidayBonusDays((int) $p->id);
    $mark = $p->id === $user->id ? ' <==' : '';
    echo sprintf("  %s | EO %s | PH sisa %s%s\n", $p->nama_lengkap, fmt($p->eo ?? 0), fmt($phAvail), $mark);
}

echo "\n=== VERDIK ===\n";
echo 'Modal Extra Off menampilkan extra_off_balance.balance = ' . fmt($eob->balance ?? 0) . "\n";
echo 'Modal PH menampilkan available bonus days = ' . fmt($availPh) . "\n";
echo "Suspicious extra-off flags: {$suspiciousEo}\n";
echo 'PH duplikat keys: ' . count($phDupes) . "\n";
echo 'EO recalc vs table selisih: ' . fmt(($eob->balance ?? 0) - $recalc) . "\n";
