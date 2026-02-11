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

class CogsSheetExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    public function __construct(protected array $cogsRows)
    {
    }

    public function collection()
    {
        return collect($this->cogsRows);
    }

    public function headings(): array
    {
        return [
            'No',
            'Outlet',
            'COGS',
            'Category Cost',
            'Meal Employees',
            'COGS Pembanding',
            'Deviasi',
            'Toleransi 2%',
            '% COGS Pembanding',
            '% COGS Actual Before Disc',
            '% COGS Actual After Disc',
            '% COGS Foods',
            '% Deviasi',
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
            (float) ($row['cogs'] ?? 0),
            (float) ($row['category_cost'] ?? 0),
            (float) ($row['meal_employees'] ?? 0),
            (float) ($row['cogs_pembanding'] ?? 0),
            (float) ($row['deviasi'] ?? 0),
            (float) ($row['toleransi_2_pct'] ?? 0),
            $this->pct($row['pct_cogs_pembanding'] ?? null),
            $this->pct($row['pct_cogs_actual_before_disc'] ?? null),
            $this->pct($row['pct_cogs_actual_after_disc'] ?? null),
            $this->pct($row['pct_cogs_foods'] ?? null),
            $this->pct($row['pct_deviasi'] ?? null),
            $this->pct($row['pct_category_cost'] ?? null),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = 'N';
        $lastRow = count($this->cogsRows) + 1;
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
            'C' => 14,
            'D' => 16,
            'E' => 16,
            'F' => 18,
            'G' => 14,
            'H' => 14,
            'I' => 20,
            'J' => 24,
            'K' => 24,
            'L' => 14,
            'M' => 12,
            'N' => 18,
        ];
    }
}
