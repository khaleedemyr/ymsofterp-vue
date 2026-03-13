<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class BankTransactionExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    private Collection $rows;
    private string $outletLabel;
    private string $bankLabel;
    private ?string $dateFrom;
    private ?string $dateTo;

    public function __construct(Collection $rows, string $outletLabel, string $bankLabel, ?string $dateFrom, ?string $dateTo)
    {
        $this->rows = $rows;
        $this->outletLabel = $outletLabel;
        $this->bankLabel = $bankLabel;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Nomor Bill',
            'Payment Type',
            'Card First',
            'Card Last',
            'Approval Code',
            'Grand Total',
            'Discount',
            'DPP (After Discount)',
            'PPN/PB1',
            'Service Charge',
            'Nilai Gesek',
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $row->tanggal ? Carbon::parse($row->tanggal)->format('d/m/Y H:i') : '-',
            $row->paid_number ?? '-',
            $row->bank_name ?? '-',
            $row->card_first4 ?? '-',
            $row->card_last4 ?? '-',
            $row->approval_code ?? '-',
            (float) ($row->grand_total ?? 0),
            (float) ($row->total_discount ?? 0),
            (float) ($row->dpp ?? 0),
            (float) ($row->pb1 ?? 0),
            (float) ($row->service ?? 0),
            (float) ($row->nilai_gesek ?? 0),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'H' => '[$Rp-421] #,##0',
            'I' => '[$Rp-421] #,##0',
            'J' => '[$Rp-421] #,##0',
            'K' => '[$Rp-421] #,##0',
            'L' => '[$Rp-421] #,##0',
            'M' => '[$Rp-421] #,##0',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                $lastRow = $this->rows->count() + 1;

                $sheet->insertNewRowBefore(1, 3);

                $sheet->setCellValue('A1', 'Rekap Transaksi Bank');
                $sheet->setCellValue('A2', "Outlet: {$this->outletLabel} | Bank: {$this->bankLabel}");

                $period = '';
                if ($this->dateFrom && $this->dateTo) {
                    $period = Carbon::parse($this->dateFrom)->format('d/m/Y') . ' - ' . Carbon::parse($this->dateTo)->format('d/m/Y');
                } elseif ($this->dateFrom) {
                    $period = 'Dari ' . Carbon::parse($this->dateFrom)->format('d/m/Y');
                } elseif ($this->dateTo) {
                    $period = 'Sampai ' . Carbon::parse($this->dateTo)->format('d/m/Y');
                }
                if ($period) {
                    $sheet->setCellValue('A3', "Periode: {$period}");
                }

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setBold(true);

                $headerRow = 4;
                $sheet->getStyle("A{$headerRow}:M{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2563EB'],
                    ],
                ]);
            },
        ];
    }
}
