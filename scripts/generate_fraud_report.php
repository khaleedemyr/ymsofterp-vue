<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$fakeIds = [
    'JT2604EZ59','JT2604P79M','JT260499ES','JT2604VDW9','JT26043Q94','JT2604C4D5',
    'JT2604SWRJ','JT2604UJUW','JT2604NJVG','JT2604KNAN','JT2604H6WW','JT2604AOVN',
    'JT2604T695','JT26041OG1','JT260421M7','JT2604J48D','JT2604M49U','JT2604NQJ5',
    'JT26046Z2Y','JT2604BR2U','JT2604IRN8','JT2604VBAL','JT2604XGPJ','JT2604RMMC',
    'JT2604M3D8','JT2604CJ9T','JT2604KIIC','JT2604Z7HD','JT26047FS9','JT2604PFKS',
    'JT2604C98W','JT2604XEES','JT2604VRFX','JT2604GG2M','JT2604FEQQ','JT2604V5ZZ',
    'JT2604SFW3','JT2604VPJE','JT2604B3D5','JT26041SII','JT2604X140','JT2604FG2Z',
    'JT2604X72R','JT2604MW2T','JT2604IVK4','JT26046SRS','JT2604DF3J','JT2604PBYQ',
    'JT2604GD8A','JT26048541','JT2604SAJ2','JT2604T5MY','JT2604G8LB','JT26044TP5','JT2604TE4M'
];

$members = DB::table('member_apps_members')
    ->whereIn('member_id', $fakeIds)
    ->orderBy('created_at')
    ->get();

$referrerDbId = 35167;
$txRows = DB::table('member_apps_point_transactions')
    ->where('member_id', $referrerDbId)
    ->where('transaction_type', 'bonus')
    ->where('channel', 'referral')
    ->orderBy('created_at')
    ->get()
    ->keyBy('reference_id');

$lines = [];
$lines[] = "# Suspicious Referral Fraud Report";
$lines[] = "";
$lines[] = "**Generated:** " . date('Y-m-d H:i:s');
$lines[] = "**Victim/Referrer:** U110108 — christianto Salimanan";
$lines[] = "**Fraud Type:** Fake account mass-registration to collect referral bonus points";
$lines[] = "";
$lines[] = "---";
$lines[] = "";
$lines[] = "## Summary";
$lines[] = "";
$lines[] = "| Metric | Value |";
$lines[] = "|--------|-------|";
$lines[] = "| Fake accounts created | 55 |";
$lines[] = "| Bonus points fraudulently earned | 2,750 pts (55 × 50 pts) |";
$lines[] = "| Date range | 2026-04-25 to 2026-04-26 |";
$lines[] = "| All fake accounts have orders | NO (0 orders each) |";
$lines[] = "| All fake accounts ever logged in | NO (never) |";
$lines[] = "| Registration pattern | 3 burst windows (scripted timing) |";
$lines[] = "";
$lines[] = "---";
$lines[] = "";
$lines[] = "## Fake Accounts Detail";
$lines[] = "";
$lines[] = "| # | Member ID | Nama | Email | Mobile Phone | Registered At | TX ID | Bonus Credited At |";
$lines[] = "|---|-----------|------|-------|--------------|---------------|-------|-------------------|";

$no = 1;
foreach ($members as $m) {
    $tx = $txRows->get($m->member_id);
    $txId = $tx ? $tx->id : '-';
    $creditedAt = $tx ? $tx->created_at : '-';
    $lines[] = "| {$no} | {$m->member_id} | {$m->nama_lengkap} | {$m->email} | {$m->mobile_phone} | {$m->created_at} | {$txId} | {$creditedAt} |";
    $no++;
}

