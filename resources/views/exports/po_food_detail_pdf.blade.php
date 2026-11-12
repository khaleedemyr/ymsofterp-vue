<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $po['number'] }}</title>
    <style>
        @page { margin: 12mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9.5pt;
            color: #1f2937;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header .logo {
            width: 180px;
            height: auto;
            margin-bottom: 6px;
        }
        .header h1 {
            margin: 0;
            color: #1d4ed8;
            font-size: 15pt;
        }
        .header .sub {
            margin-top: 3px;
            color: #6b7280;
            font-size: 9pt;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .meta td {
            padding: 2px 0;
            vertical-align: top;
        }
        .meta .label {
            width: 28%;
            color: #6b7280;
            font-weight: bold;
        }
        .section {
            margin-top: 12px;
            margin-bottom: 6px;
            padding: 5px 8px;
            background: #eff6ff;
            border-left: 3px solid #2563eb;
            color: #1e40af;
            font-weight: bold;
            font-size: 10pt;
        }
        .section.amber {
            background: #fffbeb;
            border-left-color: #d97706;
            color: #92400e;
        }
        .section.green {
            background: #ecfdf5;
            border-left-color: #059669;
            color: #065f46;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 8px;
        }
        table.items th {
            background: #2563eb;
            color: #fff;
            font-size: 8pt;
            padding: 5px 4px;
            text-align: left;
            border: 1px solid #1d4ed8;
        }
        table.items td {
            border: 1px solid #e5e7eb;
            padding: 4px;
            font-size: 8pt;
            vertical-align: top;
        }
        table.items tr:nth-child(even) td {
            background: #f9fafb;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .totals td {
            font-weight: bold;
            background: #f3f4f6 !important;
        }
        .badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 8px;
            font-size: 8pt;
            font-weight: bold;
            background: #dbeafe;
            color: #1d4ed8;
        }
        .footer {
            margin-top: 14px;
            color: #9ca3af;
            font-size: 8pt;
            text-align: right;
        }
        .sign {
            width: 100%;
            margin-top: 28px;
            border-collapse: collapse;
        }
        .sign td {
            vertical-align: top;
            width: 50%;
        }
        .notes {
            margin-top: 6px;
            padding: 6px 8px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            font-size: 8.5pt;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logo_base64))
            <img class="logo" src="data:image/png;base64,{{ $logo_base64 }}" alt="Justus Group">
        @endif
        <h1>PURCHASE ORDER</h1>
        <div class="sub">{{ $po['number'] }} · Generated at {{ $generated_at }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">No. PO</td>
            <td>{{ $po['number'] }}</td>
            <td class="label">Status</td>
            <td><span class="badge">{{ strtoupper($po['status']) }}</span></td>
        </tr>
        <tr>
            <td class="label">Tanggal</td>
            <td>{{ $po['date'] }}</td>
            <td class="label">Supplier</td>
            <td>{{ $po['supplier_name'] }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Kedatangan</td>
            <td>{{ $po['arrival_date'] }}</td>
            <td class="label">Dibuat oleh</td>
            <td>{{ $po['creator_name'] }}</td>
        </tr>
    </table>

    @if(!empty($po['notes']))
        <div class="notes"><strong>Catatan:</strong> {{ $po['notes'] }}</div>
    @endif

    @if(!empty($ro_supplier))
        <div class="section green">Informasi RO Supplier</div>
        <table class="meta">
            <tr>
                <td class="label">Nomor RO</td>
                <td>{{ $ro_supplier['ro_number'] }}</td>
                <td class="label">Tanggal RO</td>
                <td>{{ $ro_supplier['ro_date'] }}</td>
            </tr>
            <tr>
                <td class="label">Outlet</td>
                <td>{{ $ro_supplier['outlet_name'] }}</td>
                <td class="label">Warehouse Outlet</td>
                <td>{{ $ro_supplier['warehouse_outlet_name'] }}</td>
            </tr>
            <tr>
                <td class="label">Pembuat RO</td>
                <td colspan="3">{{ $ro_supplier['ro_creator'] }}</td>
            </tr>
        </table>
    @endif

    @if(!empty($pr_foods))
        <div class="section">Informasi Purchase Request</div>
        @foreach($pr_foods as $pr)
            <table class="meta">
                <tr>
                    <td class="label">Nomor PR</td>
                    <td>{{ $pr['pr_number'] }}</td>
                    <td class="label">Tanggal PR</td>
                    <td>{{ $pr['pr_date'] }}</td>
                </tr>
                <tr>
                    <td class="label">Status PR</td>
                    <td>{{ $pr['pr_status'] }}</td>
                    <td class="label">Warehouse</td>
                    <td>{{ $pr['warehouse_name'] }}</td>
                </tr>
                <tr>
                    <td class="label">Pembuat PR</td>
                    <td colspan="3">{{ $pr['pr_creator'] }}</td>
                </tr>
            </table>
        @endforeach
    @endif

    @if(!empty($gr_foods))
        <div class="section amber">Informasi Good Receive</div>
        @foreach($gr_foods as $gr)
            <table class="meta">
                <tr>
                    <td class="label">Nomor GR</td>
                    <td>{{ $gr['gr_number'] }}</td>
                    <td class="label">Tanggal Receive</td>
                    <td>{{ $gr['receive_date'] }}</td>
                </tr>
                <tr>
                    <td class="label">Diterima oleh</td>
                    <td colspan="3">{{ $gr['received_by'] }}</td>
                </tr>
            </table>
        @endforeach
    @endif

    <div class="section">Daftar Item</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;" class="center">No</th>
                <th style="width: 38%;">Item</th>
                <th style="width: 12%;" class="right">Qty</th>
                <th style="width: 12%;">Unit</th>
                <th style="width: 16%;" class="right">Harga</th>
                <th style="width: 17%;" class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $item['name'] }}</td>
                    <td class="right">{{ $item['qty'] }}</td>
                    <td>{{ $item['unit'] }}</td>
                    <td class="right">{{ $item['price'] }}</td>
                    <td class="right">{{ $item['total'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center">Tidak ada item.</td>
                </tr>
            @endforelse
            <tr class="totals">
                <td colspan="5" class="right">Subtotal</td>
                <td class="right">{{ $totals['subtotal'] }}</td>
            </tr>
            @if(!empty($totals['ppn_enabled']))
                <tr class="totals">
                    <td colspan="5" class="right">PPN (11%)</td>
                    <td class="right">{{ $totals['ppn_amount'] }}</td>
                </tr>
            @endif
            <tr class="totals">
                <td colspan="5" class="right">Grand Total</td>
                <td class="right">{{ $totals['grand_total'] }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($approvals))
        <div class="section">Riwayat Approval</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 12%;">Level</th>
                    <th style="width: 35%;">Approver</th>
                    <th style="width: 18%;">Status</th>
                    <th style="width: 20%;">Waktu</th>
                    <th style="width: 15%;">Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($approvals as $flow)
                    <tr>
                        <td class="center">{{ $flow['level'] }}</td>
                        <td>{{ $flow['approver'] }}</td>
                        <td>{{ $flow['status'] }}</td>
                        <td>{{ $flow['time'] }}</td>
                        <td>{{ $flow['note'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="sign">
        <tr>
            <td>
                <strong>Dibuat oleh,</strong>
                <div style="margin-top: 40px;">
                    <strong>{{ $po['creator_name'] }}</strong>
                </div>
            </td>
            <td></td>
        </tr>
    </table>

    <div class="footer">PO Foods Detail · {{ $po['number'] }} · {{ $generated_at }}</div>
</body>
</html>
