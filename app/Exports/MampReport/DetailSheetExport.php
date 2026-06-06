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

class DetailSheetExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        private readonly array $report
    ) {}

    public function array(): array
    {
        $categoryName = strtoupper($this->report['category']['name'] ?? 'CATEGORY');
        $rows = [];

        $rows[] = ['MAMP REPORT — ' . $categoryName . ' — ' . ($this->report['period']['label'] ?? '')];
        $rows[] = [];
        $rows[] = ['NO.', 'TANGGAL', 'OUTLET', 'REFERENSI', $categoryName, 'Db', 'Cr'];

        foreach ($this->report['rows'] as $row) {
            $rows[] = [
                $row['no'],
                $row['date_label'] ?? '',
                $row['outlet'] ?? '',
                $row['reference'] ?? '',
                $row['description'] ?? '',
                $this->formatAmountForExcel($row['debit'] ?? null),
                $this->formatAmountForExcel($row['credit'] ?? null),
            ];
        }

        $summary = $this->report['summary'] ?? [];
        $rows[] = [];
        $rows[] = ['', '', '', '', 'TOTAL', $summary['total_debit'] ?? 0, $summary['total_credit'] ?? 0];
        $rows[] = ['', '', '', '', 'SISA SALDO', $summary['ending_balance'] ?? 0, ''];

        return $rows;
    }

    public function title(): string
    {
        return 'Detail Transaksi';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 14,
            'C' => 18,
            'D' => 36,
            'E' => 40,
            'F' => 16,
            'G' => 16,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $headerRow = 3;
        $dataStart = 4;
        $dataEnd = $dataStart + count($this->report['rows']) - 1;
        $totalRow = $dataEnd + 2;

        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        if ($dataEnd >= $dataStart) {
            $sheet->getStyle("A{$dataStart}:G{$dataEnd}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
            $sheet->getStyle("F{$dataStart}:G{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0');
        }

        $sheet->getStyle("A{$totalRow}:G" . ($totalRow + 1))->applyFromArray([
            'font' => ['bold' => true],
        ]);
        $sheet->getStyle("F{$totalRow}:G" . ($totalRow + 1))->getNumberFormat()->setFormatCode('#,##0');

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
