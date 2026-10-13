<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ContraBonPoVarianceReportExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    /** @param Collection<int, array<string, mixed>> $rows */
    public function __construct(
        protected Collection $rows,
        protected array $summary = [],
        protected array $filters = [],
    ) {}

    public function title(): string
    {
        return 'Selisih PO vs CB';
    }

    public function array(): array
    {
        $out = [
            ['Report Selisih Harga/Discount PO Foods vs Contra Bon'],
            [
                'Periode',
                ($this->filters['date_from'] ?? '-') . ' s/d ' . ($this->filters['date_to'] ?? '-'),
            ],
            ['Status CB', $this->filters['status'] ?: 'Semua'],
            ['Jenis Selisih', $this->filters['diff_type'] ?? 'any'],
            ['Hanya yang beda', !empty($this->filters['only_diff']) ? 'Ya' : 'Tidak'],
            ['Generated', now()->format('Y-m-d H:i:s')],
            [],
            ['Metrik', 'Nilai'],
            ['Baris dibanding', $this->summary['total_compared_lines'] ?? 0],
            ['Ada selisih', $this->summary['any_diff_count'] ?? 0],
            ['Selisih harga/unit', $this->summary['price_diff_count'] ?? 0],
            ['Selisih discount', $this->summary['discount_diff_count'] ?? 0],
            [],
            [
                'No',
                'Tanggal CB',
                'No Contra Bon',
                'Status CB',
                'No PO',
                'Tanggal PO',
                'Supplier',
                'Item',
                'SKU',
                'Unit',
                'Qty CB',
                'Qty PO',
                'Harga PO',
                'Harga CB',
                'Δ Harga',
                'Disc% PO',
                'Disc% CB',
                'Δ Disc%',
                'Disc Rp PO',
                'Disc Rp CB',
                'Δ Disc Rp',
                'Expected Line (PO)',
                'Total Line CB',
                'Δ Line',
                'Flag',
            ],
        ];

        $no = 1;
        foreach ($this->rows as $row) {
            $out[] = [
                $no++,
                $this->formatDate($row['cb_date'] ?? null),
                $row['cb_number'] ?? '',
                $row['cb_status'] ?? '',
                $row['po_number'] ?? '',
                $this->formatDate($row['po_date'] ?? null),
                $row['supplier_name'] ?? '',
                $row['item_name'] ?? '',
                $row['item_sku'] ?? '',
                $row['unit_name'] ?? '',
                (float) ($row['cb_qty'] ?? 0),
                (float) ($row['po_qty'] ?? 0),
                (float) ($row['po_price'] ?? 0),
                (float) ($row['cb_price'] ?? 0),
                (float) ($row['price_diff'] ?? 0),
                (float) ($row['po_discount_percent'] ?? 0),
                (float) ($row['cb_discount_percent'] ?? 0),
                (float) ($row['discount_percent_diff'] ?? 0),
                (float) ($row['po_discount_amount'] ?? 0),
                (float) ($row['cb_discount_amount'] ?? 0),
                (float) ($row['discount_amount_diff'] ?? 0),
                (float) ($row['po_expected_line_total'] ?? 0),
                (float) ($row['cb_line_total'] ?? 0),
                (float) ($row['line_total_diff'] ?? 0),
                implode(', ', $row['diff_flags'] ?? []),
            ];
        }

        return $out;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A8:B8')->getFont()->setBold(true);

        $headerRow = 14;
        $sheet->getStyle("A{$headerRow}:Y{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);

        $last = $headerRow + $this->rows->count();
        if ($last >= $headerRow + 1) {
            $sheet->getStyle("K" . ($headerRow + 1) . ":X{$last}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 12,
            'C' => 18,
            'D' => 12,
            'E' => 16,
            'F' => 12,
            'G' => 24,
            'H' => 28,
            'I' => 16,
            'J' => 10,
            'K' => 10,
            'L' => 10,
            'M' => 14,
            'N' => 14,
            'O' => 12,
            'P' => 10,
            'Q' => 10,
            'R' => 10,
            'S' => 14,
            'T' => 14,
            'U' => 12,
            'V' => 16,
            'W' => 14,
            'X' => 12,
            'Y' => 14,
        ];
    }

    protected function formatDate($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}
