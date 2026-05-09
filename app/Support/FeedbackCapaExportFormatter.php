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
        $names = $ids === []
            ? []
            : User::whereIn('id', $ids)->pluck('nama_lengkap', 'id')->all();

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
        $this->push($rows, 'A. Informasi umum', 'Tanggal complaint', $this->str($a['complaint_date'] ?? null));
        $this->push($rows, 'A. Informasi umum', 'Waktu complaint', $this->str($a['complaint_time'] ?? null));
        $this->push($rows, 'A. Informasi umum', 'Nama tamu (form)', $this->str($a['guest_name'] ?? null));
        $this->push($rows, 'A. Informasi umum', 'Channel', $this->channelLabel(isset($a['channel']) ? (string) $a['channel'] : null));
        $this->push($rows, 'A. Informasi umum', 'Channel (lainnya)', $this->str($a['channel_other'] ?? null));

        $b = is_array($capa['b'] ?? null) ? $capa['b'] : [];
        $types = isset($b['types']) && is_array($b['types']) ? $b['types'] : [];
        $this->push($rows, 'B. Detail complaint', 'Jenis complaint', $this->mapComplaintTypes($types));
        $this->push($rows, 'B. Detail complaint', 'Jenis (lainnya)', $this->str($b['types_other'] ?? null));
        $this->push($rows, 'B. Detail complaint', 'Deskripsi complaint', $this->str($b['description'] ?? null));

        $c = is_array($capa['c'] ?? null) ? $capa['c'] : [];
        $actions = isset($c['actions']) && is_array($c['actions']) ? $c['actions'] : [];
        $this->push($rows, 'C. Immediate action', 'Tindakan langsung', $this->mapImmediateActions($actions));
        $this->push($rows, 'C. Immediate action', 'Tindakan (lainnya)', $this->str($c['actions_other'] ?? null));
        $this->push($rows, 'C. Immediate action', 'Waktu respon', $this->str($c['response_time_note'] ?? null));
        $picC = isset($c['pic_user_id']) ? (int) $c['pic_user_id'] : 0;
        $this->push($rows, 'C. Immediate action', 'PIC', $picC > 0 ? ($names[$picC] ?? ('#'.$picC)) : '—');

        $d = is_array($capa['d'] ?? null) ? $capa['d'] : [];
        $this->push($rows, 'D. Root cause', 'Masalah (problem statement)', $this->str($d['problem_statement'] ?? null));
        $this->push($rows, 'D. Root cause', 'Man (SDM)', $this->str($d['man'] ?? null));
        $this->push($rows, 'D. Root cause', 'Method (SOP)', $this->str($d['method'] ?? null));
        $this->push($rows, 'D. Root cause', 'Machine', $this->str($d['machine'] ?? null));
        $this->push($rows, 'D. Root cause', 'Material', $this->str($d['material'] ?? null));
        $this->push($rows, 'D. Root cause', 'Measurement', $this->str($d['measurement'] ?? null));
        $this->push($rows, 'D. Root cause', 'Environment', $this->str($d['environment'] ?? null));
        $this->push($rows, 'D. Root cause', 'Akar masalah utama', $this->str($d['root_cause_summary'] ?? null));

        $e = is_array($capa['e'] ?? null) ? $capa['e'] : [];
        $this->push($rows, 'E. Corrective action', 'Action', $this->str($e['action'] ?? null));
        $picE = isset($e['pic_user_id']) ? (int) $e['pic_user_id'] : 0;
        $this->push($rows, 'E. Corrective action', 'PIC', $picE > 0 ? ($names[$picE] ?? ('#'.$picE)) : '—');
        $this->push($rows, 'E. Corrective action', 'Deadline', $this->str($e['deadline'] ?? null));
        $this->push($rows, 'E. Corrective action', 'Status', $this->correctiveStatusLabel(isset($e['status']) ? (string) $e['status'] : null));

        $f = is_array($capa['f'] ?? null) ? $capa['f'] : [];
        $imp = isset($f['improvement_areas']) && is_array($f['improvement_areas']) ? $f['improvement_areas'] : [];
        $this->push($rows, 'F. Preventive action', 'Improvement area', $this->mapImprovementAreas($imp));
        $this->push($rows, 'F. Preventive action', 'Action', $this->str($f['action'] ?? null));
        $picF = isset($f['pic_user_id']) ? (int) $f['pic_user_id'] : 0;
        $this->push($rows, 'F. Preventive action', 'PIC', $picF > 0 ? ($names[$picF] ?? ('#'.$picF)) : '—');
        $this->push($rows, 'F. Preventive action', 'Timeline', $this->str($f['timeline'] ?? null));
        $this->push($rows, 'F. Preventive action', 'KPI', $this->str($f['kpi'] ?? null));

        $g = is_array($capa['g'] ?? null) ? $capa['g'] : [];
        $this->push($rows, 'G. Follow up & verifikasi', 'Tanggal follow up', $this->str($g['follow_up_date'] ?? null));
        $ver = isset($g['verified_by_user_id']) ? (int) $g['verified_by_user_id'] : 0;
        $this->push($rows, 'G. Follow up & verifikasi', 'Verifikasi oleh', $ver > 0 ? ($names[$ver] ?? ('#'.$ver)) : '—');
        $this->push($rows, 'G. Follow up & verifikasi', 'Hasil', $this->verificationResultLabel($g['result'] ?? null));
        $this->push($rows, 'G. Follow up & verifikasi', 'Catatan', $this->str($g['notes'] ?? null));

        $h = is_array($capa['h'] ?? null) ? $capa['h'] : [];
        $this->push($rows, 'H. Customer recovery', 'Tamu dihubungi kembali', $this->contactedLabel($h['contacted'] ?? null));
        $cm = isset($h['contact_methods']) && is_array($h['contact_methods']) ? $h['contact_methods'] : [];
        $this->push($rows, 'H. Customer recovery', 'Metode kontak', $this->mapContactMethods($cm));
        $this->push($rows, 'H. Customer recovery', 'Feedback tamu setelah recovery', $this->str($h['recovery_feedback'] ?? null));
        $this->push($rows, 'H. Customer recovery', 'Kepuasan', $this->satisfactionLabel($h['satisfaction'] ?? null));
        $this->push($rows, 'H. Customer recovery', 'Severity (dokumentasi CAPA)', $this->str($h['documented_severity'] ?? null));
        $di = isset($h['documented_impact']) && is_array($h['documented_impact']) ? $h['documented_impact'] : [];
        $this->push($rows, 'H. Customer recovery', 'Impact (dokumentasi)', $this->mapDocumentedImpact($di));

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

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
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
