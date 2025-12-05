<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Request Order - {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 20mm;
        }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10pt;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            color: #2563eb;
            font-size: 20pt;
        }
        .header h2 {
            margin: 5px 0;
            color: #666;
            font-size: 14pt;
        }
        .info-section {
            margin-bottom: 20px;
            display: table;
            width: 100%;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 30%;
            padding: 5px 10px 5px 0;
            color: #555;
        }
        .info-value {
            display: table-cell;
            padding: 5px 0;
        }
        .items-section {
            margin-top: 20px;
        }
        .category-header {
            background-color: #2563eb;
            color: white;
            padding: 8px 12px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
            font-size: 11pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.items-table {
            margin-top: 10px;
        }
        table.items-table th {
            background-color: #e5e7eb;
            color: #1f2937;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #d1d5db;
            font-size: 9pt;
        }
        table.items-table td {
            padding: 8px;
            border: 1px solid #d1d5db;
            font-size: 9pt;
        }
        table.items-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        .total-section {
            margin-top: 20px;
            text-align: right;
        }
        .total-row {
            display: table;
            width: 100%;
            margin-top: 10px;
        }
        .total-label {
            display: table-cell;
            text-align: right;
            padding-right: 20px;
            font-weight: bold;
            font-size: 11pt;
        }
        .total-value {
            display: table-cell;
            text-align: right;
            font-weight: bold;
            font-size: 12pt;
            color: #2563eb;
        }
        .grand-total {
            font-size: 14pt;
            color: #1e40af;
            border-top: 2px solid #2563eb;
            padding-top: 10px;
            margin-top: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 9pt;
        }
        .status-draft {
            background-color: #e5e7eb;
            color: #374151;
        }
        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-submitted {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>REQUEST ORDER (RO)</h1>
        <h2>{{ $order->order_number }}</h2>
    </div>

    <div class="info-section">
        <div class="info-row">
            <div class="info-label">Tanggal:</div>
            <div class="info-value">{{ \Carbon\Carbon::parse($order->tanggal)->format('d/m/Y') }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Tanggal Kedatangan:</div>
            <div class="info-value">{{ $order->arrival_date ? \Carbon\Carbon::parse($order->arrival_date)->format('d/m/Y') : '-' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Outlet:</div>
            <div class="info-value">{{ $order->outlet ? $order->outlet->nama_outlet : '-' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Warehouse:</div>
            <div class="info-value">{{ $order->warehouseOutlet ? $order->warehouseOutlet->name : '-' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">FO Mode:</div>
            <div class="info-value">{{ $order->fo_mode ?? '-' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Requester:</div>
            <div class="info-value">{{ $order->requester ? $order->requester->nama_lengkap : '-' }}</div>
        </div>
        @if($order->foSchedule)
        <div class="info-row">
            <div class="info-label">Jadwal FO:</div>
            <div class="info-value">
                {{ $order->foSchedule->fo_mode }} - 
                {{ $order->foSchedule->day }} 
                {{ $order->foSchedule->open_time }} - {{ $order->foSchedule->close_time }}
            </div>
        </div>
        @endif
        <div class="info-row">
            <div class="info-label">Status:</div>
            <div class="info-value">
                <span class="status-badge status-{{ $order->status }}">
                    {{ strtoupper($order->status) }}
                </span>
            </div>
        </div>
        @if($order->description)
        <div class="info-row">
            <div class="info-label">Deskripsi:</div>
            <div class="info-value">{{ $order->description }}</div>
        </div>
        @endif
        @if($order->approver)
        <div class="info-row">
            <div class="info-label">Approved By:</div>
            <div class="info-value">{{ $order->approver->nama_lengkap }}</div>
        </div>
        @endif
        @if($order->approval_at)
        <div class="info-row">
            <div class="info-label">Approval Date:</div>
            <div class="info-value">{{ \Carbon\Carbon::parse($order->approval_at)->format('d/m/Y H:i') }}</div>
        </div>
        @endif
    </div>

    <div class="items-section">
        <h3 style="margin-bottom: 10px; color: #2563eb; font-size: 12pt;">Detail Items</h3>
        
        @foreach($groupedItems as $categoryName => $items)
            <div class="category-header">{{ $categoryName }}</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 35%;">Nama Item</th>
                        <th style="width: 10%;" class="text-center">Qty</th>
                        <th style="width: 15%;" class="text-right">Harga</th>
                        <th style="width: 15%;" class="text-right">Subtotal</th>
                        <th style="width: 20%;">Note</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $index => $item)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $item->item_name }}</td>
                        <td class="text-center">{{ number_format($item->qty ?? 0, 2) }} {{ $item->unit ?? '' }}</td>
                        <td class="text-right">Rp {{ number_format($item->price ?? 0, 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format(($item->qty ?? 0) * ($item->price ?? 0), 0, ',', '.') }}</td>
                        <td>{{ $item->note ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>

    <div class="footer">
        <div class="total-section">
            <div class="total-row">
                <div class="total-label">Subtotal:</div>
                <div class="total-value">Rp {{ number_format($subtotal, 0, ',', '.') }}</div>
            </div>
            <div class="total-row grand-total">
                <div class="total-label">Grand Total:</div>
                <div class="total-value">Rp {{ number_format($subtotal, 0, ',', '.') }}</div>
            </div>
        </div>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <div style="display: table; width: 100%;">
                <div style="display: table-cell; width: 50%; vertical-align: top;">
                    <div style="margin-bottom: 50px;">
                        <div style="font-weight: bold; margin-bottom: 5px;">Requester:</div>
                        <div style="margin-top: 40px; border-top: 1px solid #333; padding-top: 5px;">
                            {{ $order->requester ? $order->requester->nama_lengkap : '-' }}
                        </div>
                    </div>
                </div>
                @if($order->approver)
                <div style="display: table-cell; width: 50%; vertical-align: top;">
                    <div style="margin-bottom: 50px;">
                        <div style="font-weight: bold; margin-bottom: 5px;">Approver:</div>
                        <div style="margin-top: 40px; border-top: 1px solid #333; padding-top: 5px;">
                            {{ $order->approver->nama_lengkap }}
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        
        <div style="margin-top: 20px; text-align: center; color: #666; font-size: 8pt;">
            <div>Dicetak pada: {{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}</div>
        </div>
    </div>
</body>
</html>

