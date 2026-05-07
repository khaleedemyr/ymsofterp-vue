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
        .activity-line { font-size: 6.5px; line-height: 1.35; margin: 0 0 3px 0; white-space: pre-wrap; }
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
        · Maks. {{ $maxRows ?? 600 }} baris/file · Timeline: {{ $maxActivitiesPerCase ?? 8 }} aktivitas terakhir/case
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:9%">Waktu</th>
                <th style="width:9%">Outlet</th>
                <th style="width:8%">Source</th>
                <th style="width:7%">Severity</th>
                <th style="width:10%">Ringkasan</th>
                <th style="width:16%">Komentar</th>
                <th style="width:4%">Risk</th>
                <th style="width:7%">Status</th>
                <th style="width:9%">PIC</th>
                <th style="width:21%">Timeline</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cases as $case)
                <tr>
                    <td>{{ $case->event_at }}</td>
                    <td>{{ $case->nama_outlet ?? '-' }}</td>
                    <td>{{ $case->source_type }}</td>
                    <td>{{ $case->severity ?? '-' }}</td>
                    <td>{{ $case->summary_short ?? '-' }}</td>
                    <td>{{ $case->raw_short ?? '-' }}</td>
                    <td>{{ $case->risk_score ?? 0 }}</td>
                    <td>{{ $case->status }}</td>
                    <td>{{ $case->assigned_to_name ?? '-' }}</td>
                    <td>
                        @php
                            $acts = $activitiesByCase[(int) $case->id] ?? [];
                        @endphp
                        @forelse ($acts as $a)
                            <div class="activity-line">
                                [{{ $a->created_at }}] {{ $a->activity_type }}
                                @if (!empty($a->actor_name))
                                    · {{ $a->actor_name }}
                                @endif
                                @if ($a->from_status || $a->to_status)
                                    ({{ $a->from_status ?? '-' }}→{{ $a->to_status ?? '-' }})
                                @endif
                                @if (!empty($a->note))
                                    — {{ $a->note }}
                                @endif
                            </div>
                        @empty
                            <span style="color:#94a3b8;">—</span>
                        @endforelse
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
