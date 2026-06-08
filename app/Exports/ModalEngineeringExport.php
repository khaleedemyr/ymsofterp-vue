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

class ModalEngineeringExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithEvents
{
    /**
     * @var array<string, mixed>
     */
    private array $payload;

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
            'Tanggal',
            'Hari',
            'Stock Cut',
            'Category Cost Usage',
            'Total Modal',
            'Engineering',
            '% Modal x Engineering',
        ];
    }

    public function collection(): Collection
    {
        $lines = collect($this->payload['rows'] ?? [])->map(function ($row) {
            $pct = $row['modal_x_engineering_pct'] ?? null;

            return [
                $row['date'] ?? '',
                $row['day_name'] ?? '',
                (float) ($row['stock_cut'] ?? 0),
                (float) ($row['category_cost_usage'] ?? 0),
                (float) ($row['total_modal'] ?? 0),
                (float) ($row['engineering'] ?? 0),
                $pct === null ? '' : (float) $pct,
            ];
        });

        $t = $this->payload['totals'] ?? [];
        $totalPct = $t['modal_x_engineering_pct'] ?? null;

        $lines->push([
            'Total',
            '',
            (float) ($t['stock_cut'] ?? 0),
            (float) ($t['category_cost_usage'] ?? 0),
            (float) ($t['total_modal'] ?? 0),
            (float) ($t['engineering'] ?? 0),
            $totalPct === null ? '' : (float) $totalPct,
        ]);

        return $lines;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E1F2'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $outletName = (string) ($this->payload['selectedOutletName'] ?? '—');
                $monthLabel = (string) ($this->payload['month_label'] ?? '');

                $sheet->insertNewRowBefore(1, 2);
                $sheet->setCellValue('A1', 'Laporan Modal x Engineering');
                $sheet->setCellValue('A2', 'Outlet: '.$outletName.' | Periode: '.$monthLabel);
                $sheet->mergeCells('A1:G1');
                $sheet->mergeCells('A2:G2');
                $sheet->getStyle('A1:A2')->applyFromArray([
                    'font' => ['bold' => true],
                ]);

                $lastRow = $sheet->getHighestRow();
                $sheet->getStyle('A'.$lastRow.':G'.$lastRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFF2CC'],
                    ],
                ]);
            },
        ];
    }
}
