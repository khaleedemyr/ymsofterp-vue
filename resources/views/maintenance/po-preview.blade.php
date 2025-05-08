<!DOCTYPE html>
<html>
<head>
    <title>Preview PO - {{ $po->po_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            max-width: 200px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        .info-table td {
            border: none;
            padding: 4px 8px;
        }
        .signatures {
            margin-top: 50px;
        }
        .signature-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        .signature-box {
            text-align: center;
            flex: 1;
            margin: 0 20px;
        }
        .signature-header {
            font-weight: bold;
            margin-bottom: 15px;
        }
        .signature-img {
            max-width: 150px;
            height: 60px;
            object-fit: contain;
            margin-bottom: 15px;
        }
        .signature-name {
            font-weight: bold;
            margin-bottom: 4px;
        }
        .signature-title {
            color: #666;
            font-size: 14px;
            margin-bottom: 4px;
        }
        .signature-note {
            font-size: 12px;
            color: #666;
            font-style: italic;
            margin-top: 4px;
        }
        .total-row td {
            font-weight: bold;
            text-align: right;
        }
        @media print {
            body {
                margin: 0;
                padding: 20px;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Print PO
        </button>
    </div>

    <div class="header">
        <img src="{{ asset('images/logojustusgroup.png') }}" alt="Justus Group" class="logo">
        <h1 class="title">PURCHASE ORDER</h1>
    </div>

    <table class="info-table" style="border: none;">
        <tr>
            <td style="width: 150px;">PO Number</td>
            <td>: {{ $po->po_number }}</td>
            <td style="width: 150px;">Created Date</td>
            <td>: {{ date('d M Y', strtotime($po->created_at)) }}</td>
        </tr>
        <tr>
            <td>Task Number</td>
            <td>: {{ $po->maintenanceTask->task_number ?? '-' }}</td>
            <td>Status</td>
            <td>: {{ $po->status }}</td>
        </tr>
        <tr>
            <td>Supplier</td>
            <td>: {{ $po->supplier->name ?? '-' }}</td>
            <td>Created By</td>
            <td>: {{ $po->createdBy->nama_lengkap ?? '-' }}</td>
        </tr>
        <tr>
            <td>Outlet</td>
            <td>: {{ $po->maintenanceTask->outlet->nama_outlet ?? '-' }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 50px;">No.</th>
                <th>Item Name</th>
                <th>Description</th>
                <th>Specifications</th>
                <th style="width: 100px;">Quantity</th>
                <th style="width: 100px;">Unit</th>
                <th style="width: 150px;">Price</th>
                <th style="width: 150px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->item_name }}</td>
                <td>{{ $item->description ?? '-' }}</td>
                <td>{{ $item->specifications ?? '-' }}</td>
                <td style="text-align: right;">{{ number_format($item->quantity, 2) }}</td>
                <td>{{ $item->unit->name ?? 'Unit' }}</td>
                <td style="text-align: right;">{{ number_format($item->supplier_price) }}</td>
                <td style="text-align: right;">{{ number_format($item->subtotal) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="7" style="text-align: right;">Total:</td>
                <td style="text-align: right;">{{ number_format($po->total_amount) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signatures">
        <div class="signature-row">
            <div class="signature-box">
                <div class="signature-header">Purchasing Manager</div>
                @if($signatures['purchasing_manager']['official'])
                    @if($signatures['purchasing_manager']['official']->signature_path)
                        <img src="{{ asset('storage/' . $signatures['purchasing_manager']['official']->signature_path) }}" alt="Signature" class="signature-img">
                    @endif
                    <div class="signature-name">{{ $signatures['purchasing_manager']['official']->nama_lengkap }}</div>
                    <div class="signature-title">{{ $signatures['purchasing_manager']['official']->nama_jabatan }}</div>
                    @if($signatures['purchasing_manager']['approver'] && $signatures['purchasing_manager']['approver']->id != $signatures['purchasing_manager']['official']->id)
                        <div class="signature-note">
                            {{ $signatures['purchasing_manager']['approver']->nama_lengkap }}<br>
                            Bertindak atas nama {{ $signatures['purchasing_manager']['official']->nama_jabatan }}
                        </div>
                    @endif
                    @if($po->purchasing_manager_approval_date)
                        <div class="signature-note">{{ date('d M Y H:i', strtotime($po->purchasing_manager_approval_date)) }}</div>
                        @if($po->purchasing_manager_approval_notes)
                            <div class="signature-note" style="margin-top: 4px;">{{ $po->purchasing_manager_approval_notes }}</div>
                        @endif
                    @endif
                @endif
            </div>
            <div class="signature-box">
                <div class="signature-header">GM Finance</div>
                @if($signatures['gm_finance']['official'])
                    @if($signatures['gm_finance']['official']->signature_path)
                        <img src="{{ asset('storage/' . $signatures['gm_finance']['official']->signature_path) }}" alt="Signature" class="signature-img">
                    @endif
                    <div class="signature-name">{{ $signatures['gm_finance']['official']->nama_lengkap }}</div>
                    <div class="signature-title">{{ $signatures['gm_finance']['official']->nama_jabatan }}</div>
                    @if($signatures['gm_finance']['approver'] && $signatures['gm_finance']['approver']->id != $signatures['gm_finance']['official']->id)
                        <div class="signature-note">
                            {{ $signatures['gm_finance']['approver']->nama_lengkap }}<br>
                            Bertindak atas nama {{ $signatures['gm_finance']['official']->nama_jabatan }}
                        </div>
                    @endif
                    @if($po->gm_finance_approval_date)
                        <div class="signature-note">{{ date('d M Y H:i', strtotime($po->gm_finance_approval_date)) }}</div>
                        @if($po->gm_finance_approval_notes)
                            <div class="signature-note" style="margin-top: 4px;">{{ $po->gm_finance_approval_notes }}</div>
                        @endif
                    @endif
                @endif
            </div>
        </div>
        <div class="signature-row">
            <div class="signature-box">
                <div class="signature-header">COO</div>
                @if($signatures['coo']['official'])
                    @if($signatures['coo']['official']->signature_path)
                        <img src="{{ asset('storage/' . $signatures['coo']['official']->signature_path) }}" alt="Signature" class="signature-img">
                    @endif
                    <div class="signature-name">{{ $signatures['coo']['official']->nama_lengkap }}</div>
                    <div class="signature-title">{{ $signatures['coo']['official']->nama_jabatan }}</div>
                    @if($signatures['coo']['approver'] && $signatures['coo']['approver']->id != $signatures['coo']['official']->id)
                        <div class="signature-note">
                            {{ $signatures['coo']['approver']->nama_lengkap }}<br>
                            Bertindak atas nama {{ $signatures['coo']['official']->nama_jabatan }}
                        </div>
                    @endif
                    @if($po->coo_approval_date)
                        <div class="signature-note">{{ date('d M Y H:i', strtotime($po->coo_approval_date)) }}</div>
                        @if($po->coo_approval_notes)
                            <div class="signature-note" style="margin-top: 4px;">{{ $po->coo_approval_notes }}</div>
                        @endif
                    @endif
                @endif
            </div>
            @if($po->total_amount >= 5000000)
            <div class="signature-box">
                <div class="signature-header">CEO</div>
                @if($signatures['ceo']['official'])
                    @if($signatures['ceo']['official']->signature_path)
                        <img src="{{ asset('storage/' . $signatures['ceo']['official']->signature_path) }}" alt="Signature" class="signature-img">
                    @endif
                    <div class="signature-name">{{ $signatures['ceo']['official']->nama_lengkap }}</div>
                    <div class="signature-title">{{ $signatures['ceo']['official']->nama_jabatan }}</div>
                    @if($signatures['ceo']['approver'] && $signatures['ceo']['approver']->id != $signatures['ceo']['official']->id)
                        <div class="signature-note">
                            {{ $signatures['ceo']['approver']->nama_lengkap }}<br>
                            Bertindak atas nama {{ $signatures['ceo']['official']->nama_jabatan }}
                        </div>
                    @endif
                    @if($po->ceo_approval_date)
                        <div class="signature-note">{{ date('d M Y H:i', strtotime($po->ceo_approval_date)) }}</div>
                        @if($po->ceo_approval_notes)
                            <div class="signature-note" style="margin-top: 4px;">{{ $po->ceo_approval_notes }}</div>
                        @endif
                    @endif
                @endif
            </div>
            @endif
        </div>
    </div>
</body>
</html> 