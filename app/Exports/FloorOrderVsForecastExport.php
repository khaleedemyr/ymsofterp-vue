<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FloorOrderVsForecastExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
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
            'Tanggal',
            'Hari',
            'Forecast',
            'Revenue',
            'SOH F & B',
            'SOH Service',
            'SOH Total',
            'F & B Purchase',
            'F & B Purchased',
            'Delta F & B',
            '% vs purchase F & B',
            'Svc Purchase',
            'Service Purchased',
            'Delta Svc',
            '% vs purchase Svc',
            'RO lain',
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
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'],
                ],
            ],
        ];
    }
}
