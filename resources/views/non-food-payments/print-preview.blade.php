<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Non Food Payment Print Preview</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10px;
            background-color: #f5f5f5;
            font-size: 12px;
        }
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 14px;
            color: #666;
        }
        .payment-section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .payment-header {
            background-color: #f8f9fa;
            padding: 10px;
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
        }
        .payment-number {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        .payment-title {
            font-size: 16px;
            margin-top: 5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 10px;
        }
        .info-item {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            color: #333;
        }
        .info-value {
            color: #666;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .total-section {
            text-align: right;
            margin-top: 20px;
        }
        .total-amount {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
        }
        .page-break {
            page-break-before: auto;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-paid {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-cancelled {
            background-color: #e2e3e5;
            color: #383d41;
        }
        @media print {
            body {
                background-color: white;
                font-size: 11px;
                margin: 0;
                padding: 5px;
            }
            .print-container {
                box-shadow: none;
                margin: 0;
                padding: 5px;
                max-width: none;
            }
            .payment-section {
                margin-bottom: 10px;
            }
            .payment-header {
                padding: 8px;
                margin-bottom: 8px;
            }
            .info-grid {
                gap: 10px;
                margin-bottom: 8px;
            }
            .items-table {
                font-size: 10px;
            }
            .items-table th,
            .items-table td {
                padding: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="header">
            <img src="/images/logojustusgroup.png" alt="Justus Group" class="logo">
            <div class="title">NON FOOD PAYMENT</div>
            <div class="subtitle">JUSTUS GROUP</div>
        </div>

        @foreach($payments as $index => $payment)
            @if($index > 0)
                <div style="margin-top: 20px; border-top: 2px solid #ddd; padding-top: 15px;"></div>
            @endif

            <div class="payment-section">
                <div class="payment-header">
                    <div class="payment-number">{{ $payment->payment_number }}</div>
                    <div class="payment-title">{{ $payment->supplier->name ?? 'N/A' }}</div>
                </div>

                <div class="info-grid">
                    <div>
                        <div class="info-item">
                            <div class="info-label">Payment Date:</div>
                            <div class="info-value">{{ $payment->payment_date->format('d M Y') }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Supplier:</div>
                            <div class="info-value">{{ $payment->supplier->name ?? 'N/A' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Payment Method:</div>
                            <div class="info-value">{{ ucfirst($payment->payment_method) }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Created By:</div>
                            <div class="info-value">{{ $payment->creator->nama_lengkap ?? 'N/A' }}</div>
                        </div>
                    </div>
                    <div>
                        <div class="info-item">
                            <div class="info-label">Status:</div>
                            <div class="info-value">
                                <span class="status-badge status-{{ $payment->status }}">
                                    {{ ucfirst($payment->status) }}
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Amount:</div>
                            <div class="info-value">Rp {{ number_format($payment->amount, 0, ',', '.') }}</div>
                        </div>
                        @if($payment->due_date)
                        <div class="info-item">
                            <div class="info-label">Due Date:</div>
                            <div class="info-value">{{ $payment->due_date->format('d M Y') }}</div>
                        </div>
                        @endif
                        @if($payment->reference_number)
                        <div class="info-item">
                            <div class="info-label">Reference Number:</div>
                            <div class="info-value">{{ $payment->reference_number }}</div>
                        </div>
                        @endif
                    </div>
                </div>

                @if($payment->description)
                <div class="info-item">
                    <div class="info-label">Description:</div>
                    <div class="info-value">{{ $payment->description }}</div>
                </div>
                @endif

                @if($payment->notes)
                <div class="info-item">
                    <div class="info-label">Notes:</div>
                    <div class="info-value">{{ $payment->notes }}</div>
                </div>
                @endif

                <!-- Purchase Order Information -->
                @if($payment->purchase_order_ops)
                <div style="margin-top: 15px; padding: 10px; background-color: #f8f9fa; border-left: 3px solid #28a745;">
                    <h4 style="margin: 0 0 10px 0; color: #28a745;">Purchase Order Information</h4>
                    <div class="info-grid">
                        <div>
                            <div class="info-item">
                                <div class="info-label">PO Number:</div>
                                <div class="info-value">{{ $payment->purchase_order_ops->number }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">PO Date:</div>
                                <div class="info-value">{{ $payment->purchase_order_ops->date->format('d M Y') }}</div>
                            </div>
                        </div>
                        <div>
                            <div class="info-item">
                                <div class="info-label">PO Status:</div>
                                <div class="info-value">{{ $payment->purchase_order_ops->status }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Grand Total:</div>
                                <div class="info-value">Rp {{ number_format($payment->purchase_order_ops->grand_total, 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>

                    @if($payment->purchase_order_ops->items && count($payment->purchase_order_ops->items) > 0)
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Item Name</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payment->purchase_order_ops->items as $itemIndex => $item)
                                <tr>
                                    <td>{{ $itemIndex + 1 }}</td>
                                    <td>{{ $item->item_name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ $item->unit }}</td>
                                    <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
                @endif

                <!-- Purchase Requisition Information -->
                @if($payment->purchase_requisition)
                <div style="margin-top: 15px; padding: 10px; background-color: #f8f9fa; border-left: 3px solid #17a2b8;">
                    <h4 style="margin: 0 0 10px 0; color: #17a2b8;">Purchase Requisition Information</h4>
                    <div class="info-grid">
                        <div>
                            <div class="info-item">
                                <div class="info-label">PR Number:</div>
                                <div class="info-value">{{ $payment->purchase_requisition->pr_number }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">PR Date:</div>
                                <div class="info-value">{{ $payment->purchase_requisition->date->format('d M Y') }}</div>
                            </div>
                        </div>
                        <div>
                            <div class="info-item">
                                <div class="info-label">Title:</div>
                                <div class="info-value">{{ $payment->purchase_requisition->title ?? '-' }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Amount:</div>
                                <div class="info-value">Rp {{ number_format($payment->purchase_requisition->amount, 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <div class="total-section">
                    <div class="total-amount">
                        Payment Amount: Rp {{ number_format($payment->amount, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html>
