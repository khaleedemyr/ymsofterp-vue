<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyOutletRevenueExport implements FromArray, WithStyles, ShouldAutoSize, WithEvents
{
    /** @var array<string, mixed> */
    private array $payload;

    private int $firstDataRow = 5;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = [$this->payload['title'] ?? 'Daily Outlet Revenue Report'];
        $rows[] = [$this->payload['subtitle'] ?? ''];

        $rows[] = [
            'DATE', 'DAY', 'LUNCH', '', '', '', 'DINNER', '', '', '', 'TOTAL FB REVENUE', '', '', '',
        ];
        $rows[] = [
            '', '',
            'COVER', 'REVENUE', 'A/C', 'DISC',
            'COVER', 'REVENUE', 'A/C', 'DISC',
            'COVER', 'REVENUE', 'A/C', 'DISC',
        ];

        $dailyData = $this->payload['daily_data'] ?? [];
        foreach ($dailyData as $date => $dayData) {
            $rows[] = [
                $this->formatDateLabel((string) $date),
                $dayData['day_name'] ?? '',
                (float) ($dayData['lunch']['cover'] ?? 0),
                (float) ($dayData['lunch']['revenue'] ?? 0),
                (float) ($dayData['lunch']['avg_check'] ?? 0),
                (float) ($dayData['lunch']['disc'] ?? 0),
                (float) ($dayData['dinner']['cover'] ?? 0),
                (float) ($dayData['dinner']['revenue'] ?? 0),
                (float) ($dayData['dinner']['avg_check'] ?? 0),
                (float) ($dayData['dinner']['disc'] ?? 0),
                (float) ($dayData['total']['cover'] ?? 0),
                (float) ($dayData['total']['revenue'] ?? 0),
                (float) ($dayData['total']['avg_check'] ?? 0),
                (float) ($dayData['total']['disc'] ?? 0),
            ];
        }

        $summary = $this->payload['summary'] ?? [];
        $rows[] = [
            'MONTH TO DATE', '',
            (float) ($summary['lunch']['cover'] ?? 0),
            (float) ($summary['lunch']['revenue'] ?? 0),
            (float) ($summary['lunch']['avg_check'] ?? 0),
            (float) ($summary['lunch']['disc'] ?? 0),
            (float) ($summary['dinner']['cover'] ?? 0),
            (float) ($summary['dinner']['revenue'] ?? 0),
            (float) ($summary['dinner']['avg_check'] ?? 0),
            (float) ($summary['dinner']['disc'] ?? 0),
            (float) ($summary['total']['cover'] ?? 0),
            (float) ($summary['total']['revenue'] ?? 0),
            (float) ($summary['total']['avg_check'] ?? 0),
            (float) ($summary['total']['disc'] ?? 0),
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['size' => 11, 'italic' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastCol = 'N';

                $sheet->mergeCells('A1:'.$lastCol.'1');
                $sheet->mergeCells('A2:'.$lastCol.'2');
                $sheet->mergeCells('A3:A4');
                $sheet->mergeCells('B3:B4');
                $sheet->mergeCells('C3:F3');
                $sheet->mergeCells('G3:J3');
                $sheet->mergeCells('K3:N3');

                $sheet->getStyle('A3:N4')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
                ]);

                $sheet->getStyle('A3:B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
                $sheet->getStyle('C3:F4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('059669');
                $sheet->getStyle('G3:J4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D97706');
                $sheet->getStyle('K3:N4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');

                $dailyData = $this->payload['daily_data'] ?? [];
                $rowIndex = $this->firstDataRow;
                foreach ($dailyData as $dayData) {
                    $isWeekend = ! empty($dayData['is_weekend']);
                    $isHoliday = ! empty($dayData['is_holiday']);
                    $weekendFill = ($isWeekend || $isHoliday) ? 'FFEDD5' : null;

                    if ($weekendFill) {
                        $sheet->getStyle("A{$rowIndex}:N{$rowIndex}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($weekendFill);
                    } else {
                        $sheet->getStyle("C{$rowIndex}:F{$rowIndex}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ECFDF5');
                        $sheet->getStyle("G{$rowIndex}:J{$rowIndex}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFBEB');
                        $sheet->getStyle("K{$rowIndex}:N{$rowIndex}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
                    }

                    $rowIndex++;
                }

                $sheet->getStyle("A{$lastRow}:N{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
                ]);

                $sheet->getStyle("A{$this->firstDataRow}:N{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
                ]);

                for ($r = $this->firstDataRow; $r <= $lastRow; $r++) {
                    foreach (['D', 'E', 'F', 'H', 'I', 'J', 'L', 'M', 'N'] as $col) {
                        $sheet->getStyle($col.$r)->getNumberFormat()->setFormatCode('#,##0');
                    }
                    $sheet->getStyle('A'.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('B'.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    foreach (['C', 'G', 'K'] as $col) {
                        $sheet->getStyle($col.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                    foreach (['D', 'E', 'F', 'H', 'I', 'J', 'L', 'M', 'N'] as $col) {
                        $sheet->getStyle($col.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                }

                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            },
        ];
    }

    private function formatDateLabel(string $dateStr): string
    {
        try {
            $date = new \DateTime($dateStr);

            return $date->format('j-M-y');
        } catch (\Throwable) {
            return $dateStr;
        }
    }
}
