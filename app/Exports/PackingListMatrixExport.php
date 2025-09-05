<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PackingListMatrixExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected $data;
    protected $outlets;

    public function __construct($data, $outlets)
    {
        $this->data = $data;
        $this->outlets = $outlets;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        $headings = ['Warehouse Division', 'Nama Items', 'Unit'];
        
        foreach ($this->outlets as $outlet) {
            $headings[] = $outlet->nama_outlet;
        }
        
        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        // Calculate last column properly (3 fixed columns + outlet columns)
        $totalColumns = 3 + count($this->outlets);
        $lastColumn = $this->getColumnLetter($totalColumns);
        $lastRow = count($this->data) + 1;

        // Header styling
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '000000'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEF08A'], // Yellow background
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Data rows styling
        if ($lastRow > 1) {
            $sheet->getStyle('A2:' . $lastColumn . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Left columns (Warehouse Division, Item Name and Unit) - left aligned
            $sheet->getStyle('A2:C' . $lastRow)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                ],
            ]);

            // Outlet columns - right aligned
            $sheet->getStyle('D2:' . $lastColumn . $lastRow)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                ],
            ]);
        }

        // Freeze first three columns
        $sheet->freezePane('D2');
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 25, // Warehouse Division
            'B' => 30, // Nama Items
            'C' => 15, // Unit
        ];
        
        // Set width for outlet columns
        $column = 'D';
        foreach ($this->outlets as $outlet) {
            $widths[$column] = 20;
            $column++;
        }
        
        return $widths;
    }

    private function getColumnLetter($columnNumber)
    {
        $letter = '';
        while ($columnNumber > 0) {
            $columnNumber--;
            $letter = chr(65 + ($columnNumber % 26)) . $letter;
            $columnNumber = intval($columnNumber / 26);
        }
        return $letter;
    }
}
