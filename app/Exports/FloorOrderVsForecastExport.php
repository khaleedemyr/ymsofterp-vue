<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FloorOrderVsForecastExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithEvents
{
    /**
     * @var array<string, mixed>
     */
    private array $payload;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function headings(): array
    {
        $categoryCostTypes = $this->getCategoryCostTypes();

        $row1 = [
            'Tanggal',
            'Hari',
            'Forecast',
        ];

        $this->pushGroup($row1, 'Revenue', 4);
        $this->pushGroup($row1, 'Begin Stock', 3);
        $this->pushGroup($row1, 'Cost', 4);
        if (count($categoryCostTypes) > 0) {
            $this->pushGroup($row1, 'Category Cost', count($categoryCostTypes));
        }
        $this->pushGroup($row1, 'F & B Purchase', 4);
        $this->pushGroup($row1, 'Service Purchase', 4);
        $this->pushGroup($row1, 'Outlet Transfer', 2);
        $this->pushGroup($row1, 'Stock Adjustment', 2);
        $this->pushGroup($row1, 'Stock on Hand', 3);

        $row2 = [
            '',
            '',
            '',
            'Revenue',
            'No Disc',
            'Discount',
            '% Disc',
            'F & B',
            'Service',
            'Total',
            'Menu',
            'Modifier',
            'Usage',
            'Total',
        ];

        foreach ($categoryCostTypes as $label) {
            $row2[] = $label;
        }

        array_push(
            $row2,
            'Budget',
            'Purchased',
            'Variance',
            '%',
            'Budget',
            'Purchased',
            'Variance',
            '%',
            'Transfer Out',
            'Transfer In',
            'Adj In',
            'Adj Out',
            'F & B',
            'Service',
            'Total'
        );

        return [
            $row1,
            $row2,
        ];
    }

    public function collection(): Collection
    {
        $categoryCostTypes = $this->getCategoryCostTypes();

        $rows = collect($this->payload['rows'] ?? [])->map(function ($row) use ($categoryCostTypes) {
            $line = [
                $row['date'] ?? '',
                $row['day_name'] ?? '',
                (float) ($row['forecast_revenue'] ?? 0),
                (float) ($row['revenue'] ?? 0),
                (float) ($row['revenue_before_discount'] ?? 0),
                (float) ($row['discount'] ?? 0),
                $row['pct_discount'] ?? null,
                (float) ($row['begin_stock_kitchen_bar'] ?? 0),
                (float) ($row['begin_stock_service'] ?? 0),
                (float) ($row['begin_stock_total'] ?? 0),
                (float) ($row['cost_menu'] ?? 0),
                (float) ($row['cost_modifier'] ?? 0),
                (float) ($row['category_cost_usage'] ?? 0),
                (float) ($row['cost_total'] ?? 0),
            ];

            foreach (array_keys($categoryCostTypes) as $typeKey) {
                $line[] = (float) ($row['category_cost_values'][$typeKey] ?? 0);
            }

            array_push(
                $line,
                (float) ($row['cap_kitchen_bar'] ?? 0),
                (float) ($row['ro_kitchen_bar'] ?? 0),
                (float) ($row['diff_kitchen_bar'] ?? 0),
                $row['pct_kitchen_bar_vs_cap'] ?? null,
                (float) ($row['cap_service'] ?? 0),
                (float) ($row['ro_service'] ?? 0),
                (float) ($row['diff_service'] ?? 0),
                $row['pct_service_vs_cap'] ?? null,
                (float) ($row['transfer_out'] ?? 0),
                (float) ($row['transfer_in'] ?? 0),
                (float) ($row['adj_in'] ?? 0),
                (float) ($row['adj_out'] ?? 0),
                (float) ($row['stock_on_hand_kitchen_bar'] ?? 0),
                (float) ($row['stock_on_hand_service'] ?? 0),
                (float) ($row['stock_on_hand_total'] ?? 0)
            );

            return $line;
        });

        $totals = $this->payload['totals'] ?? [];
        $totalRow = [
            'Total bulan (SOH: posisi akhir bulan)',
            '',
            (float) ($totals['forecast_revenue'] ?? 0),
            (float) ($totals['revenue'] ?? 0),
            (float) ($totals['revenue_before_discount'] ?? 0),
            (float) ($totals['discount'] ?? 0),
            $totals['pct_discount'] ?? null,
            (float) ($totals['begin_stock_kitchen_bar_start'] ?? 0),
            (float) ($totals['begin_stock_service_start'] ?? 0),
            (float) ($totals['begin_stock_total_start'] ?? 0),
            (float) ($totals['cost_menu'] ?? 0),
            (float) ($totals['cost_modifier'] ?? 0),
            (float) ($totals['category_cost_usage'] ?? 0),
            (float) ($totals['cost_total'] ?? 0),
        ];

        foreach (array_keys($categoryCostTypes) as $typeKey) {
            $totalRow[] = (float) ($totals['category_cost_values'][$typeKey] ?? 0);
        }

        array_push(
            $totalRow,
            (float) ($totals['cap_kitchen_bar'] ?? 0),
            (float) ($totals['ro_kitchen_bar'] ?? 0),
            (float) ($totals['diff_kitchen_bar'] ?? 0),
            null,
            (float) ($totals['cap_service'] ?? 0),
            (float) ($totals['ro_service'] ?? 0),
            (float) ($totals['diff_service'] ?? 0),
            null,
            (float) ($totals['transfer_out'] ?? 0),
            (float) ($totals['transfer_in'] ?? 0),
            (float) ($totals['adj_in'] ?? 0),
            (float) ($totals['adj_out'] ?? 0),
            (float) ($totals['stock_on_hand_kitchen_bar_end'] ?? 0),
            (float) ($totals['stock_on_hand_service_end'] ?? 0),
            (float) ($totals['stock_on_hand_total_end'] ?? 0)
        );

        $rows->push($totalRow);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ],
            2 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8FAFC'],
                ],
            ],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $categoryCostTypes = $this->getCategoryCostTypes();
                $categoryCount = count($categoryCostTypes);

                $mergeRanges = [
                    'A1:A2',
                    'B1:B2',
                    'C1:C2',
                ];

                $groupSpans = [
                    4, // Revenue
                    3, // Begin Stock
                    4, // Cost
                ];
                if ($categoryCount > 0) {
                    $groupSpans[] = $categoryCount; // Category Cost
                }
                array_push($groupSpans, 4, 4, 2, 2, 3); // Purchase, Transfer, SOH

                $startCol = 4;
                foreach ($groupSpans as $span) {
                    $endCol = $startCol + $span - 1;
                    if ($span > 1) {
                        $mergeRanges[] = Coordinate::stringFromColumnIndex($startCol).'1:'.Coordinate::stringFromColumnIndex($endCol).'1';
                    }
                    $startCol = $endCol + 1;
                }

                foreach ($mergeRanges as $range) {
                    $sheet->mergeCells($range);
                }

                $lastColumnIndex = $startCol - 1;
                $lastColumnLetter = Coordinate::stringFromColumnIndex($lastColumnIndex);

                $sheet->getStyle('A1:'.$lastColumnLetter.'2')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                ]);

                $sheet->freezePane('D3');
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(22);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getCategoryCostTypes(): array
    {
        $list = $this->payload['category_cost_types'] ?? [];
        $result = [];
        foreach ($list as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $label = trim((string) ($item['label'] ?? $key));
            $result[$key] = $label === '' ? $key : $label;
        }
        ksort($result);

        return $result;
    }

    /**
     * @param array<int, string> $target
     */
    private function pushGroup(array &$target, string $label, int $span): void
    {
        $target[] = $label;
        for ($i = 1; $i < $span; $i++) {
            $target[] = '';
        }
    }
}
