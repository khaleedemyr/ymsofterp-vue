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

class OutletStockReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $outletId;
    protected $warehouseOutletId;
    protected $bulan;
    protected $search;

    public function __construct($outletId, $warehouseOutletId, $bulan, $search = '')
    {
        $this->outletId = $outletId;
        $this->warehouseOutletId = $warehouseOutletId;
        $this->bulan = $bulan;
        $this->search = $search;
    }

    public function collection()
    {
        // Parse bulan
        $bulanCarbon = Carbon::parse($this->bulan . '-01');
        $bulanSebelumnya = $bulanCarbon->copy()->subMonth();
        $tanggalAwalBulan = $bulanCarbon->format('Y-m-01');
        $tanggalAkhirBulanSebelumnya = $bulanSebelumnya->format('Y-m-t');
        $tanggal1BulanIni = $bulanCarbon->format('Y-m-01');
        $tanggalAkhirBulan = $bulanCarbon->format('Y-m-t');

        // Query sama dengan controller index - ambil semua data tanpa pagination
        $query = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('units as u_small', 'i.small_unit_id', '=', 'u_small.id')
            ->leftJoin('units as u_medium', 'i.medium_unit_id', '=', 'u_medium.id')
            ->leftJoin('units as u_large', 'i.large_unit_id', '=', 'u_large.id')
            ->where('s.id_outlet', $this->outletId)
            ->where('s.warehouse_outlet_id', $this->warehouseOutletId)
            ->select(
                'fi.id as inventory_item_id',
                'i.id as item_id',
                'i.sku as item_code',
                'i.name as item_name',
                'c.id as category_id',
                'c.name as category_name',
                'u_small.name as small_unit_name',
                'u_medium.name as medium_unit_name',
                'u_large.name as large_unit_name',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            )
            ->distinct();
        
        // Search filter
        if ($this->search) {
            $query->where(function($q) {
                $q->where('i.sku', 'like', "%{$this->search}%")
                  ->orWhere('i.name', 'like', "%{$this->search}%")
                  ->orWhere('c.name', 'like', "%{$this->search}%");
            });
        }
        
        $inventoryItems = $query->orderBy('c.name', 'asc')
            ->orderBy('i.name', 'asc')
            ->get();
        
        // Hitung semua data seperti di controller (good receive, good sold, dll)
        // Good Receive
        $goodReceiveData = [];
        
        $outletGoodReceives = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
            ->join('items as i', 'gri.item_id', '=', 'i.id')
            ->where('gr.outlet_id', $this->outletId)
            ->where('gr.warehouse_outlet_id', $this->warehouseOutletId)
            ->whereYear('gr.receive_date', $bulanCarbon->year)
            ->whereMonth('gr.receive_date', $bulanCarbon->month)
            ->whereNull('gr.deleted_at')
            ->select(
                'gri.item_id',
                'gri.received_qty',
                'gri.unit_id',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            )
            ->get();
        
        foreach ($outletGoodReceives as $gr) {
            $itemId = $gr->item_id;
            if (!isset($goodReceiveData[$itemId])) {
                $goodReceiveData[$itemId] = ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0];
            }
            
            $receivedQty = (float) $gr->received_qty;
            $smallConv = (float) ($gr->small_conversion_qty ?: 1);
            $mediumConv = (float) ($gr->medium_conversion_qty ?: 1);
            
            if ($gr->unit_id == $gr->small_unit_id) {
                $goodReceiveData[$itemId]['qty_small'] += $receivedQty;
                $goodReceiveData[$itemId]['qty_medium'] += $smallConv > 0 ? $receivedQty / $smallConv : 0;
                $goodReceiveData[$itemId]['qty_large'] += ($smallConv > 0 && $mediumConv > 0) ? $receivedQty / ($smallConv * $mediumConv) : 0;
            } elseif ($gr->unit_id == $gr->medium_unit_id) {
                $goodReceiveData[$itemId]['qty_medium'] += $receivedQty;
                $goodReceiveData[$itemId]['qty_small'] += $receivedQty * $smallConv;
                $goodReceiveData[$itemId]['qty_large'] += $mediumConv > 0 ? $receivedQty / $mediumConv : 0;
            } elseif ($gr->unit_id == $gr->large_unit_id) {
                $goodReceiveData[$itemId]['qty_large'] += $receivedQty;
                $goodReceiveData[$itemId]['qty_medium'] += $receivedQty * $mediumConv;
                $goodReceiveData[$itemId]['qty_small'] += $receivedQty * $mediumConv * $smallConv;
            }
        }
        
        // Retail Food
        $retailFoods = DB::table('retail_food as rf')
            ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
            ->join('items as i', function($join) {
                $join->on(DB::raw('TRIM(i.name)'), '=', DB::raw('TRIM(rfi.item_name)'));
            })
            ->leftJoin('units as u_small', 'i.small_unit_id', '=', 'u_small.id')
            ->leftJoin('units as u_medium', 'i.medium_unit_id', '=', 'u_medium.id')
            ->leftJoin('units as u_large', 'i.large_unit_id', '=', 'u_large.id')
            ->where('rf.outlet_id', $this->outletId)
            ->where('rf.warehouse_outlet_id', $this->warehouseOutletId)
            ->whereYear('rf.transaction_date', $bulanCarbon->year)
            ->whereMonth('rf.transaction_date', $bulanCarbon->month)
            ->where('rf.status', 'approved')
            ->select(
                'i.id as item_id',
                'rfi.qty',
                'rfi.unit',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'u_small.name as small_unit_name',
                'u_medium.name as medium_unit_name',
                'u_large.name as large_unit_name'
            )
            ->get();
        
        foreach ($retailFoods as $rf) {
            $itemId = $rf->item_id;
            if (!isset($goodReceiveData[$itemId])) {
                $goodReceiveData[$itemId] = ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0];
            }
            
            $unitId = null;
            $unitName = trim($rf->unit);
            
            if ($rf->small_unit_name && trim($rf->small_unit_name) === $unitName) {
                $unitId = $rf->small_unit_id;
            } elseif ($rf->medium_unit_name && trim($rf->medium_unit_name) === $unitName) {
                $unitId = $rf->medium_unit_id;
            } elseif ($rf->large_unit_name && trim($rf->large_unit_name) === $unitName) {
                $unitId = $rf->large_unit_id;
            }
            
            if ($unitId) {
                $qty = (float) $rf->qty;
                $smallConv = (float) ($rf->small_conversion_qty ?: 1);
                $mediumConv = (float) ($rf->medium_conversion_qty ?: 1);
                
                if ($unitId == $rf->small_unit_id) {
                    $goodReceiveData[$itemId]['qty_small'] += $qty;
                    $goodReceiveData[$itemId]['qty_medium'] += $smallConv > 0 ? $qty / $smallConv : 0;
                    $goodReceiveData[$itemId]['qty_large'] += ($smallConv > 0 && $mediumConv > 0) ? $qty / ($smallConv * $mediumConv) : 0;
                } elseif ($unitId == $rf->medium_unit_id) {
                    $goodReceiveData[$itemId]['qty_medium'] += $qty;
                    $goodReceiveData[$itemId]['qty_small'] += $qty * $smallConv;
                    $goodReceiveData[$itemId]['qty_large'] += $mediumConv > 0 ? $qty / $mediumConv : 0;
                } elseif ($unitId == $rf->large_unit_id) {
                    $goodReceiveData[$itemId]['qty_large'] += $qty;
                    $goodReceiveData[$itemId]['qty_medium'] += $qty * $mediumConv;
                    $goodReceiveData[$itemId]['qty_small'] += $qty * $mediumConv * $smallConv;
                }
            }
        }
        
        // Good Sold
        $goodSoldData = [];
        $stockCutDetails = DB::table('stock_cut_details as scd')
            ->join('stock_cut_logs as scl', 'scd.stock_cut_log_id', '=', 'scl.id')
            ->where('scl.outlet_id', $this->outletId)
            ->where('scd.warehouse_outlet_id', $this->warehouseOutletId)
            ->whereBetween('scl.tanggal', [$tanggalAwalBulan, $tanggalAkhirBulan])
            ->where('scl.status', 'success')
            ->select(
                'scd.item_id',
                DB::raw('SUM(COALESCE(scd.qty_small, 0)) as total_out_qty_small'),
                DB::raw('SUM(COALESCE(scd.qty_medium, 0)) as total_out_qty_medium'),
                DB::raw('SUM(COALESCE(scd.qty_large, 0)) as total_out_qty_large')
            )
            ->groupBy('scd.item_id')
            ->havingRaw('SUM(COALESCE(scd.qty_small, 0)) > 0 OR SUM(COALESCE(scd.qty_medium, 0)) > 0 OR SUM(COALESCE(scd.qty_large, 0)) > 0')
            ->get();
        
        foreach ($stockCutDetails as $detail) {
            $goodSoldData[$detail->item_id] = [
                'qty_small' => (float) ($detail->total_out_qty_small ?? 0),
                'qty_medium' => (float) ($detail->total_out_qty_medium ?? 0),
                'qty_large' => (float) ($detail->total_out_qty_large ?? 0),
            ];
        }
        
        // Internal Use Waste
        $internalUseWasteData = [];
        $typesRequiringApproval = ['r_and_d', 'marketing', 'wrong_maker'];
        $typesNotRequiringApproval = ['internal_use', 'spoil', 'waste', 'non_commodity', 'guest_supplies'];
        
        $approvalTypes = DB::table('outlet_food_inventory_cards as card')
            ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
            ->join('outlet_internal_use_waste_headers as h', 'card.reference_id', '=', 'h.id')
            ->where('card.id_outlet', $this->outletId)
            ->where('card.warehouse_outlet_id', $this->warehouseOutletId)
            ->where('card.reference_type', 'outlet_internal_use_waste')
            ->whereIn('h.type', $typesRequiringApproval)
            ->where('h.status', 'APPROVED')
            ->whereBetween('card.date', [$tanggalAwalBulan, $tanggalAkhirBulan])
            ->select(
                'fi.item_id',
                'h.type',
                DB::raw('SUM(COALESCE(card.out_qty_small, 0)) as total_out_qty_small'),
                DB::raw('SUM(COALESCE(card.out_qty_medium, 0)) as total_out_qty_medium'),
                DB::raw('SUM(COALESCE(card.out_qty_large, 0)) as total_out_qty_large')
            )
            ->groupBy('fi.item_id', 'h.type')
            ->get();
        
        $nonApprovalTypes = DB::table('outlet_food_inventory_cards as card')
            ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
            ->join('outlet_internal_use_waste_headers as h', 'card.reference_id', '=', 'h.id')
            ->where('card.id_outlet', $this->outletId)
            ->where('card.warehouse_outlet_id', $this->warehouseOutletId)
            ->where('card.reference_type', 'outlet_internal_use_waste')
            ->whereIn('h.type', $typesNotRequiringApproval)
            ->whereIn('h.status', ['PROCESSED', 'APPROVED'])
            ->whereBetween('card.date', [$tanggalAwalBulan, $tanggalAkhirBulan])
            ->select(
                'fi.item_id',
                'h.type',
                DB::raw('SUM(COALESCE(card.out_qty_small, 0)) as total_out_qty_small'),
                DB::raw('SUM(COALESCE(card.out_qty_medium, 0)) as total_out_qty_medium'),
                DB::raw('SUM(COALESCE(card.out_qty_large, 0)) as total_out_qty_large')
            )
            ->groupBy('fi.item_id', 'h.type')
            ->get();
        
        $allInternalUseWaste = $approvalTypes->merge($nonApprovalTypes);
        $typeMapping = [
            'waste' => 'wasted',
            'spoil' => 'spoil',
            'guest_supplies' => 'guest_supplies',
            'r_and_d' => 'rnd',
            'marketing' => 'marketing',
            'wrong_maker' => 'wrong_maker',
            'internal_use' => 'internal_used',
            'non_commodity' => 'non_commodity',
        ];
        
        foreach ($allInternalUseWaste as $data) {
            $itemId = $data->item_id;
            $type = $data->type;
            $columnKey = $typeMapping[$type] ?? null;
            
            if (!$columnKey) continue;
            
            if (!isset($internalUseWasteData[$itemId])) {
                $internalUseWasteData[$itemId] = [
                    'wasted' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                    'spoil' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                    'guest_supplies' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                    'rnd' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                    'marketing' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                    'wrong_maker' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                    'internal_used' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                    'non_commodity' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                ];
            }
            
            $internalUseWasteData[$itemId][$columnKey] = [
                'qty_small' => (float) ($data->total_out_qty_small ?? 0),
                'qty_medium' => (float) ($data->total_out_qty_medium ?? 0),
                'qty_large' => (float) ($data->total_out_qty_large ?? 0),
            ];
        }
        
        // WIP Production
        $wipProductionData = [];
        $wipStockCards = DB::table('outlet_food_inventory_cards as card')
            ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
            ->leftJoin('outlet_wip_production_headers as h', function($join) {
                $join->on('card.reference_id', '=', 'h.id')
                     ->where('card.reference_type', '=', 'outlet_wip_production');
            })
            ->where('card.id_outlet', $this->outletId)
            ->where('card.warehouse_outlet_id', $this->warehouseOutletId)
            ->where('card.reference_type', 'outlet_wip_production')
            ->where(function($query) {
                $query->where('h.status', 'PROCESSED')->orWhereNull('h.status');
            })
            ->whereBetween('card.date', [$tanggalAwalBulan, $tanggalAkhirBulan])
            ->select(
                'fi.item_id',
                DB::raw('SUM(COALESCE(card.in_qty_small, 0)) as total_in_qty_small'),
                DB::raw('SUM(COALESCE(card.in_qty_medium, 0)) as total_in_qty_medium'),
                DB::raw('SUM(COALESCE(card.in_qty_large, 0)) as total_in_qty_large'),
                DB::raw('SUM(COALESCE(card.out_qty_small, 0)) as total_out_qty_small'),
                DB::raw('SUM(COALESCE(card.out_qty_medium, 0)) as total_out_qty_medium'),
                DB::raw('SUM(COALESCE(card.out_qty_large, 0)) as total_out_qty_large')
            )
            ->groupBy('fi.item_id')
            ->get();
        
        foreach ($wipStockCards as $data) {
            $itemId = $data->item_id;
            if (!isset($wipProductionData[$itemId])) {
                $wipProductionData[$itemId] = [
                    'wip_in' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                    'wip_out' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                ];
            }
            
            $wipProductionData[$itemId]['wip_in'] = [
                'qty_small' => (float) ($data->total_in_qty_small ?? 0),
                'qty_medium' => (float) ($data->total_in_qty_medium ?? 0),
                'qty_large' => (float) ($data->total_in_qty_large ?? 0),
            ];
            
            $wipProductionData[$itemId]['wip_out'] = [
                'qty_small' => (float) ($data->total_out_qty_small ?? 0),
                'qty_medium' => (float) ($data->total_out_qty_medium ?? 0),
                'qty_large' => (float) ($data->total_out_qty_large ?? 0),
            ];
        }
        
        // Internal Warehouse Transfer
        $internalWarehouseTransferData = [];
        $internalTransferStockCards = DB::table('outlet_food_inventory_cards as card')
            ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
            ->where('card.id_outlet', $this->outletId)
            ->where('card.warehouse_outlet_id', $this->warehouseOutletId)
            ->where('card.reference_type', 'internal_warehouse_transfer')
            ->whereBetween('card.date', [$tanggalAwalBulan, $tanggalAkhirBulan])
            ->select(
                'fi.item_id',
                DB::raw('SUM(COALESCE(card.in_qty_small, 0)) as total_in_qty_small'),
                DB::raw('SUM(COALESCE(card.in_qty_medium, 0)) as total_in_qty_medium'),
                DB::raw('SUM(COALESCE(card.in_qty_large, 0)) as total_in_qty_large'),
                DB::raw('SUM(COALESCE(card.out_qty_small, 0)) as total_out_qty_small'),
                DB::raw('SUM(COALESCE(card.out_qty_medium, 0)) as total_out_qty_medium'),
                DB::raw('SUM(COALESCE(card.out_qty_large, 0)) as total_out_qty_large')
            )
            ->groupBy('fi.item_id')
            ->get();
        
        foreach ($internalTransferStockCards as $data) {
            $itemId = $data->item_id;
            if (!isset($internalWarehouseTransferData[$itemId])) {
                $internalWarehouseTransferData[$itemId] = [
                    'internal_transfer_in' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                    'internal_transfer_out' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                ];
            }
            
            $internalWarehouseTransferData[$itemId]['internal_transfer_in'] = [
                'qty_small' => (float) ($data->total_in_qty_small ?? 0),
                'qty_medium' => (float) ($data->total_in_qty_medium ?? 0),
                'qty_large' => (float) ($data->total_in_qty_large ?? 0),
            ];
            
            $internalWarehouseTransferData[$itemId]['internal_transfer_out'] = [
                'qty_small' => (float) ($data->total_out_qty_small ?? 0),
                'qty_medium' => (float) ($data->total_out_qty_medium ?? 0),
                'qty_large' => (float) ($data->total_out_qty_large ?? 0),
            ];
        }
        
        // Stock Opname — hanya LAST stock opname per item (bukan di-SUM)
        $stockOpnameData = [];
        $stockOpnameItems = DB::table('outlet_stock_opname_items as soi')
            ->join('outlet_stock_opnames as so', 'soi.stock_opname_id', '=', 'so.id')
            ->join('outlet_food_inventory_items as fi', 'soi.inventory_item_id', '=', 'fi.id')
            ->where('so.outlet_id', $this->outletId)
            ->where('so.warehouse_outlet_id', $this->warehouseOutletId)
            ->whereIn('so.status', ['APPROVED', 'COMPLETED'])
            ->whereYear('so.opname_date', $bulanCarbon->year)
            ->whereMonth('so.opname_date', $bulanCarbon->month)
            ->select(
                'fi.item_id',
                'soi.qty_physical_small',
                'soi.qty_physical_medium',
                'soi.qty_physical_large',
                'soi.mac_after',
                'soi.mac_before',
                'so.opname_date',
                'so.id as so_id'
            )
            ->orderBy('so.opname_date', 'desc')
            ->orderBy('so.id', 'desc')
            ->get();
        
        foreach ($stockOpnameItems as $data) {
            if (isset($stockOpnameData[$data->item_id])) {
                continue;
            }
            $stockOpnameData[$data->item_id] = [
                'qty_small' => (float) ($data->qty_physical_small ?? 0),
                'qty_medium' => (float) ($data->qty_physical_medium ?? 0),
                'qty_large' => (float) ($data->qty_physical_large ?? 0),
                'mac' => (float) ($data->mac_after ?? $data->mac_before ?? 0),
            ];
        }
        
        // Begin Inventory = Last Stock Opname Physical bulan LALU (mis. laporan Jan → ambil last opname Des)
        // Juga include opname dengan opname_date = tgl 1 bulan laporan (opname yang disimpan ~23:00 akhir bulan s/d ~05:00 tgl 1)
        $beginInventoryFromOpname = [];
        $beginOpnamePrevMonth = DB::table('outlet_stock_opname_items as soi')
            ->join('outlet_stock_opnames as so', 'soi.stock_opname_id', '=', 'so.id')
            ->join('outlet_food_inventory_items as fi', 'soi.inventory_item_id', '=', 'fi.id')
            ->where('so.outlet_id', $this->outletId)
            ->where('so.warehouse_outlet_id', $this->warehouseOutletId)
            ->whereIn('so.status', ['APPROVED', 'COMPLETED'])
            ->where(function ($q) use ($bulanSebelumnya, $tanggal1BulanIni) {
                $q->where(function ($q2) use ($bulanSebelumnya) {
                    $q2->whereYear('so.opname_date', $bulanSebelumnya->year)
                       ->whereMonth('so.opname_date', $bulanSebelumnya->month);
                })->orWhereDate('so.opname_date', $tanggal1BulanIni);
            })
            ->select('fi.item_id', 'soi.qty_physical_small', 'soi.qty_physical_medium', 'soi.qty_physical_large', 'so.opname_date', 'so.id as so_id')
            ->orderBy('so.opname_date', 'desc')
            ->orderBy('so.id', 'desc')
            ->get();
        foreach ($beginOpnamePrevMonth as $d) {
            if (isset($beginInventoryFromOpname[$d->item_id])) {
                continue;
            }
            $beginInventoryFromOpname[$d->item_id] = [
                'qty_small' => (float) ($d->qty_physical_small ?? 0),
                'qty_medium' => (float) ($d->qty_physical_medium ?? 0),
                'qty_large' => (float) ($d->qty_physical_large ?? 0),
            ];
        }
        
        // Fallback Begin: jika tidak ada stock opname bulan lalu, ambil dari upload saldo awal (reference_type=initial_balance)
        $beginFromUpload = [];
        $inventoryItemIds = $inventoryItems->pluck('inventory_item_id')->toArray();
        if (!empty($inventoryItemIds)) {
            $uploadCards = DB::table('outlet_food_inventory_cards as card')
                ->where('card.reference_type', 'initial_balance')
                ->where('card.id_outlet', $this->outletId)
                ->where('card.warehouse_outlet_id', $this->warehouseOutletId)
                ->where('card.date', '<=', $tanggalAkhirBulanSebelumnya)
                ->whereIn('card.inventory_item_id', $inventoryItemIds)
                ->orderBy('card.date', 'desc')
                ->orderBy('card.created_at', 'desc')
                ->select('card.inventory_item_id', 'card.saldo_qty_small', 'card.saldo_qty_medium', 'card.saldo_qty_large')
                ->get();
            foreach ($uploadCards as $r) {
                if (!isset($beginFromUpload[$r->inventory_item_id])) {
                    $beginFromUpload[$r->inventory_item_id] = [
                        'qty_small' => (float) ($r->saldo_qty_small ?? 0),
                        'qty_medium' => (float) ($r->saldo_qty_medium ?? 0),
                        'qty_large' => (float) ($r->saldo_qty_large ?? 0),
                    ];
                }
            }
        }
        
        $reportData = [];
        $no = 0;
        
        foreach ($inventoryItems as $item) {
            $no++;
            
            // Begin Inventory = 1) Last Stock Opname Physical bulan lalu, 2) Fallback: upload saldo awal (initial_balance), 3) 0
            $itemBegin = $beginInventoryFromOpname[$item->item_id] ?? $beginFromUpload[$item->inventory_item_id] ?? ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0];
            $beginQtySmall = (float) ($itemBegin['qty_small'] ?? 0);
            $beginQtyMedium = (float) ($itemBegin['qty_medium'] ?? 0);
            $beginQtyLarge = (float) ($itemBegin['qty_large'] ?? 0);
            
            // Format functions
            $formatQty = function($qtySmall, $qtyMedium, $qtyLarge, $item) {
                $display = [];
                if ($item->small_unit_name && $qtySmall > 0) {
                    $display[] = number_format($qtySmall, 2, ',', '.') . ' ' . $item->small_unit_name;
                }
                if ($item->medium_unit_name && $qtyMedium > 0) {
                    $display[] = number_format($qtyMedium, 2, ',', '.') . ' ' . $item->medium_unit_name;
                }
                if ($item->large_unit_name && $qtyLarge > 0) {
                    $display[] = number_format($qtyLarge, 2, ',', '.') . ' ' . $item->large_unit_name;
                }
                return !empty($display) ? implode("\n", $display) : '0';
            };
            
            // UOM
            $uomParts = [];
            if ($item->small_unit_name) {
                $uomParts[] = $item->small_unit_name;
            }
            if ($item->medium_unit_name && $item->medium_unit_id && $item->small_conversion_qty) {
                $uomParts[] = '1 ' . $item->medium_unit_name . ' = ' . number_format((float)$item->small_conversion_qty, 2) . ' ' . $item->small_unit_name;
            }
            if ($item->large_unit_name && $item->large_unit_id && $item->medium_conversion_qty && $item->small_conversion_qty) {
                $largeToSmall = (float)$item->medium_conversion_qty * (float)$item->small_conversion_qty;
                $uomParts[] = '1 ' . $item->large_unit_name . ' = ' . number_format($largeToSmall, 2) . ' ' . $item->small_unit_name;
            }
            $uomText = !empty($uomParts) ? implode("\n", $uomParts) : '-';
            
            // Data
            $grSmall = isset($goodReceiveData[$item->item_id]) ? $goodReceiveData[$item->item_id]['qty_small'] : 0;
            $grMedium = isset($goodReceiveData[$item->item_id]) ? $goodReceiveData[$item->item_id]['qty_medium'] : 0;
            $grLarge = isset($goodReceiveData[$item->item_id]) ? $goodReceiveData[$item->item_id]['qty_large'] : 0;
            
            $gsSmall = isset($goodSoldData[$item->item_id]) ? $goodSoldData[$item->item_id]['qty_small'] : 0;
            $gsMedium = isset($goodSoldData[$item->item_id]) ? $goodSoldData[$item->item_id]['qty_medium'] : 0;
            $gsLarge = isset($goodSoldData[$item->item_id]) ? $goodSoldData[$item->item_id]['qty_large'] : 0;
            
            $itemInternalUseWaste = $internalUseWasteData[$item->item_id] ?? [
                'wasted' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                'spoil' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                'guest_supplies' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                'rnd' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                'marketing' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                'wrong_maker' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                'internal_used' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                'non_commodity' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
            ];
            
            $itemWipProduction = $wipProductionData[$item->item_id] ?? [
                'wip_in' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                'wip_out' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
            ];
            
            $itemInternalTransfer = $internalWarehouseTransferData[$item->item_id] ?? [
                'internal_transfer_in' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                'internal_transfer_out' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
            ];
            
            $currentStock = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $item->inventory_item_id)
                ->where('id_outlet', $this->outletId)
                ->where('warehouse_outlet_id', $this->warehouseOutletId)
                ->select('qty_small', 'qty_medium', 'qty_large', 'last_cost_small', 'last_cost_medium', 'last_cost_large')
                ->first();
            
            $lastQtySmall = $currentStock ? (float) ($currentStock->qty_small ?? 0) : 0;
            $lastQtyMedium = $currentStock ? (float) ($currentStock->qty_medium ?? 0) : 0;
            $lastQtyLarge = $currentStock ? (float) ($currentStock->qty_large ?? 0) : 0;
            $lastMacSmall = $currentStock ? (float) ($currentStock->last_cost_small ?? 0) : 0;
            $lastMacMedium = $currentStock ? (float) ($currentStock->last_cost_medium ?? 0) : 0;
            $lastMacLarge = $currentStock ? (float) ($currentStock->last_cost_large ?? 0) : 0;
            $lastStockSubtotalMac = $lastQtySmall * $lastMacSmall;
            
            $itemStockOpname = $stockOpnameData[$item->item_id] ?? [
                'qty_small' => 0,
                'qty_medium' => 0,
                'qty_large' => 0,
                'mac' => 0,
            ];
            
            $stockOpnameSmall = $itemStockOpname['qty_small'];
            $stockOpnameMedium = $itemStockOpname['qty_medium'];
            $stockOpnameLarge = $itemStockOpname['qty_large'];
            $stockOpnameMac = $itemStockOpname['mac'];
            $stockOpnameMacSmall = $stockOpnameMac;
            $stockOpnameSubtotalMac = $stockOpnameSmall * $stockOpnameMacSmall;
            
            $hasStockOpnameData = ($stockOpnameSmall > 0 || $stockOpnameMedium > 0 || $stockOpnameLarge > 0);
            // Selisih = Stock Opname Physical - Last Stock
            $differenceSmall = $hasStockOpnameData ? ($stockOpnameSmall - $lastQtySmall) : 0;
            $differenceMedium = $hasStockOpnameData ? ($stockOpnameMedium - $lastQtyMedium) : 0;
            $differenceLarge = $hasStockOpnameData ? ($stockOpnameLarge - $lastQtyLarge) : 0;
            $differenceMacSmall = $hasStockOpnameData ? $lastMacSmall : 0;
            $differenceSubtotalMac = $hasStockOpnameData ? ($differenceSmall * $differenceMacSmall) : 0;
            
            // Format Last Stock dengan MAC
            $lastStockText = [];
            if ($item->small_unit_name && $lastQtySmall > 0) {
                $lastStockText[] = number_format($lastQtySmall, 2, ',', '.') . ' ' . $item->small_unit_name . ' @ ' . number_format($lastMacSmall, 2, ',', '.');
            }
            if ($item->medium_unit_name && $lastQtyMedium > 0) {
                $lastStockText[] = number_format($lastQtyMedium, 2, ',', '.') . ' ' . $item->medium_unit_name . ' @ ' . number_format($lastMacMedium, 2, ',', '.');
            }
            if ($item->large_unit_name && $lastQtyLarge > 0) {
                $lastStockText[] = number_format($lastQtyLarge, 2, ',', '.') . ' ' . $item->large_unit_name . ' @ ' . number_format($lastMacLarge, 2, ',', '.');
            }
            if ($lastStockSubtotalMac > 0) {
                $lastStockText[] = 'Subtotal: ' . number_format($lastStockSubtotalMac, 2, ',', '.');
            }
            $lastStockTextFinal = !empty($lastStockText) ? implode("\n", $lastStockText) : '0';
            
            // Format Stock Opname dengan MAC
            $stockOpnameText = [];
            if ($item->small_unit_name && $stockOpnameSmall > 0) {
                $stockOpnameText[] = number_format($stockOpnameSmall, 2, ',', '.') . ' ' . $item->small_unit_name . ' @ ' . number_format($stockOpnameMacSmall, 2, ',', '.');
            }
            if ($item->medium_unit_name && $stockOpnameMedium > 0) {
                $stockOpnameMacMedium = $item->small_conversion_qty > 0 ? $stockOpnameMac / (float)$item->small_conversion_qty : 0;
                $stockOpnameText[] = number_format($stockOpnameMedium, 2, ',', '.') . ' ' . $item->medium_unit_name . ' @ ' . number_format($stockOpnameMacMedium, 2, ',', '.');
            }
            if ($item->large_unit_name && $stockOpnameLarge > 0) {
                $stockOpnameMacLarge = ($item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) ? $stockOpnameMac / ((float)$item->small_conversion_qty * (float)$item->medium_conversion_qty) : 0;
                $stockOpnameText[] = number_format($stockOpnameLarge, 2, ',', '.') . ' ' . $item->large_unit_name . ' @ ' . number_format($stockOpnameMacLarge, 2, ',', '.');
            }
            if ($stockOpnameSubtotalMac > 0) {
                $stockOpnameText[] = 'Subtotal: ' . number_format($stockOpnameSubtotalMac, 2, ',', '.');
            }
            $stockOpnameTextFinal = !empty($stockOpnameText) ? implode("\n", $stockOpnameText) : '0';
            
            // Format Difference
            $differenceText = [];
            if ($hasStockOpnameData) {
                if ($item->small_unit_name && abs($differenceSmall) > 0.0001) {
                    $sign = $differenceSmall >= 0 ? '+' : '';
                    $differenceText[] = $sign . number_format($differenceSmall, 2, ',', '.') . ' ' . $item->small_unit_name . ' @ ' . number_format($differenceMacSmall, 2, ',', '.');
                }
                if ($item->medium_unit_name && abs($differenceMedium) > 0.0001) {
                    $sign = $differenceMedium >= 0 ? '+' : '';
                    $differenceMacMedium = $item->small_conversion_qty > 0 ? $lastMacSmall / (float)$item->small_conversion_qty : 0;
                    $differenceText[] = $sign . number_format($differenceMedium, 2, ',', '.') . ' ' . $item->medium_unit_name . ' @ ' . number_format($differenceMacMedium, 2, ',', '.');
                }
                if ($item->large_unit_name && abs($differenceLarge) > 0.0001) {
                    $sign = $differenceLarge >= 0 ? '+' : '';
                    $differenceMacLarge = ($item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) ? $lastMacSmall / ((float)$item->small_conversion_qty * (float)$item->medium_conversion_qty) : 0;
                    $differenceText[] = $sign . number_format($differenceLarge, 2, ',', '.') . ' ' . $item->large_unit_name . ' @ ' . number_format($differenceMacLarge, 2, ',', '.');
                }
                if (abs($differenceSubtotalMac) > 0.0001) {
                    $sign = $differenceSubtotalMac >= 0 ? '+' : '';
                    $differenceText[] = 'Subtotal: ' . $sign . number_format(abs($differenceSubtotalMac), 2, ',', '.');
                }
            }
            $differenceTextFinal = !empty($differenceText) ? implode("\n", $differenceText) : ($hasStockOpnameData ? '0' : '-');
            
            $reportData[] = (object) [
                'no' => $no,
                'category_name' => $item->category_name ?: 'Tanpa Kategori',
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'uom' => $uomText,
                'begin_inventory' => $formatQty($beginQtySmall, $beginQtyMedium, $beginQtyLarge, $item),
                'good_receive' => $formatQty($grSmall, $grMedium, $grLarge, $item),
                'good_sold' => $formatQty($gsSmall, $gsMedium, $gsLarge, $item),
                'wasted' => $formatQty($itemInternalUseWaste['wasted']['qty_small'], $itemInternalUseWaste['wasted']['qty_medium'], $itemInternalUseWaste['wasted']['qty_large'], $item),
                'spoil' => $formatQty($itemInternalUseWaste['spoil']['qty_small'], $itemInternalUseWaste['spoil']['qty_medium'], $itemInternalUseWaste['spoil']['qty_large'], $item),
                'guest_supplies' => $formatQty($itemInternalUseWaste['guest_supplies']['qty_small'], $itemInternalUseWaste['guest_supplies']['qty_medium'], $itemInternalUseWaste['guest_supplies']['qty_large'], $item),
                'rnd' => $formatQty($itemInternalUseWaste['rnd']['qty_small'], $itemInternalUseWaste['rnd']['qty_medium'], $itemInternalUseWaste['rnd']['qty_large'], $item),
                'marketing' => $formatQty($itemInternalUseWaste['marketing']['qty_small'], $itemInternalUseWaste['marketing']['qty_medium'], $itemInternalUseWaste['marketing']['qty_large'], $item),
                'wrong_maker' => $formatQty($itemInternalUseWaste['wrong_maker']['qty_small'], $itemInternalUseWaste['wrong_maker']['qty_medium'], $itemInternalUseWaste['wrong_maker']['qty_large'], $item),
                'internal_used' => $formatQty($itemInternalUseWaste['internal_used']['qty_small'], $itemInternalUseWaste['internal_used']['qty_medium'], $itemInternalUseWaste['internal_used']['qty_large'], $item),
                'non_commodity' => $formatQty($itemInternalUseWaste['non_commodity']['qty_small'], $itemInternalUseWaste['non_commodity']['qty_medium'], $itemInternalUseWaste['non_commodity']['qty_large'], $item),
                'wip_production_in' => $formatQty($itemWipProduction['wip_in']['qty_small'], $itemWipProduction['wip_in']['qty_medium'], $itemWipProduction['wip_in']['qty_large'], $item),
                'wip_production_out' => $formatQty($itemWipProduction['wip_out']['qty_small'], $itemWipProduction['wip_out']['qty_medium'], $itemWipProduction['wip_out']['qty_large'], $item),
                'internal_transfer_in' => $formatQty($itemInternalTransfer['internal_transfer_in']['qty_small'], $itemInternalTransfer['internal_transfer_in']['qty_medium'], $itemInternalTransfer['internal_transfer_in']['qty_large'], $item),
                'internal_transfer_out' => $formatQty($itemInternalTransfer['internal_transfer_out']['qty_small'], $itemInternalTransfer['internal_transfer_out']['qty_medium'], $itemInternalTransfer['internal_transfer_out']['qty_large'], $item),
                'last_stock' => $lastStockTextFinal,
                'stock_opname_physical' => $stockOpnameTextFinal,
                'difference_stock_opname' => $differenceTextFinal,
            ];
        }
        
        return collect($reportData);
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Barang',
            'Nama Barang',
            'UOM',
            'Begin Inventory',
            'Good Receive',
            'Good Sold',
            'Wasted',
            'Spoil',
            'Guest Supplies',
            'R&D',
            'Marketing',
            'Wrong Maker',
            'Internal Used',
            'Non Commodity',
            'WIP Production IN',
            'WIP Production OUT',
            'Internal Transfer IN',
            'Internal Transfer OUT',
            'Last Stock',
            'Stock Opname Physical',
            'Selisih (Last Stock - Stock Opname)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->no,
            $row->item_code,
            $row->item_name,
            $row->uom,
            $row->begin_inventory,
            $row->good_receive,
            $row->good_sold,
            $row->wasted,
            $row->spoil,
            $row->guest_supplies,
            $row->rnd,
            $row->marketing,
            $row->wrong_maker,
            $row->internal_used,
            $row->non_commodity,
            $row->wip_production_in,
            $row->wip_production_out,
            $row->internal_transfer_in,
            $row->internal_transfer_out,
            $row->last_stock,
            $row->stock_opname_physical,
            $row->difference_stock_opname,
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
            'A' => 8,
            'B' => 20,
            'C' => 40,
            'D' => 30,
            'E' => 30,
            'F' => 30,
            'G' => 30,
            'H' => 20,
            'I' => 20,
            'J' => 20,
            'K' => 20,
            'L' => 20,
            'M' => 20,
            'N' => 20,
            'O' => 20,
            'P' => 25,
            'Q' => 25,
            'R' => 25,
            'S' => 25,
            'T' => 40,
            'U' => 40,
            'V' => 40,
        ];
    }
}
