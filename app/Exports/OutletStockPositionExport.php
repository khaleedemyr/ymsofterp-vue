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
        $latestCardKeys = DB::table('outlet_food_inventory_cards')
            ->select(
                'inventory_item_id',
                'id_outlet',
                'warehouse_outlet_id',
                DB::raw("MAX(CONCAT(DATE(`date`), ' ', LPAD(id, 20, '0'))) as max_key")
            )
            ->when($this->outletId, fn ($q) => $q->where('id_outlet', $this->outletId))
            ->when($this->warehouseOutletId, fn ($q) => $q->where('warehouse_outlet_id', $this->warehouseOutletId))
            ->groupBy('inventory_item_id', 'id_outlet', 'warehouse_outlet_id');

        $query = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('tbl_data_outlet as o', 's.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoinSub($latestCardKeys, 'lck', function ($join) {
                $join->on('lck.inventory_item_id', '=', 's.inventory_item_id')
                    ->on('lck.id_outlet', '=', 's.id_outlet')
                    ->on('lck.warehouse_outlet_id', '=', 's.warehouse_outlet_id');
            })
            ->leftJoin('outlet_food_inventory_cards as lc', function ($join) {
                $join->on('lc.inventory_item_id', '=', 'lck.inventory_item_id')
                    ->on('lc.id_outlet', '=', 'lck.id_outlet')
                    ->on('lc.warehouse_outlet_id', '=', 'lck.warehouse_outlet_id')
                    ->whereRaw("CONCAT(DATE(lc.date), ' ', LPAD(lc.id, 20, '0')) = lck.max_key");
            })
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'c.name as category_name',
                'o.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
                DB::raw('COALESCE(lc.saldo_qty_small, s.qty_small) as qty_small'),
                DB::raw('COALESCE(lc.saldo_qty_medium, s.qty_medium) as qty_medium'),
                DB::raw('COALESCE(lc.saldo_qty_large, s.qty_large) as qty_large'),
                DB::raw('COALESCE(lc.saldo_value, s.value) as value'),
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
            $row->item_name ?? '-',
            $row->outlet_name ?? '-',
            $row->warehouse_outlet_name ?? '-',
            $row->qty_small !== null ? (float) $row->qty_small : 0,
            $row->small_unit_name ?? '',
            $row->qty_medium !== null ? (float) $row->qty_medium : 0,
            $row->medium_unit_name ?? '',
            $row->qty_large !== null ? (float) $row->qty_large : 0,
            $row->large_unit_name ?? '',
            $row->updated_at ? date('Y-m-d H:i:s', strtotime($row->updated_at)) : '-',
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
            'A' => 20,
            'B' => 40,
            'C' => 20,
            'D' => 20,
            'E' => 12,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 12,
            'J' => 12,
            'K' => 20,
        ];
    }
}
