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

                <!-- Items Section - Show items from PO or PR -->
                @php
                    $items = $paymentItems[$payment->id] ?? collect();
                    $itemsSource = null;
                    
                    // Determine source based on which ID exists (prioritize PO)
                    if ($payment->purchase_order_ops_id) {
                        $itemsSource = 'PO';
                    } elseif ($payment->purchase_requisition_id) {
                        $itemsSource = 'PR';
                    }
                @endphp

                @if($items && $items->count() > 0)
                <div style="margin-top: 20px;">
                    <h4 style="margin: 0 0 15px 0; font-weight: bold; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px;">
                        Payment Items ({{ $itemsSource }})
                    </h4>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Item Name</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Price</th>
                                <th>Discount</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $subtotalBeforeDiscount = 0;
                                $totalDiscount = 0;
                            @endphp
                            @foreach($items as $itemIndex => $item)
                                @php
                                    $itemName = is_object($item) ? ($item->item_name ?? 'N/A') : (is_array($item) ? ($item['item_name'] ?? 'N/A') : 'N/A');
                                    
                                    // Format item_name for allowance type if not already formatted
                                    if ($itemsSource === 'PR') {
                                        $itemType = is_object($item) ? ($item->item_type ?? null) : (is_array($item) ? ($item['item_type'] ?? null) : null);
                                        $allowanceRecipient = is_object($item) ? ($item->allowance_recipient_name ?? null) : (is_array($item) ? ($item['allowance_recipient_name'] ?? null) : null);
                                        $allowanceAccount = is_object($item) ? ($item->allowance_account_number ?? null) : (is_array($item) ? ($item['allowance_account_number'] ?? null) : null);
                                        
                                        if ($itemType === 'allowance' && $allowanceRecipient && $allowanceAccount && strpos($itemName, 'Allowance -') === false) {
                                            $itemName = 'Allowance - ' . $allowanceRecipient . ' - ' . $allowanceAccount;
                                        } elseif ($itemType === 'allowance' && $allowanceRecipient && strpos($itemName, 'Allowance -') === false) {
                                            $itemName = 'Allowance - ' . $allowanceRecipient;
                                        }
                                    }
                                    
                                    $itemQty = $itemsSource === 'PO' 
                                        ? (is_object($item) ? ($item->quantity ?? 0) : (is_array($item) ? ($item['quantity'] ?? 0) : 0))
                                        : (is_object($item) ? ($item->qty ?? 0) : (is_array($item) ? ($item['qty'] ?? 0) : 0));
                                    $itemUnit = is_object($item) ? ($item->unit ?? 'N/A') : (is_array($item) ? ($item['unit'] ?? 'N/A') : 'N/A');
                                    $itemPrice = $itemsSource === 'PO'
                                        ? (is_object($item) ? ($item->price ?? 0) : (is_array($item) ? ($item['price'] ?? 0) : 0))
                                        : (is_object($item) ? ($item->unit_price ?? 0) : (is_array($item) ? ($item['unit_price'] ?? 0) : 0));
                                    
                                    // Calculate subtotal before discount
                                    $itemSubtotalBeforeDiscount = $itemPrice * $itemQty;
                                    $subtotalBeforeDiscount += $itemSubtotalBeforeDiscount;
                                    
                                    // Get discount
                                    $itemDiscountPercent = $itemsSource === 'PO'
                                        ? (is_object($item) ? ($item->discount_percent ?? 0) : (is_array($item) ? ($item['discount_percent'] ?? 0) : 0))
                                        : 0;
                                    $itemDiscountAmount = $itemsSource === 'PO'
                                        ? (is_object($item) ? ($item->discount_amount ?? 0) : (is_array($item) ? ($item['discount_amount'] ?? 0) : 0))
                                        : 0;
                                    
                                    // Calculate discount amount if percentage
                                    if ($itemDiscountPercent > 0) {
                                        $itemDiscountAmount = $itemSubtotalBeforeDiscount * ($itemDiscountPercent / 100);
                                    }
                                    
                                    $totalDiscount += $itemDiscountAmount;
                                    
                                    $itemTotal = $itemsSource === 'PO'
                                        ? (is_object($item) ? ($item->total ?? 0) : (is_array($item) ? ($item['total'] ?? 0) : 0))
                                        : (is_object($item) ? ($item->subtotal ?? 0) : (is_array($item) ? ($item['subtotal'] ?? 0) : 0));
                                @endphp
                                <tr>
                                    <td>{{ $itemIndex + 1 }}</td>
                                    <td>{{ $itemName }}</td>
                                    <td>{{ $itemQty }}</td>
                                    <td>{{ $itemUnit }}</td>
                                    <td>Rp {{ number_format($itemPrice, 0, ',', '.') }}</td>
                                    <td>
                                        @if($itemDiscountAmount > 0)
                                            @if($itemDiscountPercent > 0)
                                                {{ number_format($itemDiscountPercent, 2) }}%<br>
                                            @endif
                                            Rp {{ number_format($itemDiscountAmount, 0, ',', '.') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>Rp {{ number_format($itemTotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if($itemsSource === 'PO')
                            @php
                                // Try both camelCase and snake_case for PO access
                                $po = $payment->purchaseOrderOps ?? $payment->purchase_order_ops ?? null;
                                $discountTotalPercent = $po ? ($po->discount_total_percent ?? 0) : 0;
                                $discountTotalAmount = $po ? ($po->discount_total_amount ?? 0) : 0;
                                
                                // Calculate total discount (item discount + total discount)
                                $subtotalAfterItemDiscount = $subtotalBeforeDiscount - $totalDiscount;
                                
                                // Apply total discount if percentage
                                if ($discountTotalPercent > 0) {
                                    $discountTotalAmount = $subtotalAfterItemDiscount * ($discountTotalPercent / 100);
                                }
                                
                                $subtotalAfterTotalDiscount = $subtotalAfterItemDiscount - $discountTotalAmount;
                                
                                // Calculate PPN if enabled
                                $ppnAmount = 0;
                                $ppnEnabled = $po ? ($po->ppn_enabled ?? false) : false;
                                if ($ppnEnabled) {
                                    $ppnAmount = $subtotalAfterTotalDiscount * 0.11; // 11% PPN
                                }
                                
                                $grandTotalAfterDiscount = $subtotalAfterTotalDiscount + $ppnAmount;
                            @endphp
                            <tfoot>
                                <tr>
                                    <td colspan="5" style="text-align: right; font-weight: bold;">Subtotal:</td>
                                    <td colspan="2" style="font-weight: bold;">Rp {{ number_format($subtotalBeforeDiscount, 0, ',', '.') }}</td>
                                </tr>
                                @if($totalDiscount > 0)
                                <tr>
                                    <td colspan="5" style="text-align: right;">Discount (Item):</td>
                                    <td colspan="2">- Rp {{ number_format($totalDiscount, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                                @if($discountTotalAmount > 0)
                                <tr>
                                    <td colspan="5" style="text-align: right;">
                                        Discount Total
                                        @if($discountTotalPercent > 0)
                                            ({{ number_format($discountTotalPercent, 2) }}%)
                                        @endif
                                        :
                                    </td>
                                    <td colspan="2">- Rp {{ number_format($discountTotalAmount, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                                @if($ppnEnabled && $ppnAmount > 0)
                                <tr>
                                    <td colspan="5" style="text-align: right;">PPN (11%):</td>
                                    <td colspan="2">+ Rp {{ number_format($ppnAmount, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="5" style="text-align: right; font-size: 14px;">
                                        @if($totalDiscount > 0 || $discountTotalAmount > 0 || ($ppnEnabled && $ppnAmount > 0))
                                            Grand Total{{ $ppnEnabled && $ppnAmount > 0 ? ' (After Discount + PPN)' : ' After Discount' }}:
                                        @else
                                            Grand Total:
                                        @endif
                                    </td>
                                    <td colspan="2" style="font-size: 14px; color: #28a745;">Rp {{ number_format($grandTotalAfterDiscount, 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                @else
                <!-- Debug info (remove in production) -->
                <div style="margin-top: 20px; padding: 10px; background-color: #fff3cd; border-left: 3px solid #ffc107;">
                    <p style="margin: 0; color: #856404;">
                        <strong>Debug Info:</strong><br>
                        PO ID: {{ $payment->purchase_order_ops_id ?? 'N/A' }}<br>
                        PR ID: {{ $payment->purchase_requisition_id ?? 'N/A' }}<br>
                        PO Items Count: {{ $payment->purchaseOrderOps && $payment->purchaseOrderOps->items ? $payment->purchaseOrderOps->items->count() : 'N/A' }}<br>
                        PR Items Count: {{ $payment->purchaseRequisition && $payment->purchaseRequisition->items ? $payment->purchaseRequisition->items->count() : 'N/A' }}
                    </p>
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
                            @php
                                $poCreator = $payment->purchaseOrderOps->creator ?? $payment->purchase_order_ops->creator ?? null;
                            @endphp
                            @if($poCreator)
                            <div class="info-item">
                                <div class="info-label">Creator:</div>
                                <div class="info-value">{{ $poCreator->nama_lengkap ?? 'N/A' }}</div>
                            </div>
                            @endif
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
                    <h5 style="margin: 15px 0 10px 0; font-weight: bold; color: #333;">PO Items:</h5>
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
                    @else
                    <p style="margin: 15px 0; color: #666; font-style: italic;">No items found for this Purchase Order.</p>
                    @endif
                </div>
                @endif

                <!-- Purchase Requisition Information -->
                @php
                    // Try to get PR from different sources
                    $pr = null;
                    // First priority: direct PR relation (for direct PR payments)
                    if ($payment->purchase_requisition_id) {
                        // Try both camelCase and snake_case
                        $pr = $payment->purchaseRequisition ?? $payment->purchase_requisition ?? null;
                    }
                    // Second priority: PR from PO (set in controller)
                    if (!$pr && isset($payment->pr_from_po)) {
                        $pr = $payment->pr_from_po;
                    }
                    // Third priority: PR from PO source_pr relation
                    if (!$pr && $payment->purchaseOrderOps && $payment->purchaseOrderOps->source_pr) {
                        $pr = $payment->purchaseOrderOps->source_pr;
                    }
                @endphp
                @if($pr)
                <div style="margin-top: 15px; padding: 10px; background-color: #e7f3ff; border-left: 3px solid #17a2b8;">
                    <h4 style="margin: 0 0 10px 0; color: #17a2b8; font-weight: bold;">
                        <i class="fa fa-shopping-cart" style="margin-right: 5px;"></i>
                        Purchase Requisition Information
                    </h4>
                    <div class="info-grid">
                        <div>
                            <div class="info-item">
                                <div class="info-label">PR Number:</div>
                                <div class="info-value" style="font-weight: bold;">{{ $pr->pr_number ?? '-' }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">PR Date:</div>
                                <div class="info-value">
                                    @if(isset($pr->date))
                                        @if($pr->date instanceof \Carbon\Carbon)
                                            {{ $pr->date->format('d M Y') }}
                                        @elseif(is_string($pr->date))
                                            {{ \Carbon\Carbon::parse($pr->date)->format('d M Y') }}
                                        @else
                                            {{ $pr->date ?? '-' }}
                                        @endif
                                    @elseif(isset($pr->created_at))
                                        {{ \Carbon\Carbon::parse($pr->created_at)->format('d M Y') }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                            @if(!empty($pr->title))
                            <div class="info-item">
                                <div class="info-label">Title:</div>
                                <div class="info-value" style="font-weight: 600;">{{ $pr->title }}</div>
                            </div>
                            @endif
                            @php
                                $prCreator = $pr->creator ?? null;
                            @endphp
                            @if($prCreator)
                            <div class="info-item">
                                <div class="info-label">Creator:</div>
                                <div class="info-value">{{ $prCreator->nama_lengkap ?? 'N/A' }}</div>
                            </div>
                            @endif
                        </div>
                        <div>
                            @php
                                $prDivision = $pr->division ?? null;
                                $prCategory = $pr->category ?? null;
                                $prOutlet = $pr->outlet ?? null;
                            @endphp
                            @if($prDivision)
                            <div class="info-item">
                                <div class="info-label">Division:</div>
                                <div class="info-value">{{ $prDivision->nama_divisi ?? 'N/A' }}</div>
                            </div>
                            @endif
                            @if($prCategory)
                            <div class="info-item">
                                <div class="info-label">Category:</div>
                                <div class="info-value">{{ $prCategory->name ?? 'N/A' }}</div>
                            </div>
                            @if($prCategory->budget_type)
                            <div class="info-item">
                                <div class="info-label">Budget Type:</div>
                                <div class="info-value">{{ $prCategory->budget_type ?? 'N/A' }}</div>
                            </div>
                            @endif
                            @endif
                            @if($prOutlet)
                            <div class="info-item">
                                <div class="info-label">Outlet:</div>
                                <div class="info-value" style="font-weight: 600;">{{ is_object($prOutlet) ? ($prOutlet->nama_outlet ?? '-') : '-' }}</div>
                            </div>
                            @endif
                            <div class="info-item">
                                <div class="info-label">Amount:</div>
                                <div class="info-value">Rp {{ number_format($pr->amount ?? 0, 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                    @if(isset($pr->description) && $pr->description)
                    <div class="info-item" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                        <div class="info-label">Description:</div>
                        <div class="info-value" style="white-space: pre-wrap; margin-top: 5px;">{{ $pr->description }}</div>
                    </div>
                    @endif

                    @php
                        $prItems = $pr->items ?? collect();
                    @endphp
                    @if($prItems && count($prItems) > 0)
                    <h5 style="margin: 15px 0 10px 0; font-weight: bold; color: #333;">PR Items:</h5>
                    <table class="items-table" style="margin-top: 15px;">
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
                            @foreach($prItems as $itemIndex => $item)
                                @php
                                    $itemName = $item->item_name ?? 'N/A';
                                    
                                    // Format item_name for allowance type if not already formatted (fallback)
                                    if (isset($item->item_type) && $item->item_type === 'allowance') {
                                        $allowanceRecipient = $item->allowance_recipient_name ?? null;
                                        $allowanceAccount = $item->allowance_account_number ?? null;
                                        
                                        if ($allowanceRecipient && $allowanceAccount && strpos($itemName, 'Allowance -') === false) {
                                            $itemName = 'Allowance - ' . $allowanceRecipient . ' - ' . $allowanceAccount;
                                        } elseif ($allowanceRecipient && strpos($itemName, 'Allowance -') === false) {
                                            $itemName = 'Allowance - ' . $allowanceRecipient;
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $itemIndex + 1 }}</td>
                                    <td>{{ $itemName }}</td>
                                    <td>{{ $item->qty ?? 0 }}</td>
                                    <td>{{ $item->unit ?? 'N/A' }}</td>
                                    <td>Rp {{ number_format($item->unit_price ?? 0, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($item->subtotal ?? 0, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <p style="margin: 15px 0; color: #666; font-style: italic;">No items found for this Purchase Requisition.</p>
                    @endif
                </div>
                @endif

                <!-- Budget Information -->
                @php
                    // Get budget info for this payment
                    $budgetInfo = null;
                    if (isset($budgetInfos) && is_array($budgetInfos) && isset($budgetInfos[$payment->id])) {
                        $budgetInfo = $budgetInfos[$payment->id];
                    }
                    $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                @endphp
                
                @if($budgetInfo && is_array($budgetInfo) && isset($budgetInfo['budget_type']))
                <div style="margin-top: 15px; padding: 10px; background-color: #f0f9ff; border-left: 3px solid #0ea5e9;">
                    <h4 style="margin: 0 0 10px 0; color: #0ea5e9; font-weight: bold;">
                        <i class="fa fa-chart-pie" style="margin-right: 5px;"></i>
                        {{ $budgetInfo['budget_type'] === 'PER_OUTLET' ? 'Outlet Budget Information' : 'Category Budget Information' }} - {{ $monthNames[$budgetInfo['current_month'] - 1] ?? $budgetInfo['current_month'] }} {{ $budgetInfo['current_year'] }}
                        <span style="margin-left: 5px; font-size: 12px; font-weight: normal; color: #666;">
                            ({{ $budgetInfo['budget_type'] === 'PER_OUTLET' ? 'Per Outlet' : 'Global' }})
                        </span>
                    </h4>
                    
                    @if(isset($budgetInfo['division']) && $budgetInfo['division'])
                    <div style="margin-bottom: 10px; padding: 8px; background-color: #e0f2fe; border: 1px solid #7dd3fc; border-radius: 5px;">
                        <p style="margin: 0; font-size: 12px; color: #0c4a6e;">
                            <i class="fa fa-building" style="margin-right: 5px;"></i>
                            <strong>Division:</strong> {{ $budgetInfo['division'] }}
                            @if(isset($budgetInfo['category_name']) && $budgetInfo['category_name'])
                            <span style="margin-left: 10px;">
                                <strong>Category:</strong> {{ $budgetInfo['category_name'] }}
                            </span>
                            @endif
                        </p>
                    </div>
                    @endif
                    
                    @if($budgetInfo['budget_type'] === 'PER_OUTLET' && isset($budgetInfo['outlet_info']))
                    <div style="margin-bottom: 10px; padding: 8px; background-color: #dbeafe; border: 1px solid #93c5fd; border-radius: 5px;">
                        <p style="margin: 0; font-size: 12px; color: #1e40af;">
                            <i class="fa fa-store" style="margin-right: 5px;"></i>
                            <strong>Outlet:</strong> {{ $budgetInfo['outlet_info']['name'] ?? 'N/A' }}
                        </p>
                    </div>
                    @endif
                    
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 10px;">
                        <div style="background-color: #dbeafe; border: 1px solid #93c5fd; border-radius: 5px; padding: 10px;">
                            <div style="font-size: 11px; font-weight: 600; color: #1e40af; margin-bottom: 5px;">
                                {{ $budgetInfo['budget_type'] === 'PER_OUTLET' ? 'Outlet Budget' : 'Total Budget' }}
                            </div>
                            <div style="font-size: 16px; font-weight: bold; color: #1e3a8a;">
                                Rp {{ number_format($budgetInfo['budget_type'] === 'PER_OUTLET' ? $budgetInfo['outlet_budget'] : $budgetInfo['category_budget'], 0, ',', '.') }}
                            </div>
                            @if($budgetInfo['budget_type'] === 'PER_OUTLET')
                            <div style="font-size: 10px; color: #64748b; margin-top: 3px;">
                                Global: Rp {{ number_format($budgetInfo['category_budget'], 0, ',', '.') }}
                            </div>
                            @endif
                        </div>
                        
                        <div style="background-color: #fed7aa; border: 1px solid #fdba74; border-radius: 5px; padding: 10px;">
                            <div style="font-size: 11px; font-weight: 600; color: #9a3412; margin-bottom: 5px;">
                                Used Amount
                            </div>
                            <div style="font-size: 16px; font-weight: bold; color: #7c2d12;">
                                Rp {{ number_format($budgetInfo['budget_type'] === 'PER_OUTLET' ? $budgetInfo['outlet_used_amount'] : $budgetInfo['category_used_amount'], 0, ',', '.') }}
                            </div>
                        </div>
                        
                        <div style="background-color: {{ ($budgetInfo['budget_type'] === 'PER_OUTLET' ? ($budgetInfo['outlet_remaining_amount'] ?? 0) : ($budgetInfo['category_remaining_amount'] ?? 0)) < 0 ? '#fee2e2' : '#dcfce7' }}; border: 1px solid {{ ($budgetInfo['budget_type'] === 'PER_OUTLET' ? ($budgetInfo['outlet_remaining_amount'] ?? 0) : ($budgetInfo['category_remaining_amount'] ?? 0)) < 0 ? '#fca5a5' : '#86efac' }}; border-radius: 5px; padding: 10px;">
                            <div style="font-size: 11px; font-weight: 600; color: {{ ($budgetInfo['budget_type'] === 'PER_OUTLET' ? ($budgetInfo['outlet_remaining_amount'] ?? 0) : ($budgetInfo['category_remaining_amount'] ?? 0)) < 0 ? '#991b1b' : '#166534' }}; margin-bottom: 5px;">
                                Remaining Budget
                            </div>
                            <div style="font-size: 16px; font-weight: bold; color: {{ ($budgetInfo['budget_type'] === 'PER_OUTLET' ? ($budgetInfo['outlet_remaining_amount'] ?? 0) : ($budgetInfo['category_remaining_amount'] ?? 0)) < 0 ? '#991b1b' : '#166534' }};">
                                Rp {{ number_format($budgetInfo['budget_type'] === 'PER_OUTLET' ? ($budgetInfo['outlet_remaining_amount'] ?? 0) : ($budgetInfo['category_remaining_amount'] ?? 0), 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                    
                    @php
                        $totalBudget = $budgetInfo['budget_type'] === 'PER_OUTLET' ? $budgetInfo['outlet_budget'] : $budgetInfo['category_budget'];
                        $usedAmount = $budgetInfo['budget_type'] === 'PER_OUTLET' ? $budgetInfo['outlet_used_amount'] : $budgetInfo['category_used_amount'];
                        $remainingAmount = $budgetInfo['budget_type'] === 'PER_OUTLET' ? ($budgetInfo['outlet_remaining_amount'] ?? 0) : ($budgetInfo['category_remaining_amount'] ?? 0);
                        $usagePercentage = $totalBudget > 0 ? ($usedAmount / $totalBudget) * 100 : 0;
                    @endphp
                    
                    <!-- Progress Bar -->
                    <div style="margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: #666; margin-bottom: 5px;">
                            <span>Budget Usage</span>
                            <span>{{ round($usagePercentage) }}%</span>
                        </div>
                        <div style="width: 100%; height: 20px; background-color: #e5e7eb; border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; background-color: {{ $usagePercentage >= 100 ? '#ef4444' : ($usagePercentage >= 80 ? '#f59e0b' : ($usagePercentage >= 60 ? '#f97316' : '#10b981')) }}; width: {{ min($usagePercentage, 100) }}%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    
                    @if($remainingAmount < 0)
                    <div style="margin-top: 10px; padding: 8px; background-color: #fee2e2; border: 1px solid #fca5a5; border-radius: 5px; color: #991b1b; font-size: 11px;">
                        <i class="fa fa-exclamation-triangle" style="margin-right: 5px;"></i>
                        <strong>Budget Exceeded!</strong> 
                        <span>
                            {{ $budgetInfo['budget_type'] === 'PER_OUTLET' ? 'This outlet has exceeded its monthly budget limit.' : 'This category has exceeded its monthly budget limit.' }}
                        </span>
                    </div>
                    @elseif($remainingAmount < ($totalBudget * 0.1))
                    <div style="margin-top: 10px; padding: 8px; background-color: #fef3c7; border: 1px solid #fcd34d; border-radius: 5px; color: #92400e; font-size: 11px;">
                        <i class="fa fa-exclamation-circle" style="margin-right: 5px;"></i>
                        <strong>Budget Warning!</strong> Only Rp {{ number_format($remainingAmount, 0, ',', '.') }} remaining.
                    </div>
                    @endif
                </div>
                @endif

                <div class="total-section">
                    @php
                        // Calculate totals if PO
                        $showGrandTotal = false;
                        $finalGrandTotal = $payment->amount;
                        $subtotalBeforeDiscount = 0;
                        $totalItemDiscount = 0;
                        $discountTotalAmount = 0;
                        $discountTotalPercent = 0;
                        $ppnAmount = 0;
                        $ppnEnabled = false;
                        
                        if ($itemsSource === 'PO') {
                            // Try both camelCase and snake_case for PO access
                            $po = $payment->purchaseOrderOps ?? $payment->purchase_order_ops ?? null;
                            $items = $paymentItems[$payment->id] ?? collect();
                            
                            if ($items->isNotEmpty()) {
                                foreach ($items as $item) {
                                    $itemQty = is_object($item) ? ($item->quantity ?? 0) : (is_array($item) ? ($item['quantity'] ?? 0) : 0);
                                    $itemPrice = is_object($item) ? ($item->price ?? 0) : (is_array($item) ? ($item['price'] ?? 0) : 0);
                                    $itemSubtotal = $itemPrice * $itemQty;
                                    $subtotalBeforeDiscount += $itemSubtotal;
                                    
                                    $itemDiscountPercent = is_object($item) ? ($item->discount_percent ?? 0) : (is_array($item) ? ($item['discount_percent'] ?? 0) : 0);
                                    $itemDiscountAmount = is_object($item) ? ($item->discount_amount ?? 0) : (is_array($item) ? ($item['discount_amount'] ?? 0) : 0);
                                    
                                    if ($itemDiscountPercent > 0) {
                                        $itemDiscountAmount = $itemSubtotal * ($itemDiscountPercent / 100);
                                    }
                                    
                                    $totalItemDiscount += $itemDiscountAmount;
                                }
                                
                                $subtotalAfterItemDiscount = $subtotalBeforeDiscount - $totalItemDiscount;
                                
                                $discountTotalPercent = $po->discount_total_percent ?? 0;
                                $discountTotalAmount = $po->discount_total_amount ?? 0;
                                
                                if ($discountTotalPercent > 0) {
                                    $discountTotalAmount = $subtotalAfterItemDiscount * ($discountTotalPercent / 100);
                                }
                                
                                $subtotalAfterTotalDiscount = $subtotalAfterItemDiscount - $discountTotalAmount;
                                
                                // Calculate PPN if enabled
                                $ppnEnabled = $po->ppn_enabled ?? false;
                                if ($ppnEnabled) {
                                    $ppnAmount = $subtotalAfterTotalDiscount * 0.11; // 11% PPN
                                }
                                
                                $finalGrandTotal = $subtotalAfterTotalDiscount + $ppnAmount;
                                $showGrandTotal = ($totalItemDiscount > 0 || $discountTotalAmount > 0 || ($ppnEnabled && $ppnAmount > 0));
                            }
                        }
                    @endphp
                    @if($showGrandTotal)
                        <div style="margin-bottom: 10px;">
                            <div style="font-size: 14px; color: #666; margin-bottom: 5px;">
                                Subtotal: Rp {{ number_format($subtotalBeforeDiscount, 0, ',', '.') }}
                            </div>
                            @if($totalItemDiscount > 0)
                            <div style="font-size: 14px; color: #666; margin-bottom: 5px;">
                                Discount (Item): - Rp {{ number_format($totalItemDiscount, 0, ',', '.') }}
                            </div>
                            @endif
                            @if($discountTotalAmount > 0)
                            <div style="font-size: 14px; color: #666; margin-bottom: 5px;">
                                Discount Total
                                @if($discountTotalPercent > 0)
                                    ({{ number_format($discountTotalPercent, 2) }}%)
                                @endif
                                : - Rp {{ number_format($discountTotalAmount, 0, ',', '.') }}
                            </div>
                            @endif
                            @if($ppnEnabled && $ppnAmount > 0)
                            <div style="font-size: 14px; color: #666; margin-bottom: 5px;">
                                PPN (11%): + Rp {{ number_format($ppnAmount, 0, ',', '.') }}
                            </div>
                            @endif
                        </div>
                    @endif
                    <div class="total-amount">
                        @if($showGrandTotal)
                            Grand Total{{ $ppnEnabled && $ppnAmount > 0 ? ' (After Discount + PPN)' : ' After Discount' }}: Rp {{ number_format($finalGrandTotal, 0, ',', '.') }}<br>
                            <span style="font-size: 16px; font-weight: bold; color: #28a745;">Payment Amount: Rp {{ number_format($finalGrandTotal, 0, ',', '.') }}</span>
                        @else
                            Payment Amount: Rp {{ number_format($payment->amount, 0, ',', '.') }}
                        @endif
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
