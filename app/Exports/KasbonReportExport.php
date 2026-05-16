<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KasbonReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Collection $rows
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'PR',
            'Karyawan',
            'Outlet',
            'Total',
            'Termin',
            'Sudah bayar',
            'Per termin',
            'Terakhir dicatat',
            'Status tracker',
            'Approve PR',
            'Approve transfer (tanggal)',
            'No. pembayaran transfer',
            'Status pembayaran transfer',
        ];
    }

    /**
     * @param  object  $row
     */
    public function map($row): array
    {
        $termin = max(1, (int) ($row->termin_total ?? 1));
        $paid = (int) ($row->paid_installments ?? 0);
        $code = $row->tracker_status ?? '';
        $tracker = match ($code) {
            'waiting_transfer' => 'menunggu transfer',
            'completed' => 'selesai',
            default => 'aktif',
        };

        return [
            $row->pr_number ?? '',
            $row->employee_name ?? '',
            $row->outlet_name ?? '',
            (float) ($row->total_amount ?? 0),
            $termin,
            $paid . '/' . $termin,
            (float) ($row->installment_amount ?? 0),
            $this->fmtDate($row->last_installment_at ?? null),
            $tracker,
            $this->formatPrApprovalSteps($row),
            $this->fmtDateTime($row->nfp_transfer_approved_at ?? null),
            $row->nfp_payment_number ?? '',
            $row->nfp_payment_status ?? '',
        ];
    }

    private function fmtDate(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        try {
            return Carbon::parse($v)->timezone(config('app.timezone'))->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    private function fmtDateTime(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        try {
            return Carbon::parse($v)->timezone(config('app.timezone'))->format('d/m/Y H:i');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param  object  $row
     */
    private function formatPrApprovalSteps($row): string
    {
        $steps = $row->pr_approval_steps ?? null;
        if (is_array($steps) && count($steps) > 0) {
            $lines = [];
            foreach ($steps as $step) {
                $name = is_array($step) ? ($step['approver_name'] ?? '') : ($step->approver_name ?? '');
                $level = is_array($step) ? ($step['level'] ?? '') : ($step->level ?? '');
                $at = is_array($step) ? ($step['approved_at'] ?? null) : ($step->approved_at ?? null);
                $lines[] = 'Lv ' . $level . ': ' . $name . ' @ ' . $this->fmtDateTime($at);
            }

            return implode(' | ', $lines);
        }

        return $this->fmtDateTime($row->approved_at ?? null);
    }
}
