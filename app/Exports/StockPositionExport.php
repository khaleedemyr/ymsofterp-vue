<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockPositionExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $warehouseId;

    public function __construct($warehouseId = null)
    {
        $this->warehouseId = $warehouseId;
    }

    public function collection()
    {
        $query = DB::table('food_inventory_stocks as s')
            ->join('food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('warehouses as w', 's.warehouse_id', '=', 'w.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'w.id as warehouse_id',
                'w.name as warehouse_name',
                's.qty_small',
                's.qty_medium',
                's.qty_large',
                's.value',
                's.last_cost_small',
                's.last_cost_medium',
                's.last_cost_large',
                's.updated_at',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name'
            )
            ->orderBy('w.name')
            ->orderBy('i.name');

        if ($this->warehouseId) {
            $query->where('w.id', $this->warehouseId);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Nama Barang',
            'Warehouse',
            'Qty Small',
            'Unit Small',
            'Qty Medium',
            'Unit Medium',
            'Qty Large',
            'Unit Large',
            'Tanggal Update'
        ];
    }

    public function map($row): array
    {
        return [
            $row->item_name,
            $row->warehouse_name,
            $row->qty_small ? number_format($row->qty_small, 2) : '0.00',
            $row->small_unit_name ?? '-',
            $row->qty_medium ? number_format($row->qty_medium, 2) : '0.00',
            $row->medium_unit_name ?? '-',
            $row->qty_large ? number_format($row->qty_large, 2) : '0.00',
            $row->large_unit_name ?? '-',
            $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->format('d/m/Y H:i:s') : '-'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30, // Nama Barang
            'B' => 20, // Warehouse
            'C' => 15, // Qty Small
            'D' => 15, // Unit Small
            'E' => 15, // Qty Medium
            'F' => 15, // Unit Medium
            'G' => 15, // Qty Large
            'H' => 15, // Unit Large
            'I' => 20, // Tanggal Update
        ];
    }
}
