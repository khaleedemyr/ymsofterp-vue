<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Restaurant Event Order - {{ $reo->number }}</title>
    <style>
        @page { margin: 12mm 12mm 12mm 12mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #111; }
        h1 { margin: 0 0 2px; font-size: 16pt; text-align: center; letter-spacing: 0.5px; }
        .subtitle { text-align: center; font-size: 9pt; color: #444; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { vertical-align: top; padding: 2px 4px; }
        .meta .label { width: 110px; font-weight: bold; white-space: nowrap; }
        .meta .colon { width: 10px; }
        .section-title {
            margin-top: 10px; margin-bottom: 4px; font-weight: bold; font-size: 10pt;
            border-bottom: 1px solid #222; padding-bottom: 2px;
        }
        .rev th, .rev td { border: 1px solid #333; padding: 3px 4px; font-size: 8pt; }
        .rev th { background: #f3f3f3; text-align: center; }
        .rev .num { text-align: right; white-space: nowrap; }
        .rev .ctr { text-align: center; }
        .totals td { padding: 2px 4px; font-size: 9pt; }
        .totals .label { font-weight: bold; }
        .totals .num { text-align: right; white-space: nowrap; }
        .box { border: 1px solid #333; padding: 6px; min-height: 40px; white-space: pre-wrap; }
        .sig-table td { width: 25%; text-align: center; vertical-align: top; padding: 6px 4px; }
        .sig-title { font-weight: bold; margin-bottom: 4px; }
        .sig-img { height: 45px; max-width: 120px; margin: 4px auto; display: block; }
        .sig-line { border-top: 1px solid #333; margin-top: 48px; padding-top: 3px; }
        .sig-name { font-weight: bold; font-size: 8.5pt; }
        .sig-role { font-size: 7.5pt; color: #444; }
        .muted { color: #666; font-size: 8pt; }
        .two-col td { width: 50%; vertical-align: top; padding-right: 6px; }
    </style>
</head>
<body>
    <h1>RESTAURANT EVENT ORDER</h1>
    <div class="subtitle">{{ $reo->venue_setup ?: ($lead->outlet->nama_outlet ?? '') }}</div>

    <table class="meta">
        <tr>
            <td style="width:55%">
                <table class="meta">
                    <tr><td class="label">Company</td><td class="colon">:</td><td>{{ $reo->company_name }}</td></tr>
                    <tr><td class="label">Type of Event</td><td class="colon">:</td><td>{{ $lead->type_of_event ?? '-' }}</td></tr>
                    <tr><td class="label">Address</td><td class="colon">:</td><td>{{ $reo->address ?: '-' }}</td></tr>
                    <tr><td class="label">Contact Person</td><td class="colon">:</td><td>{{ $reo->contact_person ?: '-' }}</td></tr>
                    <tr><td class="label">Mobile</td><td class="colon">:</td><td>{{ $reo->mobile ?: '-' }}</td></tr>
                    <tr><td class="label">Email</td><td class="colon">:</td><td>{{ $reo->email ?: '-' }}</td></tr>
                </table>
            </td>
            <td style="width:45%">
                <table class="meta">
                    <tr><td class="label">REO No</td><td class="colon">:</td><td>{{ $reo->number }}</td></tr>
                    <tr><td class="label">Date of Issue</td><td class="colon">:</td><td>{{ $reo->date_of_issue ? \Carbon\Carbon::parse($reo->date_of_issue)->format('l, F j, Y') : '-' }}</td></tr>
                    <tr><td class="label">Venue / Set up</td><td class="colon">:</td><td>{{ $reo->venue_setup ?: '-' }}</td></tr>
                    <tr><td class="label">Sales Person</td><td class="colon">:</td><td>{{ $reo->sales_person_name ?: '-' }}</td></tr>
                    <tr><td class="label">Sign Board</td><td class="colon">:</td><td style="white-space:pre-wrap">{{ $reo->sign_board ?: '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title">REVENUE BREAKDOWN</div>
    <table class="rev">
        <thead>
            <tr>
                <th rowspan="2" style="width:12%">DATE</th>
                <th rowspan="2" style="width:20%">ARRANGEMENT</th>
                <th rowspan="2" style="width:7%">QTY</th>
                <th rowspan="2" style="width:7%">DAY(S)</th>
                <th colspan="2">RATE</th>
                <th colspan="2">TOTAL AMOUNT</th>
            </tr>
            <tr>
                <th>++</th>
                <th>Nett</th>
                <th>++</th>
                <th>Nett</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($reo->revenue_items ?? []) as $item)
                @php
                    $date = $item['date'] ?? null;
                    $arrangement = $item['arrangement'] ?? null;
                    if (!$date && !$arrangement && !empty($item['date_arrangement'])) {
                        $arrangement = $item['date_arrangement'];
                    }
                    $totalAmount = $item['total_amount'] ?? ($item['breakdown'] ?? 0);
                    $totalAmountNett = $item['total_amount_nett'] ?? ($item['breakdown_nett'] ?? 0);
                @endphp
                <tr>
                    <td class="ctr">{{ $date ?: '' }}</td>
                    <td>{{ $arrangement ?: '' }}</td>
                    <td class="ctr">{{ $item['qty'] ?? '' }}</td>
                    <td class="ctr">{{ $item['days'] ?? '' }}</td>
                    <td class="num">{{ number_format((float)($item['rate'] ?? 0), 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float)($item['rate_nett'] ?? 0), 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float)$totalAmount, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float)$totalAmountNett, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="ctr muted">No revenue items</td></tr>
            @endforelse
            <tr>
                <td colspan="6" class="num label" style="font-weight:bold">Subtotal</td>
                <td class="num" style="font-weight:bold">{{ number_format((float)$reo->subtotal_rate, 0, ',', '.') }}</td>
                <td class="num" style="font-weight:bold">{{ number_format((float)$reo->subtotal_nett, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals" style="margin-top:6px; width:55%; margin-left:auto;">
        <tr>
            <td class="label">CATERING FEE {{ rtrim(rtrim(number_format((float)$reo->catering_fee_percent, 2, '.', ''), '0'), '.') }}%</td>
            <td class="num">{{ number_format((float)$reo->catering_fee_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">TOTAL RESTAURANT REVENUE</td>
            <td class="num">{{ number_format((float)$reo->total_restaurant_revenue, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">TOTAL TRANSACTION</td>
            <td class="num">{{ number_format((float)$reo->total_transaction, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">TOTAL AMOUNT</td>
            <td class="num" style="font-size:11pt; font-weight:bold">{{ number_format((float)$reo->total_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">PAX</td>
            <td class="num">{{ $reo->pax ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">DATE OF EVENT : {{ $reo->date_of_event ? \Carbon\Carbon::parse($reo->date_of_event)->format('j F Y') : '-' }}</div>

    <div class="section-title">TIME OF EVENT</div>
    <table class="rev">
        <thead>
            <tr>
                <th style="width:12%">TIME</th>
                <th style="width:28%">MEALS</th>
                <th style="width:22%">VENUE</th>
                <th style="width:10%">PAX</th>
                <th style="width:28%">SET UP</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($reo->time_of_event ?? []) as $row)
                <tr>
                    <td>{{ $row['time'] ?? '' }}</td>
                    <td>{{ $row['meals'] ?? '' }}</td>
                    <td class="ctr">{{ $row['venue'] ?? ($row['venue_setup'] ?? '') }}</td>
                    <td class="ctr">{{ $row['pax'] ?? '' }}</td>
                    <td class="ctr">{{ $row['set_up'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="ctr muted">-</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="two-col" style="margin-top:8px; width:100%">
        <tr>
            <td style="width:50%; padding-right:4px; vertical-align:top">
                <div class="section-title">KITCHEN</div>
                <div class="box">{{ $reo->kitchen_maintenance ?: '-' }}</div>
            </td>
            <td style="width:50%; padding-left:4px; vertical-align:top">
                <div class="section-title">MAINTENANCE</div>
                <div class="box">{{ $reo->maintenance ?: '-' }}</div>
            </td>
        </tr>
        <tr>
            <td style="width:50%; padding-right:4px; padding-top:6px; vertical-align:top">
                <div class="section-title">BILLING INSTRUCTION / ACCOUNTING</div>
                <div class="box">{{ $reo->billing_instruction ?: '-' }}</div>
            </td>
            <td style="width:50%; padding-left:4px; padding-top:6px; vertical-align:top">
                <div class="section-title">MARCOM</div>
                <div class="box">{{ $reo->marcom ?: '-' }}</div>
            </td>
        </tr>
    </table>

    <table class="sig-table" style="margin-top:14px; width:100%">
        <tr>
            <td>
                <div class="sig-title">Prepared by,</div>
                @if($showSignatures && isset($signatures[0]) && $signatures[0]['signature_data_uri'])
                    <img class="sig-img" src="{{ $signatures[0]['signature_data_uri'] }}" alt="sig">
                @else
                    <div class="sig-line"></div>
                @endif
                <div class="sig-name">{{ $reo->prepared_by_name ?: '-' }}</div>
                <div class="sig-role">{{ $reo->prepared_by_title ?: '' }}</div>
            </td>
            <td>
                <div class="sig-title">Acknowledge by,</div>
                @if($showSignatures && isset($signatures[1]) && $signatures[1]['signature_data_uri'])
                    <img class="sig-img" src="{{ $signatures[1]['signature_data_uri'] }}" alt="sig">
                @else
                    <div class="sig-line"></div>
                @endif
                <div class="sig-name">{{ $reo->acknowledge_by_1_name ?: '-' }}</div>
                <div class="sig-role">{{ $reo->acknowledge_by_1_title ?: '' }}</div>
            </td>
            <td>
                <div class="sig-title">&nbsp;</div>
                @if($showSignatures && isset($signatures[2]) && $signatures[2]['signature_data_uri'])
                    <img class="sig-img" src="{{ $signatures[2]['signature_data_uri'] }}" alt="sig">
                @else
                    <div class="sig-line"></div>
                @endif
                <div class="sig-name">{{ $reo->acknowledge_by_2_name ?: '-' }}</div>
                <div class="sig-role">{{ $reo->acknowledge_by_2_title ?: '' }}</div>
            </td>
            <td>
                <div class="sig-title">Approved by,</div>
                @php
                    $finalSig = $showSignatures ? $signatures->last() : null;
                @endphp
                @if($finalSig && $finalSig['signature_data_uri'])
                    <img class="sig-img" src="{{ $finalSig['signature_data_uri'] }}" alt="sig">
                @else
                    <div class="sig-line"></div>
                @endif
                <div class="sig-name">{{ $reo->approved_by_name ?: '-' }}</div>
                <div class="sig-role">{{ $reo->approved_by_title ?: '' }}</div>
            </td>
        </tr>
    </table>

    <p class="muted" style="margin-top:10px">
        Status: {{ $reo->status }}
        @if($reo->approved_at)
            · Approved at {{ $reo->approved_at->format('d/m/Y H:i') }}
        @endif
    </p>
</body>
</html>
