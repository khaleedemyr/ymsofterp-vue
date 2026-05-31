<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji Gabungan - {{ $user->nama_lengkap }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 10px; font-size: 11px; line-height: 1.2; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 5px; margin-bottom: 10px; }
        .logo-container { text-align: center; margin-bottom: 5px; }
        .logo-container img { max-height: 120px; max-width: 400px; object-fit: contain; }
        .header h1 { margin: 0; font-size: 16px; font-weight: bold; }
        .header p { margin: 3px 0 0 0; font-size: 11px; }
        .info-section { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .info-left, .info-right { width: 48%; }
        .info-row { margin-bottom: 3px; }
        .info-label { font-weight: bold; display: inline-block; width: 80px; }
        .period-box { background: #f0f0f0; padding: 8px; text-align: center; margin-bottom: 10px; border: 1px solid #ccc; }
        .summary-box { background: #e3f2fd; padding: 10px; margin-bottom: 12px; border: 1px solid #90caf9; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .section-title { font-weight: bold; color: #1976d2; margin: 14px 0 6px; font-size: 12px; }
        .salary-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .salary-table th { background: #f0f0f0; padding: 4px 6px; border: 1px solid #ccc; text-align: left; font-size: 10px; }
        .salary-table td { padding: 3px 6px; border: 1px solid #ccc; font-size: 10px; }
        .earnings { color: #2e7d32; font-weight: bold; }
        .deductions { color: #c62828; font-weight: bold; }
        .total-row { background: #f8f9fa; font-weight: bold; font-size: 12px; }
        .total-row td { border: 2px solid #333; }
        .grand-total { background: #fff3e0; border: 2px solid #ff9800; padding: 10px; margin-top: 12px; text-align: center; font-size: 13px; font-weight: bold; }
        .page-break { page-break-before: always; }
        .footer { text-align: center; margin-top: 12px; font-size: 9px; color: #666; border-top: 1px solid #ccc; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            @if($logo_base64)
                <img src="data:image/png;base64,{{ $logo_base64 }}" alt="Justus Group Logo">
            @else
                <div style="height: 80px; background: #1e3c72; color: white; line-height: 80px; font-size: 24px; font-weight: bold;">JUSTUS GROUP</div>
            @endif
        </div>
        <h1>SLIP GAJI KARYAWAN (GABUNGAN)</h1>
        <p>Periode: {{ $periode }}</p>
    </div>

    <div class="info-section">
        <div class="info-left">
            <div class="info-row"><span class="info-label">Nama:</span> {{ $user->nama_lengkap }}</div>
            <div class="info-row"><span class="info-label">NIK:</span> {{ $user->nik }}</div>
            <div class="info-row"><span class="info-label">Jabatan:</span> {{ $jabatan ?? '-' }}</div>
            <div class="info-row"><span class="info-label">Divisi:</span> {{ $divisi ?? '-' }}</div>
        </div>
        <div class="info-right">
            <div class="info-row"><span class="info-label">Outlet:</span> {{ $outlet ?? '-' }}</div>
            <div class="info-row"><span class="info-label">Periode:</span> {{ $periode }}</div>
            <div class="info-row"><span class="info-label">Hari Kerja:</span> {{ $hari_kerja }} hari</div>
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-row"><span>Gajian 1 (Akhir Bulan)</span><span class="earnings">Rp {{ number_format($total_gajian1, 0, ',', '.') }}</span></div>
        <div class="summary-row"><span>Gajian 2 (Tanggal 8)</span><span class="earnings">Rp {{ number_format($total_gajian2, 0, ',', '.') }}</span></div>
        <div class="summary-row" style="border-top: 1px solid #90caf9; padding-top: 6px; margin-top: 6px; font-weight: bold;">
            <span>TOTAL GAJI PERIODE</span>
            <span class="earnings">Rp {{ number_format($total_gaji_combined, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="section-title">GAJIAN 1 — AKHIR BULAN</div>
    @php $type = 'gajian1'; $total_gaji = $total_gajian1; @endphp
    @include('payroll.partials.slip_salary_table')

    <div class="page-break"></div>

    <div class="section-title">GAJIAN 2 — TANGGAL 8</div>
    @php $type = 'gajian2'; $total_gaji = $total_gajian2; @endphp
    @include('payroll.partials.slip_salary_table')

    <div class="grand-total">
        TOTAL GAJI BERSIH PERIODE {{ $periode }}: Rp {{ number_format($total_gaji_combined, 0, ',', '.') }}
    </div>

    <div class="footer">
        <p>Dokumen ini dibuat secara otomatis oleh sistem</p>
        <p>Dicetak pada: {{ date('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
