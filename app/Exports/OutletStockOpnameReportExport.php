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

class OutletStockOpnameReportExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private array $rows)
    {
    }

    public function title(): string
    {
        return 'Stock Opname Report';
    }

    public function headings(): array
    {
        return [
            'No',
            'No. Opname',
            'Tanggal',
            'Outlet',
            'Warehouse',
            'Status',
            'Item Code',
            'Item Name',
            'Category',
            'Qty System (S)',
            'Qty System (M)',
            'Qty System (L)',
            'Qty Physical (S)',
            'Qty Physical (M)',
            'Qty Physical (L)',
            'Selisih (S)',
            'Selisih (M)',
            'Selisih (L)',
            'Last Cost',
            'Value Adjustment',
            'Reason',
        ];
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = max(1, count($this->rows) + 1);
        $sheet->getStyle('A1:U1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("J2:T{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        }

        return [];
    }
}
