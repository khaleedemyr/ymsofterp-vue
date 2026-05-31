<?php

namespace App\Services;

use Illuminate\Support\Collection;

class PayrollSlipBreakdownBuilder
{
    /**
     * @return array{
     *     gajian_akhir_bulan: array{title: string, earnings: array, deductions: array, total: float},
     *     gajian_tanggal_8: array{title: string, earnings: array, deductions: array, total: float}
     * }
     */
    public static function build(object $detail, Collection $customItems, array $gajiSplit): array
    {
        $gajian1Items = self::filterGajian($customItems, 'gajian1');
        $gajian2Items = self::filterGajian($customItems, 'gajian2');

        return [
            'gajian_akhir_bulan' => self::buildGajian1Section($detail, $gajian1Items, (float) $gajiSplit['total_gaji_akhir_bulan']),
            'gajian_tanggal_8' => self::buildGajian2Section($detail, $gajian2Items, (float) $gajiSplit['total_gaji_tanggal_8']),
        ];
    }

    /**
     * @return array{title: string, earnings: array<int, array<string, mixed>>, deductions: array<int, array<string, mixed>>, total: float}
     */
    private static function buildGajian1Section(object $detail, Collection $gajian1Items, float $total): array
    {
        $earnings = [
            self::line('Gaji Pokok', (float) ($detail->gaji_pokok ?? 0)),
            self::line('Tunjangan', (float) ($detail->tunjangan ?? 0)),
        ];
        $earnings = array_merge($earnings, self::customLines($gajian1Items, 'earn'));

        $deductions = array_merge(
            self::customLines($gajian1Items, 'deduction'),
            self::gajian1StandardDeductions($detail)
        );

        return [
            'title' => 'Gajian 1 - Akhir Bulan',
            'earnings' => self::filterVisibleLines($earnings, ['Gaji Pokok', 'Tunjangan']),
            'deductions' => self::filterVisibleLines($deductions),
            'total' => round($total),
        ];
    }

