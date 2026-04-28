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
        return [
            [
                'Tanggal',
                'Hari',
                'Forecast',
                'Revenue',
                '',
                '',
                'Cost',
                '',
                '',
                '',
                '',
                'Stock on Hand',
                '',
                '',
                'F & B Purchase',
                '',
                '',
                '',
                'Service Purchase',
                '',
                '',
                '',
                'RO lain',
            ],
            [
                '',
                '',
                '',
                'Revenue',
                'Discount',
                '% Disc',
                'Menu',
                'Modifier',
                'Usage',
                'Total',
                '% Cost',
                'F & B',
                'Service',
                'Total',
                'Budget',
                'Purchased',
                'Delta',
                '%',
                'Budget',
                'Purchased',
                'Delta',
                '%',
                '',
            ],
        ];
    }

    public function collection(): Collection
    {
        $rows = collect($this->payload['rows'] ?? [])->map(function ($row) {
            return [
                'date' => $row['date'] ?? '',
                'day_name' => $row['day_name'] ?? '',
                'forecast_revenue' => (float) ($row['forecast_revenue'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
                'discount' => (float) ($row['discount'] ?? 0),
                'pct_discount' => $row['pct_discount'],
                'cost_menu' => (float) ($row['cost_menu'] ?? 0),
                'cost_modifier' => (float) ($row['cost_modifier'] ?? 0),
                'category_cost_usage' => (float) ($row['category_cost_usage'] ?? 0),
                'cost_total' => (float) ($row['cost_total'] ?? 0),
                'pct_cost' => $row['pct_cost'],
                'stock_on_hand_kitchen_bar' => (float) ($row['stock_on_hand_kitchen_bar'] ?? 0),
                'stock_on_hand_service' => (float) ($row['stock_on_hand_service'] ?? 0),
                'stock_on_hand_total' => (float) ($row['stock_on_hand_total'] ?? 0),
                'cap_kitchen_bar' => (float) ($row['cap_kitchen_bar'] ?? 0),
                'ro_kitchen_bar' => (float) ($row['ro_kitchen_bar'] ?? 0),
                'diff_kitchen_bar' => (float) ($row['diff_kitchen_bar'] ?? 0),
                'pct_kitchen_bar_vs_cap' => $row['pct_kitchen_bar_vs_cap'],
                'cap_service' => (float) ($row['cap_service'] ?? 0),
                'ro_service' => (float) ($row['ro_service'] ?? 0),
                'diff_service' => (float) ($row['diff_service'] ?? 0),
                'pct_service_vs_cap' => $row['pct_service_vs_cap'],
                'ro_other' => (float) ($row['ro_other'] ?? 0),
            ];
        });

        $totals = $this->payload['totals'] ?? [];
        $rows->push([
            'date' => 'Total bulan (SOH: posisi akhir bulan)',
            'day_name' => '',
            'forecast_revenue' => (float) ($totals['forecast_revenue'] ?? 0),
            'revenue' => (float) ($totals['revenue'] ?? 0),
            'discount' => (float) ($totals['discount'] ?? 0),
            'pct_discount' => $totals['pct_discount'] ?? null,
            'cost_menu' => (float) ($totals['cost_menu'] ?? 0),
            'cost_modifier' => (float) ($totals['cost_modifier'] ?? 0),
            'category_cost_usage' => (float) ($totals['category_cost_usage'] ?? 0),
            'cost_total' => (float) ($totals['cost_total'] ?? 0),
            'pct_cost' => $totals['pct_cost'] ?? null,
            'stock_on_hand_kitchen_bar' => (float) ($totals['stock_on_hand_kitchen_bar_end'] ?? 0),
            'stock_on_hand_service' => (float) ($totals['stock_on_hand_service_end'] ?? 0),
            'stock_on_hand_total' => (float) ($totals['stock_on_hand_total_end'] ?? 0),
            'cap_kitchen_bar' => (float) ($totals['cap_kitchen_bar'] ?? 0),
            'ro_kitchen_bar' => (float) ($totals['ro_kitchen_bar'] ?? 0),
            'diff_kitchen_bar' => (float) ($totals['diff_kitchen_bar'] ?? 0),
            'pct_kitchen_bar_vs_cap' => null,
            'cap_service' => (float) ($totals['cap_service'] ?? 0),
            'ro_service' => (float) ($totals['ro_service'] ?? 0),
            'diff_service' => (float) ($totals['diff_service'] ?? 0),
            'pct_service_vs_cap' => null,
            'ro_other' => (float) ($totals['ro_other'] ?? 0),
        ]);

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

                foreach (['A1:A2', 'B1:B2', 'C1:C2', 'D1:F1', 'G1:K1', 'L1:N1', 'O1:R1', 'S1:V1', 'W1:W2'] as $range) {
                    $sheet->mergeCells($range);
                }

                $sheet->getStyle('A1:W2')->applyFromArray([
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
}
