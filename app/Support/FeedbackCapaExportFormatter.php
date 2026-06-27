<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Meratakan struktur CAPA (meta + merge presentasi) menjadi baris untuk PDF/Excel.
 */
class FeedbackCapaExportFormatter
{
    /**
     * @param  array<string, mixed>  $case  Output presentVoiceCaseRow()
     * @param  array<string, mixed>  $capa  presentVoiceCaseRow()['capa']
     * @return Collection<int, array{bagian: string, field: string, nilai: string}>
     */
    public function flatten(array $case, array $capa): Collection
    {
        $ids = $this->collectCapaUserIds($capa);
        $userRows = $ids === []
            ? collect()
            : User::whereIn('id', $ids)->get(['id', 'nama_lengkap', 'nama_jabatan']);
        $names = $userRows->pluck('nama_lengkap', 'id')->all();
        $jabatans = $userRows->pluck('nama_jabatan', 'id')->all();

        $rows = [];

        $this->push($rows, 'Ringkas kasus', 'Case ID', (string) ($case['id'] ?? ''));
        $this->push($rows, 'Ringkas kasus', 'Outlet', (string) ($case['nama_outlet'] ?? ''));
        $this->push($rows, 'Ringkas kasus', 'Source', $this->sourceLabel((string) ($case['source_type'] ?? '')));
        $this->push($rows, 'Ringkas kasus', 'Tamu / penulis', (string) ($case['author_name'] ?? ''));
        $this->push($rows, 'Ringkas kasus', 'Kontak', (string) ($case['customer_contact'] ?? ''));
        $this->push($rows, 'Ringkas kasus', 'Waktu event', $this->fmtDate($case['event_at'] ?? null));
        $this->push($rows, 'Ringkas kasus', 'Severity (AI)', (string) ($case['severity'] ?? ''));
        $this->push($rows, 'Ringkas kasus', 'Status kasus', (string) ($case['status'] ?? ''));
        $this->push($rows, 'Ringkas kasus', 'PIC (dasbor)', (string) ($case['assigned_to_name'] ?? ''));
        $topics = $case['complaint_type_labels'] ?? [];
        if (is_array($topics) && $topics !== []) {
            $this->push($rows, 'Ringkas kasus', 'Jenis komplain', implode(', ', $topics));
        }
        $this->push($rows, 'Ringkas kasus', 'Ringkasan AI', (string) ($case['summary_id'] ?? ''));
        $this->push($rows, 'Ringkas kasus', 'Teks komentar', Str::limit(preg_replace('/\s+/u', ' ', (string) ($case['raw_text'] ?? '')), 2000));

        $evidence = is_array($capa['evidence'] ?? null) ? $capa['evidence'] : [];
        if ($evidence !== []) {
            $this->push($rows, 'Lampiran', '(jumlah file)', (string) count($evidence));
            foreach ($evidence as $idx => $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                $n = (int) $idx + 1;
                $orig = (string) ($ev['original_name'] ?? 'file');
                $url = (string) ($ev['url'] ?? '');
                $line = $orig;
                if ($url !== '') {
                    $line .= ' · '.$url;
                }
                $this->push($rows, 'Lampiran', 'File #'.$n, $line);
            }
        } else {
            $this->push($rows, 'Lampiran', 'File', '—');
        }

        $a = is_array($capa['a'] ?? null) ? $capa['a'] : [];
        $this->push($rows, '1. General Information', 'Tanggal', $this->str($a['complaint_date'] ?? null));
        $this->push($rows, '1. General Information', 'Waktu', $this->str($a['complaint_time'] ?? null));
        $this->push($rows, '1. General Information', 'Reported By', $this->str($a['reported_by'] ?? null));
        $this->push($rows, '1. General Information', 'Position', $this->str($a['reported_by_position'] ?? null));
        $this->push($rows, '1. General Information', 'Nama tamu (legacy)', $this->str($a['guest_name'] ?? null));
        $this->push($rows, '1. General Information', 'Channel', $this->channelLabel(isset($a['channel']) ? (string) $a['channel'] : null));

        $b = is_array($capa['b'] ?? null) ? $capa['b'] : [];
        $types = isset($b['types']) && is_array($b['types']) ? $b['types'] : [];
        $this->push($rows, '2. Issue Details', 'Type of Issue', $this->mapComplaintTypes($types));
        $this->push($rows, '2. Issue Details', 'Description', $this->str($b['description'] ?? null));
        $this->push($rows, '2. Issue Details', 'Area / Section', $this->str($b['area_section'] ?? null));
        $involvedIds = is_array($b['involved_party_user_ids'] ?? null) ? $b['involved_party_user_ids'] : [];
        $witnessIds = is_array($b['witness_user_ids'] ?? null) ? $b['witness_user_ids'] : [];
        $involvedVal = $this->formatUserIdList($involvedIds, $names, $jabatans);
        if ($involvedVal === '—') {
            $involvedVal = $this->str($b['involved_parties'] ?? null);
        }
        $witnessVal = $this->formatUserIdList($witnessIds, $names, $jabatans);
        if ($witnessVal === '—') {
            $witnessVal = $this->str($b['witnesses'] ?? null);
        }
        $this->push($rows, '2. Issue Details', 'Involved Parties', $involvedVal);
        $this->push($rows, '2. Issue Details', 'Witness(es)', $witnessVal);

        $c = is_array($capa['c'] ?? null) ? $capa['c'] : [];
        $actions = isset($c['actions']) && is_array($c['actions']) ? $c['actions'] : [];
        $this->push($rows, '3. Action Taken', 'Immediate Action', $this->mapImmediateActions($actions));
        $this->push($rows, '3. Action Taken', 'Immediate (lainnya)', $this->str($c['actions_other'] ?? null));

        $e = is_array($capa['e'] ?? null) ? $capa['e'] : [];
        $this->push($rows, '3. Action Taken', 'Follow-Up Action', $this->str($e['action'] ?? null));
        $this->push($rows, '3. Action Taken', 'Status', $this->correctiveStatusLabel(isset($e['status']) ? (string) $e['status'] : null));
        $picE = isset($e['pic_user_id']) ? (int) $e['pic_user_id'] : 0;
        $this->push($rows, '3. Action Taken', 'Follow Up By', $picE > 0 ? ($names[$picE] ?? ('#'.$picE)) : '—');

        $f = is_array($capa['f'] ?? null) ? $capa['f'] : [];
        $this->push($rows, '4. Preventive Measures', 'Corrective Action Plan', $this->str($f['action'] ?? null));
        $picF = isset($f['pic_user_id']) ? (int) $f['pic_user_id'] : 0;
        $this->push($rows, '4. Preventive Measures', 'Responsible Person', $picF > 0 ? ($names[$picF] ?? ('#'.$picF)) : '—');
        $this->push($rows, '4. Preventive Measures', 'Target Completion Date', $this->str($f['timeline'] ?? null));

        $d = is_array($capa['d'] ?? null) ? $capa['d'] : [];
        if ($this->str($d['problem_statement'] ?? null) !== '—') {
            $this->push($rows, 'Legacy — Root cause', 'Masalah', $this->str($d['problem_statement'] ?? null));
            $this->push($rows, 'Legacy — Root cause', 'Akar masalah utama', $this->str($d['root_cause_summary'] ?? null));
        }

        $g = is_array($capa['g'] ?? null) ? $capa['g'] : [];
        if ($this->str($g['result'] ?? null) !== '—' || ! empty($g['verified_by_user_id'])) {
            $ver = isset($g['verified_by_user_id']) ? (int) $g['verified_by_user_id'] : 0;
            $this->push($rows, 'Legacy — Verifikasi', 'Verifikator', $ver > 0 ? ($names[$ver] ?? ('#'.$ver)) : '—');
            $this->push($rows, 'Legacy — Verifikasi', 'Hasil', $this->verificationResultLabel($g['result'] ?? null));
        }

        return collect($rows);
    }

