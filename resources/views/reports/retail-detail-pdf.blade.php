<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Retail Detail Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.2;
            color: #333;
            margin: 0;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
            border-bottom: 1px solid #333;
            padding-bottom: 4px;
        }
        .header h1 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
        }
        .header h2 {
            margin: 2px 0;
            font-size: 12px;
            font-weight: normal;
        }
        .info-section {
            margin-bottom: 4px;
            font-size: 8px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1px;
        }
        .info-label {
            font-weight: bold;
            width: 60px;
        }
        .info-value {
            flex: 1;
        }
        .section {
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .section-content {
            page-break-inside: avoid;
            orphans: 2;
            widows: 2;
        }
        .section-title {
            background-color: #f0f0f0;
            padding: 3px 4px;
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 3px;
            border-left: 3px solid #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            font-size: 8px;
            line-height: 1.1;
        }
        th {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 3px 4px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
            height: 16px;
        }
        td {
            border: 1px solid #ddd;
            padding: 2px 3px;
            font-size: 8px;
            height: 14px;
            vertical-align: middle;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .summary {
            margin-top: 6px;
            border: 1px solid #ddd;
            padding: 6px;
            background-color: #f9f9f9;
            font-size: 9px;
        }
        .grand-total {
            text-align: center;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #333;
        }
        .grand-total-label {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .grand-total-value {
            font-size: 14px;
            font-weight: bold;
            color: #28a745;
        }
        .no-data {
            text-align: center;
            padding: 10px;
            color: #666;
            font-style: italic;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>JUSTUS GROUP</h1>
        <h2>Retail Detail Report - {{ $customer }}</h2>
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Period:</span>
                <span class="info-value">{{ date('d/m/Y', strtotime($from)) }} - {{ date('d/m/Y', strtotime($to)) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Generated:</span>
                <span class="info-value">{{ date('d/m/Y H:i:s') }}</span>
            </div>
        </div>
    </div>

    @if(empty($detailData))
    <div class="no-data">
        Tidak ada data untuk periode yang dipilih.
    </div>
    @else
    <div class="space-y-4">
        @foreach($detailData as $category => $items)
        <div class="section">
            <div class="section-title">{{ $category }}</div>
            <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Subtotal</th>
                        <th class="text-right">Sale No.</th>
                        <th class="text-right">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td class="text-right">{{ number_format($item->qty, 2) }}</td>
                        <td class="text-right">{{ number_format($item->price, 0) }}</td>
                        <td class="text-right">{{ number_format($item->subtotal, 0) }}</td>
                        <td class="text-right text-xs">{{ $item->sale_number }}</td>
                        <td class="text-right text-xs">{{ date('d/m/Y', strtotime($item->sale_date)) }}</td>
                    </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="3" class="text-right"><strong>Total {{ $category }}:</strong></td>
                        <td class="text-right"><strong>{{ number_format(collect($items)->sum('subtotal'), 0) }}</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Summary -->
    <div class="summary">
        <div class="grand-total">
            <div class="grand-total-label">GRAND TOTAL</div>
            <div class="grand-total-value">Rp {{ number_format($totalAmount, 0) }}</div>
        </div>
    </div>
    @endif
</body>
</html>
