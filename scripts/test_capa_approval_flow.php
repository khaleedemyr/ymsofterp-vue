<?php

/**
 * Uji end-to-end CAPA approval flow (CLI, tanpa HTTP).
 * Usage: php scripts/test_capa_approval_flow.php [--case-id=N] [--cleanup]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FeedbackCapaApprovalFlow;
use App\Services\FeedbackCapaApprovalService;
use App\Services\FeedbackCapaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$caseIdArg = null;
$cleanup = in_array('--cleanup', $argv ?? [], true);
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--case-id=')) {
        $caseIdArg = (int) substr($arg, 10);
    }
}

function out(string $label, mixed $value = null): void
{
    if ($value === null) {
        echo $label.PHP_EOL;

        return;
    }
    $s = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    echo "[{$label}] {$s}".PHP_EOL;
}

function fail(string $msg): void
{
    out('FAIL', $msg);
    exit(1);
}

out('=== CAPA Approval Flow Test ===');

if (! Schema::hasTable('feedback_capa_approval_flows')) {
    fail('Tabel feedback_capa_approval_flows belum ada. Jalankan database/sql/create_feedback_capa_approval_flows.sql dulu.');
}
out('OK', 'Tabel feedback_capa_approval_flows ada');

/** @var FeedbackCapaApprovalService $approvalService */
$approvalService = app(FeedbackCapaApprovalService::class);
/** @var FeedbackCapaService $capaService */
$capaService = app(FeedbackCapaService::class);

$submitter = DB::table('users')->where('status', 'A')->orderBy('id')->first(['id', 'nama_lengkap']);
if (! $submitter) {
    fail('Tidak ada user aktif untuk test.');
}

$approver1 = DB::table('users')
    ->where('status', 'A')
    ->where('id', '!=', $submitter->id)
    ->orderBy('id')
    ->first(['id', 'nama_lengkap']);
$approver2 = DB::table('users')
    ->where('status', 'A')
    ->whereNotIn('id', [$submitter->id, $approver1->id ?? 0])
    ->orderBy('id')
    ->first(['id', 'nama_lengkap']);

if (! $approver1 || ! $approver2) {
    fail('Butuh minimal 3 user aktif (submitter + 2 approver).');
}

out('Submitter', "#{$submitter->id} {$submitter->nama_lengkap}");
out('Approver L1', "#{$approver1->id} {$approver1->nama_lengkap}");
out('Approver L2', "#{$approver2->id} {$approver2->nama_lengkap}");

$caseId = $caseIdArg;
if ($caseId <= 0) {
    $caseId = (int) DB::table('feedback_cases')->orderByDesc('id')->value('id');
}
if ($caseId <= 0) {
    fail('Tidak ada feedback_cases untuk test.');
}

$caseRow = DB::table('feedback_cases')->where('id', $caseId)->first();
if (! $caseRow) {
    fail("Case #{$caseId} tidak ditemukan.");
}
out('Case', "#{$caseId} — ".($caseRow->summary_id ?? '(no summary)'));

$division = 'service';
$testPrefix = '__capa_approval_test__';

// Bersihkan flow test sebelumnya pada case ini (divisi service)
FeedbackCapaApprovalFlow::query()
    ->where('feedback_case_id', $caseId)
    ->where('division', $division)
    ->delete();

// Simpan CAPA minimal via meta (simulasi saveCapa)
$meta = [];
if (! empty($caseRow->meta)) {
    $meta = json_decode((string) $caseRow->meta, true) ?: [];
}
$capa = $capaService->sanitizeCapa([
    'a' => [
        'complaint_date' => date('Y-m-d'),
        'complaint_time' => date('H:i'),
        'reported_by' => $testPrefix.'reporter',
        'reported_by_position' => 'QA Test',
    ],
    'b' => [
        'types' => ['service'],
        'description' => $testPrefix.' deskripsi uji approval',
        'area_section' => 'Dining',
    ],
    'c' => ['actions' => ['apology']],
    'e' => ['action' => 'Follow up tamu', 'status' => 'open'],
    'f' => ['action' => 'Training SOP', 'timeline' => date('Y-m-d', strtotime('+7 days'))],
]);
$meta['capa_divisions'] = $meta['capa_divisions'] ?? [];
$meta['capa_divisions'][$division] = $capa;
$meta['capa'] = $capa;
$meta['capa_active_division'] = $division;

DB::table('feedback_cases')->where('id', $caseId)->update([
    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
    'updated_at' => now(),
]);
out('OK', 'CAPA test data disimpan di meta');

