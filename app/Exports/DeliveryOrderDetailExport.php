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

class DeliveryOrderDetailExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, Responsable
{
    protected $data;
    public $fileName = 'delivery_order_detail.xlsx';

    public function __construct($data)
    {
        $this->data = $data;
        $this->fileName = 'delivery_order_detail_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        // Log data received
        \Log::info('DELIVERY_ORDER_DETAIL_EXPORT: Constructor called', [
            'data_type' => get_class($data),
            'data_count' => is_countable($data) ? count($data) : 'not countable',
            'first_item' => is_countable($data) && count($data) > 0 ? $data->first() : 'no data'
        ]);
    }

    public function collection()
    {
        \Log::info('DELIVERY_ORDER_DETAIL_EXPORT: Collection method called', [
            'data_count' => is_countable($this->data) ? count($this->data) : 'not countable',
            'data_type' => get_class($this->data)
        ]);
        
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'No',
            'Delivery Date',
            'DO Number',
            'Packing List',
            'Floor Order',
            'Outlet',
            'Warehouse Outlet',
            'Created By',
            'Item Name',
            'SKU',
            'Category',
            'Sub Category',
            'Qty Packing List',
            'Qty Scan',
            'Unit'
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;
        
        // Log first few rows for debugging
        if ($no <= 3) {
            \Log::info('DELIVERY_ORDER_DETAIL_EXPORT: Mapping row', [
                'row_number' => $no,
                'row_data' => $row,
                'mapped_data' => [
                    $no,
                    $row->delivery_date ? date('d/m/Y', strtotime($row->delivery_date)) : '-',
                    $row->do_number ?? '-',
                    $row->packing_number ?? '-',
                    $row->floor_order_number ?? '-',
                    $row->nama_outlet ?? '-',
                    $row->warehouse_outlet_name ?? '-',
                    $row->created_by_name ?? '-',
                    $row->item_name ?? '-',
                    $row->item_sku ?? '-',
                    $row->category_name ?? '-',
                    $row->sub_category_name ?? '-',
                    number_format($row->qty_packing_list ?? 0, 2),
                    number_format($row->qty_scan ?? 0, 2),
                    $row->unit ?? '-'
                ]
            ]);
        }
        
        return [
            $no,
            $row->delivery_date ? date('d/m/Y', strtotime($row->delivery_date)) : '-',
            $row->do_number ?? '-',
            $row->packing_number ?? '-',
            $row->floor_order_number ?? '-',
            $row->nama_outlet ?? '-',
            $row->warehouse_outlet_name ?? '-',
            $row->created_by_name ?? '-',
            $row->item_name ?? '-',
            $row->item_sku ?? '-',
            $row->category_name ?? '-',
            $row->sub_category_name ?? '-',
            number_format($row->qty_packing_list ?? 0, 2),
            number_format($row->qty_scan ?? 0, 2),
            $row->unit ?? '-'
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
