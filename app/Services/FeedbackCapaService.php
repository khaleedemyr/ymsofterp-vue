<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * CAPA (Corrective & Preventive Action) untuk Customer Voice — struktur disimpan di feedback_cases.meta["capa"].
 */
class FeedbackCapaService
{
    /**
     * Template kosong (nilai default).
     *
     * @return array<string, mixed>
     */
    public function emptyTemplate(): array
    {
        return [
            'a' => [
                'complaint_date' => null,
                'complaint_time' => null,
                'guest_name' => null,
                'channel' => null,
                'channel_other' => null,
            ],
            'b' => [
                'types' => [],
                'types_other' => null,
                'description' => null,
            ],
            'c' => [
                'actions' => [],
                'actions_other' => null,
                'response_time_note' => null,
                'pic_user_id' => null,
            ],
            'd' => [
                'problem_statement' => null,
                'man' => null,
                'method' => null,
                'machine' => null,
                'material' => null,
                'measurement' => null,
                'environment' => null,
                'root_cause_summary' => null,
            ],
            'e' => [
                'action' => null,
                'pic_user_id' => null,
                'deadline' => null,
                'status' => 'open',
            ],
            'f' => [
                'action' => null,
                'improvement_areas' => [],
                'pic_user_id' => null,
                'timeline' => null,
                'kpi' => null,
            ],
            'g' => [
                'follow_up_date' => null,
                'verified_by_user_id' => null,
                'result' => null,
                'notes' => null,
            ],
            'h' => [
                'contacted' => null,
                'contact_methods' => [],
                'recovery_feedback' => null,
                'satisfaction' => null,
                'documented_severity' => null,
                'documented_impact' => [],
            ],
            'evidence' => [],
        ];
    }

    /**
     * Lampiran file (path relatif disk public). Item: id, path, original_name, mime, size, uploaded_at.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    public function sanitizeEvidenceList(array $items): array
    {
        $max = 20;
        $out = [];
        foreach (array_slice($items, 0, $max) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = isset($item['id']) ? trim((string) $item['id']) : '';
            $path = isset($item['path']) ? trim((string) $item['path']) : '';
            if ($id === '' || strlen($id) > 80 || $path === '') {
                continue;
            }
            if (! preg_match('#^feedback_case_capa/\d+/[^/]+$#', $path)) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'path' => $path,
                'original_name' => ($this->limitStr($item['original_name'] ?? null, 255)) ?: 'file',
                'mime' => $this->limitStr($item['mime'] ?? null, 120),
                'size' => isset($item['size']) ? max(0, (int) $item['size']) : null,
                'uploaded_at' => $this->limitStr($item['uploaded_at'] ?? null, 40),
            ];
        }

        return $out;
    }

    /**
     * Tambahkan URL publik untuk preview/download (tidak disimpan di DB).
     *
     * @param  array<string, mixed>  $capa
     * @return array<string, mixed>
     */
    public function decorateEvidenceUrls(array $capa): array
    {
        if (empty($capa['evidence']) || ! is_array($capa['evidence'])) {
            $capa['evidence'] = [];

            return $capa;
        }
        foreach ($capa['evidence'] as &$item) {
            if (! is_array($item)) {
                continue;
            }
            $path = isset($item['path']) ? (string) $item['path'] : '';
            $item['url'] = ($path !== '' && Storage::disk('public')->exists($path))
                ? Storage::disk('public')->url($path)
                : null;
        }
        unset($item);

        return $capa;
    }

    /**
     * Gabungkan data case / AI dengan yang sudah tersimpan user (stored CAPA menang).
     *
     * @param  array<string, mixed>|null  $stored  Isi meta["capa"]
     * @param  array<int, string>  $topics  Topics dari kolom feedback_cases.topics (decoded)
     * @return array<string, mixed>
     */
    public function buildForPresentation(?array $stored, object $case, array $topics): array
    {
        $base = $this->emptyTemplate();
        $suggestions = $this->suggestFromCase($case, $topics);
        $merged = $this->deepMerge($base, $suggestions);
        if (is_array($stored) && $stored !== []) {
            $merged = $this->deepMerge($merged, $this->sanitizeCapa($stored));
        }

        return $merged;
    }

