<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Kontrol Suhu Chiller & Freezer</title>
    <style>
        @page { margin: 10mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8pt;
            color: #1f2937;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0891b2;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            color: #0e7490;
            font-size: 14pt;
        }
        .header .sub {
            margin-top: 4px;
            color: #6b7280;
            font-size: 9pt;
        }
        .meta {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        .meta td {
            padding: 2px 0;
            vertical-align: top;
        }
        .meta .label {
            width: 16%;
            color: #6b7280;
            font-weight: bold;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
        }
        table.items th {
            background: #0891b2;
            color: #fff;
            font-size: 7pt;
            padding: 4px 3px;
            text-align: center;
            border: 1px solid #0e7490;
        }
        table.items th.left {
            text-align: left;
        }
        table.items td {
            border: 1px solid #e5e7eb;
            padding: 3px;
            font-size: 7pt;
            vertical-align: top;
        }
        table.items tr:nth-child(even) td {
            background: #f9fafb;
        }
        .center { text-align: center; }
        .mono { font-family: DejaVu Sans Mono, monospace; color: #0e7490; font-size: 6.5pt; }
        .footer {
            margin-top: 12px;
            color: #9ca3af;
            font-size: 7.5pt;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Kontrol Suhu Chiller &amp; Freezer</h1>
        <div class="sub">Display &amp; Actual per jam (10:00 / 16:00 / 22:00) • Generated at {{ $generatedAt }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Tanggal</td>
            <td>{{ $filters['date'] ?? '-' }}</td>
            <td class="label">Outlet</td>
            <td>{{ $filters['outlet_name'] ?? 'Semua Outlet' }}</td>
        </tr>
        <tr>
            <td class="label">Search</td>
            <td colspan="3">{{ $filters['search'] ?: '-' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="left" style="width: 3%;">No</th>
                <th class="left" style="width: 16%;">Outlet</th>
                <th class="left" style="width: 10%;">Warehouse</th>
                <th class="left" style="width: 17%;">Chiller / Freezer</th>
                <th style="width: 6%;">10:00 Disp</th>
                <th style="width: 6%;">10:00 Act</th>
                <th style="width: 6%;">16:00 Disp</th>
                <th style="width: 6%;">16:00 Act</th>
                <th style="width: 6%;">22:00 Disp</th>
                <th style="width: 6%;">22:00 Act</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row['outlet_name'] ?? '-' }}</td>
                    <td>{{ $row['warehouse_name'] ?? '-' }}</td>
                    <td>
                        {{ $row['item_name'] ?? '-' }}<br>
                        <span class="mono">{{ $row['serial_number'] ?? '-' }}</span>
                    </td>
                    @foreach(['10:00', '16:00', '22:00'] as $time)
                        @php $slot = $row['slots'][$time] ?? null; @endphp
                        <td class="center">{{ $slot ? number_format((float) $slot['temp_display'], 1, '.', '') : '-' }}</td>
                        <td class="center">{{ $slot ? number_format((float) $slot['temp_actual'], 1, '.', '') : '-' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center">Tidak ada data untuk diekspor.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Kontrol Suhu Chiller &amp; Freezer • {{ $generatedAt }}</div>
</body>
</html>
