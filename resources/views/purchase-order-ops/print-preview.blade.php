<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Print Preview</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10px;
            background-color: #f5f5f5;
            font-size: 12px;
            min-height: 100vh;
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
        .po-section {
            margin-bottom: 15px;
        }
        .po-header {
            background-color: #f8f9fa;
            padding: 10px;
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
        }
        .po-number {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        .po-title {
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
        .approver-section {
            margin-top: 15px;
            border-top: 1px solid #333;
            padding-top: 10px;
        }
        .approver-title {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
            color: #333;
        }
        .approver-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }
        .approver-item {
            text-align: center;
            border: 1px solid #ddd;
            padding: 8px;
            border-radius: 3px;
            background-color: #f9f9f9;
        }
        .approver-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 3px;
            color: #333;
        }
        .approver-position {
            font-size: 10px;
            color: #666;
            margin-bottom: 8px;
        }
        .approver-signature {
            height: 120px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .approver-signature img {
            max-height: 120px;
            max-width: 300px;
            object-fit: contain;
        }
        .approver-date {
            font-size: 12px;
            color: #666;
        }
        .approver-status {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 3px;
            margin-top: 5px;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
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
                page-break-inside: avoid;
            }
            .header {
                page-break-after: avoid;
            }
            .po-section {
                margin-bottom: 10px;
            }
            .po-header {
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
            .approver-section {
                margin-top: 10px;
                padding-top: 8px;
            }
            .approver-grid {
                gap: 8px;
            }
            .approver-item {
                padding: 6px;
            }
            .approver-signature {
                height: 100px;
            }
            .approver-signature img {
                max-height: 100px;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="header">
            <img src="/images/logojustusgroup.png" alt="Justus Group" class="logo">
            <div class="title">PURCHASE ORDER</div>
            <div class="subtitle">JUSTUS GROUP</div>
        </div>

        @foreach($purchaseOrders as $index => $po)
            @if($index > 0)
                <div style="margin-top: 20px; border-top: 2px solid #ddd; padding-top: 15px;"></div>
            @endif

            <div class="po-section">
                <div class="po-header">
                    <div class="po-number">{{ $po->number }}</div>
                    <div class="po-title">{{ $po->supplier->name ?? 'N/A' }}</div>
                </div>

                <div class="info-grid">
                    <div>
                        <div class="info-item">
                            <div class="info-label">Date:</div>
                            <div class="info-value">{{ $po->created_at->format('d M Y') }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Supplier:</div>
                            <div class="info-value">{{ $po->supplier->name ?? 'N/A' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Creator:</div>
                            <div class="info-value">{{ $po->creator->nama_lengkap ?? 'N/A' }}</div>
                        </div>
                    </div>
                    <div>
                        <div class="info-item">
                            <div class="info-label">Status:</div>
                            <div class="info-value">{{ $po->status }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Total Amount:</div>
                            <div class="info-value">Rp {{ number_format($po->grand_total, 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>

                <!-- Purchase Requisition Information -->
                @if($po->purchase_requisition)
                    <div style="background-color: #f0f8ff; padding: 10px; margin: 10px 0; border-left: 4px solid #28a745; border-radius: 3px;">
                        <h4 style="margin: 0 0 8px 0; color: #28a745; font-size: 14px; font-weight: bold;">
                            <i class="fa fa-shopping-cart" style="margin-right: 5px;"></i>
                            Purchase Requisition Information
                        </h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 11px;">
                            <div>
                                <div style="margin-bottom: 5px;">
                                    <span style="font-weight: bold; color: #333;">PR Number:</span>
                                    <span style="color: #666; margin-left: 5px;">{{ $po->purchase_requisition->pr_number ?? 'N/A' }}</span>
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <span style="font-weight: bold; color: #333;">Title:</span>
                                    <span style="color: #666; margin-left: 5px;">{{ $po->purchase_requisition->title ?? 'N/A' }}</span>
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <span style="font-weight: bold; color: #333;">Division:</span>
                                    <span style="color: #666; margin-left: 5px;">{{ $po->purchase_requisition->division->nama_divisi ?? 'N/A' }}</span>
                                </div>
                            </div>
                            <div>
                                <div style="margin-bottom: 5px;">
                                    <span style="font-weight: bold; color: #333;">Category:</span>
                                    <span style="color: #666; margin-left: 5px;">
                                        @php
                                            // Get category from PR items (new structure) or fallback to PR level (old structure)
                                            $category = null;
                                            if ($po->purchase_requisition->items && $po->purchase_requisition->items->count() > 0) {
                                                // Get first item with category
                                                $itemWithCategory = $po->purchase_requisition->items->where('category_id', '!=', null)->first();
                                                if ($itemWithCategory && $itemWithCategory->category) {
                                                    $category = $itemWithCategory->category;
                                                }
                                            }
                                            // Fallback to PR level category (old structure)
                                            if (!$category && $po->purchase_requisition->category) {
                                                $category = $po->purchase_requisition->category;
                                            }
                                        @endphp
                                        @if($category)
                                            @php
                                                $division = $category->division ?? '';
                                                $name = $category->name ?? '';
                                                $display = trim($division . ($division && $name ? ' - ' : '') . $name);
                                            @endphp
                                            {{ $display ?: 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                    </span>
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <span style="font-weight: bold; color: #333;">Outlet:</span>
                                    <span style="color: #666; margin-left: 5px;">
                                        @php
                                            // Get outlet from PR items (new structure) or fallback to PR level (old structure)
                                            $outlet = null;
                                            if ($po->purchase_requisition->items && $po->purchase_requisition->items->count() > 0) {
                                                // Get first item with outlet
                                                $itemWithOutlet = $po->purchase_requisition->items->where('outlet_id', '!=', null)->first();
                                                if ($itemWithOutlet && $itemWithOutlet->outlet) {
                                                    $outlet = $itemWithOutlet->outlet;
                                                }
                                            }
                                            // Fallback to PR level outlet (old structure)
                                            if (!$outlet && $po->purchase_requisition->outlet) {
                                                $outlet = $po->purchase_requisition->outlet;
                                            }
                                        @endphp
                                        {{ $outlet->nama_outlet ?? 'N/A' }}
                                    </span>
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <span style="font-weight: bold; color: #333;">Amount:</span>
                                    <span style="color: #666; margin-left: 5px; font-weight: bold;">Rp {{ number_format($po->purchase_requisition->amount, 0, ',', '.') }}</span>
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <span style="font-weight: bold; color: #333;">Creator:</span>
                                    <span style="color: #666; margin-left: 5px;">{{ $po->purchase_requisition->creator->nama_lengkap ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                        @if($po->purchase_requisition->description)
                            <div style="margin-top: 8px; font-size: 11px;">
                                <span style="font-weight: bold; color: #333;">Description:</span>
                                <div style="color: #666; margin-top: 3px; padding: 5px; background-color: #f9f9f9; border-radius: 3px;">
                                    {{ $po->purchase_requisition->description }}
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if($po->items && count($po->items) > 0)
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Item Name</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($po->items as $itemIndex => $item)
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

                <div class="total-section">
                    <div class="total-amount">
                        Total Amount: Rp {{ number_format($po->grand_total, 0, ',', '.') }}
                    </div>
                </div>

                <!-- Approver Section -->
                @if($po->approvalFlows && count($po->approvalFlows) > 0)
                    <div class="approver-section">
                        <div class="approver-title">APPROVAL</div>
                        <div class="approver-grid">
                            @foreach($po->approvalFlows->sortBy('approval_level') as $approvalFlow)
                                <div class="approver-item">
                                    <div class="approver-name">{{ $approvalFlow->approver->nama_lengkap ?? 'N/A' }}</div>
                                    <div class="approver-position">{{ $approvalFlow->approver->jabatan->nama_jabatan ?? 'N/A' }}</div>
                                    
                                    @if($approvalFlow->status === 'APPROVED' && $approvalFlow->approver && $approvalFlow->approver->signature_path)
                                        <div class="approver-signature">
                                            <img src="{{ asset('storage/' . $approvalFlow->approver->signature_path) }}" alt="Signature">
                                        </div>
                                    @else
                                        <div class="approver-signature" style="border-bottom: 1px solid #ccc; height: 120px;"></div>
                                    @endif
                                    
                                    <div class="approver-date">
                                        @if($approvalFlow->status === 'APPROVED' && $approvalFlow->approved_at)
                                            {{ $approvalFlow->approved_at->format('d M Y') }}
                                        @else
                                            -
                                        @endif
                                    </div>
                                    
                                    <div class="approver-status {{ $approvalFlow->status === 'APPROVED' ? 'status-approved' : 'status-pending' }}">
                                        {{ $approvalFlow->status === 'APPROVED' ? 'APPROVED' : 'PENDING' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
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
