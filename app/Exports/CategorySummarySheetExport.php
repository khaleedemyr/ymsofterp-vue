<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CategorySummarySheetExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    private $itemsByCategory;
    
    public function __construct($itemsByCategory)
    {
        $this->itemsByCategory = $itemsByCategory;
    }
    
    public function collection()
    {
        return collect($this->itemsByCategory);
    }
    
    public function headings(): array
    {
        return ['No', 'Category', 'Total Qty', 'Total Sales', 'Item Count'];
    }
    
    public function map($item): array
    {
        static $no = 0;
        $no++;
        return [
            $no,
            $item['category_name'],
            $item['total_qty'],
            $item['total_subtotal'],
            $item['item_count'],
        ];
    }
    
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:E1')->applyFromArray([
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
        return [
            'A' => 10,
            'B' => 30,
            'C' => 15,
            'D' => 20,
            'E' => 15,
        ];
    }
} 