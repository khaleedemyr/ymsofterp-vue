<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Pindah Outlet</title>
    <style>
        @page { margin: 10mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8pt;
            color: #1f2937;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            color: #2563eb;
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
            background: #2563eb;
            color: #fff;
            font-size: 7pt;
            padding: 4px 3px;
            text-align: left;
            border: 1px solid #1d4ed8;
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
        .right { text-align: right; }
        .total-row td {
            background: #dbeafe !important;
            font-weight: bold;
        }
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
        <h1>Laporan Pindah Outlet</h1>
        <div class="sub">Generated: {{ $generatedAt }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Periode</td>
            <td>
                {{ $filters['from'] ? \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') : '-' }}
                –
                {{ $filters['to'] ? \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') : '-' }}
            </td>
            <td class="label">Outlet Asal</td>
            <td>{{ $filters['outlet_from_name'] ?: 'Semua' }}</td>
        </tr>
        <tr>
            <td class="label">Outlet Tujuan</td>
            <td>{{ $filters['outlet_to_name'] ?: 'Semua' }}</td>
            <td class="label">Status</td>
            <td>{{ $filters['status'] ?: 'Semua' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:10%">No. Transfer</th>
                <th style="width:7%">Tanggal</th>
                <th style="width:11%">Outlet Asal</th>
                <th style="width:6%">WH Asal</th>
                <th style="width:11%">Outlet Tujuan</th>
                <th style="width:6%">WH Tujuan</th>
                <th style="width:16%">Item</th>
                <th style="width:5%" class="right">Qty</th>
                <th style="width:5%">Unit</th>
                <th style="width:7%" class="right">Harga MAC</th>
                <th style="width:7%" class="right">Nilai</th>
                <th style="width:6%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r['transfer_number'] }}</td>
                    <td>{{ $r['transfer_date'] ? \Carbon\Carbon::parse($r['transfer_date'])->format('d/m/Y') : '-' }}</td>
                    <td>{{ $r['outlet_from_name'] }}</td>
                    <td>{{ $r['warehouse_from_name'] }}</td>
                    <td>{{ $r['outlet_to_name'] }}</td>
                    <td>{{ $r['warehouse_to_name'] }}</td>
                    <td>
                        {{ $r['item_name'] }}
                        @if(!empty($r['item_sku']))
                            ({{ $r['item_sku'] }})
                        @endif
                    </td>
                    <td class="right">{{ number_format((float) $r['quantity'], 2, ',', '.') }}</td>
                    <td>{{ $r['unit_name'] }}</td>
                    <td class="right">{{ $r['cost_per_small'] !== null ? number_format((float) $r['cost_per_small'], 2, ',', '.') : '-' }}</td>
                    <td class="right">{{ $r['nilai'] !== null ? number_format((float) $r['nilai'], 2, ',', '.') : '-' }}</td>
                    <td>{{ $statusMap[$r['status']] ?? $r['status'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" style="text-align:center;padding:12px;">Tidak ada data</td>
                </tr>
            @endforelse
            @if(count($rows) > 0)
                <tr class="total-row">
                    <td colspan="11" class="right">TOTAL NILAI (MAC)</td>
                    <td class="right">{{ number_format((float) $grandTotalNilai, 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">YM Soft ERP — Laporan Pindah Outlet</div>
</body>
</html>
