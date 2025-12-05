<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ModifierEngineeringSheetExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    private $modifiers;
    public function __construct($modifiers)
    {
        $this->modifiers = $modifiers;
    }
    public function collection()
    {
        return collect($this->modifiers);
    }
    public function headings(): array
    {
        return ['No', 'Nama Modifier', 'Qty Terjual'];
    }
    public function map($mod): array
    {
        static $no = 0;
        $no++;
        return [
            $no,
            $mod['name'],
            $mod['qty'],
        ];
    }
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563eb']
            ],
        ]);
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highestCol . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        return [];
    }
    public function columnWidths(): array
    {
        $widths = [];
        foreach (range('A', 'C') as $col) {
            $widths[$col] = 22;
        }
        return $widths;
    }
} 