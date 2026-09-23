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
            'Ending Inventory Weekly',
            'Ending Inventory MTD',
            'COGS Aktual Weekly',
            'COGS Aktual MTD',
            'Sales Before Discount',
            'Discount',
            'Sales After Discount',
            '% Discount vs Sales',
            'COGS Before Weekly',
            'COGS After Weekly',
            'COGS Before MTD',
            'COGS After MTD',
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;
        $pct = isset($row['pct_discount']) && $row['pct_discount'] !== null
            ? number_format((float) $row['pct_discount'], 2, '.', '') . '%'
            : '-';
        $fmtPct = static function ($val) {
            return isset($val) && $val !== null
                ? number_format((float) $val, 2, '.', '') . '%'
                : '-';
        };

        return [
            $no,
            $row['outlet_name'] ?? '',
            (float) ($row['total_begin_mac'] ?? 0),
            (float) ($row['official_cost'] ?? 0),
            (float) ($row['cost_rnd'] ?? 0),
            (float) ($row['outlet_transfer'] ?? 0),
            (float) ($row['total_barang_tersedia'] ?? 0),
            (float) ($row['ending_inventory_weekly'] ?? $row['ending_inventory'] ?? 0),
            (float) ($row['ending_inventory_mtd'] ?? 0),
            (float) ($row['cogs_aktual'] ?? 0),
            (float) ($row['cogs_aktual_mtd'] ?? 0),
            (float) ($row['sales_before_discount'] ?? 0),
            (float) ($row['discount'] ?? 0),
            (float) ($row['sales_after_discount'] ?? 0),
            $pct,
            $fmtPct($row['cogs_before'] ?? null),
            $fmtPct($row['cogs_after'] ?? null),
            $fmtPct($row['cogs_before_mtd'] ?? null),
            $fmtPct($row['cogs_after_mtd'] ?? null),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = 'S';
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
            'H' => 22,
            'I' => 22,
            'J' => 18,
            'K' => 18,
            'L' => 20,
            'M' => 12,
            'N' => 20,
            'O' => 18,
            'P' => 16,
            'Q' => 16,
            'R' => 16,
            'S' => 16,
        ];
    }
}
