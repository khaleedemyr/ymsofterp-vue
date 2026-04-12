<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Order {{ $order['nomor'] ?? '' }}</title>
    <style>
        @page { margin: 14mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1e293b;
        }
        h1 {
            font-size: 14pt;
            margin: 0 0 4px 0;
            color: #0f172a;
        }
        .sub {
            font-size: 8.5pt;
            color: #64748b;
            margin-bottom: 14px;
        }
        .section {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
            margin-bottom: 6px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
        }
        table.info {
            width: 100%;
            border-collapse: collapse;
        }
        table.info td {
            padding: 3px 6px 3px 0;
            vertical-align: top;
        }
        table.info td.label {
            width: 38%;
            color: #64748b;
        }
        table.info td.val {
            font-weight: 600;
            text-align: right;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.grid th, table.grid td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
        }
        table.grid th {
            background: #e2e8f0;
            font-size: 8pt;
        }
        table.grid td.num { text-align: right; }
        .mod {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 2px;
            padding-left: 2px;
        }
        .notes {
            font-size: 7.5pt;
            color: #b45309;
            margin-top: 2px;
        }
        .footer {
            margin-top: 18px;
            font-size: 7.5pt;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Detail Order</h1>
    <div class="sub">
        {{ $order['nomor'] ?? '-' }}
        @if(!empty($order['paid_number']))
            &middot; {{ $order['paid_number'] }}
        @endif
    </div>

    <div class="section">
        <div class="section-title">Info transaksi</div>
        <table class="info">
            @foreach([
                'nomor' => 'No. Order',
                'paid_number' => 'Paid Number',
                'nama_outlet' => 'Nama Outlet',
                'kode_outlet' => 'Kode Outlet',
                'table' => 'Meja',
                'cashier' => 'Kasir',
                'waiters' => 'Waiter',
                'mode' => 'Mode',
                'pax' => 'Pax',
                'created_at' => 'Waktu',
                'total' => 'Subtotal',
                'discount' => 'Diskon',
                'cashback' => 'Cashback',
                'dpp' => 'DPP',
                'pb1' => 'PB1',
                'service' => 'Service',
                'grand_total' => 'Grand Total',
                'status' => 'Status',
                'member_name' => 'Member',
            ] as $key => $label)
                @if(isset($order[$key]) && $order[$key] !== null && $order[$key] !== '')
                    <tr>
                        <td class="label">{{ $label }}</td>
                        <td class="val">
                            @if(in_array($key, ['total','discount','cashback','dpp','pb1','service','grand_total'], true))
                                Rp {{ number_format((float) $order[$key], 0, ',', '.') }}
                            @elseif($key === 'created_at')
                                {{ \Carbon\Carbon::parse($order[$key])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                            @else
                                {{ $order[$key] }}
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </table>
    </div>

    @if(!empty($order['payments']))
        <div class="section">
            <div class="section-title">Pembayaran</div>
            <table class="grid">
                <thead>
                    <tr>
                        <th>Metode</th>
                        <th class="num">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order['payments'] as $pay)
                        @php
                            $amt = (float) ($pay['amount'] ?? 0) - (float) ($pay['change'] ?? 0);
                        @endphp
                        <tr>
                            <td>{{ $pay['payment_code'] ?? $pay['payment_type'] ?? 'Payment' }}</td>
                            <td class="num">Rp {{ number_format($amt, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @php
        $manualDisc = (float) ($order['manual_discount_amount'] ?? 0);
    @endphp
    @if($manualDisc > 0)
        <div class="section">
            <div class="section-title">Diskon manual</div>
            <table class="info">
                <tr>
                    <td class="label">Nominal</td>
                    <td class="val">Rp {{ number_format($manualDisc, 0, ',', '.') }}</td>
                </tr>
                @if(!empty($order['manual_discount_reason']))
                    <tr>
                        <td class="label">Alasan</td>
                        <td class="val" style="text-align:left;font-weight:normal;">{{ $order['manual_discount_reason'] }}</td>
                    </tr>
                @endif
            </table>
        </div>
    @endif

    @php
        $promoDisc = (float) ($order['discount'] ?? 0);
    @endphp
    @if($promoDisc > 0 || !empty($order['promo_names']) || !empty($order['promo_discount_lines']))
        <div class="section">
            <div class="section-title">Diskon promo</div>
            @if($promoDisc > 0)
                <p style="margin:0 0 6px 0;font-weight:bold;">Rp {{ number_format($promoDisc, 0, ',', '.') }}</p>
            @endif
            @if(!empty($order['promo_names']))
                <div style="font-size:8pt;margin-bottom:4px;">Promo: {{ implode(', ', $order['promo_names']) }}</div>
            @endif
            @if(!empty($order['promo_discount_lines']))
                <table class="grid">
                    <thead>
                        <tr><th>Promo</th><th class="num">Nominal</th></tr>
                    </thead>
                    <tbody>
                        @foreach($order['promo_discount_lines'] as $pl)
                            <tr>
                                <td>{{ $pl['promo_name'] }}</td>
                                <td class="num">Rp {{ number_format((float) ($pl['discount_amount'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    @if(!empty($order['items']))
        <div class="section">
            <div class="section-title">Item pesanan</div>
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width:28px;">No</th>
                        <th>Nama item</th>
                        <th class="num" style="width:40px;">Qty</th>
                        <th class="num" style="width:72px;">Harga</th>
                        <th class="num" style="width:72px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order['items'] as $idx => $item)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td>
                                <strong>{{ $item['item_name'] ?? '-' }}</strong>
                                @if(!empty($item['modifiers_formatted']))
                                    @foreach($item['modifiers_formatted'] as $line)
                                        <div class="mod">&bull; {{ $line }}</div>
                                    @endforeach
                                @endif
                                @php
                                    $n = isset($item['notes']) ? trim((string) $item['notes']) : '';
                                @endphp
                                @if($n !== '' && strtolower($n) !== 'null')
                                    <div class="notes">Notes: {{ $n }}</div>
                                @endif
                            </td>
                            <td class="num">{{ $item['qty'] ?? 0 }}</td>
                            <td class="num">Rp {{ number_format((float) ($item['price'] ?? 0), 0, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format((float) ($item['subtotal'] ?? 0), 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">Dicetak: {{ $generatedAt }}</div>
</body>
</html>