$lines[] = "";
$lines[] = "---";
$lines[] = "";
$lines[] = "## Fraud Indicators";
$lines[] = "";
$lines[] = "### 1. All Accounts Have Zero Activity";
$lines[] = "- **0 orders** across all 55 accounts";
$lines[] = "- **Never logged in** after registration (last_login_at = NULL)";
$lines[] = "- **0 spending** (total_spending = 0 for all)";
$lines[] = "";
$lines[] = "### 2. Scripted Registration Pattern (3 Burst Windows)";
$lines[] = "";
$lines[] = "| Burst | Window | Count | Avg Gap |";
$lines[] = "|-------|--------|-------|---------|";
$lines[] = "| Burst 1 | 2026-04-25 05:19 — 05:40 | 13 accounts | ~80 seconds |";
$lines[] = "| Burst 2 | 2026-04-26 17:18 — 17:27 | 9 accounts | ~60 seconds |";
$lines[] = "| Burst 3 | 2026-04-26 18:42 — 19:33 | 33 accounts | ~55 seconds |";
$lines[] = "";
$lines[] = "25 of 55 registrations have **< 60 second gap** between consecutive accounts — consistent with automated scripting.";
$lines[] = "";
$lines[] = "### 3. Sequential / Clustered Phone Numbers";
$lines[] = "";
$lines[] = "The following phone number clusters indicate mass generation:";
$lines[] = "";
$lines[] = "| Cluster | Phone Numbers |";
$lines[] = "|---------|--------------|";
$lines[] = "| 0895337770xxx | `0895337770208`, `0895337770209`, `0895337770109` |";
$lines[] = "| 0831593216xx | `083159321646`, `083159321644`, `083159321619` |";
$lines[] = "| 0812343576x | `08123435766`, `08123435761`, `081234357569` |";
$lines[] = "| 08123456xxxx | `0812345669752`, `081234545659`, `081234546385`, `08123455568` |";
$lines[] = "";
$lines[] = "Two numbers differ by only **1 digit**: `0895337770208` → `0895337770209`";
$lines[] = "";
$lines[] = "### 4. Duplicate Name";
$lines[] = "";
$lines[] = "- **\"titin nurhayati\"** registered twice:";
$lines[] = "  - JT2604T695 → `titinnurhayati@gmail.com` → `083821040725` (2026-04-25 05:39)";
$lines[] = "  - JT2604VBAL → `titinnurhayati@yahoo.com` → `081234546385` (2026-04-26 17:26)";
$lines[] = "";
$lines[] = "---";
$lines[] = "";
$lines[] = "## Code Vulnerability Analysis";
$lines[] = "";
$lines[] = "### Bug #1 — No Rate Limiting on Registration Endpoint";
$lines[] = "";
$lines[] = "**File:** `routes/api.php` line 1295";
$lines[] = "```php";
$lines[] = "// TANPA throttle middleware:";
$lines[] = "Route::post('/auth/register', [AuthController::class, 'register']);";
$lines[] = "```";
$lines[] = "";
$lines[] = "**File:** `app/Http/Kernel.php` line 17";
$lines[] = "```php";
$lines[] = "// throttle:api dikomentari — TIDAK AKTIF:";
$lines[] = "//    'throttle:api',";
$lines[] = "```";
$lines[] = "";
$lines[] = "Endpoint `/api/v1/member/auth/register` **tidak ada rate limit sama sekali**, sehingga siapapun dapat melakukan ribuan request registrasi per menit tanpa dibatasi.";
$lines[] = "";
$lines[] = "### Bug #2 — No Maximum Referral Bonus Cap";
$lines[] = "";
$lines[] = "**File:** `app/Http/Controllers/Mobile/Member/AuthController.php` ~line 143";
$lines[] = "```php";
$lines[] = "// Tidak ada cek berapa kali referrer sudah mendapat bonus:";
$lines[] = "if (\$request->has('referral_member_id') && \$request->referral_member_id) {";
$lines[] = "    \$pointEarningService->earnBonusPoints(\$referrer->id, 'referral', 50, null, \$member->member_id);";
$lines[] = "}";
$lines[] = "```";
$lines[] = "";
$lines[] = "Tidak ada batas maksimal berapa kali satu member bisa menerima referral bonus. Satu member bisa menerima bonus tak terbatas jika seseorang mendaftar 1000 akun palsu menggunakan referral code-nya.";
$lines[] = "";
$lines[] = "### Bug #3 — No Phone OTP Verification Before Account Active";
$lines[] = "";
$lines[] = "Registrasi berhasil dan **langsung aktif** tanpa verifikasi nomor HP. Hanya email verification yang dikirim (tapi tidak blocking — akun tetap `is_active = true` setelah register).";
$lines[] = "";
$lines[] = "```php";
$lines[] = "// Di MemberAppsMember::create():";
$lines[] = "'is_active' => true, // Langsung aktif!";
$lines[] = "```";
$lines[] = "";
$lines[] = "Ini memungkinkan penggunaan nomor HP palsu/random.";
$lines[] = "";
$lines[] = "### Bug #4 — Duplicate Phone Check Bypassed by Random Numbers";
$lines[] = "";
$lines[] = "Ada pengecekan duplicate phone, tapi pelaku cukup membuat nomor HP baru yang belum terdaftar setiap kali daftar. Tidak ada validasi apakah nomor HP tersebut valid/aktif (tidak ada OTP challenge).";
$lines[] = "";
$lines[] = "---";
$lines[] = "";
$lines[] = "## Verdict";
$lines[] = "";
$lines[] = "**INI ADALAH FRAUD, BUKAN BUG SISTEM.**";
$lines[] = "";
$lines[] = "Perbedaan:";
$lines[] = "- **Bug** = sistem secara tidak sengaja memberikan poin tanpa ada orang yang memintanya";
$lines[] = "- **Fraud** = seseorang secara sadar mengeksploitasi sistem yang memiliki kelemahan (no rate limit, no cap)";
$lines[] = "";
$lines[] = "Kode berjalan **sesuai desain** — setiap registrasi baru dengan `referral_member_id` valid akan memberikan 50 poin ke referrer. Yang terjadi adalah **seseorang (kemungkinan U110108 sendiri atau seseorang yang tahu referral code-nya) membuat script otomatis** untuk mendaftar 55 akun palsu menggunakan member ID U110108 sebagai referral.";
$lines[] = "";
$lines[] = "Kelemahan kode yang **dieksploitasi** (bukan bug yang men-trigger sendiri):";
$lines[] = "1. Tidak ada rate limiting → bisa register banyak akun cepat";
$lines[] = "2. Tidak ada batas referral bonus → bisa dapat unlimited poin";
$lines[] = "3. Tidak ada OTP HP → bisa pakai nomor HP random";
$lines[] = "";
$lines[] = "---";
$lines[] = "";
$lines[] = "## Recommended Actions";
$lines[] = "";
$lines[] = "### Immediate (Data Correction)";
$lines[] = "- [ ] Set `is_active = 0` untuk 55 akun fake";
$lines[] = "- [ ] Koreksi `just_points` member U110108: hapus 2,750 poin referral fraud";
$lines[] = "- [ ] Koreksi juga 544 poin yang hilang akibat bug duplicate redemption";
$lines[] = "";
$lines[] = "### Code Fix (Security)";
$lines[] = "- [ ] Tambahkan `throttle:5,1` (5 request/menit per IP) ke endpoint `/auth/register`";
$lines[] = "- [ ] Tambahkan batas maksimal referral bonus per member (misal: 10/bulan atau 50/lifetime)";
$lines[] = "- [ ] Tambahkan OTP verification nomor HP sebelum akun aktif";
$lines[] = "- [ ] Fix bug duplicate redemption di `redeemBySerialCode`";
$lines[] = "";

$md = implode("\n", $lines);
file_put_contents(__DIR__ . '/../docs/referral_fraud_report_20260428.md', $md);
echo "Done! MD file created.\n";
echo "Total chars: " . strlen($md) . "\n";
