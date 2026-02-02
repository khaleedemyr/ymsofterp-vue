<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\DB;

class InternalUseWasteReportSummaryExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        // Get all outlets
        $outlets = DB::table('tbl_data_outlet')
            ->select('id_outlet', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();
        
        $summaryData = [];
        
        // ===== FIX N+1 QUERY: Pre-load semua data sebelum loop outlet =====
        // Query semua data sekaligus untuk semua outlet
        $allOutletData = DB::table('outlet_internal_use_waste_headers as h')
            ->join('outlet_internal_use_waste_details as d', 'h.id', '=', 'd.header_id')
            ->join('items as item', 'd.item_id', '=', 'item.id')
            ->leftJoin('outlet_food_inventory_items as fi', 'item.id', '=', 'fi.item_id')
            ->whereIn('h.type', ['r_and_d', 'marketing', 'wrong_maker'])
            ->where('h.status', 'APPROVED')
            ->whereBetween('h.date', [$this->startDate, $this->endDate])
            ->select(
                'h.outlet_id',
                'h.id as header_id',
                'h.date',
                'd.qty',
                'd.unit_id',
                'fi.id as inventory_item_id',
                'item.small_unit_id',
                'item.medium_unit_id',
                'item.large_unit_id',
                'item.small_conversion_qty',
                'item.medium_conversion_qty',
                'h.warehouse_outlet_id'
            )
            ->get();
        
        // Group by outlet_id
        $dataByOutlet = $allOutletData->groupBy('outlet_id');
        
        // Pre-load MAC data untuk semua inventory items
        $allInventoryItemIds = $allOutletData->pluck('inventory_item_id')->filter()->unique()->toArray();
        $macDataCache = [];
        $stockDataCache = [];
        
        if (!empty($allInventoryItemIds)) {
            // Pre-load cost histories
            $costHistories = DB::table('outlet_food_inventory_cost_histories')
                ->whereIn('inventory_item_id', $allInventoryItemIds)
                ->where('date', '<=', $this->endDate)
                ->select('inventory_item_id', 'outlet_id', 'warehouse_outlet_id', 'mac', 'date')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get();
            
            foreach ($costHistories as $cost) {
                $key = $cost->inventory_item_id . '_' . $cost->outlet_id . '_' . $cost->warehouse_outlet_id;
                if (!isset($macDataCache[$key])) {
                    $macDataCache[$key] = (float) ($cost->mac ?? 0);
                }
            }
            
            // Pre-load stock data sebagai fallback
            $stockData = DB::table('outlet_food_inventory_stocks')
                ->whereIn('inventory_item_id', $allInventoryItemIds)
                ->select('inventory_item_id', 'id_outlet', 'warehouse_outlet_id', 'last_cost_small')
                ->get();
            
            foreach ($stockData as $stock) {
                $key = $stock->inventory_item_id . '_' . $stock->id_outlet . '_' . $stock->warehouse_outlet_id;
                $stockDataCache[$key] = (float) ($stock->last_cost_small ?? 0);
            }
        }
        // ===== END FIX N+1 QUERY =====
        
        foreach ($outlets as $outlet) {
            // ===== FIX N+1 QUERY: Gunakan data yang sudah di-group =====
            $items = $dataByOutlet->get($outlet->id_outlet, collect());
            
            if ($items->isEmpty()) {
                continue;
            }
            
            $totalMac = 0;
            
            foreach ($items as $row) {
                // ===== FIX N+1 QUERY: Gunakan cached MAC data =====
                $mac = 0;
                if ($row->inventory_item_id) {
                    $key = $row->inventory_item_id . '_' . $outlet->id_outlet . '_' . $row->warehouse_outlet_id;
                    $mac = $macDataCache[$key] ?? $stockDataCache[$key] ?? 0;
                }
                
                // Konversi qty ke small
                $qty = (float) $row->qty;
                $smallConv = (float) ($row->small_conversion_qty ?: 1);
                $mediumConv = (float) ($row->medium_conversion_qty ?: 1);
                
                $qtySmall = 0;
                if ($row->unit_id == $row->small_unit_id) {
                    $qtySmall = $qty;
                } elseif ($row->unit_id == $row->medium_unit_id) {
                    $qtySmall = $qty * $smallConv;
                } elseif ($row->unit_id == $row->large_unit_id) {
                    $qtySmall = $qty * $mediumConv * $smallConv;
                }
                
                // Hitung subtotal MAC
                $subtotalMac = $qtySmall * $mac;
                $totalMac += $subtotalMac;
            }
            
            if ($totalMac > 0) {
                $summaryData[] = (object) [
                    'outlet_id' => $outlet->id_outlet,
                    'outlet_name' => $outlet->name,
                    'total_mac' => $totalMac,
                ];
            }
        }
        
        return collect($summaryData);
    }

    public function headings(): array
    {
        return [
            'No',
            'Outlet',
            'Total MAC',
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;
        
        return [
            $no,
            $row->outlet_name,
            $row->total_mac,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 40,
            'C' => 20,
        ];
    }
}

