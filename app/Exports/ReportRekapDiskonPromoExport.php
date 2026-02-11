<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Events\AfterSheet;

class ReportRekapDiskonPromoExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithCustomStartCell, WithEvents, Responsable
{
    private $detail;
    private $summary;
    private $dateFrom;
    private $dateTo;
    private $search;
    public $fileName;

    public function __construct($detail, $summary, $dateFrom, $dateTo, $search = '')
    {
        $this->detail = $detail;
        $this->summary = $summary;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->search = $search;
        $suffix = ($dateFrom && $dateTo) ? $dateFrom . '_to_' . $dateTo : date('Y-m-d');
        $this->fileName = 'Rekap_Diskon_Promo_' . $suffix . '.xlsx';
    }

    public function collection()
    {
        return collect($this->detail);
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'No. Order',
            'Paid Number',
            'Nama Outlet',
            'Nama Promo',
            'Kode Promo',
            'Tipe',
            'Discount (Alokasi)',
            'Grand Total',
        ];
    }

    public function map($row): array
    {
        $date = isset($row->order_created_at)
            ? (\Carbon\Carbon::parse($row->order_created_at)->format('d/m/Y H:i'))
            : '';
        return [
            $date,
            $row->order_nomor ?? '',
            $row->paid_number ?? '',
            $row->outlet_name ?? $row->kode_outlet ?? '',
            $row->promo_name ?? '',
            $row->promo_code ?? '',
            $row->promo_type ?? '-',
            (float) ($row->allocated_discount ?? 0),
            (float) ($row->order_grand_total ?? 0),
        ];
    }

    public function startCell(): string
    {
        return 'A11';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 18,
            'C' => 16,
            'D' => 25,
            'E' => 30,
            'F' => 15,
            'G' => 12,
            'H' => 18,
            'I' => 15,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setCellValue('A1', 'Report Rekap Diskon - Diskon Promo');
        $sheet->setCellValue('A2', 'Periode: ' . ($this->dateFrom ? $this->dateFrom . ' s/d ' . $this->dateTo : 'Semua'));
        if ($this->search !== '') {
            $sheet->setCellValue('A3', 'Filter Cari: ' . $this->search);
        }
        $sheet->setCellValue('A4', 'Generated: ' . now()->format('Y-m-d H:i:s'));

        $sheet->setCellValue('A6', 'Rekap per Promo:');
        $row = 7;
        foreach ($this->summary as $s) {
            $sheet->setCellValue('A' . $row, $s['promo_name'] . ' (' . $s['promo_code'] . ') - Pemakaian: ' . $s['jumlah_pemakaian'] . ', Total Diskon: ' . number_format($s['total_discount'], 0, ',', '.'));
            $row++;
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A6')->getFont()->setBold(true);

        return [
            11 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE0E0E0'],
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
                $dataRange = 'A11:I' . $lastRow;
                $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('H12:H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('I12:I' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
                foreach (range('A', 'I') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    public function toResponse($request)
    {
        try {
            return Excel::download($this, $this->fileName);
        } catch (\Exception $e) {
            \Log::error('Export Rekap Diskon Promo error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat generate file Excel'], 500);
        }
    }
}
