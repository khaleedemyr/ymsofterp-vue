<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class OpexByCategoryExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;
    protected $dateFrom;
    protected $dateTo;

    public function __construct($data, $dateFrom, $dateTo)
    {
        $this->data = $data;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Category Name',
            'Division',
            'Subcategory',
            'RNF Total',
            'PR Total',
            'NFP Total',
            'Category Total'
        ];
    }

    public function title(): string
    {
        return 'OPEX By Category';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 20,
            'C' => 20,
            'D' => 15,
            'E' => 15,
            'F' => 15,
            'G' => 18,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 2; // +2 for header and title
        $lastColumn = 'G';

        // Title row
        $sheet->insertNewRowBefore(1, 2);
        $sheet->mergeCells('A1:' . $lastColumn . '1');
        $sheet->setCellValue('A1', 'OPEX BY CATEGORY REPORT');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Date range row
        $sheet->mergeCells('A2:' . $lastColumn . '2');
        $sheet->setCellValue('A2', 'Period: ' . date('d M Y', strtotime($this->dateFrom)) . ' - ' . date('d M Y', strtotime($this->dateTo)));
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'size' => 12,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Header styling (row 3)
        $sheet->getStyle('A3:' . $lastColumn . '3')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // Data rows
        $dataStartRow = 4;
        $dataEndRow = $dataStartRow + count($this->data) - 1;

        $sheet->getStyle('A' . $dataStartRow . ':' . $lastColumn . $dataEndRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Number formatting for amount columns (D, E, F, G)
        $sheet->getStyle('D' . $dataStartRow . ':' . $lastColumn . $dataEndRow)->getNumberFormat()
            ->setFormatCode('#,##0');

        // Grand total row styling (last row)
        $grandTotalRow = $dataEndRow;
        $sheet->getStyle('A' . $grandTotalRow . ':' . $lastColumn . $grandTotalRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2'],
            ],
        ]);

        // Text alignment
        $sheet->getStyle('A' . $dataStartRow . ':C' . $dataEndRow)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
        ]);

        $sheet->getStyle('D' . $dataStartRow . ':' . $lastColumn . $dataEndRow)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
            ],
        ]);

        return $sheet;
    }
}

