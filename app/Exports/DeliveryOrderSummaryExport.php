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

class DeliveryOrderSummaryExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, Responsable
{
    protected $data;
    public $fileName = 'delivery_order_summary.xlsx';

    public function __construct($data)
    {
        $this->data = $data;
        $this->fileName = 'delivery_order_summary_' . date('Y-m-d_H-i-s') . '.xlsx';
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'No',
            'Item Name',
            'SKU',
            'Category',
            'Sub Category',
            'Unit',
            'Total Qty Packing List',
            'Total Qty Scan',
            'Total DO Count',
            'Outlets',
            'Warehouse Outlets',
            'Unit Details'
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;
        
        // Format unit details
        $unitDetails = '';
        if ($row->unit) {
            $unitDetails = "Used Unit: " . $row->unit;
            
            // Add master unit information
            $masterUnits = [];
            if ($row->small_unit_name) {
                $masterUnits[] = "Small: " . $row->small_unit_name;
            }
            if ($row->medium_unit_name) {
                $masterUnits[] = "Medium: " . $row->medium_unit_name;
            }
            if ($row->large_unit_name) {
                $masterUnits[] = "Large: " . $row->large_unit_name;
            }
            
            if (!empty($masterUnits)) {
                $unitDetails .= " | Master Units: " . implode(", ", $masterUnits);
            }
            
            // Add conversion info if available
            if ($row->small_conversion_qty || $row->medium_conversion_qty) {
                $conversions = [];
                if ($row->small_conversion_qty) {
                    $conversions[] = "Small Conv: " . $row->small_conversion_qty;
                }
                if ($row->medium_conversion_qty) {
                    $conversions[] = "Medium Conv: " . $row->medium_conversion_qty;
                }
                if (!empty($conversions)) {
                    $unitDetails .= " | " . implode(", ", $conversions);
                }
            }
        }
        
        return [
            $no,
            $row->item_name ?? '-',
            $row->item_sku ?? '-',
            $row->category_name ?? '-',
            $row->sub_category_name ?? '-',
            $row->unit ?? '-',
            number_format($row->total_qty_packing_list ?? 0, 2),
            number_format($row->total_qty_scan ?? 0, 2),
            $row->total_do_count ?? 0,
            $row->outlets ?? '-',
            $row->warehouse_outlets ?? '-',
            $unitDetails
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
