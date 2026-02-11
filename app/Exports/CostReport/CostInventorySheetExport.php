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

class CostInventorySheetExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    public function __construct(protected array $reportRows)
    {
    }

    public function collection()
    {
        return collect($this->reportRows);
    }

    public function headings(): array
    {
        return [
            'No',
            'Outlet',
            'Begin Inventory (Total MAC)',
            'Official Cost',
            'Cost RND',
            'Outlet Transfer',
            'Total Barang Tersedia',
            'Ending Inventory',
            'COGS Aktual',
            'Sales Before Discount',
            'Discount',
            'Sales After Discount',
            '% Discount vs Sales',
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;
        $pct = isset($row['pct_discount']) && $row['pct_discount'] !== null
            ? number_format((float) $row['pct_discount'], 2, '.', '') . '%'
            : '-';
        return [
            $no,
            $row['outlet_name'] ?? '',
            (float) ($row['total_begin_mac'] ?? 0),
            (float) ($row['official_cost'] ?? 0),
            (float) ($row['cost_rnd'] ?? 0),
            (float) ($row['outlet_transfer'] ?? 0),
            (float) ($row['total_barang_tersedia'] ?? 0),
            (float) ($row['ending_inventory'] ?? 0),
            (float) ($row['cogs_aktual'] ?? 0),
            (float) ($row['sales_before_discount'] ?? 0),
            (float) ($row['discount'] ?? 0),
            (float) ($row['sales_after_discount'] ?? 0),
            $pct,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = 'M';
        $lastRow = count($this->reportRows) + 1;
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
            'C' => 20,
            'D' => 14,
            'E' => 12,
            'F' => 16,
            'G' => 20,
            'H' => 18,
            'I' => 14,
            'J' => 20,
            'K' => 12,
            'L' => 20,
            'M' => 18,
        ];
    }
}
