<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Asset Disposal - {{ $number }}</title>
    <style>
        @page { margin: 16mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #1f2937; }
        .header { text-align: center; border-bottom: 3px solid #0d9488; padding-bottom: 12px; margin-bottom: 18px; }
        .header h1 { margin: 0; color: #0d9488; font-size: 18pt; }
        .header .sub { margin-top: 4px; color: #6b7280; font-size: 10pt; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta td { padding: 4px 0; vertical-align: top; }
        .meta .label { width: 28%; color: #6b7280; font-weight: bold; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9pt; font-weight: bold; background: #f3f4f6; }
        .section-title { margin: 18px 0 8px; color: #0f766e; font-size: 11pt; font-weight: bold; border-bottom: 1px solid #ccfbf1; padding-bottom: 4px; }
        table.items, table.approvals { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.items th, table.approvals th { background: #f0fdfa; color: #115e59; border: 1px solid #99f6e4; padding: 7px 6px; font-size: 8.5pt; text-align: left; }
        table.items td, table.approvals td { border: 1px solid #e5e7eb; padding: 6px; font-size: 8.5pt; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .notes { margin-top: 8px; padding: 8px 10px; background: #f9fafb; border: 1px solid #e5e7eb; }
        .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 8pt; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ASSET DISPOSAL</h1>
        <div class="sub">{{ $number }} &middot; {{ $date }}</div>
    </div>

    <table class="meta">
        <tr><td class="label">Status</td><td><span class="badge">{{ $status }}</span></td></tr>
        <tr><td class="label">Tipe</td><td>{{ $type }}</td></tr>
        <tr><td class="label">Pemilik</td><td>{{ $owner_outlet_name }}</td></tr>
        <tr><td class="label">Lokasi</td><td>{{ $outlet_name }} / {{ $warehouse_outlet_name }}</td></tr>
        <tr><td class="label">Dibuat Oleh</td><td>{{ $creator_name }}</td></tr>
        @if(strtoupper($type) === 'SOLD')
        <tr><td class="label">Pembeli</td><td>{{ $buyer_name ?: '-' }}</td></tr>
        <tr><td class="label">Kontak</td><td>{{ $buyer_contact ?: '-' }}</td></tr>
        <tr><td class="label">Total Jual</td><td>Rp {{ number_format((float) $total_sale_price, 0, ',', '.') }}</td></tr>
        @endif
    </table>

    @if(!empty($description))
        <div class="notes"><strong>Deskripsi:</strong> {{ $description }}</div>
    @endif

    <div class="section-title">Item Disposal</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width:28px;" class="text-center">#</th>
                <th>Item</th>
                <th style="width:70px;">Unit</th>
                <th style="width:70px;" class="text-right">Qty</th>
                @if(strtoupper($type) === 'SOLD')
                <th style="width:100px;" class="text-right">Harga Jual</th>
                @endif
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $item)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $item['item_name'] }}</td>
                    <td>{{ $item['unit'] }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format((float) $item['qty'], 4, '.', ','), '0'), '.') }}</td>
                    @if(strtoupper($type) === 'SOLD')
                    <td class="text-right">{{ number_format((float) $item['sale_price'], 0, ',', '.') }}</td>
                    @endif
                    <td>{{ $item['note'] ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ strtoupper($type) === 'SOLD' ? 6 : 5 }}" class="text-center">Tidak ada item.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($approval_flows->count())
        <div class="section-title">Approval Flow</div>
        <table class="approvals">
            <thead><tr><th style="width:50px;">Level</th><th>Approver</th><th style="width:90px;">Status</th><th>Komentar</th></tr></thead>
            <tbody>
                @foreach($approval_flows as $flow)
                    <tr>
                        <td>{{ $flow['level'] }}</td>
                        <td>{{ $flow['approver_name'] }}</td>
                        <td>{{ $flow['status'] }}</td>
                        <td>{{ $flow['comments'] ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">Digenerate {{ $generated_at }} oleh {{ $generated_by }}</div>
</body>
</html>
