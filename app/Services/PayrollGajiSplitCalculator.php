<?php

namespace App\Services;

/**
 * Pembagian total gaji: Gajian 1 (akhir bulan) + Gajian 2 (tanggal 8).
 */
class PayrollGajiSplitCalculator
{
    /**
     * @param  array<string, float|int>  $components
     * @return array{total_gaji_akhir_bulan: float, total_gaji_tanggal_8: float, total_gaji: float}
     */
    public static function calculate(array $components): array
    {
        $totalGajiAkhirBulan =
            (float) ($components['gaji_pokok'] ?? 0)
            + (float) ($components['tunjangan'] ?? 0)
            + (float) ($components['custom_earnings_gajian1'] ?? 0)
            - (float) ($components['custom_deductions_gajian1'] ?? 0)
            - (float) ($components['bpjs_jkn'] ?? 0)
            - (float) ($components['bpjs_tk'] ?? 0)
            - (float) ($components['potongan_telat'] ?? 0)
            - (float) ($components['potongan_alpha'] ?? 0)
            - (float) ($components['potongan_unpaid_leave'] ?? 0)
            - (float) ($components['potongan_kasbon'] ?? 0);

        $totalGajiTanggal8 =
            (float) ($components['service_charge'] ?? 0)
            + (float) ($components['uang_makan'] ?? 0)
            + (float) ($components['gaji_lembur'] ?? 0)
            + (float) ($components['ph_bonus'] ?? 0)
            + (float) ($components['custom_earnings_gajian2'] ?? 0)
            - (float) ($components['lb_total'] ?? 0)
            - (float) ($components['deviasi_total'] ?? 0)
            - (float) ($components['city_ledger_total'] ?? 0)
            - (float) ($components['custom_deductions_gajian2'] ?? 0);

        return [
            'total_gaji_akhir_bulan' => round($totalGajiAkhirBulan),
            'total_gaji_tanggal_8' => round($totalGajiTanggal8),
            'total_gaji' => round($totalGajiAkhirBulan + $totalGajiTanggal8),
        ];
    }
}
