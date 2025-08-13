<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - {{ $user->nama_lengkap }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 15px;
            font-size: 12px;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 12px;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .info-left, .info-right {
            width: 48%;
        }
        .info-row {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
        }
        .period-box {
            background: #f0f0f0;
            padding: 8px;
            text-align: center;
            margin-bottom: 15px;
            border: 1px solid #ccc;
        }
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .salary-table th {
            background: #f0f0f0;
            padding: 8px;
            border: 1px solid #ccc;
            text-align: left;
            font-size: 11px;
        }
        .salary-table td {
            padding: 6px 8px;
            border: 1px solid #ccc;
            font-size: 11px;
        }
        .earnings {
            color: #2e7d32;
            font-weight: bold;
        }
        .deductions {
            color: #c62828;
            font-weight: bold;
        }
        .total-row {
            background: #f8f9fa;
            font-weight: bold;
            font-size: 14px;
        }
        .total-row td {
            border: 2px solid #333;
        }
        .custom-items {
            margin-top: 10px;
            font-size: 10px;
        }
        .custom-item {
            margin-bottom: 3px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        .nominal-info {
            font-size: 9px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>SLIP GAJI KARYAWAN</h1>
        <p>Periode: {{ $periode }}</p>
    </div>

    <div class="info-section">
        <div class="info-left">
            <div class="info-row">
                <span class="info-label">Nama:</span>
                <span>{{ $user->nama_lengkap }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">NIK:</span>
                <span>{{ $user->nik }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Jabatan:</span>
                <span>{{ $jabatan ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Divisi:</span>
                <span>{{ $divisi ?? '-' }}</span>
            </div>
        </div>
        <div class="info-right">
            <div class="info-row">
                <span class="info-label">Outlet:</span>
                <span>{{ $outlet ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Periode:</span>
                <span>{{ $periode }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Hari Kerja:</span>
                <span>{{ $hari_kerja }} hari</span>
            </div>
        </div>
    </div>

    <div class="period-box">
        <strong>Periode Penggajian: {{ $periode }}</strong>
    </div>

    <table class="salary-table">
        <thead>
            <tr>
                <th style="width: 40%;">KETERANGAN</th>
                <th style="width: 20%;">JUMLAH</th>
                <th style="width: 40%;">NOMINAL</th>
            </tr>
        </thead>
        <tbody>
            <!-- PENDAPATAN -->
            <tr>
                <td colspan="3" style="background: #e8f5e8; font-weight: bold; text-align: center;">PENDAPATAN</td>
            </tr>
            <tr>
                <td>Gaji Pokok</td>
                <td>-</td>
                <td class="earnings">Rp {{ number_format($gaji_pokok, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Tunjangan</td>
                <td>-</td>
                <td class="earnings">Rp {{ number_format($tunjangan, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Jam Lembur</td>
                <td>{{ $total_lembur }} jam</td>
                <td class="earnings">
                    @if($master_data->ot == 1)
                        Rp {{ number_format($gaji_lembur, 0, ',', '.') }}
                        <div class="nominal-info">@ Rp {{ number_format($nominal_lembur_per_jam, 0, ',', '.') }}/jam</div>
                    @else
                        Rp 0 (OT Disabled)
                    @endif
                </td>
            </tr>
            <tr>
                <td>Uang Makan</td>
                <td>{{ $hari_kerja }} hari</td>
                <td class="earnings">
                    @if($master_data->um == 1)
                        Rp {{ number_format($uang_makan, 0, ',', '.') }}
                        <div class="nominal-info">@ Rp {{ number_format($nominal_uang_makan, 0, ',', '.') }}/hari</div>
                    @else
                        Rp 0 (UM Disabled)
                    @endif
                </td>
            </tr>
            @if($custom_earnings > 0)
            <tr>
                <td>Pendapatan Tambahan</td>
                <td>-</td>
                <td class="earnings">Rp {{ number_format($custom_earnings, 0, ',', '.') }}</td>
            </tr>
            @endif

            <!-- POTONGAN -->
            <tr>
                <td colspan="3" style="background: #ffebee; font-weight: bold; text-align: center;">POTONGAN</td>
            </tr>
            @if($total_telat > 0)
            <tr>
                <td>Menit Telat</td>
                <td>{{ $total_telat }} menit</td>
                <td class="deductions">
                    Rp {{ number_format($potongan_telat, 0, ',', '.') }}
                    <div class="nominal-info">@ Rp {{ number_format($gaji_per_menit, 2, ',', '.') }}/menit</div>
                </td>
            </tr>
            @endif
            <tr>
                <td>BPJS JKN</td>
                <td>-</td>
                <td class="deductions">
                    @if($master_data->bpjs_jkn == 1)
                        Rp {{ number_format($bpjs_jkn, 0, ',', '.') }}
                    @else
                        Rp 0 (JKN Disabled)
                    @endif
                </td>
            </tr>
            <tr>
                <td>BPJS TK</td>
                <td>-</td>
                <td class="deductions">
                    @if($master_data->bpjs_tk == 1)
                        Rp {{ number_format($bpjs_tk, 0, ',', '.') }}
                    @else
                        Rp 0 (TK Disabled)
                    @endif
                </td>
            </tr>
            @if($custom_deductions > 0)
            <tr>
                <td>Potongan Tambahan</td>
                <td>-</td>
                <td class="deductions">Rp {{ number_format($custom_deductions, 0, ',', '.') }}</td>
            </tr>
            @endif

            <!-- TOTAL -->
            <tr class="total-row">
                <td><strong>TOTAL GAJI BERSIH</strong></td>
                <td></td>
                <td class="earnings"><strong>Rp {{ number_format($total_gaji, 0, ',', '.') }}</strong></td>
            </tr>
        </tbody>
    </table>

    @if($custom_items->count() > 0)
    <div class="custom-items">
        <strong>Detail Item Tambahan:</strong><br>
        @foreach($custom_items as $item)
            <div class="custom-item">
                • {{ $item->item_name }} ({{ ucfirst($item->item_type) }}): 
                <span class="{{ $item->item_type == 'earn' ? 'earnings' : 'deductions' }}">
                    {{ $item->item_type == 'earn' ? '+' : '-' }} Rp {{ number_format($item->item_amount, 0, ',', '.') }}
                </span>
                @if($item->item_description)
                    <br><span style="margin-left: 10px; font-style: italic;">{{ $item->item_description }}</span>
                @endif
            </div>
        @endforeach
    </div>
    @endif

    <div class="footer">
        <p>Dokumen ini dibuat secara otomatis oleh sistem</p>
        <p>Dicetak pada: {{ date('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
