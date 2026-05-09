<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #0f172a; }
        h2 { font-size: 14px; margin: 0 0 6px 0; }
        .meta { font-size: 9px; color: #475569; margin-bottom: 10px; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e293b; color: #fff; padding: 5px 3px; font-size: 7px; text-align: left; vertical-align: top; }
        td { border: 1px solid #cbd5e1; padding: 4px 3px; vertical-align: top; word-wrap: break-word; }
        .tiny { font-size: 6.5px; color: #475569; line-height: 1.35; }
    </style>
</head>
<body>
    <h2>Customer Voice Command Center — Export</h2>
    <div class="meta">
        Periode event: {{ $dateFrom }} s/d {{ $dateTo }} · Diekspor: {{ $generatedAt }} ·
        Ditampilkan {{ $totalExported }} baris
        @if (($totalMatching ?? $totalExported) > $totalExported)
            (dari total {{ $totalMatching }} sesuai filter; sisanya persempit tanggal/filter atau export berulang)
        @endif
        · Maks. {{ $maxRows ?? 600 }} baris/file
    </div>
    @php
        $statusLabel = function ($status) {
            $s = strtolower(trim((string) $status));
            return match ($s) {
                'new' => 'New',
                'courtesy_by_cs' => 'Courtesy by CS',
                'follow_up_by_ops' => 'Follow Up by Ops',
                'done' => 'Done',
                'in_progress' => 'In Progress (legacy)',
                'resolved' => 'Resolved (legacy)',
                'ignored' => 'Ignored (legacy)',
                default => $status ?: '-',
            };
        };
        $sourceLabel = function ($source) {
            $s = strtolower(trim((string) $source));
            return match ($s) {
                'google_review' => 'Google',
                'instagram_comment' => 'Instagram',
                'guest_comment' => 'Guest Comment',
                default => $source ?: '-',
            };
        };
        $followUpLabel = function ($v) {
            $s = strtolower(trim((string) $v));
            if ($s === 'customer') return 'Customer';
            if ($s === 'internal') return 'Internal';
            return '-';
        };
        $verifLabel = function ($verif) {
            if (!is_array($verif)) return '?';
            $state = strtolower(trim((string) ($verif['state'] ?? '')));
            if ($state === 'pending') return '⏳';
            if ($state === 'done') {
                $r = strtolower(trim((string) ($verif['result'] ?? '')));
                return $r === 'not_effective' ? '✖' : '✔';
            }
            return '?';
        };
        $capaIcon = function ($filled) {
            return $filled ? '✔' : '−';
        };
        $slaLabel = function ($dueAt, $status) {
            if (empty($dueAt)) return 'Tanpa SLA';
            $s = strtolower(trim((string) $status));
            if (!in_array($s, ['new', 'courtesy_by_cs', 'follow_up_by_ops', 'in_progress'], true)) {
                return 'Closed';
            }
            try {
                $due = \Carbon\Carbon::parse($dueAt);
            } catch (\Throwable) {
                return 'SLA invalid';
            }
            $now = now();
            if ($due->lt($now)) return 'Overdue';
            $mins = $now->diffInMinutes($due);
            if ($mins < 60) return $mins.'m tersisa';
            $h = intdiv($mins, 60);
            $m = $mins % 60;
            return $h.'j '.$m.'m tersisa';
        };
    @endphp
    <table>
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Outlet</th>
                <th>Regional</th>
                <th>Source</th>
                <th>Tamu</th>
                <th>FU target</th>
                <th>Severity</th>
                <th>Jenis komplain</th>
                <th>Ringkasan</th>
                <th>Risk</th>
                <th>CAPA</th>
                <th>Verif. CAPA</th>
                <th>SLA</th>
                <th>CS PIC</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cases as $case)
                <tr>
                    <td>{{ $case->event_at }}</td>
                    <td>{{ $case->nama_outlet ?? '-' }}</td>
                    <td>{{ $case->regional !== '' ? $case->regional : '-' }}</td>
                    <td>{{ $sourceLabel($case->source_type ?? null) }}</td>
                    <td>
                        {{ $case->author_name !== '' ? $case->author_name : '—' }}
                        @if (!empty($case->customer_contact))
                            <div class="tiny">{{ $case->customer_contact }}</div>
                        @endif
                        @if (!empty($case->customer_email))
                            <div class="tiny">{{ $case->customer_email }}</div>
                        @endif
                    </td>
                    <td>{{ $followUpLabel($case->follow_up_target ?? null) }}</td>
                    <td>{{ $case->severity ?? '-' }}</td>
                    <td>
                        @php
                            $labels = is_array($case->complaint_type_labels ?? null) ? $case->complaint_type_labels : [];
                        @endphp
                        {{ count($labels) ? implode(', ', $labels) : '-' }}
                    </td>
                    <td>
                        {{ $case->summary_short ?? '-' }}
                        @if (!empty($case->raw_short))
                            <div class="tiny">{{ $case->raw_short }}</div>
                        @endif
                    </td>
                    <td>{{ $case->risk_score ?? 0 }}</td>
                    <td>{{ $capaIcon(!empty($case->capa_filled)) }}</td>
                    <td>{{ $verifLabel($case->capa_verification ?? null) }}</td>
                    <td>{{ $slaLabel($case->due_at ?? null, $case->status ?? '') }}</td>
                    <td>{{ $case->assigned_to_name !== '' ? $case->assigned_to_name : '-' }}</td>
                    <td>{{ $statusLabel($case->status ?? '') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
