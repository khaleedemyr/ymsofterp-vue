<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemEngineeringSheetExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    private $items;
    public function __construct($items)
    {
        $this->items = $items;
    }
    public function collection()
    {
        return collect($this->items);
    }
    public function headings(): array
    {
        return ['No', 'Category', 'Nama Item', 'Qty Terjual', 'Harga Jual', 'Subtotal'];
    }
    public function map($item): array
    {
        static $no = 0;
        $no++;
        return [
            $no,
            $item->category_name ?? 'Uncategorized',
            $item->item_name,
            $item->qty_terjual,
            $item->harga_jual,
            $item->subtotal,
        ];
    }
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:F1')->applyFromArray([
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
        foreach (range('A', 'F') as $col) {
            $widths[$col] = 22;
        }
        return $widths;
    }
} 