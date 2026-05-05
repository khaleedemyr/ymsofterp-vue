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

class RekapPb1OutletExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithEvents
{
    /** @var array<string, mixed> */
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
            'Total',
            'Disc',
            'DPP',
            'PB1',
            'Service',
            'Grand Total',
            'Pax',
            'Commfee',
        ];
    }

    public function collection(): Collection
    {
        $lines = collect($this->payload['rows'] ?? [])->map(function ($row) {
            return [
                $row['date_display'] ?? '',
                (float) ($row['total'] ?? 0),
                (float) ($row['disc'] ?? 0),
                (float) ($row['dpp'] ?? 0),
                (float) ($row['pb1'] ?? 0),
                (float) ($row['service'] ?? 0),
                (float) ($row['grand_total'] ?? 0),
                (int) ($row['pax'] ?? 0),
                (float) ($row['commfee'] ?? 0),
            ];
        });

        $t = $this->payload['totals'] ?? [];

        $lines->push([
            'Total',
            (float) ($t['total'] ?? 0),
            (float) ($t['disc'] ?? 0),
            (float) ($t['dpp'] ?? 0),
            (float) ($t['pb1'] ?? 0),
            (float) ($t['service'] ?? 0),
            (float) ($t['grand_total'] ?? 0),
            (int) ($t['pax'] ?? 0),
            (float) ($t['commfee'] ?? 0),
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
                    'startColor' => ['rgb' => 'FFEB9C'],
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
                $lastRow = $sheet->getHighestRow();
                $sheet->getStyle('A'.$lastRow.':I'.$lastRow)->applyFromArray([
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
