<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class UnpickedFloorOrdersExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data ?? [];
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Outlet',
            'Warehouse Outlet',
            'No. Floor Order',
            'Pemohon',
            'Warehouse Division',
            'Nama Item',
            'Qty',
            'Unit',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Hanya style header; styling range dilakukan minimal untuk hindari error versi library
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, // Tanggal
            'B' => 20, // Outlet
            'C' => 20, // Warehouse Outlet
            'D' => 18, // No. Floor Order
            'E' => 20, // Pemohon
            'F' => 20, // Warehouse Division
            'G' => 30, // Nama Item
            'H' => 10, // Qty
            'I' => 10, // Unit
        ];
    }
} 