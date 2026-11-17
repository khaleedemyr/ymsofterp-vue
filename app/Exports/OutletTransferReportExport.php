<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletTransferReportExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        private array $rows,
        private float $grandTotalNilai = 0
    ) {
    }

    public function title(): string
    {
        return 'Pindah Outlet';
    }

    public function headings(): array
    {
        return [
            'No',
            'No. Transfer',
            'Tanggal',
            'Outlet Asal',
            'WH Asal',
            'Outlet Tujuan',
            'WH Tujuan',
            'Item',
            'Qty',
            'Unit',
            'Harga (MAC)',
            'Nilai',
            'Status',
        ];
    }

    public function array(): array
    {
        $rows = $this->rows;
        if (! empty($rows)) {
            $rows[] = [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'TOTAL NILAI (MAC)',
                '',
                '',
                '',
                $this->grandTotalNilai,
                '',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = max(1, count($this->rows) + 1 + (! empty($this->rows) ? 1 : 0));
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("I2:L{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle("A{$lastRow}:M{$lastRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            ]);
        }

        return [];
    }
}
