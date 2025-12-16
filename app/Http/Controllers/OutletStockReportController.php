<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class OutletStockReportController extends Controller
{
    public function index(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $warehouseOutletId = $request->input('warehouse_outlet_id');
        $bulan = $request->input('bulan', date('Y-m')); // Format: YYYY-MM
        
        // Parse bulan
        $bulanCarbon = Carbon::parse($bulan . '-01');
        $bulanSebelumnya = $bulanCarbon->copy()->subMonth();
        $tanggalAwalBulan = $bulanCarbon->format('Y-m-01');
        $tanggalAkhirBulanSebelumnya = $bulanSebelumnya->format('Y-m-t'); // t = last day of month
        $tanggal1BulanIni = $bulanCarbon->format('Y-m-01');
        
        // Get outlets
        $outlets = DB::table('tbl_data_outlet')
            ->select('id_outlet', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();
        
        // Get warehouse outlets
        $warehouseOutlets = [];
        if ($outletId) {
            $warehouseOutlets = DB::table('warehouse_outlets')
                ->where('outlet_id', $outletId)
                ->where('status', 'active')
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->toArray();
        }
        
        $reportData = [];
        $totalItems = 0;
        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);
        
        if ($outletId && $warehouseOutletId) {
            // Ambil semua inventory items untuk outlet dan warehouse ini
            // Menggunakan outlet_food_inventory_stocks sebagai base karena memiliki id_outlet dan warehouse_outlet_id
            $query = DB::table('outlet_food_inventory_stocks as s')
                ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
                ->join('items as i', 'fi.item_id', '=', 'i.id')
                ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
                ->leftJoin('units as u_small', 'i.small_unit_id', '=', 'u_small.id')
                ->leftJoin('units as u_medium', 'i.medium_unit_id', '=', 'u_medium.id')
                ->leftJoin('units as u_large', 'i.large_unit_id', '=', 'u_large.id')
                ->where('s.id_outlet', $outletId)
                ->where('s.warehouse_outlet_id', $warehouseOutletId)
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
            $search = $request->input('search');
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('i.sku', 'like', "%{$search}%")
                      ->orWhere('i.name', 'like', "%{$search}%")
                      ->orWhere('c.name', 'like', "%{$search}%");
                });
            }
            
            // Get total count for pagination (before pagination)
            $totalItems = $query->count();
            
            // Pagination
            $offset = ($page - 1) * $perPage;
            
            $inventoryItems = $query->orderBy('c.name', 'asc')
                ->orderBy('i.name', 'asc')
                ->offset($offset)
                ->limit($perPage)
                ->get();
            
            // Hitung good receive per item untuk bulan yang dipilih
            $goodReceiveData = [];
            
            // 1. Good Receive dari Outlet Food Good Receive
            $outletGoodReceives = DB::table('outlet_food_good_receives as gr')
                ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
                ->join('items as i', 'gri.item_id', '=', 'i.id')
                ->where('gr.outlet_id', $outletId)
                ->where('gr.warehouse_outlet_id', $warehouseOutletId)
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
                    $goodReceiveData[$itemId] = [
                        'qty_small' => 0,
                        'qty_medium' => 0,
                        'qty_large' => 0,
                    ];
                }
                
                // Konversi received_qty ke small, medium, large
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
            
            // 2. Good Receive dari Retail Food
            $retailFoods = DB::table('retail_food as rf')
                ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
                ->join('items as i', function($join) {
                    $join->on(DB::raw('TRIM(i.name)'), '=', DB::raw('TRIM(rfi.item_name)'));
                })
                ->leftJoin('units as u_small', 'i.small_unit_id', '=', 'u_small.id')
                ->leftJoin('units as u_medium', 'i.medium_unit_id', '=', 'u_medium.id')
                ->leftJoin('units as u_large', 'i.large_unit_id', '=', 'u_large.id')
                ->where('rf.outlet_id', $outletId)
                ->where('rf.warehouse_outlet_id', $warehouseOutletId)
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
                    $goodReceiveData[$itemId] = [
                        'qty_small' => 0,
                        'qty_medium' => 0,
                        'qty_large' => 0,
                    ];
                }
                
                // Cari unit_id dari unit name
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
                    // Konversi qty ke small, medium, large
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
            
            // 3. Good Sold dari Stock Cut (ambil langsung dari stock_cut_details)
            $goodSoldData = [];
            
            // Ambil data dari stock_cut_details yang sudah disimpan saat stock cut dilakukan
            // Gunakan whereBetween untuk range tanggal bulan yang dipilih
            $tanggalAwalBulan = $bulanCarbon->format('Y-m-01');
            $tanggalAkhirBulan = $bulanCarbon->format('Y-m-t');
            
            // Query untuk mengambil good sold dari stock_cut_details
            $stockCutDetails = DB::table('stock_cut_details as scd')
                ->join('stock_cut_logs as scl', 'scd.stock_cut_log_id', '=', 'scl.id')
                ->where('scl.outlet_id', $outletId)
                ->where('scd.warehouse_outlet_id', $warehouseOutletId)
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
                $itemId = $detail->item_id;
                $totalSmall = (float) ($detail->total_out_qty_small ?? 0);
                $totalMedium = (float) ($detail->total_out_qty_medium ?? 0);
                $totalLarge = (float) ($detail->total_out_qty_large ?? 0);
                
                // Tambahkan ke goodSoldData
                $goodSoldData[$itemId] = [
                    'qty_small' => $totalSmall,
                    'qty_medium' => $totalMedium,
                    'qty_large' => $totalLarge,
                ];
            }
            
            // 4. Data dari Outlet Internal Use Waste (Wasted, Spoil, Guest Supplies, RnD, Marketing, Wrong Maker, Internal Used, Non Commodity)
            $internalUseWasteData = [];
            
            // Type yang membutuhkan approval: hanya ambil yang statusnya APPROVED
            $typesRequiringApproval = ['r_and_d', 'marketing', 'wrong_maker'];
            
            // Type yang tidak membutuhkan approval: ambil yang statusnya PROCESSED atau APPROVED
            $typesNotRequiringApproval = ['internal_use', 'spoil', 'waste', 'non_commodity', 'guest_supplies'];
            
            // Query untuk mengambil data dari outlet_food_inventory_cards
            $tanggalAwalBulan = $bulanCarbon->format('Y-m-01');
            $tanggalAkhirBulan = $bulanCarbon->format('Y-m-t');
            
            // Query untuk type yang membutuhkan approval (status APPROVED)
            $approvalTypes = DB::table('outlet_food_inventory_cards as card')
                ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
                ->join('outlet_internal_use_waste_headers as h', 'card.reference_id', '=', 'h.id')
                ->where('card.id_outlet', $outletId)
                ->where('card.warehouse_outlet_id', $warehouseOutletId)
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
            
            // Query untuk type yang tidak membutuhkan approval (status PROCESSED atau APPROVED)
            $nonApprovalTypes = DB::table('outlet_food_inventory_cards as card')
                ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
                ->join('outlet_internal_use_waste_headers as h', 'card.reference_id', '=', 'h.id')
                ->where('card.id_outlet', $outletId)
                ->where('card.warehouse_outlet_id', $warehouseOutletId)
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
            
            // Gabungkan hasil
            $allInternalUseWaste = $approvalTypes->merge($nonApprovalTypes);
            
            // Mapping type ke kolom
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
                
                if (!$columnKey) {
                    continue;
                }
                
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
            
            // 5. Data dari Outlet WIP Production (Stock Tambah dan Stock Berkurang)
            $wipProductionData = [];
            
            // Query untuk mengambil data dari outlet_food_inventory_cards dengan reference_type = 'outlet_wip_production'
            // Hanya ambil yang sudah diproses (status PROCESSED atau data lama yang tidak punya header)
            // Data dengan status DRAFT tidak akan ada di stock card karena belum diproses
            $wipStockCards = DB::table('outlet_food_inventory_cards as card')
                ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
                ->leftJoin('outlet_wip_production_headers as h', function($join) {
                    $join->on('card.reference_id', '=', 'h.id')
                         ->where('card.reference_type', '=', 'outlet_wip_production');
                })
                ->where('card.id_outlet', $outletId)
                ->where('card.warehouse_outlet_id', $warehouseOutletId)
                ->where('card.reference_type', 'outlet_wip_production')
                ->where(function($query) {
                    // Hanya ambil yang statusnya PROCESSED (atau null untuk data lama yang sudah diproses)
                    // Status DRAFT tidak akan ada di stock card karena belum diproses
                    $query->where('h.status', 'PROCESSED')
                          ->orWhereNull('h.status'); // Data lama (outlet_wip_productions tanpa header_id) sudah otomatis diproses
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
            
            // 6. Data dari Internal Warehouse Transfer (Stock Tambah dan Stock Berkurang)
            $internalWarehouseTransferData = [];
            
            // Query untuk mengambil data dari outlet_food_inventory_cards dengan reference_type = 'internal_warehouse_transfer'
            // Filter berdasarkan warehouse_outlet_id yang dipilih:
            // - OUT: transfer dari warehouse yang dipilih ke warehouse lain
            // - IN: transfer dari warehouse lain ke warehouse yang dipilih
            $internalTransferStockCards = DB::table('outlet_food_inventory_cards as card')
                ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
                ->where('card.id_outlet', $outletId)
                ->where('card.warehouse_outlet_id', $warehouseOutletId) // Filter berdasarkan warehouse yang dipilih
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
            
            // 7. Data dari Outlet Stock Opname (Stock Fisik)
            $stockOpnameData = [];
            
            // Query untuk mengambil data stock fisik dari outlet_stock_opname_items
            // Hanya ambil yang sudah diproses (status APPROVED atau PROCESSED)
            $stockOpnameItems = DB::table('outlet_stock_opname_items as soi')
                ->join('outlet_stock_opnames as so', 'soi.stock_opname_id', '=', 'so.id')
                ->join('outlet_food_inventory_items as fi', 'soi.inventory_item_id', '=', 'fi.id')
                ->where('so.outlet_id', $outletId)
                ->where('so.warehouse_outlet_id', $warehouseOutletId)
                ->whereIn('so.status', ['APPROVED', 'PROCESSED']) // Hanya yang sudah diproses
                ->whereYear('so.opname_date', $bulanCarbon->year)
                ->whereMonth('so.opname_date', $bulanCarbon->month)
                ->select(
                    'fi.item_id',
                    DB::raw('SUM(COALESCE(soi.qty_physical_small, 0)) as total_qty_physical_small'),
                    DB::raw('SUM(COALESCE(soi.qty_physical_medium, 0)) as total_qty_physical_medium'),
                    DB::raw('SUM(COALESCE(soi.qty_physical_large, 0)) as total_qty_physical_large'),
                    // Ambil MAC dari stock opname (gunakan mac_after, jika tidak ada gunakan mac_before)
                    DB::raw('AVG(COALESCE(soi.mac_after, soi.mac_before, 0)) as avg_mac')
                )
                ->groupBy('fi.item_id')
                ->get();
            
            foreach ($stockOpnameItems as $data) {
                $itemId = $data->item_id;
                
                $stockOpnameData[$itemId] = [
                    'qty_small' => (float) ($data->total_qty_physical_small ?? 0),
                    'qty_medium' => (float) ($data->total_qty_physical_medium ?? 0),
                    'qty_large' => (float) ($data->total_qty_physical_large ?? 0),
                    'mac' => (float) ($data->avg_mac ?? 0),
                ];
            }
            
            foreach ($inventoryItems as $item) {
                // Ambil begin inventory dari stock card berdasarkan warehouse_outlet_id yang spesifik
                // 1. Cek last stock di akhir bulan sebelumnya (tanggal terakhir bulan sebelumnya)
                // CRITICAL: Pastikan filter berdasarkan warehouse_outlet_id yang dipilih
                $lastStockCard = DB::table('outlet_food_inventory_cards')
                    ->where('inventory_item_id', $item->inventory_item_id)
                    ->where('id_outlet', $outletId)
                    ->where('warehouse_outlet_id', $warehouseOutletId) // Filter spesifik warehouse yang dipilih
                    ->whereDate('date', '<=', $tanggalAkhirBulanSebelumnya)
                    ->orderBy('date', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->select('saldo_qty_small', 'saldo_qty_medium', 'saldo_qty_large', 'date', 'created_at', 'warehouse_outlet_id')
                    ->first();
                
                // 2. Jika ada transaksi setelah tanggal akhir bulan sebelumnya, ambil stock di tanggal 1 bulan ini sebelum jam 8 pagi
                // CRITICAL: Pastikan juga filter berdasarkan warehouse_outlet_id yang dipilih
                $stockCardTanggal1 = null;
                if (!$lastStockCard || ($lastStockCard && $lastStockCard->date < $tanggalAkhirBulanSebelumnya)) {
                    $stockCardTanggal1 = DB::table('outlet_food_inventory_cards')
                        ->where('inventory_item_id', $item->inventory_item_id)
                        ->where('id_outlet', $outletId)
                        ->where('warehouse_outlet_id', $warehouseOutletId) // Filter spesifik warehouse yang dipilih
                        ->whereDate('date', $tanggal1BulanIni)
                        ->whereTime('created_at', '<', '08:00:00')
                        ->orderBy('created_at', 'desc')
                        ->select('saldo_qty_small', 'saldo_qty_medium', 'saldo_qty_large', 'date', 'created_at', 'warehouse_outlet_id')
                        ->first();
                }
                
                // Gunakan stock card tanggal 1 jika ada, jika tidak gunakan last stock bulan sebelumnya
                $beginStock = $stockCardTanggal1 ?? $lastStockCard;
                
                // Jika tidak ada di stock card, ambil dari outlet_food_inventory_stocks (current stock)
                $beginQtySmall = 0;
                $beginQtyMedium = 0;
                $beginQtyLarge = 0;
                
                if ($beginStock) {
                    $beginQtySmall = (float) ($beginStock->saldo_qty_small ?? 0);
                    $beginQtyMedium = (float) ($beginStock->saldo_qty_medium ?? 0);
                    $beginQtyLarge = (float) ($beginStock->saldo_qty_large ?? 0);
                } else {
                    // Fallback: ambil dari current stock berdasarkan warehouse_outlet_id yang spesifik
                    // Pastikan filter berdasarkan warehouse_outlet_id yang dipilih
                    $currentStock = DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $item->inventory_item_id)
                        ->where('id_outlet', $outletId)
                        ->where('warehouse_outlet_id', $warehouseOutletId) // Filter spesifik warehouse
                        ->select('qty_small', 'qty_medium', 'qty_large', 'warehouse_outlet_id')
                        ->first();
                    
                    if ($currentStock) {
                        $beginQtySmall = (float) ($currentStock->qty_small ?? 0);
                        $beginQtyMedium = (float) ($currentStock->qty_medium ?? 0);
                        $beginQtyLarge = (float) ($currentStock->qty_large ?? 0);
                    } else {
                        // Jika tidak ada stock sama sekali untuk warehouse ini, set ke 0
                        $beginQtySmall = 0;
                        $beginQtyMedium = 0;
                        $beginQtyLarge = 0;
                    }
                }
                
                // Format UOM dengan konversi dari table items (array untuk ditampilkan vertikal)
                $uomParts = [];
                
                if ($item->small_unit_name) {
                    $uomParts[] = $item->small_unit_name;
                }
                
                // Konversi medium ke small (dari medium_conversion_qty)
                if ($item->medium_unit_name && $item->medium_unit_id && $item->small_conversion_qty) {
                    $uomParts[] = '1 ' . $item->medium_unit_name . ' = ' . number_format((float)$item->small_conversion_qty, 2) . ' ' . $item->small_unit_name;
                }
                
                // Konversi large ke small (dari medium_conversion_qty, karena large biasanya = medium_conversion_qty * small_conversion_qty)
                if ($item->large_unit_name && $item->large_unit_id && $item->medium_conversion_qty && $item->small_conversion_qty) {
                    $largeToSmall = (float)$item->medium_conversion_qty * (float)$item->small_conversion_qty;
                    $uomParts[] = '1 ' . $item->large_unit_name . ' = ' . number_format($largeToSmall, 2) . ' ' . $item->small_unit_name;
                }
                
                // Jika tidak ada data, tetap kirim array dengan '-'
                if (empty($uomParts)) {
                    $uomParts = ['-'];
                }
                
                // Format begin inventory dengan unit (array untuk ditampilkan vertikal)
                $beginInventoryDisplay = [];
                if ($item->small_unit_name && $beginQtySmall > 0) {
                    $beginInventoryDisplay[] = number_format($beginQtySmall, 2, ',', '.') . ' ' . $item->small_unit_name;
                }
                if ($item->medium_unit_name && $beginQtyMedium > 0) {
                    $beginInventoryDisplay[] = number_format($beginQtyMedium, 2, ',', '.') . ' ' . $item->medium_unit_name;
                }
                if ($item->large_unit_name && $beginQtyLarge > 0) {
                    $beginInventoryDisplay[] = number_format($beginQtyLarge, 2, ',', '.') . ' ' . $item->large_unit_name;
                }
                
                // Jika tidak ada data, tetap kirim array kosong atau dengan '0'
                if (empty($beginInventoryDisplay)) {
                    $beginInventoryDisplay = ['0'];
                }
                
                // Ambil good receive untuk item ini
                $grSmall = isset($goodReceiveData[$item->item_id]) ? $goodReceiveData[$item->item_id]['qty_small'] : 0;
                $grMedium = isset($goodReceiveData[$item->item_id]) ? $goodReceiveData[$item->item_id]['qty_medium'] : 0;
                $grLarge = isset($goodReceiveData[$item->item_id]) ? $goodReceiveData[$item->item_id]['qty_large'] : 0;
                
                // Format good receive dengan unit (array untuk ditampilkan vertikal)
                $goodReceiveDisplay = [];
                if ($item->small_unit_name && $grSmall > 0) {
                    $goodReceiveDisplay[] = number_format($grSmall, 2, ',', '.') . ' ' . $item->small_unit_name;
                }
                if ($item->medium_unit_name && $grMedium > 0) {
                    $goodReceiveDisplay[] = number_format($grMedium, 2, ',', '.') . ' ' . $item->medium_unit_name;
                }
                if ($item->large_unit_name && $grLarge > 0) {
                    $goodReceiveDisplay[] = number_format($grLarge, 2, ',', '.') . ' ' . $item->large_unit_name;
                }
                
                // Jika tidak ada data, tetap kirim array kosong atau dengan '0'
                if (empty($goodReceiveDisplay)) {
                    $goodReceiveDisplay = ['0'];
                }
                
                // Ambil good sold untuk item ini
                $gsSmall = isset($goodSoldData[$item->item_id]) ? $goodSoldData[$item->item_id]['qty_small'] : 0;
                $gsMedium = isset($goodSoldData[$item->item_id]) ? $goodSoldData[$item->item_id]['qty_medium'] : 0;
                $gsLarge = isset($goodSoldData[$item->item_id]) ? $goodSoldData[$item->item_id]['qty_large'] : 0;
                
                // Format good sold dengan unit (array untuk ditampilkan vertikal)
                $goodSoldDisplay = [];
                if ($item->small_unit_name && $gsSmall > 0) {
                    $goodSoldDisplay[] = number_format($gsSmall, 2, ',', '.') . ' ' . $item->small_unit_name;
                }
                if ($item->medium_unit_name && $gsMedium > 0) {
                    $goodSoldDisplay[] = number_format($gsMedium, 2, ',', '.') . ' ' . $item->medium_unit_name;
                }
                if ($item->large_unit_name && $gsLarge > 0) {
                    $goodSoldDisplay[] = number_format($gsLarge, 2, ',', '.') . ' ' . $item->large_unit_name;
                }
                
                // Jika tidak ada data, tetap kirim array kosong atau dengan '0'
                if (empty($goodSoldDisplay)) {
                    $goodSoldDisplay = ['0'];
                }
                
                // Ambil data internal use waste untuk item ini
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
                
                // Format untuk setiap type
                $formatTypeData = function($typeData) use ($item) {
                    $display = [];
                    if ($item->small_unit_name && $typeData['qty_small'] > 0) {
                        $display[] = number_format($typeData['qty_small'], 2, ',', '.') . ' ' . $item->small_unit_name;
                    }
                    if ($item->medium_unit_name && $typeData['qty_medium'] > 0) {
                        $display[] = number_format($typeData['qty_medium'], 2, ',', '.') . ' ' . $item->medium_unit_name;
                    }
                    if ($item->large_unit_name && $typeData['qty_large'] > 0) {
                        $display[] = number_format($typeData['qty_large'], 2, ',', '.') . ' ' . $item->large_unit_name;
                    }
                    return !empty($display) ? $display : ['0'];
                };
                
                $wastedDisplay = $formatTypeData($itemInternalUseWaste['wasted']);
                $spoilDisplay = $formatTypeData($itemInternalUseWaste['spoil']);
                $guestSuppliesDisplay = $formatTypeData($itemInternalUseWaste['guest_supplies']);
                $rndDisplay = $formatTypeData($itemInternalUseWaste['rnd']);
                $marketingDisplay = $formatTypeData($itemInternalUseWaste['marketing']);
                $wrongMakerDisplay = $formatTypeData($itemInternalUseWaste['wrong_maker']);
                $internalUsedDisplay = $formatTypeData($itemInternalUseWaste['internal_used']);
                $nonCommodityDisplay = $formatTypeData($itemInternalUseWaste['non_commodity']);
                
                // Ambil data WIP Production untuk item ini
                $itemWipProduction = $wipProductionData[$item->item_id] ?? [
                    'wip_in' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                    'wip_out' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                ];
                
                $wipInDisplay = $formatTypeData($itemWipProduction['wip_in']);
                $wipOutDisplay = $formatTypeData($itemWipProduction['wip_out']);
                
                // Ambil data Internal Warehouse Transfer untuk item ini
                $itemInternalTransfer = $internalWarehouseTransferData[$item->item_id] ?? [
                    'internal_transfer_in' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                    'internal_transfer_out' => ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0],
                ];
                
                $internalTransferInDisplay = $formatTypeData($itemInternalTransfer['internal_transfer_in']);
                $internalTransferOutDisplay = $formatTypeData($itemInternalTransfer['internal_transfer_out']);
                
                // Ambil last stock (current stock) dari outlet_food_inventory_stocks
                $currentStock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $item->inventory_item_id)
                    ->where('id_outlet', $outletId)
                    ->where('warehouse_outlet_id', $warehouseOutletId)
                    ->select('qty_small', 'qty_medium', 'qty_large', 'last_cost_small', 'last_cost_medium', 'last_cost_large')
                    ->first();
                
                $lastQtySmall = $currentStock ? (float) ($currentStock->qty_small ?? 0) : 0;
                $lastQtyMedium = $currentStock ? (float) ($currentStock->qty_medium ?? 0) : 0;
                $lastQtyLarge = $currentStock ? (float) ($currentStock->qty_large ?? 0) : 0;
                
                // Ambil MAC untuk last stock
                $lastMacSmall = $currentStock ? (float) ($currentStock->last_cost_small ?? 0) : 0;
                $lastMacMedium = $currentStock ? (float) ($currentStock->last_cost_medium ?? 0) : 0;
                $lastMacLarge = $currentStock ? (float) ($currentStock->last_cost_large ?? 0) : 0;
                
                // Hitung subtotal MAC untuk last stock (gunakan qty_small * last_cost_small)
                $lastStockSubtotalMac = $lastQtySmall * $lastMacSmall;
                
                // Format last stock dengan unit dan MAC (array untuk ditampilkan vertikal)
                $lastStockDisplay = [];
                if ($item->small_unit_name && $lastQtySmall > 0) {
                    $lastStockDisplay[] = number_format($lastQtySmall, 2, ',', '.') . ' ' . $item->small_unit_name . ' @ ' . number_format($lastMacSmall, 2, ',', '.');
                }
                if ($item->medium_unit_name && $lastQtyMedium > 0) {
                    $lastStockDisplay[] = number_format($lastQtyMedium, 2, ',', '.') . ' ' . $item->medium_unit_name . ' @ ' . number_format($lastMacMedium, 2, ',', '.');
                }
                if ($item->large_unit_name && $lastQtyLarge > 0) {
                    $lastStockDisplay[] = number_format($lastQtyLarge, 2, ',', '.') . ' ' . $item->large_unit_name . ' @ ' . number_format($lastMacLarge, 2, ',', '.');
                }
                
                // Tambahkan subtotal MAC
                if ($lastStockSubtotalMac > 0) {
                    $lastStockDisplay[] = 'Subtotal: ' . number_format($lastStockSubtotalMac, 2, ',', '.');
                }
                
                // Jika tidak ada data, tetap kirim array kosong atau dengan '0'
                if (empty($lastStockDisplay)) {
                    $lastStockDisplay = ['0'];
                }
                
                // Ambil data stock fisik dari stock opname untuk item ini
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
                
                // Hitung MAC per unit untuk stock opname (gunakan MAC dari stock opname)
                $stockOpnameMacSmall = $stockOpnameMac;
                $stockOpnameMacMedium = $item->small_conversion_qty > 0 ? $stockOpnameMac / (float)$item->small_conversion_qty : 0;
                $stockOpnameMacLarge = ($item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) ? $stockOpnameMac / ((float)$item->small_conversion_qty * (float)$item->medium_conversion_qty) : 0;
                
                // Hitung subtotal MAC untuk stock opname (gunakan qty_small * mac)
                $stockOpnameSubtotalMac = $stockOpnameSmall * $stockOpnameMacSmall;
                
                // Format stock fisik dengan unit dan MAC (array untuk ditampilkan vertikal)
                $stockOpnameDisplay = [];
                if ($item->small_unit_name && $stockOpnameSmall > 0) {
                    $stockOpnameDisplay[] = number_format($stockOpnameSmall, 2, ',', '.') . ' ' . $item->small_unit_name . ' @ ' . number_format($stockOpnameMacSmall, 2, ',', '.');
                }
                if ($item->medium_unit_name && $stockOpnameMedium > 0) {
                    $stockOpnameDisplay[] = number_format($stockOpnameMedium, 2, ',', '.') . ' ' . $item->medium_unit_name . ' @ ' . number_format($stockOpnameMacMedium, 2, ',', '.');
                }
                if ($item->large_unit_name && $stockOpnameLarge > 0) {
                    $stockOpnameDisplay[] = number_format($stockOpnameLarge, 2, ',', '.') . ' ' . $item->large_unit_name . ' @ ' . number_format($stockOpnameMacLarge, 2, ',', '.');
                }
                
                // Tambahkan subtotal MAC
                if ($stockOpnameSubtotalMac > 0) {
                    $stockOpnameDisplay[] = 'Subtotal: ' . number_format($stockOpnameSubtotalMac, 2, ',', '.');
                }
                
                // Jika tidak ada data, tetap kirim array kosong atau dengan '0'
                if (empty($stockOpnameDisplay)) {
                    $stockOpnameDisplay = ['0'];
                }
                
                // Hitung selisih antara last stock dan stock opname physical
                // Hanya tampilkan jika ada data stock opname (stock opname > 0)
                $hasStockOpnameData = ($stockOpnameSmall > 0 || $stockOpnameMedium > 0 || $stockOpnameLarge > 0);
                
                $differenceSmall = 0;
                $differenceMedium = 0;
                $differenceLarge = 0;
                $differenceDisplay = [];
                
                if ($hasStockOpnameData) {
                    $differenceSmall = $lastQtySmall - $stockOpnameSmall;
                    $differenceMedium = $lastQtyMedium - $stockOpnameMedium;
                    $differenceLarge = $lastQtyLarge - $stockOpnameLarge;
                    
                    // Hitung MAC untuk selisih (gunakan MAC dari Last Stock)
                    $differenceMacSmall = $lastMacSmall;
                    $differenceMacMedium = $lastMacMedium;
                    $differenceMacLarge = $lastMacLarge;
                    
                    // Hitung subtotal MAC untuk selisih (gunakan qty_small * mac_small)
                    $differenceSubtotalMac = $differenceSmall * $differenceMacSmall;
                    
                    // Format selisih dengan unit dan MAC (array untuk ditampilkan vertikal)
                    if ($item->small_unit_name && abs($differenceSmall) > 0.0001) { // Gunakan tolerance untuk floating point
                        $sign = $differenceSmall >= 0 ? '+' : '';
                        $differenceDisplay[] = $sign . number_format($differenceSmall, 2, ',', '.') . ' ' . $item->small_unit_name . ' @ ' . number_format($differenceMacSmall, 2, ',', '.');
                    }
                    if ($item->medium_unit_name && abs($differenceMedium) > 0.0001) {
                        $sign = $differenceMedium >= 0 ? '+' : '';
                        $differenceDisplay[] = $sign . number_format($differenceMedium, 2, ',', '.') . ' ' . $item->medium_unit_name . ' @ ' . number_format($differenceMacMedium, 2, ',', '.');
                    }
                    if ($item->large_unit_name && abs($differenceLarge) > 0.0001) {
                        $sign = $differenceLarge >= 0 ? '+' : '';
                        $differenceDisplay[] = $sign . number_format($differenceLarge, 2, ',', '.') . ' ' . $item->large_unit_name . ' @ ' . number_format($differenceMacLarge, 2, ',', '.');
                    }
                    
                    // Tambahkan subtotal MAC
                    if (abs($differenceSubtotalMac) > 0.0001) {
                        $sign = $differenceSubtotalMac >= 0 ? '+' : '';
                        $differenceDisplay[] = 'Subtotal: ' . $sign . number_format(abs($differenceSubtotalMac), 2, ',', '.');
                    }
                    
                    // Jika tidak ada selisih yang signifikan, tampilkan '0'
                    if (empty($differenceDisplay)) {
                        $differenceDisplay = ['0'];
                    }
                } else {
                    // Jika tidak ada data stock opname, set ke null atau empty
                    $differenceDisplay = null;
                    $differenceMacSmall = 0;
                    $differenceMacMedium = 0;
                    $differenceMacLarge = 0;
                    $differenceSubtotalMac = 0;
                }
                
                $reportData[] = [
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'category_id' => $item->category_id,
                    'category_name' => $item->category_name ?: 'Tanpa Kategori',
                    'uom' => $uomParts, // Array untuk ditampilkan vertikal
                    'begin_inventory' => $beginInventoryDisplay, // Array untuk ditampilkan vertikal
                    'begin_inventory_small' => $beginQtySmall,
                    'begin_inventory_medium' => $beginQtyMedium,
                    'begin_inventory_large' => $beginQtyLarge,
                    'good_receive' => $goodReceiveDisplay, // Array untuk ditampilkan vertikal
                    'good_receive_small' => $grSmall,
                    'good_receive_medium' => $grMedium,
                    'good_receive_large' => $grLarge,
                    'good_sold' => $goodSoldDisplay, // Array untuk ditampilkan vertikal
                    'good_sold_small' => $gsSmall,
                    'good_sold_medium' => $gsMedium,
                    'good_sold_large' => $gsLarge,
                    'wasted' => $wastedDisplay, // Array untuk ditampilkan vertikal
                    'spoil' => $spoilDisplay, // Array untuk ditampilkan vertikal
                    'guest_supplies' => $guestSuppliesDisplay, // Array untuk ditampilkan vertikal
                    'rnd' => $rndDisplay, // Array untuk ditampilkan vertikal
                    'marketing' => $marketingDisplay, // Array untuk ditampilkan vertikal
                    'wrong_maker' => $wrongMakerDisplay, // Array untuk ditampilkan vertikal
                    'internal_used' => $internalUsedDisplay, // Array untuk ditampilkan vertikal
                    'non_commodity' => $nonCommodityDisplay, // Array untuk ditampilkan vertikal
                    'wip_production_in' => $wipInDisplay, // Array untuk ditampilkan vertikal (Stock Tambah)
                    'wip_production_out' => $wipOutDisplay, // Array untuk ditampilkan vertikal (Stock Berkurang)
                    'internal_transfer_in' => $internalTransferInDisplay, // Array untuk ditampilkan vertikal (Stock Tambah)
                    'internal_transfer_out' => $internalTransferOutDisplay, // Array untuk ditampilkan vertikal (Stock Berkurang)
                    'last_stock' => $lastStockDisplay, // Array untuk ditampilkan vertikal (Last Stock saat report di-generate)
                    'last_stock_small' => $lastQtySmall,
                    'last_stock_medium' => $lastQtyMedium,
                    'last_stock_large' => $lastQtyLarge,
                    'last_stock_mac_small' => $lastMacSmall,
                    'last_stock_mac_medium' => $lastMacMedium,
                    'last_stock_mac_large' => $lastMacLarge,
                    'last_stock_subtotal_mac' => $lastStockSubtotalMac,
                    'stock_opname_physical' => $stockOpnameDisplay, // Array untuk ditampilkan vertikal (Stock Fisik dari Stock Opname)
                    'stock_opname_physical_small' => $stockOpnameSmall,
                    'stock_opname_physical_medium' => $stockOpnameMedium,
                    'stock_opname_physical_large' => $stockOpnameLarge,
                    'stock_opname_mac_small' => $stockOpnameMacSmall,
                    'stock_opname_mac_medium' => $stockOpnameMacMedium,
                    'stock_opname_mac_large' => $stockOpnameMacLarge,
                    'stock_opname_subtotal_mac' => $stockOpnameSubtotalMac,
                    'difference_stock_opname' => $differenceDisplay, // Array untuk ditampilkan vertikal (Selisih Last Stock - Stock Opname Physical), null jika tidak ada data stock opname
                    'difference_stock_opname_small' => $differenceSmall,
                    'difference_stock_opname_medium' => $differenceMedium,
                    'difference_stock_opname_large' => $differenceLarge,
                    'difference_mac_small' => $differenceMacSmall ?? 0,
                    'difference_mac_medium' => $differenceMacMedium ?? 0,
                    'difference_mac_large' => $differenceMacLarge ?? 0,
                    'difference_subtotal_mac' => $differenceSubtotalMac ?? 0,
                    'has_stock_opname_data' => $hasStockOpnameData, // Flag untuk menampilkan kolom selisih
                ];
            }
            
            // Group by category dan urutkan
            $reportDataGrouped = [];
            foreach ($reportData as $item) {
                $categoryName = $item['category_name'];
                if (!isset($reportDataGrouped[$categoryName])) {
                    $reportDataGrouped[$categoryName] = [];
                }
                $reportDataGrouped[$categoryName][] = $item;
            }
            
            // Urutkan category secara ascending
            ksort($reportDataGrouped, SORT_STRING);
            
            // Hitung subtotal per category dan grand total
            $grandTotalLastStockMac = 0;
            $grandTotalStockOpnameMac = 0;
            $grandTotalDifferenceMac = 0;
            
            // Urutkan item di dalam setiap category berdasarkan item_name secara ascending
            // dan hitung subtotal per category
            $reportDataGroupedWithSubtotal = [];
            foreach ($reportDataGrouped as $categoryName => $items) {
                // Urutkan items
                usort($items, function($a, $b) {
                    // Urutkan berdasarkan item_name saja
                    return strcmp($a['item_name'] ?? '', $b['item_name'] ?? '');
                });
                
                // Hitung subtotal MAC per category
                $categorySubtotalLastStockMac = 0;
                $categorySubtotalStockOpnameMac = 0;
                $categorySubtotalDifferenceMac = 0;
                
                foreach ($items as $item) {
                    $categorySubtotalLastStockMac += $item['last_stock_subtotal_mac'] ?? 0;
                    $categorySubtotalStockOpnameMac += $item['stock_opname_subtotal_mac'] ?? 0;
                    $categorySubtotalDifferenceMac += $item['difference_subtotal_mac'] ?? 0;
                }
                
                // Simpan items dan subtotal dalam struktur yang benar
                $reportDataGroupedWithSubtotal[$categoryName] = [
                    'items' => $items,
                    '_subtotal_last_stock_mac' => $categorySubtotalLastStockMac,
                    '_subtotal_stock_opname_mac' => $categorySubtotalStockOpnameMac,
                    '_subtotal_difference_mac' => $categorySubtotalDifferenceMac,
                ];
                
                // Tambahkan ke grand total
                $grandTotalLastStockMac += $categorySubtotalLastStockMac;
                $grandTotalStockOpnameMac += $categorySubtotalStockOpnameMac;
                $grandTotalDifferenceMac += $categorySubtotalDifferenceMac;
            }
            
            // Ganti reportDataGrouped dengan yang sudah ada subtotal
            $reportDataGrouped = $reportDataGroupedWithSubtotal;
        }
        
        // Calculate pagination
        $totalPages = $totalItems > 0 ? ceil($totalItems / $perPage) : 0;
        
        return Inertia::render('OutletStockReport/Index', [
            'reportData' => $reportData,
            'reportDataGrouped' => $reportDataGrouped ?? [],
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'grandTotalLastStockMac' => $grandTotalLastStockMac ?? 0,
            'grandTotalStockOpnameMac' => $grandTotalStockOpnameMac ?? 0,
            'grandTotalDifferenceMac' => $grandTotalDifferenceMac ?? 0,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'outlet_id' => $outletId,
                'warehouse_outlet_id' => $warehouseOutletId,
                'bulan' => $bulan,
                'search' => $search ?? '',
                'per_page' => $perPage,
                'page' => $page,
            ]
        ]);
    }
}

