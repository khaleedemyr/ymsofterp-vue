<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Food Payment Export</title>
    <style>
        @page { margin: 8mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 7pt;
            color: #111827;
            margin: 0;
            padding: 0;
        }
        .date-group {
            margin: 8px 0 2px;
            padding: 3px 6px;
            background: #e5e7eb;
            color: #111827;
            font-weight: bold;
            font-size: 7.5pt;
            border-left: 3px solid #374151;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            table-layout: fixed;
        }
        table.items th {
            background: #f3f4f6;
            color: #111827;
            border: 1.5px solid #111827;
            padding: 3px 4px;
            font-size: 6.5pt;
            font-weight: bold;
            text-align: left;
        }
        table.items td {
            border: 1.5px solid #111827;
            padding: 2px 4px;
            font-size: 6.5pt;
            vertical-align: top;
            word-wrap: break-word;
            line-height: 1.25;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    @foreach($groups as $group)
        <div class="date-group">Tanggal: {{ $group['date_label'] }}</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 18%;">Supplier</th>
                    <th style="width: 16%;">Nomor FP</th>
                    <th style="width: 12%;" class="text-right">Nominal</th>
                    <th style="width: 8%;">Admin</th>
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
                    <td>{{ $item['fp_number'] ?: '-' }}</td>
                    <td class="text-right">Rp {{ number_format($item['nominal'], 0, ',', '.') }}</td>
                    <td></td>
                    <td></td>
                    <td>{{ $item['bank_account_number'] ?: '-' }}</td>
                    <td>{{ $item['bank_name'] ?: '-' }}</td>
                    <td>{{ $item['bank_account_name'] ?: '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
    @php $grandTotal = collect($groups)->sum(fn ($g) => collect($g['items'])->sum('nominal')); @endphp
    <table class="items" style="margin-top: 8px;">
        <tr>
            <th style="width: 34%;">GRAND TOTAL ({{ collect($groups)->sum(fn ($g) => count($g['items'])) }} transaksi)</th>
            <th style="width: 12%;" class="text-right">Rp {{ number_format($grandTotal, 0, ',', '.') }}</th>
            <th style="width: 54%;"></th>
        </tr>
    </table>
</body>
</html>