    /**
     * @param  array<int, array{bagian: string, field: string, nilai: string}>  $rows
     */
    private function push(array &$rows, string $bagian, string $field, string $nilai): void
    {
        $rows[] = [
            'bagian' => $bagian,
            'field' => $field,
            'nilai' => $nilai === '' ? '—' : $nilai,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function collectCapaUserIds(array $capa): array
    {
        $ids = [];
        foreach (['c', 'e', 'f'] as $sec) {
            $s = $capa[$sec] ?? null;
            if (is_array($s) && isset($s['pic_user_id']) && $s['pic_user_id'] !== null && $s['pic_user_id'] !== '') {
                $ids[] = (int) $s['pic_user_id'];
            }
        }
        $g = $capa['g'] ?? null;
        if (is_array($g) && isset($g['verified_by_user_id']) && $g['verified_by_user_id'] !== null && $g['verified_by_user_id'] !== '') {
            $ids[] = (int) $g['verified_by_user_id'];
        }
        $b = $capa['b'] ?? null;
        if (is_array($b)) {
            foreach (['involved_party_user_ids', 'witness_user_ids'] as $key) {
                if (! is_array($b[$key] ?? null)) {
                    continue;
                }
                foreach ($b[$key] as $uid) {
                    $ids[] = (int) $uid;
                }
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }

    /**
     * @param  array<int|string>  $ids
     * @param  array<int, string>  $names
     * @param  array<int, string|null>  $jabatans
     */
    private function formatUserIdList(array $ids, array $names, array $jabatans): string
    {
        $parts = [];
        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($names[$id] ?? ''));
            if ($name === '') {
                $name = '#'.$id;
            }
            $jab = trim((string) ($jabatans[$id] ?? ''));
            $parts[] = $jab !== '' ? $name.' ('.$jab.')' : $name;
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    }

    private function str(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'Ya' : 'Tidak';
        }
        if (is_array($v)) {
            return json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        return trim((string) $v);
    }

    private function fmtDate(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        try {
            return (string) $v;
        } catch (\Throwable) {
            return '—';
        }
    }

    private function sourceLabel(string $s): string
    {
        return match ($s) {
            'google_review' => 'Google Review',
            'instagram_comment' => 'Instagram',
            'guest_comment' => 'Guest Comment',
            default => $s !== '' ? $s : '—',
        };
    }

    private function channelLabel(?string $ch): string
    {
        if ($ch === null || $ch === '') {
            return '—';
        }

        return match ($ch) {
            'dine_in' => 'Dine-in',
            'online_review' => 'Online Review',
            'delivery' => 'Delivery',
            'walk_in' => 'Walk-in',
            'other' => 'Lainnya',
            'google_review' => 'Google Review',
            'instagram_comment' => 'Instagram',
            'guest_comment' => 'Guest Comment',
            default => $ch,
        };
    }

    /**
     * @param  array<int, mixed>  $types
     */
    private function mapComplaintTypes(array $types): string
    {
        $map = [
            'food_quality' => 'Food Quality',
            'service' => 'Service',
            'cleanliness' => 'Cleanliness',
            'waiting_time' => 'Waiting Time',
            'billing' => 'Billing',
            'other' => 'Others',
        ];
        $out = [];
        foreach ($types as $t) {
            $k = strtolower(trim((string) $t));
            $out[] = $map[$k] ?? $k;
        }

        return $out !== [] ? implode(', ', $out) : '—';
    }

    /**
     * @param  array<int, mixed>  $actions
     */
    private function mapImmediateActions(array $actions): string
    {
        $map = [
            'apology' => 'Apology diberikan',
            'replace_product' => 'Replace product',
            'refund_discount' => 'Refund / Discount',
            'escalate' => 'Escalate ke Supervisor / Manager',
            'other' => 'Lainnya',
        ];
        $out = [];
        foreach ($actions as $t) {
            $k = strtolower(trim((string) $t));
            $out[] = $map[$k] ?? $k;
        }

        return $out !== [] ? implode(', ', $out) : '—';
    }

    /**
     * @param  array<int, mixed>  $areas
     */
    private function mapImprovementAreas(array $areas): string
    {
        $map = [
            'sop' => 'SOP',
            'training' => 'Training',
            'equipment' => 'Equipment',
            'manpower' => 'Manpower',
            'system' => 'System',
        ];
        $out = [];
        foreach ($areas as $t) {
            $k = strtolower(trim((string) $t));
            $out[] = $map[$k] ?? $k;
        }

        return $out !== [] ? implode(', ', $out) : '—';
    }

    /**
     * @param  array<int, mixed>  $methods
     */
    private function mapContactMethods(array $methods): string
    {
        $map = [
            'call' => 'Call',
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
        ];
        $out = [];
        foreach ($methods as $t) {
            $k = strtolower(trim((string) $t));
            $out[] = $map[$k] ?? $k;
        }

        return $out !== [] ? implode(', ', $out) : '—';
    }

    /**
     * @param  array<int, mixed>  $impact
     */
    private function mapDocumentedImpact(array $impact): string
    {
        $map = [
            'reputasi' => 'Reputasi',
            'finansial' => 'Finansial',
            'operasional' => 'Operasional',
        ];
        $out = [];
        foreach ($impact as $t) {
            $k = strtolower(trim((string) $t));
            $out[] = $map[$k] ?? $k;
        }

        return $out !== [] ? implode(', ', $out) : '—';
    }

    private function correctiveStatusLabel(?string $s): string
    {
        if ($s === null || $s === '') {
            return '—';
        }

        return match (strtolower($s)) {
            'open' => 'Open — terbuka',
            'on_progress' => 'On progress — berjalan',
            'closed' => 'Closed — selesai',
            default => $s,
        };
    }

    private function verificationResultLabel(mixed $v): string
    {
        $s = $v === null ? '' : strtolower(trim((string) $v));
        if ($s === '') {
            return '—';
        }

        return match ($s) {
            'effective' => 'Effective — efektif',
            'not_effective' => 'Not effective — tidak efektif',
            default => (string) $v,
        };
    }

    private function contactedLabel(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        $s = strtolower(trim((string) $v));

        return match ($s) {
            'yes' => 'Ya',
            'no' => 'Tidak',
            default => (string) $v,
        };
    }

    private function satisfactionLabel(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        $s = strtolower(trim((string) $v));

        return match ($s) {
            'satisfied' => 'Satisfied — puas',
            'neutral' => 'Neutral — netral',
            'unsatisfied' => 'Unsatisfied — tidak puas',
            default => (string) $v,
        };
    }

    /**
     * Mengelompokkan baris berturut dengan nama bagian sama — untuk rowspan (PDF) / merge cells (Excel).
     *
     * @param  Collection<int, array{bagian: string, field: string, nilai: string}>  $flatRows
     * @return array<int, array{bagian: string, items: list<array{field: string, nilai: string}>}>
     */
    public function groupConsecutiveBagian(Collection $flatRows): array
    {
        $groups = [];
        $current = null;
        foreach ($flatRows as $row) {
            $bagian = (string) ($row['bagian'] ?? '');
            if ($current === null || $current['bagian'] !== $bagian) {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = ['bagian' => $bagian, 'items' => []];
            }
            $current['items'][] = [
                'field' => (string) ($row['field'] ?? ''),
                'nilai' => (string) ($row['nilai'] ?? ''),
            ];
        }
        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }
}
