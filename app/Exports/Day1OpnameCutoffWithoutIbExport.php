<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Day1OpnameCutoffWithoutIbExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    public function __construct(protected array $rows, protected string $bulan)
    {
    }

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return [
            'No',
            'Bulan',
            'Outlet ID',
            'Outlet',
            'Warehouse',
            'SKU',
            'Item',
            'Category',
            'Unit (Small)',
            'Stock Qty (Small)',
            'Ada Kartu di Bulan',
            'Kartu Pertama (Tgl)',
            'Kartu Pertama (Tipe)',
            'Punya IB Tgl 1',
            'Punya Opname Tgl 1',
            'Inventory Item ID',
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $this->bulan,
            $row['outlet_id'] ?? '',
            $row['outlet_name'] ?? '',
            $row['warehouse_name'] ?? '',
            $row['item_sku'] ?? '',
            $row['item_name'] ?? '',
            $row['category_name'] ?? '',
            $row['unit_name'] ?? '',
            (float) ($row['stock_qty_small'] ?? 0),
            !empty($row['has_card_in_month']) ? 'Ya' : 'Tidak',
            $row['first_card_date'] ?? '',
            $row['first_card_reference'] ?? '',
            'Tidak',
            'Tidak',
            $row['inventory_item_id'] ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = 'P';
        $lastRow = max(1, count($this->rows) + 1);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'B45309'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            'A2:'.$lastCol.$lastRow => [
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
            'J2:J'.$lastRow => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 10,
            'C' => 10,
            'D' => 28,
            'E' => 14,
            'F' => 18,
            'G' => 32,
            'H' => 18,
            'I' => 12,
            'J' => 14,
            'K' => 14,
            'L' => 16,
            'M' => 18,
            'N' => 14,
            'O' => 16,
            'P' => 14,
        ];
    }
}
