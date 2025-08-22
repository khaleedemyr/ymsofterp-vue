<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Facades\Excel;

class FoodGoodReceiveReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, Responsable
{
    protected $data;
    public $fileName = 'food_good_receive_report.xlsx';

    public function __construct($data)
    {
        $this->data = $data;
        $this->fileName = 'food_good_receive_report_' . date('Y-m-d_H-i-s') . '.xlsx';
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'GR Number',
            'Receive Date',
            'PO Number',
            'PO Date',
            'Supplier Name',
            'Supplier Code',
            'Received By',
            'Item Name',
            'Item SKU',
            'Qty Ordered',
            'Qty Received',
            'Remaining Qty',
            'Unit',
            'Notes'
        ];
    }

    public function map($row): array
    {
        return [
            $row['gr_number'] ?? '-',
            $row['receive_date'] ? date('d/m/Y', strtotime($row['receive_date'])) : '-',
            $row['po_number'] ?? '-',
            $row['po_date'] ? date('d/m/Y', strtotime($row['po_date'])) : '-',
            $row['supplier_name'] ?? '-',
            $row['supplier_code'] ?? '-',
            $row['received_by_name'] ?? '-',
            $row['item_name'] ?? '-',
            $row['item_sku'] ?? '-',
            number_format($row['qty_ordered'] ?? 0, 2),
            number_format($row['qty_received'] ?? 0, 2),
            number_format($row['remaining_qty'] ?? 0, 2),
            $row['unit_name'] ?? '-',
            $row['notes'] ?? '-'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB']
                ]
            ]
        ];
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
}
