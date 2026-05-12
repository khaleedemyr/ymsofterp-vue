<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AssetStockPositionExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $outletId;
    protected $warehouseOutletId;

    public function __construct($outletId = null, $warehouseOutletId = null)
    {
        $this->outletId = $outletId;
        $this->warehouseOutletId = $warehouseOutletId;
    }

    public function collection()
    {
        $query = DB::table('asset_inventory_stocks as s')
            ->join('asset_inventory_items as ai', 's.inventory_item_id', '=', 'ai.id')
            ->join('items as i', 'ai.item_id', '=', 'i.id')
            ->join('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->select(
                'i.name as item_name',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_name',
                's.qty_small', 's.qty_medium', 's.qty_large',
                's.value',
                's.last_cost_small', 's.last_cost_medium', 's.last_cost_large',
                's.updated_at',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name'
            )
            ->orderBy('o.nama_outlet')
            ->orderBy('wo.name')
            ->orderBy('i.name');

        if ($this->outletId) {
            $query->where('s.outlet_id', $this->outletId);
        }
        if ($this->warehouseOutletId) {
            $query->where('s.warehouse_outlet_id', $this->warehouseOutletId);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Nama Barang',
            'Outlet',
            'Warehouse',
            'Qty Small', 'Unit Small',
            'Qty Medium', 'Unit Medium',
            'Qty Large', 'Unit Large',
            'Value',
            'Tanggal Update',
        ];
    }

    public function map($row): array
    {
        return [
            $row->item_name,
            $row->outlet_name ?? '-',
            $row->warehouse_name,
            $row->qty_small ? number_format($row->qty_small, 2) : '0.00',
            $row->small_unit_name ?? '-',
            $row->qty_medium ? number_format($row->qty_medium, 2) : '0.00',
            $row->medium_unit_name ?? '-',
            $row->qty_large ? number_format($row->qty_large, 2) : '0.00',
            $row->large_unit_name ?? '-',
            $row->value ? number_format($row->value, 0) : '0',
            $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->format('d/m/Y H:i:s') : '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 25,
            'C' => 20,
            'D' => 15, 'E' => 12,
            'F' => 15, 'G' => 12,
            'H' => 15, 'I' => 12,
            'J' => 18,
            'K' => 20,
        ];
    }
}
