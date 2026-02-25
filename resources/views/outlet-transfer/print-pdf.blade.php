<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Outlet Transfer {{ $transfer->transfer_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #222; }
        .header { border-bottom: 1px solid #444; margin-bottom: 10px; padding-bottom: 6px; }
        .title { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta td { padding: 3px 4px; vertical-align: top; }
        .meta .label { width: 140px; font-weight: bold; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px; }
        th { background: #f3f4f6; text-align: left; font-size: 9px; }
        td.num, th.num { text-align: right; }
        .summary { margin-top: 10px; width: 40%; margin-left: auto; border-collapse: collapse; }
        .summary td { border: 1px solid #ccc; padding: 6px; }
        .summary .label { font-weight: bold; background: #f9fafb; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Outlet Transfer - Detail Transaksi</div>
        <div>Generated: {{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">No. Transfer</td>
            <td>{{ $transfer->transfer_number }}</td>
            <td class="label">Tanggal Transfer</td>
            <td>{{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Outlet Asal</td>
            <td>{{ $transfer->warehouseOutletFrom->outlet->nama_outlet ?? '-' }}</td>
            <td class="label">Warehouse Asal</td>
            <td>{{ $transfer->warehouseOutletFrom->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Outlet Tujuan</td>
            <td>{{ $transfer->warehouseOutletTo->outlet->nama_outlet ?? '-' }}</td>
            <td class="label">Warehouse Tujuan</td>
            <td>{{ $transfer->warehouseOutletTo->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td>{{ strtoupper($transfer->status ?? '-') }}</td>
            <td class="label">Dibuat Oleh</td>
            <td>{{ $transfer->creator->nama_lengkap ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Catatan</td>
            <td colspan="3">{{ $transfer->notes ?: '-' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th>Item</th>
                <th class="num">Qty Input</th>
                <th class="num">Qty Small</th>
                <th class="num">Qty Medium</th>
                <th class="num">Qty Large</th>
                <th class="num">MAC Out (Small)</th>
                <th class="num">MAC In (After Transfer)</th>
                <th class="num">Value Out</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item['item_name'] }}</td>
                    <td class="num">{{ number_format($item['quantity'], 2) }}</td>
                    <td class="num">{{ number_format($item['qty_small'], 2) }}</td>
                    <td class="num">{{ number_format($item['qty_medium'], 2) }}</td>
                    <td class="num">{{ number_format($item['qty_large'], 2) }}</td>
                    <td class="num">{{ $item['mac_out'] !== null ? number_format($item['mac_out'], 2) : '-' }}</td>
                    <td class="num">{{ $item['mac_in'] !== null ? number_format($item['mac_in'], 2) : '-' }}</td>
                    <td class="num">{{ number_format($item['value_out'], 2) }}</td>
                    <td>{{ $item['note'] ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align: center;">Tidak ada item.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td class="label">Total Value Out</td>
            <td class="num">{{ number_format($totalValueOut, 2) }}</td>
        </tr>
    </table>
</body>
</html>
