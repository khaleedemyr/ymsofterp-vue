<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Food Payment - {{ $number }}</title>
    <style>
        @page { margin: 14mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10pt;
            color: #1f2937;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header .logo {
            max-height: 48px;
            margin-bottom: 8px;
        }
        .header h1 {
            margin: 0;
            color: #2563eb;
            font-size: 16pt;
        }
        .header .sub {
            margin-top: 4px;
            color: #6b7280;
            font-size: 10pt;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .meta td {
            padding: 3px 0;
            vertical-align: top;
        }
        .meta .label {
            width: 32%;
            color: #6b7280;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9pt;
            font-weight: bold;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .section-title {
            margin: 16px 0 8px;
            color: #1e40af;
            font-size: 11pt;
            font-weight: bold;
            border-bottom: 1px solid #bfdbfe;
            padding-bottom: 4px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.items th {
            background: #eff6ff;
            color: #1e3a8a;
            border: 1px solid #bfdbfe;
            padding: 6px;
            font-size: 8.5pt;
            text-align: left;
        }
        table.items td {
            border: 1px solid #e5e7eb;
            padding: 5px 6px;
            font-size: 8.5pt;
        }
        table.items tr:nth-child(even) td {
            background: #f9fafb;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row td {
            font-weight: bold;
            background: #eff6ff !important;
        }
        .footer {
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 8pt;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logo_base64))
            <img src="data:image/png;base64,{{ $logo_base64 }}" alt="Justus Group" class="logo">
        @endif
        <h1>FOOD PAYMENT</h1>
        <div class="sub">{{ $number }} &middot; {{ $date }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Status</td>
            <td><span class="badge">{{ $status }}</span></td>
        </tr>
        <tr>
            <td class="label">Supplier</td>
            <td>{{ $supplier_name }}</td>
        </tr>
        <tr>
            <td class="label">Payment Type</td>
            <td>{{ $payment_type }}</td>
        </tr>
        <tr>
            <td class="label">Total</td>
            <td><strong>Rp {{ number_format($total, 0, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <td class="label">Dibuat Oleh</td>
            <td>{{ $creator_name }}@if($created_at) ({{ $created_at }})@endif</td>
        </tr>
        @if(!empty($notes))
        <tr>
            <td class="label">Notes</td>
            <td>{{ $notes }}</td>
        </tr>
        @endif
    </table>

    @if($finance_manager_approved_at || $gm_finance_approved_at)
    <div class="section-title">Informasi Approval</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 28%;">Level</th>
                <th style="width: 30%;">Oleh</th>
                <th style="width: 22%;">Tanggal</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @if($finance_manager_approved_at)
            <tr>
                <td>Finance Manager</td>
                <td>{{ $finance_manager_name ?: '-' }}</td>
                <td>{{ $finance_manager_approved_at }}</td>
                <td>{{ $finance_manager_note ?: '-' }}</td>
            </tr>
            @endif
            @if($gm_finance_approved_at)
            <tr>
                <td>GM Finance</td>
                <td>{{ $gm_finance_name ?: '-' }}</td>
                <td>{{ $gm_finance_approved_at }}</td>
                <td>{{ $gm_finance_note ?: '-' }}</td>
            </tr>
            @endif
        </tbody>
    </table>
    @endif

    <div class="section-title">Daftar Contra Bon yang Dibayar</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 20%;">No. CB</th>
                <th style="width: 18%;">Sumber</th>
                <th style="width: 20%;">No. Invoice</th>
                <th style="width: 16%;">Tgl Invoice</th>
                <th style="width: 21%;" class="text-right">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contra_bons as $i => $cb)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $cb['number'] }}</td>
                <td>{{ $cb['source_type_display'] }}</td>
                <td>{{ $cb['supplier_invoice_number'] ?: '-' }}</td>
                <td>{{ $cb['supplier_invoice_date'] ?: '-' }}</td>
                <td class="text-right">Rp {{ number_format($cb['total_amount'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center">Tidak ada Contra Bon</td>
            </tr>
            @endforelse
            @if(count($contra_bons) > 0)
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL</td>
                <td class="text-right">Rp {{ number_format($total, 0, ',', '.') }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Dicetak: {{ $generated_at }} &middot; {{ $number }}
    </div>
</body>
</html>
