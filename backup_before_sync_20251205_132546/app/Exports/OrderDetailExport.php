<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Facades\Excel;

class OrderDetailExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, Responsable
{
    private $orders;
    public $fileName = 'order_detail.xlsx';

    public function __construct($orders, $tanggal = null)
    {
        $this->orders = $orders;
        if ($tanggal) {
            $this->fileName = 'order_detail_' . $tanggal . '.xlsx';
        }
    }

    public function collection()
    {
        return collect($this->orders);
    }

    public function headings(): array
    {
        return [
            'No', 'Nomor Order', 'Table', 'Pax', 'Total', 'Discount', 'Cashback', 'Service', 'PB1', 'Grand Total', 'Status'
        ];
    }

    public function map($order): array
    {
        static $no = 0;
        $no++;
        return [
            $no,
            $order->nomor,
            $order->table,
            $order->pax,
            $order->total,
            $order->discount,
            $order->cashback,
            $order->service,
            $order->pb1,
            $order->grand_total,
            $order->status,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563eb']
            ],
        ]);
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highestCol . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        return [];
    }

    public function columnWidths(): array
    {
        $widths = [];
        foreach (range('A', 'K') as $col) {
            $widths[$col] = 18;
        }
        return $widths;
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
} 