    /**
     * Apakah meta["capa"] punya isian nyata dari user (bukan hanya struktur kosong / default).
     * Dipakai badge di daftar Command Center tanpa memuat seluruh form.
     *
     * @param  array<string, mixed>|null  $stored  Isi meta["capa"] mentah
     */
    /**
     * Status verifikasi bagian G dari meta CAPA tersimpan (bukan merge presentasi).
     *
     * @return array{state: 'none'|'pending'|'done', result: 'effective'|'not_effective'|null}
     */
    public function storedCapaVerificationState(?array $storedCapa): array
    {
        if ($storedCapa === null || ! is_array($storedCapa)) {
            return ['state' => 'none', 'result' => null];
        }
        $g = isset($storedCapa['g']) && is_array($storedCapa['g']) ? $storedCapa['g'] : [];
        $verifierRaw = $g['verified_by_user_id'] ?? null;
        $verifierId = ($verifierRaw !== null && $verifierRaw !== '')
            ? (int) $verifierRaw
            : 0;
        $rawResult = isset($g['result']) ? strtolower(trim((string) $g['result'])) : '';
        $result = in_array($rawResult, ['effective', 'not_effective'], true) ? $rawResult : null;

        if ($result !== null) {
            return ['state' => 'done', 'result' => $result];
        }
        if ($verifierId > 0) {
            return ['state' => 'pending', 'result' => null];
        }

        return ['state' => 'none', 'result' => null];
    }

    public function storedCapaHasUserInput(?array $stored): bool
    {
        if ($stored === null || $stored === []) {
            return false;
        }

        try {
            $c = $this->sanitizeCapa($stored);
        } catch (\Throwable) {
            return true;
        }

        if (($c['evidence'] ?? []) !== []) {
            return true;
        }

        $a = $c['a'] ?? [];
        foreach (['complaint_date', 'complaint_time', 'guest_name', 'channel', 'channel_other'] as $k) {
            $v = $a[$k] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                return true;
            }
        }

