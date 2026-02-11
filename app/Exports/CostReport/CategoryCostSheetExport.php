<?php

namespace App\Exports\CostReport;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CategoryCostSheetExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    public function __construct(protected array $categoryCostRows)
    {
    }

    public function collection()
    {
        return collect($this->categoryCostRows);
    }

    public function headings(): array
    {
        return [
            'No',
            'Outlet',
            'Guest Supplies',
            '% Guest Supplies',
            'Spoilage',
            '% Spoilage',
            'Waste',
            '% Waste',
            'Non Commodity',
            '% Non Commodity',
            'Category Cost',
            '% Category Cost',
        ];
    }

    private function pct($val): string
    {
        return $val !== null && $val !== '' ? number_format((float) $val, 2, '.', '') . '%' : '-';
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;
        return [
            $no,
            $row['outlet_name'] ?? '',
            (float) ($row['guest_supplies'] ?? 0),
            $this->pct($row['pct_guest_supplies'] ?? null),
            (float) ($row['spoilage'] ?? 0),
            $this->pct($row['pct_spoilage'] ?? null),
            (float) ($row['waste'] ?? 0),
            $this->pct($row['pct_waste'] ?? null),
            (float) ($row['non_commodity'] ?? 0),
            $this->pct($row['pct_non_commodity'] ?? null),
            (float) ($row['category_cost'] ?? 0),
            $this->pct($row['pct_category_cost'] ?? null),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = 'L';
        $lastRow = count($this->categoryCostRows) + 1;
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ],
            'A2:' . $lastCol . $lastRow => [
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
            'C2:' . $lastCol . $lastRow => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 22,
            'C' => 16,
            'D' => 18,
            'E' => 14,
            'F' => 14,
            'G' => 14,
            'H' => 12,
            'I' => 16,
            'J' => 18,
            'K' => 16,
            'L' => 18,
        ];
    }
}
