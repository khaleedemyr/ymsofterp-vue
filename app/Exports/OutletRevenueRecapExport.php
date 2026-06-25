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
    /** @var array<string, mixed> */
    private array $payload;

    /** @var list<int> */
    private array $regionHeaderRows = [];

    /** @var list<int> */
    private array $subtotalRows = [];

    private int $grandTotalRow = 0;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function headings(): array
    {
        return [
            'Region',
            'Outlet',
            'Total Sales',
            'Discount',
            'Service Charge',
            'PB 1',
            'Commfee',
            'Total Pax',
            'Average Check',
        ];
    }

    public function collection(): Collection
    {
        $lines = collect();
        $rowIndex = 2;

        foreach ($this->payload['groups'] ?? [] as $group) {
            $this->regionHeaderRows[] = $rowIndex;
            $lines->push([
                (string) ($group['region_name'] ?? ''),
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ]);
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
        return [
            $region,
            $outlet,
            (float) ($metrics['total_sales'] ?? 0),
            (float) ($metrics['discount'] ?? 0),
            (float) ($metrics['service_charge'] ?? 0),
            (float) ($metrics['pb1'] ?? 0),
            (float) ($metrics['commfee'] ?? 0),
            (float) ($metrics['total_pax'] ?? 0),
            (float) ($metrics['avg_check'] ?? 0),
        ];
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

                $sheet->insertNewRowBefore(1, $offset);
                $sheet->setCellValue('A1', 'Rekap Revenue Outlet');
                $sheet->setCellValue(
                    'A2',
                    'Periode: '.($this->payload['date_from'] ?? '').' s/d '.($this->payload['date_to'] ?? '')
                );
                $sheet->mergeCells('A1:I1');
                $sheet->mergeCells('A2:I2');
                $sheet->getStyle('A1:I2')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $headerRow = 3;
                $sheet->getStyle('A'.$headerRow.':I'.$headerRow)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '111827'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                foreach ($this->regionHeaderRows as $row) {
                    $actualRow = $row + $offset;
                    $sheet->getStyle('A'.$actualRow.':I'.$actualRow)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '312E81']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E0E7FF'],
                        ],
                    ]);
                }

                foreach ($this->subtotalRows as $row) {
                    $actualRow = $row + $offset;
                    $sheet->getStyle('A'.$actualRow.':I'.$actualRow)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F3F4F6'],
                        ],
                    ]);
                }

                if ($this->grandTotalRow > 0) {
                    $actualRow = $this->grandTotalRow + $offset;
                    $sheet->getStyle('A'.$actualRow.':I'.$actualRow)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '1E3A8A'],
                        ],
                    ]);
                }

                $dataStart = 4;
                $dataEnd = $lastRow + $offset;
                $sheet->getStyle('C'.$dataStart.':G'.$dataEnd)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
                $sheet->getStyle('I'.$dataStart.':I'.$dataEnd)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
                $sheet->getStyle('H'.$dataStart.':H'.$dataEnd)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                $sheet->freezePane('A4');
            },
        ];
    }
}
