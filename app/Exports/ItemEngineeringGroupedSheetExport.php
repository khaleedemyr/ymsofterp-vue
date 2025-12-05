<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemEngineeringGroupedSheetExport implements FromArray, WithStyles, WithColumnWidths
{
    private $items;
    public function __construct($items)
    {
        $this->items = $items;
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;
        $grouped = collect($this->items)->groupBy(function($item) {
            return $item->category_name ?? 'Uncategorized';
        });
        foreach ($grouped as $category => $items) {
            $categoryTotal = $items->sum('subtotal');
            // Judul kategori
            $rows[] = [
                '', // No
                $category . '   ' . 'Rp ' . number_format($categoryTotal, 0, ',', '.'),
                '', '', '',
            ];
            // Header kolom
            $rows[] = ['No', 'Nama Item', 'Qty Terjual', 'Harga Jual', 'Subtotal'];
            // List item
            foreach ($items as $item) {
                $rows[] = [
                    $no++, 
                    $item->item_name, 
                    $item->qty_terjual, 
                    $item->harga_jual, 
                    $item->subtotal
                ];
            }
            // Baris kosong antar kategori
            $rows[] = ['', '', '', '', ''];
        }
        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $rowNum = 1;
        $data = $this->array();
        foreach ($data as $row) {
            // Jika baris judul kategori (kolom 1 kosong, kolom 2 ada nama kategori)
            if (empty($row[0]) && !empty($row[1]) && strpos($row[1], 'Rp') !== false) {
                $sheet->getStyle('A'.$rowNum.':E'.$rowNum)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '2563eb']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'e0eaff']
                    ],
                ]);
            }
            // Jika baris header kolom
            if ($row[0] === 'No' && $row[1] === 'Nama Item') {
                $sheet->getStyle('A'.$rowNum.':E'.$rowNum)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2563eb']
                    ],
                ]);
            }
            $rowNum++;
        }
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highestCol . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 35,
            'C' => 15,
            'D' => 18,
            'E' => 18,
        ];
    }
} 