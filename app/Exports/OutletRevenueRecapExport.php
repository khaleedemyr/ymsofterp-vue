<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletRevenueRecapExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithEvents
{
    /** @var list<array{key: string, label: string}> */
    private const METRICS = [
        ['key' => 'total_sales', 'label' => 'Total Sales'],
        ['key' => 'discount', 'label' => 'Discount'],
        ['key' => 'service_charge', 'label' => 'Service Charge'],
        ['key' => 'pb1', 'label' => 'PB 1'],
        ['key' => 'commfee', 'label' => 'Commfee'],
        ['key' => 'grand_total', 'label' => 'Grand Total'],
        ['key' => 'total_pax', 'label' => 'Total Pax'],
        ['key' => 'avg_check', 'label' => 'Average Check'],
    ];

    /** @var array<string, mixed> */
    private array $payload;

    /** @var list<int> */
    private array $regionHeaderRows = [];

    /** @var list<int> */
    private array $subtotalRows = [];

    private int $grandTotalRow = 0;

    private bool $compare;

    private int $periodCount;

    /** @var list<array<string, mixed>> */
    private array $periods;

    private string $lastCol;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->compare = ! empty($payload['compare']);
        $this->periods = $payload['periods'] ?? [];
        $this->periodCount = (int) ($payload['period_count'] ?? count($this->periods) ?: 1);

        $metricCols = $this->compare
            ? count(self::METRICS) * ($this->periodCount + ($this->periodCount - 1) * 2)
            : count(self::METRICS);
        $this->lastCol = $this->columnLetter(2 + $metricCols);
    }

    public function headings(): array
    {
        if (! $this->compare) {
            return [
                'Region',
                'Outlet',
                'Total Sales',
                'Discount',
                'Service Charge',
                'PB 1',
                'Commfee',
                'Grand Total',
                'Total Pax',
                'Average Check',
            ];
        }

        $heads = ['Region', 'Outlet'];
        foreach (self::METRICS as $metric) {
            $label = $metric['label'];
            for ($i = 0; $i < $this->periodCount; $i++) {
                $pLabel = $this->periods[$i]['label'] ?? ('P'.($i + 1));
                $heads[] = $label.' ('.$pLabel.')';
            }
            for ($i = 1; $i < $this->periodCount; $i++) {
                $pLabel = $this->periods[$i]['label'] ?? ('P'.($i + 1));
                $heads[] = $label.' (Δ'.$pLabel.')';
                $heads[] = $label.' (%'.$pLabel.')';
            }
        }

        return $heads;
    }

    public function collection(): Collection
    {
        $lines = collect();
        $rowIndex = 2;
        $emptyWidth = count($this->headings());

        foreach ($this->payload['groups'] ?? [] as $group) {
            $this->regionHeaderRows[] = $rowIndex;
            $lines->push(array_pad([(string) ($group['region_name'] ?? ''), ''], $emptyWidth, ''));
            $rowIndex++;

            foreach ($group['rows'] ?? [] as $row) {
                $lines->push($this->metricLine('', (string) ($row['outlet_name'] ?? ''), $row));
                $rowIndex++;
            }

            $this->subtotalRows[] = $rowIndex;
            $lines->push($this->metricLine(
                '',
                'Subtotal '.($group['region_name'] ?? ''),
                $group['subtotal'] ?? []
            ));
            $rowIndex++;
        }

        $this->grandTotalRow = $rowIndex;
        $lines->push($this->metricLine('', 'GRAND TOTAL', $this->payload['totals'] ?? []));

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<int, mixed>
     */
    private function metricLine(string $region, string $outlet, array $metrics): array
    {
        $line = [$region, $outlet];

        foreach (self::METRICS as $metric) {
            $key = $metric['key'];

            if (! $this->compare) {
                $line[] = (float) ($metrics[$key] ?? 0);
                continue;
            }

            $values = $metrics[$key] ?? [];
            $diffs = $metrics[$key.'_diff'] ?? [];
            $pcts = $metrics[$key.'_pct'] ?? [];

            if (! is_array($values)) {
                $values = [$values];
            }

            for ($i = 0; $i < $this->periodCount; $i++) {
                $line[] = (float) ($values[$i] ?? 0);
            }
            for ($i = 1; $i < $this->periodCount; $i++) {
                $line[] = isset($diffs[$i]) ? (float) $diffs[$i] : null;
                $line[] = isset($pcts[$i]) && $pcts[$i] !== null ? (float) $pcts[$i] : null;
            }
        }

        return $line;
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
                $lastRow = max(2, (int) $sheet->getHighestRow());
                $offset = 2;
                $lastCol = $this->lastCol;

                $sheet->insertNewRowBefore(1, $offset);
                $sheet->setCellValue(
                    'A1',
                    'Rekap Revenue Outlet'.($this->compare ? ' (Pembanding '.$this->periodCount.' Periode)' : '')
                );

                if ($this->compare) {
                    $parts = [];
                    foreach ($this->periods as $p) {
                        $parts[] = ($p['label'] ?? '').': '.($p['from'] ?? '').' s/d '.($p['to'] ?? '');
                    }
                    $periodeLine = implode('  |  ', $parts).'  |  Selisih/% vs Periode 1';
                } else {
                    $periodeLine = 'Periode: '.($this->payload['date_from'] ?? '').' s/d '.($this->payload['date_to'] ?? '');
                }

                $sheet->setCellValue('A2', $periodeLine);
                $sheet->mergeCells('A1:'.$lastCol.'1');
                $sheet->mergeCells('A2:'.$lastCol.'2');
                $sheet->getStyle('A1:'.$lastCol.'2')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $headerRow = 3;
                $sheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '111827'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                foreach ($this->regionHeaderRows as $row) {
                    $actualRow = $row + $offset;
                    $sheet->getStyle('A'.$actualRow.':'.$lastCol.$actualRow)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '312E81']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E0E7FF'],
                        ],
                    ]);
                }

                foreach ($this->subtotalRows as $row) {
                    $actualRow = $row + $offset;
                    $sheet->getStyle('A'.$actualRow.':'.$lastCol.$actualRow)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F3F4F6'],
                        ],
                    ]);
                }

                if ($this->grandTotalRow > 0) {
                    $actualRow = $this->grandTotalRow + $offset;
                    $sheet->getStyle('A'.$actualRow.':'.$lastCol.$actualRow)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '1E3A8A'],
                        ],
                    ]);
                }

                $dataStart = 4;
                $dataEnd = $lastRow + $offset;
                $sheet->getStyle('C'.$dataStart.':'.$lastCol.$dataEnd)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');

                $sheet->freezePane('A4');
            },
        ];
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }
}
