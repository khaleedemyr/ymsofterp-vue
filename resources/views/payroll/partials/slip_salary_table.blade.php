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
            $type = $type ?? 'gajian1';
        @endphp

        @if($type === 'gajian1')
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
            @php
                $gajian1CustomEarnings = $custom_earnings_gajian1 ?? $custom_earnings ?? 0;
                $gajian1CustomDeductions = $custom_deductions_gajian1 ?? $custom_deductions ?? 0;
            @endphp
            <tr>
                <td>3. Custom Earning</td>
                <td>{{ $gajian1CustomEarnings > 0 ? ($custom_items_gajian1 ?? $custom_items)->where('item_type', 'earn')->count() : 0 }} item</td>
                <td class="earnings">Rp {{ number_format($gajian1CustomEarnings, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td colspan="3" style="background: #ffebee; font-weight: bold; text-align: center;">POTONGAN</td>
            </tr>
            <tr>
                <td>1. Custom Deduction</td>
                <td>{{ $gajian1CustomDeductions > 0 ? ($custom_items_gajian1 ?? $custom_items)->where('item_type', 'deduction')->count() : 0 }} item</td>
                <td class="deductions">Rp {{ number_format($gajian1CustomDeductions, 0, ',', '.') }}</td>
            </tr>
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
                <td>-</td>
                <td class="deductions">Rp {{ number_format(($potongan_alpha ?? 0) + ($potongan_unpaid_leave ?? 0), 0, ',', '.') }}</td>
            </tr>
            @if(($bpjs_jkn ?? 0) > 0)
            <tr>
                <td>4. BPJS Kesehatan (JKN)</td>
                <td>-</td>
                <td class="deductions">Rp {{ number_format($bpjs_jkn, 0, ',', '.') }}</td>
            </tr>
            @endif
            @if(($bpjs_tk ?? 0) > 0)
            <tr>
                <td>5. BPJS Ketenagakerjaan (TK)</td>
                <td>-</td>
                <td class="deductions">Rp {{ number_format($bpjs_tk, 0, ',', '.') }}</td>
            </tr>
            @endif
            @if(($potongan_kasbon ?? 0) > 0)
            <tr>
                <td>6. Potongan Kasbon</td>
                <td>-</td>
                <td class="deductions">Rp {{ number_format($potongan_kasbon, 0, ',', '.') }}</td>
            </tr>
            @endif
        @else
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
                <td class="earnings">Rp {{ number_format($uang_makan ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>4. Lembur</td>
                <td>{{ $total_lembur ?? 0 }} jam</td>
                <td class="earnings">Rp {{ number_format($gaji_lembur ?? 0, 0, ',', '.') }}</td>
            </tr>
            @if(($ph_bonus ?? 0) > 0)
            <tr>
                <td>5. PH Bonus</td>
                <td>-</td>
                <td class="earnings">Rp {{ number_format($ph_bonus, 0, ',', '.') }}</td>
            </tr>
            @endif
            <tr>
                <td colspan="3" style="background: #ffebee; font-weight: bold; text-align: center;">POTONGAN</td>
            </tr>
            <tr>
                <td>1. L & B</td>
                <td>-</td>
                <td class="deductions">Rp {{ number_format($lb_total ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>2. Deviasi</td>
                <td>-</td>
                <td class="deductions">Rp {{ number_format($deviasi_total ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>3. City Ledger</td>
                <td>-</td>
                <td class="deductions">Rp {{ number_format($city_ledger_total ?? 0, 0, ',', '.') }}</td>
            </tr>
            @if(isset($custom_earnings_gajian2) && $custom_earnings_gajian2 > 0)
            <tr>
                <td>4. Custom Earning</td>
                <td>{{ ($custom_items_gajian2 ?? collect())->where('item_type', 'earn')->count() }} item</td>
                <td class="earnings">Rp {{ number_format($custom_earnings_gajian2, 0, ',', '.') }}</td>
            </tr>
            @endif
            @if(isset($custom_deductions_gajian2) && $custom_deductions_gajian2 > 0)
            <tr>
                <td>5. Custom Deduction</td>
                <td>{{ ($custom_items_gajian2 ?? collect())->where('item_type', 'deduction')->count() }} item</td>
                <td class="deductions">Rp {{ number_format($custom_deductions_gajian2, 0, ',', '.') }}</td>
            </tr>
            @endif
        @endif

        <tr class="total-row">
            <td><strong>
                @if($type === 'gajian1')
                    TOTAL GAJIAN 1 (AKHIR BULAN)
                @else
                    TOTAL GAJIAN 2 (TANGGAL 8)
                @endif
            </strong></td>
            <td></td>
            <td class="earnings"><strong>Rp {{ number_format($total_gaji, 0, ',', '.') }}</strong></td>
        </tr>
    </tbody>
</table>
