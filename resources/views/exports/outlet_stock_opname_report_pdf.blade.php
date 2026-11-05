<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Outlet Stock Opname Report</title>
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
            width: 18%;
            color: #6b7280;
            font-weight: bold;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .summary td {
            width: 25%;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            padding: 6px 8px;
        }
        .summary .title {
            color: #6b7280;
            font-size: 7.5pt;
        }
        .summary .value {
            font-weight: bold;
            color: #1d4ed8;
            font-size: 9pt;
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
        .neg { color: #dc2626; }
        .pos { color: #16a34a; }
        .section {
            margin-top: 14px;
            margin-bottom: 6px;
            padding: 6px 8px;
            background: #f3f4f6;
            border-left: 3px solid #2563eb;
        }
        .section strong { color: #1e40af; }
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
        <h1>Outlet Stock Opname Report</h1>
        <div class="sub">Generated at {{ $generatedAt }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Outlet</td>
            <td>{{ $filters['outlet_name'] ?? 'Semua' }}</td>
            <td class="label">Warehouse</td>
            <td>{{ $filters['warehouse_name'] ?? 'Semua' }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal</td>
            <td>{{ $filters['date'] ?: 'Semua' }}</td>
            <td class="label">Search</td>
            <td>{{ $filters['search'] ?: '-' }}</td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                <div class="title">Total Selisih Qty Small</div>
                <div class="value">{{ number_format($totals['total_qty_diff_small'] ?? 0, 2, ',', '.') }}</div>
            </td>
            <td>
                <div class="title">Total Selisih Qty Medium</div>
                <div class="value">{{ number_format($totals['total_qty_diff_medium'] ?? 0, 2, ',', '.') }}</div>
            </td>
            <td>
                <div class="title">Total Selisih Qty Large</div>
                <div class="value">{{ number_format($totals['total_qty_diff_large'] ?? 0, 2, ',', '.') }}</div>
            </td>
            <td>
                <div class="title">Total Value Adjustment</div>
                <div class="value">Rp {{ number_format($totals['total_value_adjustment'] ?? 0, 2, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    @forelse($stockOpnames as $opname)
        <div class="section">
            <strong>{{ $opname['opname_number'] }}</strong>
            &nbsp;|&nbsp; {{ $opname['opname_date'] }}
            &nbsp;|&nbsp; {{ $opname['outlet'] }} / {{ $opname['warehouse_outlet'] }}
            &nbsp;|&nbsp; {{ $opname['status'] }}
            &nbsp;|&nbsp; Items: {{ $opname['items_count'] }}
            &nbsp;|&nbsp; Adj: Rp {{ number_format($opname['total_value_adjustment'], 2, ',', '.') }}
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 3%;">No</th>
                    <th style="width: 9%;">Item Code</th>
                    <th style="width: 16%;">Item Name</th>
                    <th style="width: 9%;">Category</th>
                    <th style="width: 10%;">Qty System</th>
                    <th style="width: 10%;">Qty Physical</th>
                    <th style="width: 10%;">Selisih</th>
                    <th style="width: 8%;" class="right">Last Cost</th>
                    <th style="width: 10%;" class="right">Value Adj</th>
                    <th style="width: 15%;">Reason</th>
                </tr>
            </thead>
            <tbody>
                @foreach($opname['items'] as $index => $item)
                    @php
                        $diffClassS = ($item['qty_diff_small'] ?? 0) < 0 ? 'neg' : (($item['qty_diff_small'] ?? 0) > 0 ? 'pos' : '');
                        $diffClassM = ($item['qty_diff_medium'] ?? 0) < 0 ? 'neg' : (($item['qty_diff_medium'] ?? 0) > 0 ? 'pos' : '');
                        $diffClassL = ($item['qty_diff_large'] ?? 0) < 0 ? 'neg' : (($item['qty_diff_large'] ?? 0) > 0 ? 'pos' : '');
                        $valClass = ($item['value_adjustment'] ?? 0) < 0 ? 'neg' : (($item['value_adjustment'] ?? 0) > 0 ? 'pos' : '');
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item['item_code'] }}</td>
                        <td>{{ $item['item_name'] }}</td>
                        <td>{{ $item['category_name'] }}</td>
                        <td>
                            S: {{ number_format($item['qty_system_small'], 2, ',', '.') }} {{ $item['small_unit_name'] }}<br>
                            M: {{ number_format($item['qty_system_medium'], 2, ',', '.') }} {{ $item['medium_unit_name'] }}<br>
                            L: {{ number_format($item['qty_system_large'], 2, ',', '.') }} {{ $item['large_unit_name'] }}
                        </td>
                        <td>
                            S: {{ number_format($item['qty_physical_small'], 2, ',', '.') }} {{ $item['small_unit_name'] }}<br>
                            M: {{ number_format($item['qty_physical_medium'], 2, ',', '.') }} {{ $item['medium_unit_name'] }}<br>
                            L: {{ number_format($item['qty_physical_large'], 2, ',', '.') }} {{ $item['large_unit_name'] }}
                        </td>
                        <td>
                            <span class="{{ $diffClassS }}">S: {{ number_format($item['qty_diff_small'], 2, ',', '.') }}</span><br>
                            <span class="{{ $diffClassM }}">M: {{ number_format($item['qty_diff_medium'], 2, ',', '.') }}</span><br>
                            <span class="{{ $diffClassL }}">L: {{ number_format($item['qty_diff_large'], 2, ',', '.') }}</span>
                        </td>
                        <td class="right">{{ number_format($item['mac_before'], 2, ',', '.') }}</td>
                        <td class="right {{ $valClass }}">{{ number_format($item['value_adjustment'], 2, ',', '.') }}</td>
                        <td>{{ $item['reason'] ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>Tidak ada data untuk diekspor.</p>
    @endforelse

    <div class="footer">Outlet Stock Opname Report • {{ $generatedAt }}</div>
</body>
</html>
