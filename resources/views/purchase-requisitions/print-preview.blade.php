<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Requisition Print Preview</title>
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
        .pr-section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .pr-header {
            background-color: #f8f9fa;
            padding: 10px;
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
        }
        .pr-number {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        .pr-title {
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
        .budget-section {
            background-color: #e3f2fd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .budget-title {
            font-size: 16px;
            font-weight: bold;
            color: #1976d2;
            margin-bottom: 10px;
        }
        .budget-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        .budget-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 3px;
        }
        .budget-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        .budget-value {
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
        .outlet-info {
            background-color: #fff3cd;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 10px;
            font-size: 14px;
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
            padding-top: 20px;
            border-top: 2px solid #333;
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
            }
            .pr-section {
                margin-bottom: 10px;
            }
            .pr-header {
                padding: 8px;
                margin-bottom: 8px;
            }
            .budget-section {
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
            <div class="title">PURCHASE REQUISITION</div>
            <div class="subtitle">JUSTUS GROUP</div>
        </div>

        @foreach($purchaseRequisitions as $index => $pr)
            @if($index > 0)
                <div style="margin-top: 20px; border-top: 2px solid #ddd; padding-top: 15px;"></div>
            @endif

            <div class="pr-section">
                <div class="pr-header">
                    <div class="pr-number">{{ $pr->pr_number }}</div>
                    <div class="pr-title">{{ $pr->title }}</div>
                </div>

                <div class="info-grid">
                    <div>
                        <div class="info-item">
                            <div class="info-label">Date:</div>
                            <div class="info-value">{{ $pr->created_at->format('d M Y') }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Division:</div>
                            <div class="info-value">{{ $pr->division->nama_divisi ?? '-' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Outlet:</div>
                            <div class="info-value">{{ $pr->outlet->nama_outlet ?? '-' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Category:</div>
                            <div class="info-value">{{ $pr->category->name ?? '-' }}</div>
                        </div>
                    </div>
                    <div>
                        <div class="info-item">
                            <div class="info-label">Status:</div>
                            <div class="info-value">{{ $pr->status }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Created By:</div>
                            <div class="info-value">{{ $pr->creator->nama_lengkap ?? '-' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ticket:</div>
                            <div class="info-value">{{ $pr->ticket->ticket_number ?? '-' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Total Amount:</div>
                            <div class="info-value">Rp {{ number_format($pr->amount, 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>

                @if(isset($budgetInfos[$pr->id]) && $budgetInfos[$pr->id])
                    @php $budgetInfo = $budgetInfos[$pr->id]; @endphp
                    <div class="budget-section">
                        <div class="budget-title">
                            {{ $budgetInfo['budget_type'] === 'PER_OUTLET' ? 'Informasi Budget Outlet' : 'Informasi Budget Category' }} - 
                            {{ \Carbon\Carbon::create()->month($budgetInfo['current_month'])->format('F') }} {{ $budgetInfo['current_year'] }}
                            <span style="font-size: 12px; font-weight: normal; color: #666;">
                                ({{ $budgetInfo['budget_type'] === 'PER_OUTLET' ? 'Per Outlet' : 'Global' }})
                            </span>
                        </div>
                        
                        @if($budgetInfo['budget_type'] === 'PER_OUTLET' && isset($budgetInfo['outlet_info']))
                            <div class="outlet-info">
                                <strong>Outlet:</strong> {{ $budgetInfo['outlet_info']['name'] }}
                            </div>
                        @endif
                        
                        <div class="budget-grid">
                            <div class="budget-item">
                                <div class="budget-label">{{ $budgetInfo['budget_type'] === 'PER_OUTLET' ? 'Outlet Budget' : 'Total Budget' }}</div>
                                <div class="budget-value">Rp {{ number_format($budgetInfo['budget_type'] === 'PER_OUTLET' ? $budgetInfo['outlet_budget'] : $budgetInfo['category_budget'], 0, ',', '.') }}</div>
                                @if($budgetInfo['budget_type'] === 'PER_OUTLET')
                                    <div style="font-size: 10px; color: #999; margin-top: 2px;">
                                        Global: Rp {{ number_format($budgetInfo['category_budget'], 0, ',', '.') }}
                                    </div>
                                @endif
                            </div>
                            <div class="budget-item">
                                <div class="budget-label">Used This Month</div>
                                <div class="budget-value">Rp {{ number_format($budgetInfo['budget_type'] === 'PER_OUTLET' ? $budgetInfo['outlet_used_amount'] : $budgetInfo['category_used_amount'], 0, ',', '.') }}</div>
                            </div>
                            <div class="budget-item">
                                <div class="budget-label">Remaining Budget</div>
                                <div class="budget-value" style="color: {{ ($budgetInfo['budget_type'] === 'PER_OUTLET' ? $budgetInfo['outlet_remaining_amount'] : $budgetInfo['category_remaining_amount']) < 0 ? '#dc3545' : '#28a745' }}">
                                    Rp {{ number_format($budgetInfo['budget_type'] === 'PER_OUTLET' ? $budgetInfo['outlet_remaining_amount'] : $budgetInfo['category_remaining_amount'], 0, ',', '.') }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($pr->description)
                    <div class="info-item">
                        <div class="info-label">Description:</div>
                        <div class="info-value">{{ $pr->description }}</div>
                    </div>
                @endif

                @if($pr->items && count($pr->items) > 0)
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
                            @foreach($pr->items as $itemIndex => $item)
                                <tr>
                                    <td>{{ $itemIndex + 1 }}</td>
                                    <td>{{ $item->item_name }}</td>
                                    <td>{{ $item->qty }}</td>
                                    <td>{{ $item->unit }}</td>
                                    <td>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="total-section">
                    <div class="total-amount">
                        Total Amount: Rp {{ number_format($pr->amount, 0, ',', '.') }}
                    </div>
                </div>

                <!-- Approver Section -->
                @if($pr->approvalFlows && count($pr->approvalFlows) > 0)
                    <div class="approver-section">
                        <div class="approver-title">APPROVAL</div>
                        <div class="approver-grid">
                            @foreach($pr->approvalFlows->sortBy('approval_level') as $approvalFlow)
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
