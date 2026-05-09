<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #0f172a; }
        h2 { font-size: 14px; margin: 0 0 6px 0; }
        .meta { font-size: 9px; color: #475569; margin-bottom: 12px; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e293b; color: #fff; padding: 6px 5px; font-size: 8px; text-align: left; vertical-align: top; }
        td { border: 1px solid #cbd5e1; padding: 5px 5px; vertical-align: top; word-wrap: break-word; }
        td.nilai { white-space: pre-wrap; font-size: 8px; line-height: 1.35; max-width: 65%; }
        .bagian { font-weight: bold; background: #f8fafc; width: 18%; vertical-align: middle; text-align: left; }
        .field { width: 22%; }
    </style>
</head>
<body>
    <h2>CAPA — Customer Voice</h2>
    <div class="meta">
        Case #{{ $caseId ?? '' }}
        @if (!empty($outlet))
            · {{ $outlet }}
        @endif
        · Diekspor: {{ $generatedAt ?? '' }}
    </div>
    <table>
        <thead>
            <tr>
                <th>Bagian</th>
                <th>Field</th>
                <th>Nilai</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($capaGroupedSections as $section)
                @foreach ($section['items'] as $idx => $item)
                    <tr>
                        @if ($idx === 0)
                            <td class="bagian" rowspan="{{ count($section['items']) }}">{{ $section['bagian'] ?? '' }}</td>
                        @endif
                        <td class="field">{{ $item['field'] ?? '' }}</td>
                        <td class="nilai">{{ $item['nilai'] ?? '' }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
