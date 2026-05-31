<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PayrollGeneratePhaseService
{
    public const GAJIAN1 = 'gajian1';
    public const GAJIAN2 = 'gajian2';

    public function assertPhaseNotLocked(object $payroll, string $gajianType): ?array
    {
        $column = $gajianType === self::GAJIAN2 ? 'gajian2_status' : 'gajian1_status';
        $label = $gajianType === self::GAJIAN2 ? 'Gajian 2' : 'Gajian 1';

        if (($payroll->{$column} ?? 'draft') === 'locked' || ($payroll->status ?? '') === 'locked') {
            return [
                'success' => false,
                'message' => "{$label} sudah di-lock dan tidak bisa diubah",
                'code' => 403,
            ];
        }

        return null;
    }

    public function headerUpdatesForPhase(string $gajianType, array $amounts): array
    {
        if ($gajianType !== self::GAJIAN2) {
            return [];
        }

        return [
            'service_charge' => $amounts['service_charge'] ?? 0,
            'lb_amount' => $amounts['lb_amount'] ?? 0,
            'deviasi_amount' => $amounts['deviasi_amount'] ?? 0,
            'city_ledger_amount' => $amounts['city_ledger_amount'] ?? 0,
        ];
    }

    public function phaseFieldsFromItem(string $gajianType, array $item): array
    {
        if ($gajianType === self::GAJIAN1) {
            $customEarnings = $item['custom_earnings_gajian1'] ?? $item['custom_earnings'] ?? 0;
            $customDeductions = $item['custom_deductions_gajian1'] ?? $item['custom_deductions'] ?? 0;

            return [
                'gaji_pokok' => $item['gaji_pokok'] ?? 0,
                'tunjangan' => $item['tunjangan'] ?? 0,
                'total_telat' => $item['total_telat'] ?? 0,
                'gaji_per_menit' => $item['gaji_per_menit'] ?? 0,
                'potongan_telat' => $item['potongan_telat'] ?? 0,
                'total_alpha' => $item['total_alpha'] ?? 0,
                'potongan_alpha' => $item['potongan_alpha'] ?? 0,
                'potongan_unpaid_leave' => $item['potongan_unpaid_leave'] ?? 0,
                'bpjs_jkn' => $item['bpjs_jkn'] ?? 0,
                'bpjs_tk' => $item['bpjs_tk'] ?? 0,
                'bpjs_perusahaan_detail' => isset($item['bpjs_perusahaan_detail']) && $item['bpjs_perusahaan_detail'] !== null
                    ? json_encode($item['bpjs_perusahaan_detail'])
                    : null,
                'potongan_kasbon' => $item['potongan_kasbon'] ?? 0,
                'pr_kasbon_id' => $item['pr_kasbon_id'] ?? null,
                'kasbon_cicilan_ke' => $item['kasbon_cicilan_ke'] ?? null,
                'custom_earnings' => $customEarnings,
                'custom_deductions' => $customDeductions,
                'leave_data' => isset($item['leave_data'])
                    ? json_encode($item['leave_data'])
                    : (isset($item['izin_cuti_breakdown']) ? json_encode($item['izin_cuti_breakdown']) : null),
                'hari_kerja' => $item['hari_kerja'] ?? 0,
            ];
        }

        return [
            'total_lembur' => $item['total_lembur'] ?? 0,
            'nominal_lembur_per_jam' => $item['nominal_lembur_per_jam'] ?? 0,
            'gaji_lembur' => $item['gaji_lembur'] ?? 0,
            'nominal_uang_makan' => $item['nominal_uang_makan'] ?? 0,
            'uang_makan' => $item['uang_makan'] ?? 0,
            'service_charge_by_point' => $item['service_charge_by_point'] ?? 0,
            'service_charge_pro_rate' => $item['service_charge_pro_rate'] ?? 0,
            'service_charge' => $item['service_charge'] ?? 0,
            'lb_by_point' => $item['lb_by_point'] ?? 0,
            'lb_pro_rate' => $item['lb_pro_rate'] ?? 0,
            'lb_total' => $item['lb_total'] ?? 0,
            'deviasi_by_point' => $item['deviasi_by_point'] ?? 0,
            'deviasi_pro_rate' => $item['deviasi_pro_rate'] ?? 0,
            'deviasi_total' => $item['deviasi_total'] ?? 0,
            'city_ledger_by_point' => $item['city_ledger_by_point'] ?? 0,
            'city_ledger_pro_rate' => $item['city_ledger_pro_rate'] ?? 0,
            'city_ledger_total' => $item['city_ledger_total'] ?? 0,
            'ph_bonus' => $item['ph_bonus'] ?? 0,
        ];
    }

    public function baseFieldsFromItem(array $item): array
    {
        return [
            'nik' => $item['nik'] ?? null,
            'nama_lengkap' => $item['nama_lengkap'] ?? null,
            'jabatan' => $item['jabatan'] ?? null,
            'divisi' => $item['divisi'] ?? null,
            'point' => $item['point'] ?? 0,
            'periode' => $item['periode'] ?? null,
            'custom_items' => isset($item['custom_items']) ? json_encode($item['custom_items']) : null,
            'payment_method' => $item['payment_method'] ?? 'transfer',
        ];
    }

    public function calculateTotalGaji(array $merged): float
    {
        $customEarningsG1 = (float) ($merged['custom_earnings'] ?? 0);
        $customDeductionsG1 = (float) ($merged['custom_deductions'] ?? 0);

        $totalGajian1 = (float) ($merged['gaji_pokok'] ?? 0)
            + (float) ($merged['tunjangan'] ?? 0)
            + $customEarningsG1
            - $customDeductionsG1
            - (float) ($merged['bpjs_jkn'] ?? 0)
            - (float) ($merged['bpjs_tk'] ?? 0)
            - (float) ($merged['potongan_telat'] ?? 0)
            - (float) ($merged['potongan_alpha'] ?? 0)
            - (float) ($merged['potongan_unpaid_leave'] ?? 0)
            - (float) ($merged['potongan_kasbon'] ?? 0);

        $totalGajian2 = (float) ($merged['service_charge'] ?? 0)
            + (float) ($merged['uang_makan'] ?? 0)
            + (float) ($merged['gaji_lembur'] ?? 0)
            + (float) ($merged['ph_bonus'] ?? 0)
            - (float) ($merged['lb_total'] ?? 0)
            - (float) ($merged['deviasi_total'] ?? 0)
            - (float) ($merged['city_ledger_total'] ?? 0);

        return round($totalGajian1 + $totalGajian2);
    }

    public function mergeDetailRow(?object $existing, array $phaseFields, array $baseFields): array
    {
        $existingArray = $existing ? (array) $existing : [];
        $merged = array_merge($existingArray, $baseFields, $phaseFields);
        $merged['total_gaji'] = $this->calculateTotalGaji($merged);

        return $merged;
    }

    public function syncOverallStatus(int $payrollId): void
    {
        $payroll = DB::table('payroll_generated')->where('id', $payrollId)->first();
        if (!$payroll) {
            return;
        }

        $g1 = $payroll->gajian1_status ?? 'draft';
        $g2 = $payroll->gajian2_status ?? 'draft';

        $status = 'draft';
        if ($g1 === 'locked' || $g2 === 'locked') {
            $status = 'locked';
        } elseif ($g1 === 'generated' && $g2 === 'generated') {
            $status = 'generated';
        } elseif ($g1 === 'generated' || $g2 === 'generated') {
            $status = 'generated';
        }

        DB::table('payroll_generated')->where('id', $payrollId)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    public function clearPhaseDetailFields(string $gajianType): array
    {
        if ($gajianType === self::GAJIAN1) {
            return [
                'gaji_pokok' => 0,
                'tunjangan' => 0,
                'total_telat' => 0,
                'gaji_per_menit' => 0,
                'potongan_telat' => 0,
                'total_alpha' => 0,
                'potongan_alpha' => 0,
                'potongan_unpaid_leave' => 0,
                'bpjs_jkn' => 0,
                'bpjs_tk' => 0,
                'bpjs_perusahaan_detail' => null,
                'potongan_kasbon' => 0,
                'pr_kasbon_id' => null,
                'kasbon_cicilan_ke' => null,
                'custom_earnings' => 0,
                'custom_deductions' => 0,
                'leave_data' => null,
            ];
        }

        return [
            'total_lembur' => 0,
            'nominal_lembur_per_jam' => 0,
            'gaji_lembur' => 0,
            'nominal_uang_makan' => 0,
            'uang_makan' => 0,
            'service_charge_by_point' => 0,
            'service_charge_pro_rate' => 0,
            'service_charge' => 0,
            'lb_by_point' => 0,
            'lb_pro_rate' => 0,
            'lb_total' => 0,
            'deviasi_by_point' => 0,
            'deviasi_pro_rate' => 0,
            'deviasi_total' => 0,
            'city_ledger_by_point' => 0,
            'city_ledger_pro_rate' => 0,
            'city_ledger_total' => 0,
            'ph_bonus' => 0,
        ];
    }

    public function detailInsertPayload(int $payrollId, int $userId, array $merged): array
    {
        return [
            'payroll_generated_id' => $payrollId,
            'user_id' => $userId,
            'nik' => $merged['nik'] ?? null,
            'nama_lengkap' => $merged['nama_lengkap'] ?? null,
            'jabatan' => $merged['jabatan'] ?? null,
            'divisi' => $merged['divisi'] ?? null,
            'point' => $merged['point'] ?? 0,
            'gaji_pokok' => $merged['gaji_pokok'] ?? 0,
            'tunjangan' => $merged['tunjangan'] ?? 0,
            'total_telat' => $merged['total_telat'] ?? 0,
            'total_lembur' => $merged['total_lembur'] ?? 0,
            'nominal_lembur_per_jam' => $merged['nominal_lembur_per_jam'] ?? 0,
            'gaji_lembur' => $merged['gaji_lembur'] ?? 0,
            'nominal_uang_makan' => $merged['nominal_uang_makan'] ?? 0,
            'uang_makan' => $merged['uang_makan'] ?? 0,
            'service_charge_by_point' => $merged['service_charge_by_point'] ?? 0,
            'service_charge_pro_rate' => $merged['service_charge_pro_rate'] ?? 0,
            'service_charge' => $merged['service_charge'] ?? 0,
            'bpjs_jkn' => $merged['bpjs_jkn'] ?? 0,
            'bpjs_tk' => $merged['bpjs_tk'] ?? 0,
            'bpjs_perusahaan_detail' => $merged['bpjs_perusahaan_detail'] ?? null,
            'lb_by_point' => $merged['lb_by_point'] ?? 0,
            'lb_pro_rate' => $merged['lb_pro_rate'] ?? 0,
            'lb_total' => $merged['lb_total'] ?? 0,
            'deviasi_by_point' => $merged['deviasi_by_point'] ?? 0,
            'deviasi_pro_rate' => $merged['deviasi_pro_rate'] ?? 0,
            'deviasi_total' => $merged['deviasi_total'] ?? 0,
            'city_ledger_by_point' => $merged['city_ledger_by_point'] ?? 0,
            'city_ledger_pro_rate' => $merged['city_ledger_pro_rate'] ?? 0,
            'city_ledger_total' => $merged['city_ledger_total'] ?? 0,
            'ph_bonus' => $merged['ph_bonus'] ?? 0,
            'custom_earnings' => $merged['custom_earnings'] ?? 0,
            'custom_deductions' => $merged['custom_deductions'] ?? 0,
            'gaji_per_menit' => $merged['gaji_per_menit'] ?? 0,
            'potongan_telat' => $merged['potongan_telat'] ?? 0,
            'total_alpha' => $merged['total_alpha'] ?? 0,
            'potongan_alpha' => $merged['potongan_alpha'] ?? 0,
            'potongan_unpaid_leave' => $merged['potongan_unpaid_leave'] ?? 0,
            'potongan_kasbon' => $merged['potongan_kasbon'] ?? 0,
            'pr_kasbon_id' => $merged['pr_kasbon_id'] ?? null,
            'kasbon_cicilan_ke' => $merged['kasbon_cicilan_ke'] ?? null,
            'total_gaji' => $merged['total_gaji'] ?? 0,
            'hari_kerja' => $merged['hari_kerja'] ?? 0,
            'periode' => $merged['periode'] ?? null,
            'custom_items' => $merged['custom_items'] ?? null,
            'leave_data' => $merged['leave_data'] ?? null,
            'payment_method' => $merged['payment_method'] ?? 'transfer',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
