<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>QA Audit — {{ $audit['audit_number'] ?? '' }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #0f172a;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #0f766e;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .brand {
            font-size: 10px;
            font-weight: bold;
            color: #0f766e;
            letter-spacing: 0.02em;
        }
        .title {
            font-size: 15px;
            font-weight: bold;
            margin: 2px 0 0 0;
            color: #0f172a;
        }
        .subtitle {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            background: #f8fafc;
        }
        .meta td {
            border: 1px solid #e2e8f0;
            padding: 5px 6px;
            vertical-align: top;
        }
        .meta .lbl {
            width: 16%;
            color: #64748b;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-weight: bold;
        }
        .meta .val {
            width: 34%;
            font-size: 9px;
        }
        h2 {
            font-size: 11px;
            margin: 14px 0 6px 0;
            color: #0f172a;
            border-left: 3px solid #0f766e;
            padding-left: 6px;
        }
        table.summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.summary th {
            background: #134e4a;
            color: #fff;
            padding: 5px 4px;
            font-size: 7.5px;
            text-align: center;
        }
        table.summary th.name { text-align: left; }
        table.summary td {
            border: 1px solid #cbd5e1;
            padding: 4px;
            text-align: center;
            font-size: 8px;
        }
        table.summary td.name { text-align: left; }
        table.summary tr.total td {
            background: #ecfdf5;
            font-weight: bold;
        }
        .finding {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .finding .row {
            margin-bottom: 4px;
        }
        .finding .k {
            color: #64748b;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .finding .v {
            font-size: 9px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 8px;
            font-weight: bold;
        }
        .badge-nc, .badge-my { background: #fee2e2; color: #991b1b; }
        .badge-mn { background: #ffedd5; color: #9a3412; }
        .badge-c { background: #dcfce7; color: #166534; }
        .badge-na, .badge-a { background: #e2e8f0; color: #334155; }
        .photos {
            margin-top: 6px;
        }
        .photos-title {
            font-size: 8px;
            font-weight: bold;
            color: #334155;
            margin: 6px 0 4px 0;
        }
        .photo-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .photo-grid td {
            width: 50%;
            vertical-align: top;
            padding: 3px;
            border: none;
        }
        .photo-grid img {
            max-width: 100%;
            max-height: 220px;
            height: auto;
            display: block;
            border: 1px solid #e2e8f0;
        }
        .photo-note {
            font-size: 7.5px;
            color: #64748b;
            margin: 2px 0 6px 0;
        }
        .empty {
            color: #64748b;
            font-size: 8px;
            font-style: italic;
        }
        .footer {
            margin-top: 14px;
            font-size: 7.5px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">YM Soft — Quality Assurance</div>
        <div class="title">QA Audit {{ $audit['audit_number'] ?? '-' }}</div>
        <div class="subtitle">
            Status: {{ $audit['status'] ?? '-' }}
            · Diekspor: {{ $generatedAt ?? '' }}
        </div>
    </div>

    <table class="meta">
        <tr>
            <td class="lbl">Outlet</td>
            <td class="val">{{ $audit['outlet_name'] ?? '-' }}</td>
            <td class="lbl">Template</td>
            <td class="val">{{ $audit['template_name'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">Audit Date</td>
            <td class="val">{{ $audit['audit_datetime'] ?? '-' }}</td>
            <td class="lbl">Time</td>
            <td class="val">
                {{ $audit['audit_time_start'] ?? '-' }}
                —
                {{ $audit['audit_time_end'] ?? '-' }}
            </td>
        </tr>
        <tr>
            <td class="lbl">Auditor</td>
            <td class="val">
                @if (!empty($auditorNames))
                    {{ implode(', ', $auditorNames) }}
                @else
                    —
                @endif
            </td>
            <td class="lbl">Auditee</td>
            <td class="val">
                @if (!empty($auditeeNames))
                    {{ implode(', ', $auditeeNames) }}
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Catatan Umum</td>
            <td class="val" colspan="3">{{ trim((string) ($audit['notes'] ?? '')) !== '' ? $audit['notes'] : '—' }}</td>
        </tr>
    </table>

    <h2>Ringkasan Skor</h2>
    @php
        $summaryRows = $audit['summary_rows'] ?? [];
        $summaryTotal = $audit['summary_total'] ?? [];
        $isCmnMy = ($scoringMode ?? 'legacy') === 'c_mn_my';
    @endphp
    <table class="summary">
        <thead>
            <tr>
                <th class="name">Kategori</th>
                <th>C</th>
                @if ($isCmnMy)
                    <th>MN</th>
                    <th>MY</th>
                    <th>A</th>
                @else
                    <th>NC</th>
                @endif
                <th>NA</th>
                <th>Score %</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($summaryRows as $row)
                <tr>
                    <td class="name">{{ $row['name'] ?? '-' }}</td>
                    <td>{{ $row['compliant'] ?? 0 }}</td>
                    @if ($isCmnMy)
                        <td>{{ $row['minor'] ?? 0 }}</td>
                        <td>{{ $row['major'] ?? 0 }}</td>
                        <td>{{ $row['applicable'] ?? 0 }}</td>
                    @else
                        <td>{{ $row['non_compliant'] ?? 0 }}</td>
                    @endif
                    <td>{{ $row['non_applicable'] ?? 0 }}</td>
                    <td>{{ number_format((float) ($row['score'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isCmnMy ? 7 : 5 }}" class="empty">Belum ada ringkasan.</td>
                </tr>
            @endforelse
            @if (!empty($summaryRows))
                <tr class="total">
                    <td class="name">TOTAL</td>
                    <td>{{ $summaryTotal['compliant'] ?? 0 }}</td>
                    @if ($isCmnMy)
                        <td>{{ $summaryTotal['minor'] ?? 0 }}</td>
                        <td>{{ $summaryTotal['major'] ?? 0 }}</td>
                        <td>{{ $summaryTotal['applicable'] ?? 0 }}</td>
                    @else
                        <td>{{ $summaryTotal['non_compliant'] ?? 0 }}</td>
                    @endif
                    <td>{{ $summaryTotal['non_applicable'] ?? 0 }}</td>
                    <td>{{ number_format((float) ($summaryTotal['score'] ?? 0), 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <h2>Temuan &amp; Bukti Media</h2>
    @forelse ($findings as $idx => $finding)
        @php
            $result = strtoupper((string) ($finding['result'] ?? ''));
            $badgeClass = match ($result) {
                'NC', 'MY' => 'badge-nc',
                'MN' => 'badge-mn',
                'C' => 'badge-c',
                'A', 'NA' => 'badge-na',
                default => 'badge-na',
            };
        @endphp
        <div class="finding">
            <div class="row">
                <span class="k">#{{ $idx + 1 }} · {{ $finding['category_name'] ?? '-' }}
                    @if (!empty($finding['subcategory_name']))
                        / {{ $finding['subcategory_name'] }}
                    @endif
                </span>
                <div class="v" style="margin-top:2px;">
                    <strong>{{ $finding['parameter_code'] ?? '' }}</strong>
                    {{ $finding['parameter_text'] ?? '' }}
                    <span class="badge {{ $badgeClass }}">{{ $result !== '' ? $result : '-' }}</span>
                </div>
            </div>

            <div class="row">
                <div class="k">Temuan Auditor</div>
                <div class="v">{{ trim((string) ($finding['comment'] ?? '')) !== '' ? $finding['comment'] : '—' }}</div>
            </div>

            <div class="row">
                <div class="k">Due Date</div>
                <div class="v">{{ !empty($finding['due_date']) ? $finding['due_date'] : '—' }}</div>
            </div>

            @if (trim((string) ($finding['cap_action_plan'] ?? '')) !== '' || !empty($finding['cap_target_date']) || !empty($finding['cap_status']))
                <div class="row">
                    <div class="k">CAP — Action Plan</div>
                    <div class="v">{{ trim((string) ($finding['cap_action_plan'] ?? '')) !== '' ? $finding['cap_action_plan'] : '—' }}</div>
                </div>
                <div class="row">
                    <div class="k">CAP Target / Status</div>
                    <div class="v">
                        {{ !empty($finding['cap_target_date']) ? $finding['cap_target_date'] : '—' }}
                        · {{ !empty($finding['cap_status']) ? strtoupper($finding['cap_status']) : '—' }}
                    </div>
                </div>
            @endif

            <div class="photos">
                <div class="photos-title">Bukti Media Auditor</div>
                @if (!empty($finding['item_photos']))
                    <table class="photo-grid">
                        @foreach (array_chunk($finding['item_photos'], 2) as $chunk)
                            <tr>
                                @foreach ($chunk as $photo)
                                    <td>
                                        @if (!empty($photo['src']))
                                            <img src="{{ $photo['src'] }}" alt="{{ $photo['label'] ?? '' }}" />
                                        @else
                                            <p class="photo-note">{{ $photo['label'] ?? 'gambar' }} — {{ $photo['note'] ?? 'Tidak dapat disematkan.' }}</p>
                                        @endif
                                    </td>
                                @endforeach
                                @if (count($chunk) === 1)
                                    <td></td>
                                @endif
                            </tr>
                        @endforeach
                    </table>
                @else
                    <p class="photo-note">Tidak ada foto bukti.</p>
                @endif
                @if (($finding['item_videos'] ?? 0) > 0)
                    <p class="photo-note">{{ (int) $finding['item_videos'] }} video tidak disematkan di PDF (lihat di aplikasi).</p>
                @endif

                @if (!empty($finding['cap_photos']) || ($finding['cap_videos'] ?? 0) > 0)
                    <div class="photos-title">Bukti Media CAP</div>
                    @if (!empty($finding['cap_photos']))
                        <table class="photo-grid">
                            @foreach (array_chunk($finding['cap_photos'], 2) as $chunk)
                                <tr>
                                    @foreach ($chunk as $photo)
                                        <td>
                                            @if (!empty($photo['src']))
                                                <img src="{{ $photo['src'] }}" alt="{{ $photo['label'] ?? '' }}" />
                                            @else
                                                <p class="photo-note">{{ $photo['label'] ?? 'gambar' }} — {{ $photo['note'] ?? 'Tidak dapat disematkan.' }}</p>
                                            @endif
                                        </td>
                                    @endforeach
                                    @if (count($chunk) === 1)
                                        <td></td>
                                    @endif
                                </tr>
                            @endforeach
                        </table>
                    @endif
                    @if (($finding['cap_videos'] ?? 0) > 0)
                        <p class="photo-note">{{ (int) $finding['cap_videos'] }} video CAP tidak disematkan di PDF (lihat di aplikasi).</p>
                    @endif
                @endif
            </div>
        </div>
    @empty
        <p class="empty">Tidak ada temuan NC / media untuk diekspor.</p>
    @endforelse

    <div class="footer">
        Dokumen digenerate otomatis dari YM Soft ERP · {{ $generatedAt ?? '' }}
    </div>
</body>
</html>