// 1) Submit approval 2 level
$submit = $approvalService->submitForApproval(
    $caseId,
    $division,
    [(int) $approver1->id, (int) $approver2->id],
    (int) $submitter->id
);
if (! ($submit['success'] ?? false)) {
    fail('submitForApproval gagal: '.($submit['message'] ?? '?'));
}
out('OK', 'submitForApproval: '.$submit['message']);

$summary = $approvalService->divisionSummary($caseId, $division);
if (($summary['state'] ?? '') !== 'pending') {
    fail('State setelah submit harus pending, dapat: '.($summary['state'] ?? 'null'));
}
if ((int) ($summary['next_approver_id'] ?? 0) !== (int) $approver1->id) {
    fail('Next approver harus L1 #'.$approver1->id.', dapat: '.($summary['next_approver_id'] ?? 'null'));
}
out('OK', 'State pending, next approver = L1');

// 2) L2 tidak boleh approve dulu
$early = $approvalService->approve($caseId, $division, (int) $approver2->id, true, 'early', false);
if ($early['success'] ?? false) {
    fail('Approver L2 seharusnya ditolak sebelum L1 approve.');
}
out('OK', 'L2 ditolak sebelum L1: '.($early['message'] ?? ''));

// 3) L1 approve
$a1 = $approvalService->approve($caseId, $division, (int) $approver1->id, true, 'OK level 1', false);
if (! ($a1['success'] ?? false)) {
    fail('L1 approve gagal: '.($a1['message'] ?? '?'));
}
$summary = $approvalService->divisionSummary($caseId, $division);
if ((int) ($summary['next_approver_id'] ?? 0) !== (int) $approver2->id) {
    fail('Setelah L1, next harus L2 #'.$approver2->id);
}
out('OK', 'L1 approved, menunggu L2');

// 4) Pending list untuk L2
$pendingL2 = $approvalService->pendingItemsForUser((int) $approver2->id, false);
$foundL2 = collect($pendingL2)->first(fn ($x) => (int) ($x['id'] ?? 0) === $caseId && in_array($division, $x['pending_divisions'] ?? [], true));
if (! $foundL2) {
    fail('Case tidak muncul di pendingItemsForUser approver L2.');
}
out('OK', 'pendingItemsForUser(L2) memuat case ini');

// 5) L2 approve → selesai
$a2 = $approvalService->approve($caseId, $division, (int) $approver2->id, true, 'Final OK', false);
if (! ($a2['success'] ?? false)) {
    fail('L2 approve gagal: '.($a2['message'] ?? '?'));
}
$summary = $approvalService->divisionSummary($caseId, $division);
if (($summary['state'] ?? '') !== 'approved') {
    fail('State akhir harus approved, dapat: '.($summary['state'] ?? 'null'));
}
out('OK', 'Semua level approved');

// 6) Resubmit ditolak
$resubmit = $approvalService->submitForApproval($caseId, $division, [(int) $approver1->id], (int) $submitter->id);
if ($resubmit['success'] ?? false) {
    fail('Resubmit setelah full approved seharusnya ditolak.');
}
out('OK', 'Resubmit ditolak setelah approved: '.($resubmit['message'] ?? ''));

// 7) Test reject path pada case yang sama (reset flows)
FeedbackCapaApprovalFlow::query()
    ->where('feedback_case_id', $caseId)
    ->where('division', $division)
    ->delete();

$approvalService->submitForApproval($caseId, $division, [(int) $approver1->id], (int) $submitter->id);
$reject = $approvalService->approve($caseId, $division, (int) $approver1->id, false, 'Tidak lengkap', false);
if (! ($reject['success'] ?? false)) {
    fail('Reject gagal: '.($reject['message'] ?? '?'));
}
$summary = $approvalService->divisionSummary($caseId, $division);
if (($summary['state'] ?? '') !== 'rejected') {
    fail('State setelah reject harus rejected.');
}
out('OK', 'Reject path OK, can_resubmit='.(($summary['can_resubmit'] ?? false) ? 'true' : 'false'));

$reAfterReject = $approvalService->submitForApproval(
    $caseId,
    $division,
    [(int) $approver1->id, (int) $approver2->id],
    (int) $submitter->id
);
if (! ($reAfterReject['success'] ?? false)) {
    fail('Resubmit setelah reject gagal: '.($reAfterReject['message'] ?? '?'));
}
out('OK', 'Resubmit setelah reject berhasil');

if ($cleanup) {
    FeedbackCapaApprovalFlow::query()
        ->where('feedback_case_id', $caseId)
        ->where('division', $division)
        ->delete();
    out('Cleanup', 'Approval flows divisi service dihapus (--cleanup)');
}

out('=== SEMUA TEST LULUS ===');
out('Case ID untuk uji manual UI', (string) $caseId);
out('URL', '/customer-voice-command-center?show_all=1&open_case='.$caseId.'&capa_approval=1');
