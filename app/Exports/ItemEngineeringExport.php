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

class ItemEngineeringExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithCustomStartCell, WithEvents, Responsable
{
    private $items;
    private $outletName;
    private $dateFrom;
    private $dateTo;
    public $fileName;

    public function __construct($items, $outletName, $dateFrom, $dateTo)
    {
        $this->items = $items;
        $this->outletName = $outletName;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->fileName = 'item_engineering_' . ($outletName ? str_replace(' ', '_', $outletName) : 'all') . '_' . $dateFrom . '_to_' . $dateTo . '.xlsx';
    }

    public function collection()
    {
        return collect($this->items);
    }

    public function headings(): array
    {
        return [
            'No', 'Nama Item', 'Qty Terjual', 'Harga Jual', 'Subtotal'
        ];
    }

    public function map($item): array
    {
        static $no = 0;
        $no++;
        return [
            $no,
            $item->item_name,
            $item->qty_terjual,
            $item->harga_jual,
            $item->subtotal,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A3:E3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563eb']
            ],
        ]);
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $sheet->getStyle('A3:' . $highestCol . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('A1:E2')->getFont()->setBold(true);
        return [];
    }

    public function columnWidths(): array
    {
        $widths = [];
        foreach (range('A', 'E') as $col) {
            $widths[$col] = 22;
        }
        return $widths;
    }

    public function startCell(): string
    {
        return 'A3';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->setCellValue('A1', 'Outlet: ' . $this->outletName);
                $event->sheet->setCellValue('A2', 'Periode: ' . $this->dateFrom . ' s/d ' . $this->dateTo);
            }
        ];
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
} 