        $b = $c['b'] ?? [];
        if (count($b['types'] ?? []) > 0) {
            return true;
        }
        foreach (['types_other', 'description'] as $k) {
            $v = $b[$k] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                return true;
            }
        }

        $secC = $c['c'] ?? [];
        if (count($secC['actions'] ?? []) > 0) {
            return true;
        }
        foreach (['actions_other', 'response_time_note'] as $k) {
            $v = $secC[$k] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                return true;
            }
        }
        if (! empty($secC['pic_user_id'])) {
            return true;
        }

        $d = $c['d'] ?? [];
        foreach (['problem_statement', 'man', 'method', 'machine', 'material', 'measurement', 'environment', 'root_cause_summary'] as $k) {
            $v = $d[$k] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                return true;
            }
        }

        $e = $c['e'] ?? [];
        foreach (['action', 'deadline'] as $k) {
            $v = $e[$k] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                return true;
            }
        }
        if (! empty($e['pic_user_id'])) {
            return true;
        }
        $est = strtolower((string) ($e['status'] ?? 'open'));
        if (in_array($est, ['on_progress', 'closed'], true)) {
            return true;
        }

        $f = $c['f'] ?? [];
        if (count($f['improvement_areas'] ?? []) > 0) {
            return true;
        }
        foreach (['action', 'timeline', 'kpi'] as $k) {
            $v = $f[$k] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                return true;
            }
        }
        if (! empty($f['pic_user_id'])) {
            return true;
        }

        $g = $c['g'] ?? [];
        foreach (['follow_up_date', 'notes'] as $k) {
            $v = $g[$k] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                return true;
            }
        }
        if (! empty($g['verified_by_user_id']) || ! empty($g['result'])) {
            return true;
        }

        $h = $c['h'] ?? [];
        if (! empty($h['contacted']) || count($h['contact_methods'] ?? []) > 0 || ! empty($h['satisfaction'])) {
            return true;
        }
        $rf = $h['recovery_feedback'] ?? null;
        if ($rf !== null && trim((string) $rf) !== '') {
            return true;
        }
        if (! empty($h['documented_severity']) || count($h['documented_impact'] ?? []) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $input  Request CAPA (parsial diperbolehkan)
     * @return array<string, mixed>
     */
    public function sanitizeCapa(array $input): array
    {
        $empty = $this->emptyTemplate();
        $merged = $this->deepMerge($empty, $input);

        $merged['b']['types'] = $this->filterEnum($merged['b']['types'] ?? [], [
            'food_quality', 'service', 'cleanliness', 'waiting_time', 'billing', 'other',
        ]);
        $merged['c']['actions'] = $this->filterEnum($merged['c']['actions'] ?? [], [
            'apology', 'replace_product', 'refund_discount', 'escalate', 'other',
        ]);
        $merged['f']['improvement_areas'] = $this->filterEnum($merged['f']['improvement_areas'] ?? [], [
            'sop', 'training', 'equipment', 'manpower', 'system',
        ]);
        $merged['h']['contact_methods'] = $this->filterEnum($merged['h']['contact_methods'] ?? [], [
            'call', 'whatsapp', 'email',
        ]);
        $merged['h']['documented_impact'] = $this->filterEnum($merged['h']['documented_impact'] ?? [], [
            'reputasi', 'finansial', 'operasional',
        ]);

        if (isset($merged['e']['status'])) {
            $es = strtolower((string) $merged['e']['status']);
            $merged['e']['status'] = in_array($es, ['open', 'on_progress', 'closed'], true) ? $es : 'open';
        }

        $ch = strtolower((string) ($merged['a']['channel'] ?? ''));
        $merged['a']['channel'] = in_array($ch, ['dine_in', 'online_review', 'delivery', 'walk_in', 'other'], true) ? $ch : null;

        foreach (['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'] as $sec) {
            if (! isset($merged[$sec]) || ! is_array($merged[$sec])) {
                $merged[$sec] = $empty[$sec] ?? [];
            }
        }

        $merged['a']['guest_name'] = $this->limitStr($merged['a']['guest_name'] ?? null, 500);
        $merged['a']['channel_other'] = $this->limitStr($merged['a']['channel_other'] ?? null, 500);
        $merged['b']['types_other'] = $this->limitStr($merged['b']['types_other'] ?? null, 500);
        $merged['b']['description'] = $this->limitStr($merged['b']['description'] ?? null, 12000);
        $merged['d']['problem_statement'] = $this->limitStr($merged['d']['problem_statement'] ?? null, 4000);
        foreach (['man', 'method', 'machine', 'material', 'measurement', 'environment', 'root_cause_summary'] as $fk) {
            $merged['d'][$fk] = $this->limitStr($merged['d'][$fk] ?? null, 8000);
        }
        $merged['c']['actions_other'] = $this->limitStr($merged['c']['actions_other'] ?? null, 500);
        $merged['c']['response_time_note'] = $this->limitStr($merged['c']['response_time_note'] ?? null, 500);
        $merged['c']['pic_user_id'] = $this->sanitizeOptionalUserId($merged['c']['pic_user_id'] ?? null);

        $merged['e']['action'] = $this->limitStr($merged['e']['action'] ?? null, 4000);
        $merged['e']['pic_user_id'] = $this->sanitizeOptionalUserId($merged['e']['pic_user_id'] ?? null);
        $merged['e']['deadline'] = $this->limitStr($merged['e']['deadline'] ?? null, 40);

        $merged['f']['action'] = $this->limitStr($merged['f']['action'] ?? null, 4000);
        $merged['f']['pic_user_id'] = $this->sanitizeOptionalUserId($merged['f']['pic_user_id'] ?? null);
        $merged['f']['timeline'] = $this->limitStr($merged['f']['timeline'] ?? null, 500);
        $merged['f']['kpi'] = $this->limitStr($merged['f']['kpi'] ?? null, 4000);

        $merged['g']['follow_up_date'] = $this->limitStr($merged['g']['follow_up_date'] ?? null, 40);
        $merged['g']['verified_by_user_id'] = $this->sanitizeOptionalUserId($merged['g']['verified_by_user_id'] ?? null);
        $merged['g']['notes'] = $this->limitStr($merged['g']['notes'] ?? null, 4000);

        $merged['h']['recovery_feedback'] = $this->limitStr($merged['h']['recovery_feedback'] ?? null, 8000);

        $gr = strtolower((string) ($merged['g']['result'] ?? ''));
        $merged['g']['result'] = in_array($gr, ['effective', 'not_effective'], true) ? $gr : null;

        $hc = strtolower((string) ($merged['h']['contacted'] ?? ''));
        $merged['h']['contacted'] = in_array($hc, ['yes', 'no'], true) ? $hc : null;

        $hsat = strtolower((string) ($merged['h']['satisfaction'] ?? ''));
        $merged['h']['satisfaction'] = in_array($hsat, ['satisfied', 'neutral', 'unsatisfied'], true) ? $hsat : null;

        $ds = strtolower((string) ($merged['h']['documented_severity'] ?? ''));
        $merged['h']['documented_severity'] = in_array($ds, ['minor', 'major', 'critical'], true) ? $ds : null;

        $merged['evidence'] = $this->sanitizeEvidenceList($merged['evidence'] ?? []);

        unset(
            $merged['a']['pic_receiver_name'],
            $merged['c']['pic'],
            $merged['e']['pic'],
            $merged['f']['pic'],
            $merged['g']['verified_by'],
            $merged['d']['use_fishbone'],
        );

        return $merged;
    }

    private function sanitizeOptionalUserId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = (int) $raw;
        if ($id <= 0) {
            return null;
        }

        return DB::table('users')->where('id', $id)->exists() ? $id : null;
    }

    /**
     * Gabungkan CAPA baru ke meta JSON existing (pertahankan follow_up_target, impact, customer_email).
     *
     * @param  array<string, mixed>  $meta  Meta decoded
     * @param  array<string, mixed>  $capa  CAPA dari request (sudah sanitize)
     * @return array<string, mixed>
     */
    public function mergeIntoMeta(array $meta, array $capa): array
    {
        $meta['capa'] = $capa;

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private function deepMerge(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            if ($k === 'evidence') {
                $base[$k] = is_array($v) ? $v : [];

                continue;
            }
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = $this->deepMerge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }

        return $base;
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<int, string>  $allowed
     * @return array<int, string>
     */
    private function filterEnum(array $values, array $allowed): array
    {
        $set = array_flip($allowed);
        $out = [];
        foreach ($values as $v) {
            $s = strtolower(trim((string) $v));
            if ($s !== '' && isset($set[$s])) {
                $out[$s] = $s;
            }
        }

        return array_values($out);
    }

    private function limitStr(?string $s, int $max): ?string
    {
        if ($s === null) {
            return null;
        }
        $t = trim($s);

        return $t === '' ? null : mb_substr($t, 0, $max);
    }

    /**
     * @param  array<int, string>  $topics
     * @return array<string, mixed>
     */
    private function suggestFromCase(object $case, array $topics): array
    {
        $out = [
            'a' => [],
            'b' => [],
            'h' => [],
        ];

        try {
            if (! empty($case->event_at)) {
                $dt = Carbon::parse((string) $case->event_at);
                $out['a']['complaint_date'] = $dt->format('Y-m-d');
                $out['a']['complaint_time'] = $dt->format('H:i');
            }
        } catch (\Throwable) {
        }

        $out['a']['channel'] = $this->guessChannel((string) ($case->source_type ?? ''));

        $mappedTypes = $this->topicsToComplaintTypes($topics);
        if ($mappedTypes !== []) {
            $out['b']['types'] = $mappedTypes;
        }

        $author = isset($case->author_name) ? trim((string) $case->author_name) : '';
        if ($author !== '') {
            $out['a']['guest_name'] = $author;
        }

        $raw = isset($case->raw_text) ? trim((string) $case->raw_text) : '';
        if ($raw !== '') {
            $out['b']['description'] = $raw;
            $lines = preg_split("/\r\n|\r|\n/", $raw);
            $first = trim((string) ($lines[0] ?? ''));
            if ($first !== '') {
                $out['d']['problem_statement'] = mb_substr($first, 0, 500);
            }
        }

        $sev = strtolower(trim((string) ($case->severity ?? '')));
        if (in_array($sev, ['minor', 'major', 'critical', 'positive', 'neutral'], true)) {
            $out['h']['documented_severity'] = in_array($sev, ['minor', 'major', 'critical'], true) ? $sev : null;
        }

        return $out;
    }

    private function guessChannel(string $sourceType): ?string
    {
        return match ($sourceType) {
            'google_review', 'instagram_comment' => 'online_review',
            'guest_comment' => 'dine_in',
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $topics
     * @return array<int, string>
     */
    private function topicsToComplaintTypes(array $topics): array
    {
        $map = [
            'food_quality' => 'food_quality',
            'service' => 'service',
            'hygiene' => 'cleanliness',
            'cleanliness' => 'cleanliness',
            'wait_time' => 'waiting_time',
            'price' => 'billing',
            'parking' => 'other',
            'noise' => 'other',
            'reservation' => 'other',
            'ambiance' => 'other',
            'portion' => 'food_quality',
            'other' => 'other',
        ];

        $out = [];
        foreach ($topics as $t) {
            $k = strtolower(trim((string) $t));
            if ($k === '') {
                continue;
            }
            if (isset($map[$k])) {
                $out[$map[$k]] = $map[$k];
            }
        }

        return array_values(array_unique(array_values($out)));
    }

    /**
     * Siapkan lampiran gambar CAPA untuk disematkan di PDF (data URI, untuk DomPDF).
     *
     * @param  array<string, mixed>  $capa  CAPA yang sudah disanitasi / dipresentasikan (berisi evidence).
     * @return list<array{label: string, src: string|null, note: string|null}>
     */
    public function pdfEmbedCapaEvidenceImages(array $capa): array
    {
        $evidence = isset($capa['evidence']) && is_array($capa['evidence']) ? $capa['evidence'] : [];
        if ($evidence === []) {
            return [];
        }

        $maxBytes = 5 * 1024 * 1024;
        $allowedMimes = [
            'image/jpeg' => true,
            'image/png' => true,
            'image/gif' => true,
            'image/webp' => true,
        ];

        $out = [];
        foreach ($evidence as $item) {
            if (! is_array($item)) {
                continue;
            }
            $path = isset($item['path']) ? trim((string) $item['path']) : '';
            $original = (string) ($item['original_name'] ?? 'file');
            if ($path === '' || ! preg_match('#^feedback_case_capa/\d+/[^/]+$#', $path)) {
                continue;
            }

            $mime = strtolower(trim((string) ($item['mime'] ?? '')));
            if ($mime === 'image/jpg') {
                $mime = 'image/jpeg';
            }
            if ($mime === '') {
                $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    default => '',
                };
            }

            if ($mime === '' || ! isset($allowedMimes[$mime])) {
                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                $out[] = ['label' => $original, 'src' => null, 'note' => 'Berkas tidak ditemukan di server.'];

                continue;
            }

            try {
                $size = (int) Storage::disk('public')->size($path);
            } catch (\Throwable) {
                $out[] = ['label' => $original, 'src' => null, 'note' => 'Tidak dapat membaca berkas.'];

                continue;
            }

            if ($size > $maxBytes) {
                $out[] = [
                    'label' => $original,
                    'src' => null,
                    'note' => 'Gambar terlalu besar untuk disematkan di PDF (maks. 5 MB). Buka lampiran di aplikasi.',
                ];

                continue;
            }

            try {
                $binary = Storage::disk('public')->get($path);
            } catch (\Throwable) {
                $binary = false;
            }
            if ($binary === false || $binary === '') {
                $out[] = ['label' => $original, 'src' => null, 'note' => 'Tidak dapat membaca berkas.'];

                continue;
            }

            $out[] = [
                'label' => $original,
                'src' => 'data:'.$mime.';base64,'.base64_encode($binary),
                'note' => null,
            ];
        }

        return $out;
    }
}
