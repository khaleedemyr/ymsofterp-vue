<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletStockPositionExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $outletId;
    protected $warehouseOutletId;
    protected $search;

    public function __construct($outletId = null, $warehouseOutletId = null, $search = null)
    {
        $this->outletId = $outletId;
        $this->warehouseOutletId = $warehouseOutletId;
        $this->search = $search;
    }

    public function collection()
    {
        $query = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('tbl_data_outlet as o', 's.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'c.name as category_name',
                'o.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
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
                'ul.name as large_unit_name',
                'wo.name as warehouse_outlet_name',
                's.warehouse_outlet_id'
            )
            ->orderBy('c.name')
            ->orderBy('i.name');

        // Apply filters
        if ($this->outletId) {
            $query->where('s.id_outlet', $this->outletId);
        }
        if ($this->warehouseOutletId) {
            $query->where('s.warehouse_outlet_id', $this->warehouseOutletId);
        }
        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('i.name', 'like', "%{$search}%")
                  ->orWhere('c.name', 'like', "%{$search}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Kategori',
            'Nama Barang',
            'Outlet',
            'Warehouse Outlet',
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
            $row->category_name ?? '-',
            $row->item_name,
            $row->outlet_name,
            $row->warehouse_outlet_name ?? '-',
            $row->qty_small ? number_format($row->qty_small, 2, ',', '.') : '0,00',
            $row->small_unit_name ?? '-',
            $row->qty_medium ? number_format($row->qty_medium, 2, ',', '.') : '0,00',
            $row->medium_unit_name ?? '-',
            $row->qty_large ? number_format($row->qty_large, 2, ',', '.') : '0,00',
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
            'A' => 20, // Kategori
            'B' => 30, // Nama Barang
            'C' => 20, // Outlet
            'D' => 20, // Warehouse Outlet
            'E' => 15, // Qty Small
            'F' => 15, // Unit Small
            'G' => 15, // Qty Medium
            'H' => 15, // Unit Medium
            'I' => 15, // Qty Large
            'J' => 15, // Unit Large
            'K' => 20, // Tanggal Update
        ];
    }
}

