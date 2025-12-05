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

class PoGrReportExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
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
            'GR Number',
            'Receive Date',
            'PO Number',
            'PO Date',
            'Supplier',
            'Item',
            'Category',
            'Qty PO',
            'Qty Received',
            'Unit',
            'PO Price',
            'Previous Price',
            'Price Change',
            'Price Change %',
            'PO Creator',
            'Received By'
        ];
    }

    public function title(): string
    {
        return 'PO GR Report';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // GR Number
            'B' => 15, // Receive Date
            'C' => 18, // PO Number
            'D' => 15, // PO Date
            'E' => 25, // Supplier
            'F' => 30, // Item
            'G' => 20, // Category
            'H' => 12, // Qty PO
            'I' => 15, // Qty Received
            'J' => 12, // Unit
            'K' => 15, // PO Price
            'L' => 15, // Previous Price
            'M' => 15, // Price Change
            'N' => 15, // Price Change %
            'O' => 20, // PO Creator
            'P' => 20, // Received By
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 2; // +2 for header and title
        $lastColumn = 'P';

        // Title row
        $sheet->insertNewRowBefore(1, 2);
        $sheet->mergeCells('A1:' . $lastColumn . '1');
        $sheet->setCellValue('A1', 'PO GR REPORT');
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
        $dateRange = '';
        if ($this->dateFrom && $this->dateTo) {
            $dateRange = 'Period: ' . date('d M Y', strtotime($this->dateFrom)) . ' - ' . date('d M Y', strtotime($this->dateTo));
        } elseif ($this->dateFrom) {
            $dateRange = 'From: ' . date('d M Y', strtotime($this->dateFrom));
        } elseif ($this->dateTo) {
            $dateRange = 'To: ' . date('d M Y', strtotime($this->dateTo));
        }
        $sheet->setCellValue('A2', $dateRange);
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

        if ($dataEndRow >= $dataStartRow) {
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

            // Number formatting for price columns (K, L, M)
            $sheet->getStyle('K' . $dataStartRow . ':M' . $dataEndRow)->getNumberFormat()
                ->setFormatCode('#,##0.00');

            // Text alignment
            $sheet->getStyle('A' . $dataStartRow . ':J' . $dataEndRow)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                ],
            ]);

            $sheet->getStyle('K' . $dataStartRow . ':M' . $dataEndRow)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                ],
            ]);

            $sheet->getStyle('N' . $dataStartRow . ':' . $lastColumn . $dataEndRow)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                ],
            ]);
        }

        return $sheet;
    }
}

