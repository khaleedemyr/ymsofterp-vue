<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>KPI Evaluation — {{ $evaluation['evaluation_code'] ?? '' }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.35;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #4338ca;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; border: none; padding: 0; }
        .brand {
            font-size: 11px;
            font-weight: bold;
            color: #4338ca;
            letter-spacing: 0.02em;
        }
        .title {
            font-size: 14px;
            font-weight: bold;
            margin: 2px 0 0 0;
            color: #0f172a;
        }
        .subtitle {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }
        .score-box {
            text-align: right;
            white-space: nowrap;
        }
        .score-flag {
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 10px;
            margin-bottom: 4px;
        }
        .flag-excellence { background: #d1fae5; color: #047857; }
        .flag-satisfactory { background: #fef3c7; color: #b45309; }
        .flag-to_improve { background: #ffe4e6; color: #be123c; }
        .score-value {
            font-size: 20px;
            font-weight: bold;
            line-height: 1.1;
        }
        .score-excellence { color: #059669; }
        .score-satisfactory { color: #d97706; }
        .score-to_improve { color: #e11d48; }
        .score-label { font-size: 7px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background: #f8fafc;
        }
        .meta td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            vertical-align: top;
        }
        .meta .lbl {
            width: 14%;
            color: #64748b;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .meta .val { width: 19.3%; font-weight: bold; font-size: 8px; }
        .scope {
            margin-bottom: 10px;
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            background: #fff;
        }
        .scope strong { color: #4338ca; }
        .scope-outlets { color: #475569; font-size: 7.5px; margin-top: 3px; }

        .section {
            margin-top: 8px;
            page-break-inside: avoid;
        }
        .section-title {
            background: #4338ca;
            color: #fff;
            padding: 5px 8px;
            font-size: 9px;
            font-weight: bold;
        }
        .section-title.rose { background: #be123c; }
        .section-sub {
            background: #fff1f2;
            color: #9f1239;
            padding: 3px 8px;
            font-size: 7.5px;
            border: 1px solid #fecdd3;
            border-top: none;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        table.data th {
            background: #eef2ff;
            color: #312e81;
            border: 1px solid #c7d2fe;
            padding: 4px 5px;
            text-align: left;
            font-size: 7.5px;
            font-weight: bold;
        }
        table.data.rose th {
            background: #ffe4e6;
            color: #9f1239;
            border-color: #fecdd3;
        }
        table.data td {
            border: 1px solid #e2e8f0;
            padding: 3px 5px;
            vertical-align: top;
            font-size: 7.5px;
        }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .mono { font-family: DejaVu Sans Mono, DejaVu Sans, monospace; color: #4338ca; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #64748b; font-size: 7px; }
        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .lvl-exceeding { background: #d1fae5; color: #047857; }
        .lvl-meeting { background: #dbeafe; color: #1d4ed8; }
        .lvl-below { background: #ffe4e6; color: #be123c; }
        .plan { white-space: pre-wrap; word-wrap: break-word; }

        .comments {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .comments td {
            width: 50%;
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            vertical-align: top;
        }
        .comments h4 {
            margin: 0 0 4px 0;
            font-size: 8px;
            color: #4338ca;
            text-transform: uppercase;
        }
        .footer {
            margin-top: 12px;
            padding-top: 6px;
            border-top: 1px solid #e2e8f0;
            font-size: 7px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>
@php
    $fmt = function ($val) {
        if ($val === null || $val === '') {
            return '—';
        }
        if (!is_numeric($val)) {
            return (string) $val;
        }
        $n = (float) $val;
        if (!is_finite($n)) {
            return '—';
        }

        return number_format($n, abs($n - round($n)) < 0.005 ? 0 : 2, ',', '.');
    };

    $fmtAchievement = function ($item) use ($fmt) {
        $value = $item['achievement_percent'] ?? null;
        if ($value === null || $value === '') {
            return '—';
        }
        $formatted = $fmt($value);
        $valueType = $item['value_type'] ?? 'percent';
        $suffix = $item['unit_suffix'] ?? ($valueType === 'percent' ? '%' : '');
        $label = $item['unit_label'] ?? '';
        if ($suffix !== '') {
            return $formatted.$suffix;
        }
        if ($label !== '') {
            return $formatted.' '.$label;
        }

        return $formatted;
    };

    $flagKey = $scoreFlag['key'] ?? null;
    $flagLabel = $scoreFlag['label'] ?? null;
@endphp
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 72%;">
                    <div class="brand">YM Soft ERP · KPI Evaluation</div>
                    <div class="title">{{ $evaluation['employee_name'] ?? '—' }}</div>
                    <div class="subtitle">
                        {{ $evaluation['evaluation_code'] ?? '' }}
                        · {{ $evaluation['period_month'] ?? '' }}
                        · {{ strtoupper($evaluation['eval_status'] ?? '') }}
                        <br>
                        {{ $evaluation['jabatan_name'] ?? '—' }}
                        · {{ $evaluation['outlet_name'] ?? '—' }}
                        @if (!empty($evaluation['division_name']))
                            · {{ $evaluation['division_name'] }}
                        @endif
                    </div>
                </td>
                <td class="score-box" style="width: 28%;">
                    @if ($flagLabel)
                        <div class="score-flag flag-{{ $flagKey }}">{{ $flagLabel }}</div>
                    @endif
                    <div class="score-value score-{{ $flagKey ?? 'to_improve' }}">{{ $fmt($evaluation['total_score'] ?? null) }}</div>
                    <div class="score-label">Total Skor</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td class="lbl">Template</td>
            <td class="val">{{ $evaluation['template']['name'] ?? '—' }}</td>
            <td class="lbl">Periode Evaluasi</td>
            <td class="val">{{ $evaluation['period_info']['evaluation_label'] ?? ($evaluation['period_month'] ?? '—') }}</td>
            <td class="lbl">Data KPI</td>
            <td class="val">
                {{ $evaluation['period_start'] ?? '—' }} s/d {{ $evaluation['period_end'] ?? '—' }}
                @if (!empty($evaluation['period_info']['data_label']))
                    <div class="muted">({{ $evaluation['period_info']['data_label'] }})</div>
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Attendance</td>
            <td class="val">
                @if (!empty($evaluation['period_info']['attendance_start']))
                    {{ $evaluation['period_info']['attendance_start'] }} s/d {{ $evaluation['period_info']['attendance_end'] }}
                @else
                    —
                @endif
            </td>
            <td class="lbl">Disubmit</td>
            <td class="val">{{ $evaluation['submitted_at'] ?? '—' }}</td>
            <td class="lbl">Scope ERP</td>
            <td class="val">{{ $scopeLabel }} ({{ count($scopeOutletLabels) }} outlet)</td>
        </tr>
    </table>

    @if (count($scopeOutletLabels) > 0)
        <div class="scope">
            <strong>Outlet scope:</strong>
            <div class="scope-outlets">{{ implode(', ', $scopeOutletLabels) }}</div>
        </div>
    @endif

    <div class="section">
        <div class="section-title">Data Parameter (D*)</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 7%;">Kode</th>
                    <th style="width: 28%;">Nama</th>
                    <th style="width: 14%;">Frequency</th>
                    <th style="width: 8%;">Sumber</th>
                    <th style="width: 10%;" class="num">ERP</th>
                    <th style="width: 10%;" class="num">Manual</th>
                    <th style="width: 10%;" class="num">Final</th>
                    <th style="width: 13%;">Override</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($evaluation['parameter_values'] ?? []) as $pv)
                    <tr>
                        <td class="mono">{{ $pv['parameter_code'] ?? '—' }}</td>
                        <td>{{ $pv['parameter_name'] ?? '—' }}</td>
                        <td>
                            {{ $pv['frequency_label'] ?? ucfirst($pv['frequency'] ?? '—') }}
                            @if (!empty($pv['data_window_label']))
                                <div class="muted">{{ $pv['data_window_label'] }}</div>
                            @endif
                        </td>
                        <td>{{ strtoupper($pv['source_type'] ?? '—') }}</td>
                        <td class="num">{{ $fmt($pv['erp_value'] ?? null) }}</td>
                        <td class="num">{{ $fmt($pv['manual_value'] ?? null) }}</td>
                        <td class="num"><strong>{{ $fmt($pv['final_value'] ?? null) }}</strong></td>
                        <td class="muted">{{ !empty($pv['is_overridden']) ? ($pv['override_reason'] ?? 'Override') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Tidak ada parameter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach (($evaluation['strategies'] ?? []) as $strategy)
        <div class="section">
            <div class="section-title rose">{{ $strategy['name'] ?? 'Strategy' }}</div>
            <div class="section-sub">
                Bobot Strategy: {{ $fmt($strategy['weight_percent'] ?? null) }}%
                · Skor Strategy: <strong>{{ $fmt($strategy['score'] ?? null) }}</strong>
                · Kontribusi: {{ $fmt($strategy['weighted_score'] ?? null) }}
            </div>
            <table class="data rose">
                <thead>
                    <tr>
                        <th style="width: 24%;">KPI</th>
                        <th style="width: 12%;">Frequency</th>
                        <th style="width: 12%;">Target</th>
                        <th style="width: 10%;" class="num">Achievement</th>
                        <th style="width: 9%;">Level</th>
                        <th style="width: 7%;" class="num">Skor</th>
                        <th style="width: 6%;" class="num">Bobot</th>
                        <th style="width: 20%;">Improvement Plan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (($strategy['items'] ?? []) as $item)
                        @php $level = strtolower((string) ($item['performance_level'] ?? '')); @endphp
                        <tr>
                            <td><strong>{{ $item['item_name'] ?? '—' }}</strong></td>
                            <td>
                                {{ $item['frequency_label'] ?? ucfirst($item['frequency'] ?? '—') }}
                                @if (!empty($item['data_window_label']))
                                    <div class="muted">{{ $item['data_window_label'] }}</div>
                                @endif
                            </td>
                            <td>{{ $item['target_value'] ?: '—' }}</td>
                            <td class="num">{{ $fmtAchievement($item) }}</td>
                            <td>
                                @if ($level !== '')
                                    <span class="badge lvl-{{ $level }}">{{ $level }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num"><strong>{{ $fmt($item['score'] ?? null) }}</strong></td>
                            <td class="num">{{ $fmt($item['weight_percent'] ?? null) }}%</td>
                            <td class="plan">
                                {{ $item['improvement_plan'] ?: '—' }}
                                @if (!empty($item['improvement_plan_due_date']))
                                    <div class="muted">Due: {{ $item['improvement_plan_due_date'] }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    @if (!empty($evaluation['employee_comments']) || !empty($evaluation['assessor_comments']))
        <table class="comments">
            <tr>
                <td>
                    <h4>Komentar Karyawan</h4>
                    <div class="plan">{{ $evaluation['employee_comments'] ?: '—' }}</div>
                </td>
                <td>
                    <h4>Komentar Assessor</h4>
                    <div class="plan">{{ $evaluation['assessor_comments'] ?: '—' }}</div>
                </td>
            </tr>
        </table>
    @endif

    <div class="footer">
        Diekspor {{ $generatedAt }} · {{ $evaluation['evaluation_code'] ?? '' }}
    </div>
</body>
</html>
