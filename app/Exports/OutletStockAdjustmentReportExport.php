<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class OutletStockAdjustmentReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $type;
    protected $warehouseOutletId;
    protected $outletId;
    protected $from;
    protected $to;
    protected $userOutletId;

    public function __construct($type, $warehouseOutletId, $outletId, $from, $to, $userOutletId)
    {
        $this->type = $type;
        $this->warehouseOutletId = $warehouseOutletId;
        $this->outletId = $outletId;
        $this->from = $from;
        $this->to = $to;
        $this->userOutletId = $userOutletId;
    }

    public function collection()
    {
        $query = DB::table('outlet_food_inventory_adjustments as adj')
            ->leftJoin('tbl_data_outlet as o', 'adj.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'adj.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'adj.created_by', '=', 'u.id')
            ->select(
                'adj.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );
            
        if ($this->userOutletId != 1) {
            $query->where('adj.id_outlet', $this->userOutletId);
        } else if ($this->outletId) {
            $query->where('adj.id_outlet', $this->outletId);
        }
        
        if ($this->type) {
            $query->where('adj.type', $this->type);
        }
        
        if ($this->warehouseOutletId) {
            $query->where('adj.warehouse_outlet_id', $this->warehouseOutletId);
        }
        
        $query->where('adj.date', '>=', $this->from);
        $query->where('adj.date', '<=', $this->to);
        $query->where('adj.status', 'approved');
        
        $data = $query->orderByDesc('adj.date')->orderByDesc('adj.id')->get();

        // Hitung subtotal MAC untuk setiap header
        $headerIds = $data->pluck('id')->all();
        $subtotalPerHeader = [];
        
        if ($headerIds && count($headerIds) > 0) {
            $details = DB::table('outlet_food_inventory_adjustment_items as i')
                ->join('outlet_food_inventory_adjustments as adj', 'i.adjustment_id', '=', 'adj.id')
                ->leftJoin('items as it', 'i.item_id', '=', 'it.id')
                ->leftJoin('outlet_food_inventory_items as fi', 'it.id', '=', 'fi.item_id')
                ->leftJoin('units as u_small', 'it.small_unit_id', '=', 'u_small.id')
                ->leftJoin('units as u_medium', 'it.medium_unit_id', '=', 'u_medium.id')
                ->leftJoin('units as u_large', 'it.large_unit_id', '=', 'u_large.id')
                ->select(
                    'i.*',
                    'adj.type as adjustment_type',
                    'adj.date as adjustment_date',
                    'adj.id_outlet',
                    'adj.warehouse_outlet_id',
                    'it.small_unit_id',
                    'it.medium_unit_id',
                    'it.large_unit_id',
                    'it.small_conversion_qty',
                    'it.medium_conversion_qty',
                    'u_small.name as small_unit_name',
                    'u_medium.name as medium_unit_name',
                    'u_large.name as large_unit_name',
                    'fi.id as inventory_item_id'
                )
                ->whereIn('i.adjustment_id', $headerIds)
                ->get();
            
            $itemIds = $details->pluck('item_id')->unique()->all();
            $inventoryItems = [];
            if (count($itemIds) > 0) {
                $inventoryItemsData = DB::table('outlet_food_inventory_items')
                    ->whereIn('item_id', $itemIds)
                    ->get()
                    ->keyBy('item_id');
                $inventoryItems = $inventoryItemsData->toArray();
            }
            
            $inventoryItemIds = collect($inventoryItems)->pluck('id')->unique()->all();
            $macHistories = [];
            if (count($inventoryItemIds) > 0 && count($headerIds) > 0) {
                $headerData = $data->keyBy('id');
                $macQueryConditions = [];
                foreach ($details as $detail) {
                    $header = $headerData->get($detail->adjustment_id);
                    if ($header && isset($inventoryItems[$detail->item_id])) {
                        $inventoryItemId = $inventoryItems[$detail->item_id]->id;
                        $key = "{$inventoryItemId}_{$header->id_outlet}_{$header->warehouse_outlet_id}_{$header->date}";
                        if (!isset($macQueryConditions[$key])) {
                            $macQueryConditions[$key] = [
                                'inventory_item_id' => $inventoryItemId,
                                'id_outlet' => $header->id_outlet,
                                'warehouse_outlet_id' => $header->warehouse_outlet_id,
                                'date' => $header->date
                            ];
                        }
                    }
                }
                
                foreach ($macQueryConditions as $condition) {
                    $macRow = DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $condition['inventory_item_id'])
                        ->where('id_outlet', $condition['id_outlet'])
                        ->where('warehouse_outlet_id', $condition['warehouse_outlet_id'])
                        ->where('date', '<=', $condition['date'])
                        ->orderByDesc('date')
                        ->orderByDesc('id')
                        ->first();
                    if ($macRow) {
                        $macKey = "{$condition['inventory_item_id']}_{$condition['id_outlet']}_{$condition['warehouse_outlet_id']}_{$condition['date']}";
                        $macHistories[$macKey] = $macRow->mac;
                    }
                }
            }
            
            foreach ($details as $item) {
                $mac = null;
                if (isset($inventoryItems[$item->item_id])) {
                    $inventoryItem = $inventoryItems[$item->item_id];
                    $header = $data->firstWhere('id', $item->adjustment_id);
                    if ($header) {
                        $macKey = "{$inventoryItem->id}_{$header->id_outlet}_{$header->warehouse_outlet_id}_{$header->date}";
                        if (isset($macHistories[$macKey])) {
                            $mac = $macHistories[$macKey];
                        }
                    }
                }
                
                $qty_small = $item->qty;
                if ($item->unit == $item->medium_unit_name && $item->small_conversion_qty > 0) {
                    $qty_small = $item->qty * $item->small_conversion_qty;
                } elseif ($item->unit == $item->large_unit_name && $item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) {
                    $qty_small = $item->qty * $item->small_conversion_qty * $item->medium_conversion_qty;
                }
                
                $subtotal_mac = ($mac !== null) ? ($mac * $qty_small) : 0;
                
                if (!isset($subtotalPerHeader[$item->adjustment_id])) {
                    $subtotalPerHeader[$item->adjustment_id] = 0;
                }
                $subtotalPerHeader[$item->adjustment_id] += $subtotal_mac;
            }
            
            $data = collect($data)->map(function($row) use ($subtotalPerHeader) {
                $row->subtotal_mac = $subtotalPerHeader[$row->id] ?? 0;
                return $row;
            });
        } else {
            $data = collect($data)->map(function($row) {
                $row->subtotal_mac = 0;
                return $row;
            });
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'No. Adjustment',
            'Tanggal',
            'Tipe',
            'Outlet',
            'Warehouse Outlet',
            'Alasan',
            'Subtotal MAC',
            'Dibuat Oleh',
            'Status'
        ];
    }

    public function map($row): array
    {
        return [
            $row->number ?? '-',
            $row->date ? Carbon::parse($row->date)->format('d/m/Y') : '-',
            $row->type === 'in' ? 'Stock In' : 'Stock Out',
            $row->outlet_name ?? '-',
            $row->warehouse_outlet_name ?? '-',
            $row->reason ?? '-',
            $row->subtotal_mac ?? 0,
            $row->creator_name ?? '-',
            'Approved'
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // No. Adjustment
            'B' => 15, // Tanggal
            'C' => 12, // Tipe
            'D' => 25, // Outlet
            'E' => 25, // Warehouse Outlet
            'F' => 40, // Alasan
            'G' => 18, // Subtotal MAC
            'H' => 25, // Dibuat Oleh
            'I' => 12, // Status
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E3F2FD']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}

