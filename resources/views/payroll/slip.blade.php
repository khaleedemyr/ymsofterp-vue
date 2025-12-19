<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - {{ $user->nama_lengkap }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10px;
            font-size: 11px;
            line-height: 1.2;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 5px;
        }
        .logo-container img {
            max-height: 180px;
            max-width: 600px;
            object-fit: contain;
        }
        .header h1 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
        }
        .header p {
            margin: 3px 0 0 0;
            font-size: 11px;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .info-left, .info-right {
            width: 48%;
        }
        .info-row {
            margin-bottom: 3px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
        }
        .period-box {
            background: #f0f0f0;
            padding: 5px;
            text-align: center;
            margin-bottom: 8px;
            border: 1px solid #ccc;
        }
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .salary-table th {
            background: #f0f0f0;
            padding: 4px 6px;
            border: 1px solid #ccc;
            text-align: left;
            font-size: 10px;
        }
        .salary-table td {
            padding: 3px 6px;
            border: 1px solid #ccc;
            font-size: 10px;
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
            font-size: 12px;
        }
        .total-row td {
            border: 2px solid #333;
        }
        .custom-items {
            margin-top: 5px;
            font-size: 9px;
        }
        .custom-item {
            margin-bottom: 2px;
        }
        .footer {
            text-align: center;
            margin-top: 8px;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 5px;
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
        <div class="logo-container">
            @if($logo_base64)
                <img src="data:image/png;base64,{{ $logo_base64 }}" alt="Justus Group Logo" style="max-height: 180px; max-width: 600px;">
            @else
                <div style="height: 180px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); display: flex; align-items: center; justify-content: center; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <span style="color: white; font-size: 48px; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">JUSTUS GROUP</span>
                </div>
            @endif
        </div>
        <h1>SLIP GAJI KARYAWAN</h1>
        <p>Periode: {{ $periode }}</p>
        @if(isset($type))
            <p style="font-weight: bold; color: #1976d2;">
                @if($type === 'gajian1')
                    GAJIAN 1 (AKHIR BULAN)
                @else
                    GAJIAN 2 (TANGGAL 8)
                @endif
            </p>
        @endif
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
            @php
                $type = $type ?? 'gajian1'; // Default to gajian1 if not set
            @endphp

            @if($type === 'gajian1')
                <!-- GAJIAN 1: AKHIR BULAN -->
                <!-- PENDAPATAN -->
                <tr>
                    <td colspan="3" style="background: #e8f5e8; font-weight: bold; text-align: center;">PENDAPATAN</td>
                </tr>
                <tr>
                    <td>1. Gaji Pokok</td>
                    <td>-</td>
                    <td class="earnings">Rp {{ number_format($gaji_pokok, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>2. Tunjangan</td>
                    <td>-</td>
                    <td class="earnings">Rp {{ number_format($tunjangan, 0, ',', '.') }}</td>
                </tr>
                @if(isset($custom_earnings) && $custom_earnings > 0)
                <tr>
                    <td>3. Custom Earning</td>
                    <td>{{ $custom_items && $custom_items->where('item_type', 'earn')->count() > 0 ? $custom_items->where('item_type', 'earn')->count() : 0 }} item</td>
                    <td class="earnings">Rp {{ number_format($custom_earnings, 0, ',', '.') }}</td>
                </tr>
                @else
                <tr>
                    <td>3. Custom Earning</td>
                    <td>-</td>
                    <td class="earnings">Rp 0</td>
                </tr>
                @endif

                <!-- POTONGAN -->
                <tr>
                    <td colspan="3" style="background: #ffebee; font-weight: bold; text-align: center;">POTONGAN</td>
                </tr>
                @if(isset($custom_deductions) && $custom_deductions > 0)
                <tr>
                    <td>1. Custom Deduction</td>
                    <td>{{ $custom_items && $custom_items->where('item_type', 'deduction')->count() > 0 ? $custom_items->where('item_type', 'deduction')->count() : 0 }} item</td>
                    <td class="deductions">Rp {{ number_format($custom_deductions, 0, ',', '.') }}</td>
                </tr>
                @else
                <tr>
                    <td>1. Custom Deduction</td>
                    <td>-</td>
                    <td class="deductions">Rp 0</td>
                </tr>
                @endif
                <tr>
                    <td>2. Potongan Telat</td>
                    <td>{{ $total_telat ?? 0 }} menit</td>
                    <td class="deductions">
                        Rp {{ number_format($potongan_telat ?? 0, 0, ',', '.') }}
                        @if(isset($total_telat) && $total_telat > 0)
                            <div class="nominal-info">@ Rp {{ number_format($gaji_per_menit ?? 500, 2, ',', '.') }}/menit</div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>3. Alpha & Unpaid Leave</td>
                    <td>
                        @if(isset($total_alpha) && $total_alpha > 0)
                            Alpha: {{ $total_alpha }} hari
                        @endif
                        @if(isset($leave_data['unpaid_leave_days']) && $leave_data['unpaid_leave_days'] > 0)
                            @if(isset($total_alpha) && $total_alpha > 0), @endif
                            Unpaid: {{ $leave_data['unpaid_leave_days'] }} hari
                        @endif
                        @if((!isset($total_alpha) || $total_alpha == 0) && (!isset($leave_data['unpaid_leave_days']) || $leave_data['unpaid_leave_days'] == 0))
                            -
                        @endif
                    </td>
                    <td class="deductions">
                        Rp {{ number_format(($potongan_alpha ?? 0) + ($potongan_unpaid_leave ?? 0), 0, ',', '.') }}
                        @if(isset($total_alpha) && $total_alpha > 0)
                            <div class="nominal-info">Alpha: Rp {{ number_format($potongan_alpha ?? 0, 0, ',', '.') }}</div>
                        @endif
                        @if(isset($potongan_unpaid_leave) && $potongan_unpaid_leave > 0)
                            <div class="nominal-info">Unpaid Leave: Rp {{ number_format($potongan_unpaid_leave, 0, ',', '.') }}</div>
                        @endif
                    </td>
                </tr>

            @else
                <!-- GAJIAN 2: TANGGAL 8 -->
                <!-- PENDAPATAN -->
                <tr>
                    <td colspan="3" style="background: #e8f5e8; font-weight: bold; text-align: center;">PENDAPATAN</td>
                </tr>
                <tr>
                    <td>1. Service Charge (By Point)</td>
                    <td>-</td>
                    <td class="earnings">Rp {{ number_format($service_charge_by_point ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>2. Service Charge (Pro Rate)</td>
                    <td>-</td>
                    <td class="earnings">Rp {{ number_format($service_charge_pro_rate ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>3. Uang Makan</td>
                    <td>{{ $hari_kerja ?? 0 }} hari</td>
                    <td class="earnings">
                        @if($master_data->um == 1)
                            Rp {{ number_format($uang_makan ?? 0, 0, ',', '.') }}
                            <div class="nominal-info">@ Rp {{ number_format($nominal_uang_makan ?? 0, 0, ',', '.') }}/hari</div>
                        @else
                            Rp 0 (UM Disabled)
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>4. Lembur</td>
                    <td>{{ $total_lembur ?? 0 }} jam</td>
                    <td class="earnings">
                        @if($master_data->ot == 1)
                            Rp {{ number_format($gaji_lembur ?? 0, 0, ',', '.') }}
                            <div class="nominal-info">@ Rp {{ number_format($nominal_lembur_per_jam ?? 0, 0, ',', '.') }}/jam</div>
                        @else
                            Rp 0 (OT Disabled)
                        @endif
                    </td>
                </tr>

                <!-- POTONGAN -->
                <tr>
                    <td colspan="3" style="background: #ffebee; font-weight: bold; text-align: center;">POTONGAN</td>
                </tr>
                <tr>
                    <td>5. L & B</td>
                    <td>-</td>
                    <td class="deductions">Rp {{ number_format($lb_total ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>6. Deviasi</td>
                    <td>-</td>
                    <td class="deductions">Rp {{ number_format($deviasi_total ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>7. City Ledger</td>
                    <td>-</td>
                    <td class="deductions">Rp {{ number_format($city_ledger_total ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>8. PH Bonus</td>
                    <td>-</td>
                    <td class="earnings">Rp {{ number_format($ph_bonus ?? 0, 0, ',', '.') }}</td>
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

    @php
        $type = $type ?? 'gajian1'; // Default to gajian1 if not set
    @endphp

    @if($type === 'gajian1')
        <!-- GAJIAN 1: Tampilkan Custom Items dan Leave Type Breakdown -->
        @if(isset($custom_items) && $custom_items->count() > 0)
        <div class="custom-items">
            <strong>Detail Custom Items:</strong><br>
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

        @if(isset($leave_data) && is_array($leave_data) && count($leave_data) > 0)
        <div class="custom-items" style="margin-top: 10px;">
            <strong>Leave Type Breakdown:</strong><br>
            @foreach($leave_data as $key => $value)
                @if(strpos($key, '_days') !== false && $value > 0)
                    @php
                        // Extract leave type name from key (e.g., 'sick_leave_days' -> 'Sick Leave')
                        $leaveTypeName = str_replace('_days', '', $key);
                        $leaveTypeName = str_replace('_', ' ', $leaveTypeName);
                        $leaveTypeName = ucwords($leaveTypeName);
                        
                        // Try to get from leave_types table if available
                        if(isset($leave_types)) {
                            $foundType = $leave_types->firstWhere('name', $leaveTypeName);
                            if($foundType) {
                                $leaveTypeName = $foundType->name;
                            }
                        }
                    @endphp
                    <div class="custom-item">
                        • {{ $leaveTypeName }}: <span class="earnings">{{ $value }} hari</span>
                    </div>
                @endif
            @endforeach
        </div>
        @endif
    @endif

    <div class="footer">
        <p>Dokumen ini dibuat secara otomatis oleh sistem</p>
        <p>Dicetak pada: {{ date('d/m/Y H:i:s') }}</p>
    </div>


</body>
</html>
