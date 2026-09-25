<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RwsReceivedReportExport implements WithMultipleSheets
{
    /** @var Collection<int, object> */
    protected Collection $rows;

    /** @var Collection<int, object> */
    protected Collection $byOutlet;

    /** @var array{total_rws:int,total_amount:float,total_rf:int,outlet_count:int} */
    protected array $summary;

    /** @var array<string, mixed> */
    protected array $filters;

    public function __construct(Collection $rows, Collection $byOutlet, array $summary, array $filters = [])
    {
        $this->rows = $rows;
        $this->byOutlet = $byOutlet;
        $this->summary = $summary;
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        return [
            new RwsReceivedSummarySheet($this->summary, $this->byOutlet, $this->filters),
            new RwsReceivedDetailSheet($this->rows),
        ];
    }
}

class RwsReceivedSummarySheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        protected array $summary,
        protected Collection $byOutlet,
        protected array $filters,
    ) {}

    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $rows = [
            ['Report RWS Sudah Diterima (Retail Food)'],
            ['Periode', ($this->filters['from_date'] ?? '-').' s/d '.($this->filters['to_date'] ?? '-')],
            ['Generated', now()->format('Y-m-d H:i:s')],
            [],
            ['Metrik', 'Nilai'],
            ['Total RWS sudah diterima', $this->summary['total_rws'] ?? 0],
            ['Total dokumen RF', $this->summary['total_rf'] ?? 0],
            ['Total Nominal RWS', $this->summary['total_amount'] ?? 0],
            ['Jumlah Outlet', $this->summary['outlet_count'] ?? 0],
            [],
            ['Outlet', 'Jumlah RWS', 'Jumlah RF', 'Total Nominal'],
        ];

        foreach ($this->byOutlet as $o) {
            $rows[] = [
                $o->outlet_name,
                $o->total_rws,
                $o->total_rf ?? 0,
                (float) $o->total_amount,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A5:B5')->getFont()->setBold(true);
        $sheet->getStyle('A11:D11')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('B8')->getNumberFormat()->setFormatCode('#,##0');
        $last = 11 + $this->byOutlet->count();
        if ($last >= 12) {
            $sheet->getStyle("D12:D{$last}")->getNumberFormat()->setFormatCode('#,##0');
        }

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 40, 'B' => 14, 'C' => 12, 'D' => 18];
    }
}

class RwsReceivedDetailSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(protected Collection $rows) {}

    public function title(): string
    {
        return 'RWS Sudah Diterima';
    }

    public function array(): array
    {
        $out = [[
            'No',
            'Nomor RWS',
            'Tgl RWS',
            'Nomor RF',
            'Tgl RF',
            'Supplier RF',
            'Outlet',
            'Customer',
            'Warehouse',
            'Nominal RWS',
            'Nominal RF',
            'Tipe Match',
            'Notes',
            'Item Detail',
        ]];

        $no = 1;
        foreach ($this->rows as $w) {
            $out[] = [
                $no++,
                $w->number,
                substr((string) $w->sale_date, 0, 10),
                $w->rf_number ?? '-',
                substr((string) ($w->rf_date ?? ''), 0, 10) ?: '-',
                $w->rf_supplier_name ?? '-',
                $w->outlet_name ?: '-',
                $w->customer_name ?: '-',
                $w->warehouse_name ?: '-',
                (float) $w->total_amount,
                (float) ($w->rf_amount ?? 0),
                $w->match_type ?? '-',
                $w->notes ?: '-',
                $w->items_summary ?? '',
            ];
        }

        return $out;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
        ]);
        $last = 1 + $this->rows->count();
        if ($last >= 2) {
            $sheet->getStyle("J2:K{$last}")->getNumberFormat()->setFormatCode('#,##0');
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 18,
            'C' => 12,
            'D' => 18,
            'E' => 12,
            'F' => 18,
            'G' => 28,
            'H' => 22,
            'I' => 16,
            'J' => 14,
            'K' => 14,
            'L' => 16,
            'M' => 24,
            'N' => 50,
        ];
    }
}
