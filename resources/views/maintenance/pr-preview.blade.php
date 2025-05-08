<!DOCTYPE html>
<html>
<head>
    <title>Preview PR - {{ $pr->pr_number }}</title>
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
            Print PR
        </button>
    </div>

    <div class="header">
        <img src="{{ asset('images/logojustusgroup.png') }}" alt="Justus Group" class="logo">
        <h1 class="title">PURCHASE REQUISITION</h1>
    </div>

    <table class="info-table" style="border: none;">
        <tr>
            <td style="width: 150px;">PR Number</td>
            <td>: {{ $pr->pr_number }}</td>
            <td style="width: 150px;">Created Date</td>
            <td>: {{ date('d M Y', strtotime($pr->created_at)) }}</td>
        </tr>
        <tr>
            <td>Task Number</td>
            <td>: {{ $pr->task_number }}</td>
            <td>Status</td>
            <td>: {{ $pr->status }}</td>
        </tr>
        <tr>
            <td>Created By</td>
            <td colspan="3">: {{ $creator->nama_lengkap }}</td>
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
            @foreach($items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->item_name }}</td>
                <td>{{ $item->description ?? '-' }}</td>
                <td>{{ $item->specifications ?? '-' }}</td>
                <td style="text-align: right;">{{ number_format($item->quantity, 2) }}</td>
                <td>{{ $item->unit_name ?? 'Unit' }}</td>
                <td style="text-align: right;">{{ number_format($item->price) }}</td>
                <td style="text-align: right;">{{ number_format($item->subtotal) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="7" style="text-align: right;">Total:</td>
                <td style="text-align: right;">{{ number_format($pr->total_amount) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin: 30px 0;">
        <strong>Reason for Purchase:</strong>
        <p style="margin-top: 8px;">{{ $pr->description ?? '-' }}</p>
    </div>

    <div class="signatures">
        <div class="signature-row">
            <div class="signature-box">
                <div class="signature-header">Dibuat Oleh</div>
                @if($creator->signature_path)
                    <img src="{{ asset('storage/' . $creator->signature_path) }}" alt="Signature" class="signature-img">
                @endif
                <div class="signature-name">{{ $creator->nama_lengkap }}</div>
                <div class="signature-title">{{ $creator->nama_jabatan }}</div>
                @if($pr->created_at)
                    <div class="signature-note">{{ date('d M Y H:i', strtotime($pr->created_at)) }}</div>
                @endif
            </div>

            <div class="signature-box">
                <div class="signature-header">Disetujui Oleh</div>
                @if($signatures['chief_engineering']['official'])
                    @if($signatures['chief_engineering']['official']->signature_path)
                        <img src="{{ asset('storage/' . $signatures['chief_engineering']['official']->signature_path) }}" alt="Signature" class="signature-img">
                    @endif
                    <div class="signature-name">{{ $signatures['chief_engineering']['official']->nama_lengkap }}</div>
                    <div class="signature-title">{{ $signatures['chief_engineering']['official']->nama_jabatan }}</div>
                    @if($signatures['chief_engineering']['approver'] && $signatures['chief_engineering']['approver']->id != $signatures['chief_engineering']['official']->id)
                        <div class="signature-note">
                            {{ $signatures['chief_engineering']['approver']->nama_lengkap }}<br>
                            Bertindak atas nama {{ $signatures['chief_engineering']['official']->nama_jabatan }}
                        </div>
                    @endif
                    @if($pr->chief_engineering_approval_date)
                        <div class="signature-note">{{ date('d M Y H:i', strtotime($pr->chief_engineering_approval_date)) }}</div>
                        @if($pr->chief_engineering_approval_notes)
                            <div class="signature-note" style="margin-top: 4px;">{{ $pr->chief_engineering_approval_notes }}</div>
                        @endif
                    @endif
                @endif
            </div>
        </div>

        <div style="text-align: center;">
            <div class="signature-header">Mengetahui dan Menyetujui</div>
            @if($pr->total_amount < 5000000)
                <div class="signature-box" style="margin: 0 auto; max-width: 300px;">
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
                        @if($pr->coo_approval_date)
                            <div class="signature-note">{{ date('d M Y H:i', strtotime($pr->coo_approval_date)) }}</div>
                            @if($pr->coo_approval_notes)
                                <div class="signature-note" style="margin-top: 4px;">{{ $pr->coo_approval_notes }}</div>
                            @endif
                        @endif
                    @endif
                </div>
            @else
                <div class="signature-row" style="justify-content: center;">
                    <div class="signature-box">
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
                            @if($pr->coo_approval_date)
                                <div class="signature-note">{{ date('d M Y H:i', strtotime($pr->coo_approval_date)) }}</div>
                                @if($pr->coo_approval_notes)
                                    <div class="signature-note" style="margin-top: 4px;">{{ $pr->coo_approval_notes }}</div>
                                @endif
                            @endif
                        @endif
                    </div>

                    <div class="signature-box">
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
                            @if($pr->ceo_approval_date)
                                <div class="signature-note">{{ date('d M Y H:i', strtotime($pr->ceo_approval_date)) }}</div>
                                @if($pr->ceo_approval_notes)
                                    <div class="signature-note" style="margin-top: 4px;">{{ $pr->ceo_approval_notes }}</div>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html> 