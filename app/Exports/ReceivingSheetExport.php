<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReceivingSheetExport implements FromArray, WithStyles, ShouldAutoSize, WithEvents
{
    /** @var list<array<int, mixed>> */
    private array $rows;

    /** @var list<string> */
    private array $headings;

    /** @var list<array{key: string, name: string}> */
    private array $warehouseColumns;

    /** @var list<array{id: int|string, name: string}> */
    private array $suppliers;

    private string $title;

    private string $periodeLine;

    private int $headerRow = 3;

    /**
     * @param  list<array<string, mixed>>  $report
     * @param  list<array{key: string, name: string}>  $warehouseColumns
     * @param  list<array{id: int|string, name: string}>  $suppliers
     * @param  array{outlet_label?: string, date_from?: string|null, date_to?: string|null}  $meta
     */
    public function __construct(array $report, array $warehouseColumns, array $suppliers, array $meta = [])
    {
        $this->warehouseColumns = $warehouseColumns;
        $this->suppliers = $suppliers;
        $this->title = 'Receiving Sheet Report';
        $outletLabel = $meta['outlet_label'] ?? 'Semua Outlet';
        $from = $meta['date_from'] ?? '-';
        $to = $meta['date_to'] ?? '-';
        $this->periodeLine = 'Outlet: '.$outletLabel.'  |  Periode: '.$from.' s/d '.$to;

        $this->headings = ['No', 'Tanggal', 'Omzet'];
        foreach ($warehouseColumns as $wh) {
            $this->headings[] = $wh['name'];
        }
        foreach ($suppliers as $sp) {
            $this->headings[] = $sp['name'];
        }
        $this->headings[] = 'Cost';
        $this->headings[] = '% Cost';

        $this->rows = [];
        foreach ($report as $index => $row) {
            $line = [
                $index + 1,
                $this->formatDateLabel((string) ($row['tanggal'] ?? '')),
                (float) ($row['omzet'] ?? 0),
            ];
            foreach ($warehouseColumns as $wh) {
                $line[] = (float) ($row[$wh['key']] ?? 0);
            }
            foreach ($suppliers as $sp) {
                $line[] = (float) ($row['supplier_'.$sp['id']] ?? 0);
            }
            $line[] = (float) ($row['cost'] ?? 0);
            $line[] = (float) ($row['persentase_cost'] ?? 0);
            $this->rows[] = $line;
        }

        if (count($this->rows) > 0) {
            $count = count($this->rows);
            $grand = ['', 'GRAND TOTAL'];
            $colCount = count($this->headings);
            for ($c = 2; $c < $colCount; $c++) {
                if ($c === $colCount - 1) {
                    // % Cost = average
                    $sumPct = 0.0;
                    foreach ($this->rows as $r) {
                        $sumPct += (float) ($r[$c] ?? 0);
                    }
                    $grand[] = round($sumPct / $count, 2);
                } else {
                    $sum = 0.0;
                    foreach ($this->rows as $r) {
                        $sum += (float) ($r[$c] ?? 0);
                    }
                    $grand[] = $sum;
                }
            }
            $this->rows[] = $grand;
        }
    }

    public function array(): array
    {
        return array_merge([$this->headings], $this->rows);
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colCount = count($this->headings);
                $lastCol = Coordinate::stringFromColumnIndex($colCount);

                $sheet->insertNewRowBefore(1, 2);
                $sheet->setCellValue('A1', $this->title);
                $sheet->setCellValue('A2', $this->periodeLine);
                $sheet->mergeCells('A1:'.$lastCol.'1');
                $sheet->mergeCells('A2:'.$lastCol.'2');
                $sheet->getStyle('A1:'.$lastCol.'2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('A1')->getFont()->setSize(14);

                $headerRow = $this->headerRow;
                $dataStart = $headerRow + 1;
                $dataEnd = max($dataStart - 1, (int) $sheet->getHighestRow());

                $columnColors = $this->columnColors();

                for ($col = 1; $col <= $colCount; $col++) {
                    $letter = Coordinate::stringFromColumnIndex($col);
                    $colors = $columnColors[$col - 1];

                    $sheet->getStyle($letter.$headerRow)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $colors['header']],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);

                    if ($dataEnd >= $dataStart) {
                        $sheet->getStyle($letter.$dataStart.':'.$letter.$dataEnd)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $colors['body']],
                            ],
                            'font' => [
                                'color' => ['rgb' => $colors['text']],
                            ],
                            'alignment' => [
                                'horizontal' => $col <= 2
                                    ? Alignment::HORIZONTAL_LEFT
                                    : Alignment::HORIZONTAL_RIGHT,
                                'vertical' => Alignment::VERTICAL_CENTER,
                            ],
                        ]);
                    }
                }

                $sheet->getStyle('A'.$headerRow.':'.$lastCol.$dataEnd)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                ]);

                if ($dataEnd >= $dataStart) {
                    // Currency columns: Omzet .. Cost (skip No, Tanggal, % Cost)
                    $currencyStart = Coordinate::stringFromColumnIndex(3);
                    $currencyEnd = Coordinate::stringFromColumnIndex($colCount - 1);
                    $sheet->getStyle($currencyStart.$dataStart.':'.$currencyEnd.$dataEnd)
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');

                    $pctCol = Coordinate::stringFromColumnIndex($colCount);
                    $sheet->getStyle($pctCol.$dataStart.':'.$pctCol.$dataEnd)
                        ->getNumberFormat()
                        ->setFormatCode('0.00"%"');

                    // % Cost conditional colors (match UI badges)
                    for ($row = $dataStart; $row <= $dataEnd; $row++) {
                        $pct = (float) $sheet->getCell($pctCol.$row)->getValue();
                        if ($pct <= 30) {
                            $fill = 'DCFCE7';
                            $text = '166534';
                        } elseif ($pct <= 50) {
                            $fill = 'FEF9C3';
                            $text = '854D0E';
                        } else {
                            $fill = 'FEE2E2';
                            $text = '991B1B';
                        }
                        $sheet->getStyle($pctCol.$row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $fill],
                            ],
                            'font' => [
                                'bold' => true,
                                'color' => ['rgb' => $text],
                            ],
                        ]);
                    }
                }

                $sheet->freezePane('A4');
                $sheet->getRowDimension($headerRow)->setRowHeight(28);
            },
        ];
    }

    /**
     * @return list<array{header: string, body: string, text: string}>
     */
    private function columnColors(): array
    {
        $colors = [
            ['header' => '475569', 'body' => 'F8FAFC', 'text' => '334155'], // No — slate
            ['header' => '0284C7', 'body' => 'F0F9FF', 'text' => '0C4A6E'], // Tanggal — sky
            ['header' => '059669', 'body' => 'ECFDF5', 'text' => '064E3B'], // Omzet — emerald
        ];

        foreach ($this->warehouseColumns as $_) {
            $colors[] = ['header' => '4F46E5', 'body' => 'EEF2FF', 'text' => '312E81']; // indigo
        }
        foreach ($this->suppliers as $_) {
            $colors[] = ['header' => 'D97706', 'body' => 'FFFBEB', 'text' => '78350F']; // amber
        }

        $colors[] = ['header' => 'E11D48', 'body' => 'FFF1F2', 'text' => '881337']; // Cost — rose
        $colors[] = ['header' => '7C3AED', 'body' => 'F5F3FF', 'text' => '5B21B6']; // % Cost — violet

        return $colors;
    }

    private function formatDateLabel(string $date): string
    {
        if ($date === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM YYYY');
        } catch (\Throwable $e) {
            return $date;
        }
    }
}
