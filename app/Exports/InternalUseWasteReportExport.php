<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InternalUseWasteReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $outletId;
    protected $warehouseOutletId;
    protected $startDate;
    protected $endDate;
    protected $search;

    public function __construct($outletId = null, $warehouseOutletId = null, $startDate = null, $endDate = null, $search = null)
    {
        $this->outletId = $outletId;
        $this->warehouseOutletId = $warehouseOutletId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->search = $search;
    }

    public function collection()
    {
        if (!$this->outletId || !$this->startDate || !$this->endDate) {
            return collect([]);
        }

        // Query untuk mengambil data internal use waste dengan type RnD, Marketing, Wrong Maker
        $query = DB::table('outlet_internal_use_waste_headers as h')
            ->join('outlet_internal_use_waste_details as d', 'h.id', '=', 'd.header_id')
            ->join('items as item', 'd.item_id', '=', 'item.id')
            ->leftJoin('outlet_food_inventory_items as fi', 'item.id', '=', 'fi.item_id')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('units as u_small', 'item.small_unit_id', '=', 'u_small.id')
            ->leftJoin('units as u_medium', 'item.medium_unit_id', '=', 'u_medium.id')
            ->leftJoin('units as u_large', 'item.large_unit_id', '=', 'u_large.id')
            ->where('h.outlet_id', $this->outletId)
            ->whereIn('h.type', ['r_and_d', 'marketing', 'wrong_maker'])
            ->where('h.status', 'APPROVED')
            ->whereBetween('h.date', [$this->startDate, $this->endDate])
            ->select(
                'h.id as header_id',
                'h.number as header_number',
                'h.date',
                'h.type',
                'item.id as item_id',
                'item.name as item_name',
                'item.sku as item_code',
                'd.qty',
                'd.unit_id',
                'fi.id as inventory_item_id',
                'wo.name as warehouse_outlet_name',
                'u_small.name as small_unit_name',
                'u_medium.name as medium_unit_name',
                'u_large.name as large_unit_name',
                'item.small_unit_id',
                'item.medium_unit_id',
                'item.large_unit_id',
                'item.small_conversion_qty',
                'item.medium_conversion_qty',
                'h.warehouse_outlet_id'
            );

        // Filter warehouse outlet
        if ($this->warehouseOutletId && $this->warehouseOutletId !== 'all') {
            $query->where('h.warehouse_outlet_id', $this->warehouseOutletId);
        }

        // Search filter
        if ($this->search) {
            $query->where(function($q) {
                $q->where('item.name', 'like', "%{$this->search}%")
                  ->orWhere('item.sku', 'like', "%{$this->search}%")
                  ->orWhere('h.type', 'like', "%{$this->search}%");
            });
        }

        $items = $query->orderBy('h.date', 'desc')
            ->orderBy('h.type', 'asc')
            ->orderBy('item.name', 'asc')
            ->get();

        // ===== FIX N+1 QUERY: Pre-load MAC data sebelum loop =====
        $inventoryItemIds = $items->pluck('inventory_item_id')->filter()->unique()->toArray();
        $macDataCache = [];
        $stockDataCache = [];
        
        if (!empty($inventoryItemIds)) {
            // Pre-load cost histories
            foreach ($inventoryItemIds as $invItemId) {
                $macRow = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $invItemId)
                    ->where('id_outlet', $this->outletId)
                    ->where('date', '<=', $this->endDate)
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->select('mac', 'warehouse_outlet_id')
                    ->first();
                
                if ($macRow) {
                    $key = $invItemId . '_' . $macRow->warehouse_outlet_id;
                    $macDataCache[$key] = (float) ($macRow->mac ?? 0);
                }
            }
            
            // Pre-load stock data sebagai fallback
            $stockData = DB::table('outlet_food_inventory_stocks')
                ->whereIn('inventory_item_id', $inventoryItemIds)
                ->where('id_outlet', $this->outletId)
                ->select('inventory_item_id', 'warehouse_outlet_id', 'last_cost_small')
                ->get();
            
            foreach ($stockData as $stock) {
                $key = $stock->inventory_item_id . '_' . $stock->warehouse_outlet_id;
                $stockDataCache[$key] = (float) ($stock->last_cost_small ?? 0);
            }
        }
        // ===== END FIX N+1 QUERY =====

        // Ambil data approver untuk setiap header
        $headerIds = $items->pluck('header_id')->unique()->toArray();
        $approvers = [];

        if (!empty($headerIds)) {
            $approvalFlows = DB::table('outlet_internal_use_waste_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->whereIn('af.header_id', $headerIds)
                ->where('af.status', 'APPROVED')
                ->select(
                    'af.header_id',
                    'u.nama_lengkap as approver_name',
                    'j.nama_jabatan as approver_position',
                    'af.approved_at'
                )
                ->orderBy('af.approval_level')
                ->get();

            foreach ($approvalFlows as $flow) {
                $headerId = $flow->header_id;
                if (!isset($approvers[$headerId])) {
                    $approvers[$headerId] = [
                        'approvers' => [],
                        'last_approved_at' => null,
                    ];
                }
                $approvers[$headerId]['approvers'][] = [
                    'name' => $flow->approver_name,
                    'position' => $flow->approver_position,
                    'approved_at' => $flow->approved_at,
                ];
                if ($flow->approved_at && (!$approvers[$headerId]['last_approved_at'] || $flow->approved_at > $approvers[$headerId]['last_approved_at'])) {
                    $approvers[$headerId]['last_approved_at'] = $flow->approved_at;
                }
            }
        }

        $reportData = [];
        foreach ($items as $row) {
            // ===== FIX N+1 QUERY: Gunakan cached MAC data =====
            $mac = 0;
            if ($row->inventory_item_id) {
                $key = $row->inventory_item_id . '_' . $row->warehouse_outlet_id;
                $mac = $macDataCache[$key] ?? $stockDataCache[$key] ?? 0;
            }

            // Konversi qty ke small, medium, large
            $qty = (float) $row->qty;
            $smallConv = (float) ($row->small_conversion_qty ?: 1);
            $mediumConv = (float) ($row->medium_conversion_qty ?: 1);

            $qtySmall = 0;
            $qtyMedium = 0;
            $qtyLarge = 0;

            if ($row->unit_id == $row->small_unit_id) {
                $qtySmall = $qty;
                $qtyMedium = $smallConv > 0 ? $qty / $smallConv : 0;
                $qtyLarge = ($smallConv > 0 && $mediumConv > 0) ? $qty / ($smallConv * $mediumConv) : 0;
            } elseif ($row->unit_id == $row->medium_unit_id) {
                $qtyMedium = $qty;
                $qtySmall = $qty * $smallConv;
                $qtyLarge = $mediumConv > 0 ? $qty / $mediumConv : 0;
            } elseif ($row->unit_id == $row->large_unit_id) {
                $qtyLarge = $qty;
                $qtyMedium = $qty * $mediumConv;
                $qtySmall = $qty * $mediumConv * $smallConv;
            }

            // Format type
            $typeDisplay = '';
            switch ($row->type) {
                case 'r_and_d':
                    $typeDisplay = 'RnD';
                    break;
                case 'marketing':
                    $typeDisplay = 'Marketing';
                    break;
                case 'wrong_maker':
                    $typeDisplay = 'Wrong Maker';
                    break;
                default:
                    $typeDisplay = ucfirst(str_replace('_', ' ', $row->type));
            }

            // Ambil approvers
            $headerApproverData = $approvers[$row->header_id] ?? ['approvers' => [], 'last_approved_at' => null];
            $headerApprovers = $headerApproverData['approvers'] ?? [];
            $lastApprovedAt = $headerApproverData['last_approved_at'] ?? null;

            $approverNames = [];
            foreach ($headerApprovers as $approver) {
                $approverNames[] = $approver['name'] . ($approver['position'] ? ' (' . $approver['position'] . ')' : '');
            }
            $approverDisplay = !empty($approverNames) ? implode(', ', $approverNames) : '-';

            // Hitung subtotal MAC
            $macPerUnit = $mac;
            $subtotalMac = $qtySmall * $macPerUnit;

            // Format qty display
            $qtyDisplay = [];
            if ($row->small_unit_name && $qtySmall > 0) {
                $qtyDisplay[] = number_format($qtySmall, 2, ',', '.') . ' ' . $row->small_unit_name;
            }
            if ($row->medium_unit_name && $qtyMedium > 0) {
                $qtyDisplay[] = number_format($qtyMedium, 2, ',', '.') . ' ' . $row->medium_unit_name;
            }
            if ($row->large_unit_name && $qtyLarge > 0) {
                $qtyDisplay[] = number_format($qtyLarge, 2, ',', '.') . ' ' . $row->large_unit_name;
            }
            $qtyDisplayStr = !empty($qtyDisplay) ? implode(' / ', $qtyDisplay) : '0';

            $reportData[] = (object) [
                'date' => $row->date,
                'type' => $typeDisplay,
                'header_number' => $row->header_number ?? '-',
                'warehouse_outlet_name' => $row->warehouse_outlet_name ?? '-',
                'item_name' => $row->item_name,
                'item_code' => $row->item_code,
                'qty_display' => $qtyDisplayStr,
                'mac_per_unit' => $macPerUnit,
                'subtotal_mac' => $subtotalMac,
                'approvers' => $approverDisplay,
                'approved_at' => $lastApprovedAt,
            ];
        }

        return collect($reportData);
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Type',
            'Nomor Transaksi',
            'Warehouse Outlet',
            'Kode Barang',
            'Nama Barang',
            'Qty (Konversi)',
            'MAC Per Unit',
            'Qty x MAC',
            'Approver',
            'Tanggal Approval'
        ];
    }

    public function map($row): array
    {
        return [
            $row->date ? date('d/m/Y', strtotime($row->date)) : '-',
            $row->type,
            $row->header_number,
            $row->warehouse_outlet_name,
            $row->item_code,
            $row->item_name,
            $row->qty_display,
            $row->mac_per_unit ? number_format($row->mac_per_unit, 2, ',', '.') : '0,00',
            $row->subtotal_mac ? number_format($row->subtotal_mac, 2, ',', '.') : '0,00',
            $row->approvers,
            $row->approved_at ? date('d/m/Y H:i', strtotime($row->approved_at)) : '-',
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
            'A' => 12,  // Tanggal
            'B' => 15,  // Type
            'C' => 20,  // Nomor Transaksi
            'D' => 20,  // Warehouse Outlet
            'E' => 15,  // Kode Barang
            'F' => 30,  // Nama Barang
            'G' => 25,  // Qty (Konversi)
            'H' => 15,  // MAC Per Unit
            'I' => 15,  // Qty x MAC
            'J' => 30,  // Approver
            'K' => 20,  // Tanggal Approval
        ];
    }
}

