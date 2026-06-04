<?php

namespace App\Exports\MampReport;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletSummarySheetExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        private readonly array $report
    ) {}

    public function array(): array
    {
        $categoryName = strtoupper($this->report['category']['name'] ?? 'CATEGORY');
        $outletSummary = $this->report['outlet_summary'] ?? ['rows' => [], 'total' => 0];
        $rows = [];

        $rows[] = ['REKAP PER OUTLET — ' . $categoryName . ' — ' . ($this->report['period']['label'] ?? '')];
        $rows[] = [];
        $rows[] = ['Outlet', 'Total'];

        foreach ($outletSummary['rows'] ?? [] as $row) {
            $rows[] = [
                $row['outlet'] ?? '-',
                $this->formatAmountForExcel($row['total'] ?? 0),
            ];
        }

        $rows[] = [
            '',
            $this->formatAmountForExcel($outletSummary['total'] ?? 0),
        ];

        return $rows;
    }

    public function title(): string
    {
        return 'Rekap per Outlet';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 18,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $outletRows = $this->report['outlet_summary']['rows'] ?? [];
        $headerRow = 3;
        $dataStart = 4;
        $dataEnd = $dataStart + count($outletRows) - 1;
        $totalRow = $dataEnd + 1;

        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("A{$headerRow}:B{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        if ($dataEnd >= $dataStart) {
            $sheet->getStyle("A{$dataStart}:B{$dataEnd}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
            $sheet->getStyle("B{$dataStart}:B{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0');
        }

        if ($totalRow >= $dataStart) {
            $sheet->getStyle("A{$totalRow}:B{$totalRow}")->applyFromArray([
                'font' => ['bold' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
            $sheet->getStyle("B{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        }

        return [];
    }

    private function formatAmountForExcel($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