    /**
     * @return array{title: string, earnings: array<int, array<string, mixed>>, deductions: array<int, array<string, mixed>>, total: float}
     */
    private static function buildGajian2Section(object $detail, Collection $gajian2Items, float $total): array
    {
        $hariKerja = (int) ($detail->hari_kerja ?? 0);
        $totalLembur = (float) ($detail->total_lembur ?? 0);
        $nominalLembur = (float) ($detail->nominal_lembur_per_jam ?? 0);
        $nominalUangMakan = (float) ($detail->nominal_uang_makan ?? 0);

        $earnings = [
            self::line('Service Charge (By Point)', (float) ($detail->service_charge_by_point ?? 0)),
            self::line('Service Charge (Pro Rate)', (float) ($detail->service_charge_pro_rate ?? 0)),
            self::line(
                'Uang Makan',
                (float) ($detail->uang_makan ?? 0),
                $hariKerja > 0 ? "{$hariKerja} hari" : null,
                $nominalUangMakan > 0 ? '@ Rp ' . number_format($nominalUangMakan, 0, ',', '.') . '/hari' : null
            ),
            self::line(
                'Lembur',
                (float) ($detail->gaji_lembur ?? 0),
                $totalLembur > 0 ? "{$totalLembur} jam" : null,
                $nominalLembur > 0 ? '@ Rp ' . number_format($nominalLembur, 0, ',', '.') . '/jam' : null
            ),
            self::line('PH Bonus', (float) ($detail->ph_bonus ?? 0)),
        ];
        $earnings = array_merge($earnings, self::customLines($gajian2Items, 'earn'));

        $deductions = array_merge(
            [
                self::line('L & B', (float) ($detail->lb_total ?? 0)),
                self::line('Deviasi', (float) ($detail->deviasi_total ?? 0)),
                self::line('City Ledger', (float) ($detail->city_ledger_total ?? 0)),
            ],
            self::customLines($gajian2Items, 'deduction')
        );

        return [
            'title' => 'Gajian 2 - Tanggal 8',
            'earnings' => self::filterVisibleLines($earnings),
            'deductions' => self::filterVisibleLines($deductions),
            'total' => round($total),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function gajian1StandardDeductions(object $detail): array
    {
        $lines = [];

        $bpjsJkn = (float) ($detail->bpjs_jkn ?? 0);
        if ($bpjsJkn > 0) {
            $lines[] = self::line('BPJS Kesehatan (JKN)', $bpjsJkn);
        }

        $bpjsTk = (float) ($detail->bpjs_tk ?? 0);
        if ($bpjsTk > 0) {
            $lines[] = self::line('BPJS Ketenagakerjaan (TK)', $bpjsTk);
        }

        $totalTelat = (float) ($detail->total_telat ?? 0);
        $potonganTelat = (float) ($detail->potongan_telat ?? 0);
        $gajiPerMenit = (float) ($detail->gaji_per_menit ?? 500);
        $lines[] = self::line(
            'Potongan Telat',
            $potonganTelat,
            $totalTelat > 0 ? number_format($totalTelat, 0, ',', '.') . ' menit' : null,
            $totalTelat > 0 ? '@ Rp ' . number_format($gajiPerMenit, 0, ',', '.') . '/menit' : null
        );

        $totalAlpha = (int) ($detail->total_alpha ?? 0);
        $potonganAlpha = (float) ($detail->potongan_alpha ?? 0);
        $potonganUnpaidLeave = (float) ($detail->potongan_unpaid_leave ?? 0);
        $leaveData = json_decode($detail->leave_data ?? '[]', true) ?? [];
        $unpaidDays = (int) ($leaveData['unpaid_leave_days'] ?? 0);

        $qtyParts = [];
        if ($totalAlpha > 0) {
            $qtyParts[] = "Alpha: {$totalAlpha} hari";
        }
        if ($unpaidDays > 0) {
            $qtyParts[] = "Unpaid: {$unpaidDays} hari";
        }

        $notes = [];
        if ($potonganAlpha > 0) {
            $notes[] = 'Alpha: Rp ' . number_format($potonganAlpha, 0, ',', '.');
        }
        if ($potonganUnpaidLeave > 0) {
            $notes[] = 'Unpaid Leave: Rp ' . number_format($potonganUnpaidLeave, 0, ',', '.');
        }

        $lines[] = self::line(
            'Alpha & Unpaid Leave',
            $potonganAlpha + $potonganUnpaidLeave,
            $qtyParts !== [] ? implode(', ', $qtyParts) : null,
            $notes !== [] ? implode(' · ', $notes) : null
        );

        $potonganKasbon = (float) ($detail->potongan_kasbon ?? 0);
        if ($potonganKasbon > 0) {
            $kasbonNote = null;
            if (! empty($detail->kasbon_pr_number)) {
                $kasbonNote = 'PR: ' . $detail->kasbon_pr_number;
                if (! empty($detail->kasbon_cicilan_ke)) {
                    $kasbonNote .= ' · Cicilan ke-' . $detail->kasbon_cicilan_ke;
                }
            }
            $lines[] = self::line('Potongan Kasbon', $potonganKasbon, null, $kasbonNote);
        }

        return $lines;
    }

    /**
     * @return array<int, array{label: string, qty: string|null, amount: float, note: string|null}>
     */
    private static function customLines(Collection $items, string $type): array
    {
        return $items
            ->filter(function ($item) use ($type) {
                $itemType = is_object($item) ? ($item->item_type ?? null) : ($item['item_type'] ?? null);

                return $itemType === $type;
            })
            ->map(function ($item) use ($type) {
                $name = is_object($item) ? ($item->item_name ?? 'Custom Item') : ($item['item_name'] ?? 'Custom Item');
                $amount = is_object($item) ? ($item->item_amount ?? 0) : ($item['item_amount'] ?? 0);
                $description = is_object($item) ? ($item->item_description ?? null) : ($item['item_description'] ?? null);
                $prefix = $type === 'earn' ? 'Custom Earning' : 'Custom Deduction';

                return self::line("{$prefix}: {$name}", (float) $amount, null, $description ?: null);
            })
            ->values()
            ->all();
    }

    private static function filterGajian(Collection $items, string $gajian): Collection
    {
        if ($gajian === 'gajian1') {
            return $items->filter(function ($item) {
                $gajianType = is_object($item) ? ($item->gajian_type ?? null) : ($item['gajian_type'] ?? null);

                return ! isset($gajianType) || $gajianType === null || $gajianType === 'gajian1';
            });
        }

        return $items->filter(function ($item) {
            $gajianType = is_object($item) ? ($item->gajian_type ?? null) : ($item['gajian_type'] ?? null);

            return $gajianType === 'gajian2';
        });
    }

    /**
     * @param  array<int, string>  $alwaysShowLabels
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private static function filterVisibleLines(array $lines, array $alwaysShowLabels = []): array
    {
        return array_values(array_filter($lines, function (array $line) use ($alwaysShowLabels) {
            if (in_array($line['label'], $alwaysShowLabels, true)) {
                return true;
            }

            return (float) ($line['amount'] ?? 0) !== 0.0;
        }));
    }

    /**
     * @return array{label: string, qty: string|null, amount: float, note: string|null}
     */
    private static function line(string $label, float $amount, ?string $qty = null, ?string $note = null): array
    {
        return [
            'label' => $label,
            'qty' => $qty,
            'amount' => round($amount),
            'note' => $note,
        ];
    }
}
