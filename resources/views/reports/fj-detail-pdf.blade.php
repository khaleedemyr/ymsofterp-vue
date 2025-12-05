<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>FJ Detail Report</title>
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
        .summary-grid {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 4px;
        }
        .summary-item {
            text-align: center;
            padding: 4px 6px;
            border: 1px solid #ddd;
            background-color: white;
            min-width: 80px;
            flex: 1;
        }
        .summary-label {
            font-size: 8px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .summary-value {
            font-size: 10px;
            font-weight: bold;
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
        .page-break {
            page-break-before: always;
        }
        .avoid-break {
            page-break-inside: avoid;
        }
        .keep-together {
            page-break-inside: avoid;
            orphans: 2;
            widows: 2;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>JUSTUS GROUP</h1>
        <h2>FJ Detail Report - {{ $customer }}</h2>
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



    <!-- Main Kitchen Section -->
    @if($mainKitchen['gr']->count() > 0)
    <div class="section">
        <div class="section-title">Main Kitchen</div>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mainKitchen['gr'] as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->unit }}</td>
                        <td class="text-right">{{ number_format($item->received_qty, 2) }}</td>
                        <td class="text-right">{{ number_format($item->price, 0) }}</td>
                        <td class="text-right">{{ number_format($item->subtotal, 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" class="text-right"><strong>Total Main Kitchen:</strong></td>
                        <td class="text-right"><strong>{{ number_format($mainKitchenTotal, 0) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    <!-- Main Store Section -->
    @if($mainStore['gr']->count() > 0)
    <div class="section">
        <div class="section-title">Main Store</div>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mainStore['gr'] as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->unit }}</td>
                        <td class="text-right">{{ number_format($item->received_qty, 2) }}</td>
                        <td class="text-right">{{ number_format($item->price, 0) }}</td>
                        <td class="text-right">{{ number_format($item->subtotal, 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" class="text-right"><strong>Total Main Store:</strong></td>
                        <td class="text-right"><strong>{{ number_format($mainStoreTotal, 0) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    <!-- Chemical Section -->
    @if($chemical['gr']->count() > 0)
    <div class="section">
        <div class="section-title">Chemical</div>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($chemical['gr'] as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->unit }}</td>
                        <td class="text-right">{{ number_format($item->received_qty, 2) }}</td>
                        <td class="text-right">{{ number_format($item->price, 0) }}</td>
                        <td class="text-right">{{ number_format($item->subtotal, 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" class="text-right"><strong>Total Chemical:</strong></td>
                        <td class="text-right"><strong>{{ number_format($chemicalTotal, 0) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    <!-- Stationary Section -->
    @if($stationary['gr']->count() > 0)
    <div class="section">
        <div class="section-title">Stationary</div>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stationary['gr'] as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->unit }}</td>
                        <td class="text-right">{{ number_format($item->received_qty, 2) }}</td>
                        <td class="text-right">{{ number_format($item->price, 0) }}</td>
                        <td class="text-right">{{ number_format($item->subtotal, 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" class="text-right"><strong>Total Stationary:</strong></td>
                        <td class="text-right"><strong>{{ number_format($stationaryTotal, 0) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    <!-- Marketing Section -->
    @if($marketing['gr']->count() > 0)
    <div class="section">
        <div class="section-title">Marketing</div>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($marketing['gr'] as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->unit }}</td>
                        <td class="text-right">{{ number_format($item->received_qty, 2) }}</td>
                        <td class="text-right">{{ number_format($item->price, 0) }}</td>
                        <td class="text-right">{{ number_format($item->subtotal, 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" class="text-right"><strong>Total Marketing:</strong></td>
                        <td class="text-right"><strong>{{ number_format($marketingTotal, 0) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    @if($mainKitchen['gr']->count() == 0 && $mainStore['gr']->count() == 0 && $chemical['gr']->count() == 0 && $stationary['gr']->count() == 0 && $marketing['gr']->count() == 0)
    <div class="no-data">
        Tidak ada data GR untuk periode yang dipilih.
    </div>
    @else
    <!-- Force display Main Store if it has data -->
    @if($mainStore['gr']->count() > 0)
    <div class="section">
        <div class="section-title">Main Store ({{ $mainStore['gr']->count() }} items)</div>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mainStore['gr'] as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->unit }}</td>
                        <td class="text-right">{{ number_format($item->received_qty, 2) }}</td>
                        <td class="text-right">{{ number_format($item->price, 0) }}</td>
                        <td class="text-right">{{ number_format($item->subtotal, 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" class="text-right"><strong>Total Main Store:</strong></td>
                        <td class="text-right"><strong>{{ number_format($mainStoreTotal, 0) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif
    @endif

    <!-- Summary -->
    @php
        $categoryCount = 0;
        if($mainKitchenTotal > 0) $categoryCount++;
        if($mainStoreTotal > 0) $categoryCount++;
        if($chemicalTotal > 0) $categoryCount++;
        if($stationaryTotal > 0) $categoryCount++;
        if($marketingTotal > 0) $categoryCount++;
    @endphp
    
    @if($grandTotal > 0 && $categoryCount > 1)
    <div class="summary">
        <div class="summary-grid">
            @if($mainKitchenTotal > 0)
            <div class="summary-item">
                <div class="summary-label">Main Kitchen</div>
                <div class="summary-value">{{ number_format($mainKitchenTotal, 0) }}</div>
            </div>
            @endif
            @if($mainStoreTotal > 0)
            <div class="summary-item">
                <div class="summary-label">Main Store</div>
                <div class="summary-value">{{ number_format($mainStoreTotal, 0) }}</div>
            </div>
            @endif
            @if($chemicalTotal > 0)
            <div class="summary-item">
                <div class="summary-label">Chemical</div>
                <div class="summary-value">{{ number_format($chemicalTotal, 0) }}</div>
            </div>
            @endif
            @if($stationaryTotal > 0)
            <div class="summary-item">
                <div class="summary-label">Stationary</div>
                <div class="summary-value">{{ number_format($stationaryTotal, 0) }}</div>
            </div>
            @endif
            @if($marketingTotal > 0)
            <div class="summary-item">
                <div class="summary-label">Marketing</div>
                <div class="summary-value">{{ number_format($marketingTotal, 0) }}</div>
            </div>
            @endif
        </div>
        <div class="grand-total">
            <div class="grand-total-label">GRAND TOTAL</div>
            <div class="grand-total-value">Rp {{ number_format($grandTotal, 0) }}</div>
        </div>
    </div>
    @endif
</body>
</html>
