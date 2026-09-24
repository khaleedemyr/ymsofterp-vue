<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Food Payment Export</title>
    <style>
        @page { margin: 12mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9pt;
            color: #1f2937;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header .logo {
            width: 260px;
            height: auto;
            margin-bottom: 6px;
        }
        .header h1 {
            margin: 0;
            color: #2563eb;
            font-size: 14pt;
        }
        .header .sub {
            margin-top: 3px;
            color: #6b7280;
            font-size: 9pt;
        }
        .date-group {
            margin: 12px 0 4px;
            padding: 5px 8px;
            background: #dbeafe;
            color: #1e3a8a;
            font-weight: bold;
            font-size: 10pt;
            border-left: 4px solid #2563eb;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        table.items th {
            background: #eff6ff;
            color: #1e3a8a;
            border: 1px solid #bfdbfe;
            padding: 5px 4px;
            font-size: 8pt;
            text-align: left;
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
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer {
            margin-top: 16px;
            padding-top: 6px;
            border-top: 1px solid #e5e7eb;
            font-size: 8pt;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logo_base64))
            <img src="data:image/png;base64,{{ $logo_base64 }}" alt="Justus Group" class="logo" width="260" style="width: 260px; height: auto;">
        @endif
        <h1>FOOD PAYMENT</h1>
        <div class="sub">Export {{ $total_count }} data &middot; {{ $generated_at }}</div>
    </div>

    @foreach($groups as $group)
        <div class="date-group">Tanggal: {{ $group['date_label'] }}</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 18%;">Supplier</th>
                    <th style="width: 12%;" class="text-right">Nominal</th>
                    <th style="width: 8%;">Admin</th>
                    <th style="width: 16%;">Deskripsi</th>
                    <th style="width: 8%;">Validasi</th>
                    <th style="width: 12%;">No Rekening</th>
                    <th style="width: 12%;">Bank</th>
                    <th style="width: 14%;">Atas Nama</th>
                </tr>
            </thead>
            <tbody>
                @foreach($group['items'] as $item)
                <tr>
                    <td>{{ $item['supplier_name'] }}</td>
                    <td class="text-right">Rp {{ number_format($item['nominal'], 0, ',', '.') }}</td>
                    <td></td>
                    <td>{{ $item['description'] ?: '-' }}</td>
                    <td></td>
                    <td>{{ $item['bank_account_number'] ?: '-' }}</td>
                    <td>{{ $item['bank_name'] ?: '-' }}</td>
                    <td>{{ $item['bank_account_name'] ?: '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="footer">
        Dicetak: {{ $generated_at }}
    </div>
</body>
</html>
