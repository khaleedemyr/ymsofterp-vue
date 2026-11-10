<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Report Item PO yang Sudah di-GR</title>
    <style>
        @page { margin: 8mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 7.5pt;
            color: #1f2937;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #2563eb;
            font-size: 13pt;
        }
        .header .sub {
            margin-top: 3px;
            color: #6b7280;
            font-size: 8pt;
        }
        .meta {
            width: 100%;
            margin-bottom: 8px;
            border-collapse: collapse;
        }
        .meta td {
            padding: 2px 0;
            vertical-align: top;
        }
        .meta .label {
            width: 14%;
            color: #6b7280;
            font-weight: bold;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .summary td {
            width: 25%;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            padding: 5px 7px;
        }
        .summary .title {
            color: #6b7280;
            font-size: 7pt;
        }
        .summary .value {
            font-weight: bold;
            color: #1d4ed8;
            font-size: 9pt;
        }
        .summary .up { color: #dc2626; }
        .summary .down { color: #16a34a; }
        .summary .stable { color: #4b5563; }
        table.items {
            width: 100%;
            border-collapse: collapse;
        }
        table.items th {
            background: #2563eb;
            color: #fff;
            font-size: 6.5pt;
            padding: 3px 2px;
            text-align: left;
            border: 1px solid #1d4ed8;
        }
        table.items td {
            border: 1px solid #e5e7eb;
            padding: 2px;
            font-size: 6.5pt;
            vertical-align: top;
        }
        table.items tr:nth-child(even) td {
            background: #f9fafb;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .neg { color: #dc2626; }
        .pos { color: #16a34a; }
        .footer {
            margin-top: 10px;
            color: #9ca3af;
            font-size: 7pt;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Report Item PO yang Sudah di-GR</h1>
        <div class="sub">Generated at {{ $generatedAt }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Periode</td>
            <td>
                @if(!empty($filters['from']) || !empty($filters['to']))
                    {{ $filters['from'] ? \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') : '-' }}
                    –
                    {{ $filters['to'] ? \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') : '-' }}
                @else
                    Semua
                @endif
            </td>
            <td class="label">Supplier</td>
            <td>{{ $filters['supplier_name'] ?? 'Semua' }}</td>
        </tr>
        <tr>
            <td class="label">Item</td>
            <td>{{ $filters['item_name'] ?? 'Semua' }}</td>
            <td class="label">Search</td>
            <td>{{ $filters['search'] ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Filter Harga</td>
            <td colspan="3">
                @php
                    $priceFilters = [];
                    if (!empty($filters['has_price_change'])) $priceFilters[] = 'Berubah harga';
                    if (!empty($filters['price_increase'])) $priceFilters[] = 'Kenaikan harga';
                    if (!empty($filters['price_decrease'])) $priceFilters[] = 'Penurunan harga';
                @endphp
                {{ count($priceFilters) ? implode(', ', $priceFilters) : 'Semua' }}
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                <div class="title">Total Items</div>
                <div class="value">{{ number_format($summary['total_items'] ?? 0, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="title">Naik Harga</div>
                <div class="value up">{{ number_format($summary['naik_harga'] ?? 0, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="title">Turun Harga</div>
                <div class="value down">{{ number_format($summary['turun_harga'] ?? 0, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="title">Harga Stabil</div>
                <div class="value stable">{{ number_format($summary['harga_stabil'] ?? 0, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 2%;">No</th>
                <th style="width: 8%;">GR Number</th>
                <th style="width: 5%;">Receive</th>
                <th style="width: 7%;">PO Number</th>
                <th style="width: 5%;">PO Date</th>
                <th style="width: 10%;">Supplier</th>
                <th style="width: 10%;">Item</th>
                <th style="width: 6%;">Category</th>
                <th style="width: 4%;" class="right">Qty PO</th>
                <th style="width: 4%;" class="right">Qty Rec</th>
                <th style="width: 4%;">Unit</th>
                <th style="width: 6%;" class="right">PO Price</th>
                <th style="width: 6%;" class="right">Prev Price</th>
                <th style="width: 6%;" class="right">Change</th>
                <th style="width: 8%;">PO Creator</th>
                <th style="width: 8%;">Received By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                @php
                    $change = (float) ($row->price_change ?? 0);
                    $changeClass = $change > 0 ? 'pos' : ($change < 0 ? 'neg' : '');
                    $changePct = $row->price_change_percentage ?? 0;
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row->gr_number ?? '-' }}</td>
                    <td>{{ $row->receive_date ? \Carbon\Carbon::parse($row->receive_date)->format('d/m/Y') : '-' }}</td>
                    <td>{{ $row->po_number ?? '-' }}</td>
                    <td>{{ $row->po_date ? \Carbon\Carbon::parse($row->po_date)->format('d/m/Y') : '-' }}</td>
                    <td>{{ $row->supplier_name ?? '-' }}</td>
                    <td>{{ $row->item_name ?? '-' }}</td>
                    <td>{{ $row->category_name ?? '-' }}</td>
                    <td class="right">{{ number_format((float) ($row->po_qty ?? 0), 2, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) ($row->qty_received ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $row->unit_name ?? '-' }}</td>
                    <td class="right">{{ number_format((float) ($row->po_price ?? 0), 0, ',', '.') }}</td>
                    <td class="right">{{ $row->previous_price !== null ? number_format((float) $row->previous_price, 0, ',', '.') : '-' }}</td>
                    <td class="right {{ $changeClass }}">
                        @if($row->previous_price === null)
                            -
                        @else
                            {{ number_format($change, 0, ',', '.') }}
                            @if($changePct)
                                ({{ number_format((float) $changePct, 2, ',', '.') }}%)
                            @endif
                        @endif
                    </td>
                    <td>{{ $row->po_creator_name ?? '-' }}</td>
                    <td>{{ $row->received_by_name ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="16" class="center">Tidak ada data untuk diekspor.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">PO GR Report • {{ $generatedAt }} • Total {{ number_format($summary['total_items'] ?? 0, 0, ',', '.') }} baris</div>
</body>
</html>
