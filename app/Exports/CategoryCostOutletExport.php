<?php

namespace App\Exports;

use App\Support\CategoryCostMacResolver;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class CategoryCostOutletExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
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
        // Type yang memerlukan approval: hanya yang sudah approved yang masuk ke report
        $typesRequiringApproval = ['r_and_d', 'marketing', 'wrong_maker', 'training'];
        
        // Query sama dengan controller reportUniversal
        $query = DB::table('outlet_internal_use_waste_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select(
                'h.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );
        
        if ($this->userOutletId != 1) {
            $query->where('h.outlet_id', $this->userOutletId);
        } else if ($this->outletId) {
            $query->where('h.outlet_id', $this->outletId);
        }
        
        if ($this->type) {
            $query->where('h.type', $this->type);
            // Jika type memerlukan approval, hanya ambil yang sudah approved
            if (in_array($this->type, $typesRequiringApproval)) {
                $query->where('h.status', 'APPROVED');
            }
        } else {
            // Jika tidak ada filter type, untuk type yang memerlukan approval hanya ambil yang sudah approved
            $query->where(function($q) use ($typesRequiringApproval) {
                // Type yang tidak memerlukan approval: semua status
                $q->whereNotIn('h.type', $typesRequiringApproval)
                  // Type yang memerlukan approval: hanya yang sudah approved
                  ->orWhere(function($subQ) use ($typesRequiringApproval) {
                      $subQ->whereIn('h.type', $typesRequiringApproval)
                           ->where('h.status', 'APPROVED');
                  });
            });
        }
        
        if ($this->warehouseOutletId) {
            $query->where('h.warehouse_outlet_id', $this->warehouseOutletId);
        }
        
        $query->where('h.date', '>=', $this->from);
        $query->where('h.date', '<=', $this->to);
        
        $data = $query->orderByDesc('h.date')->orderByDesc('h.id')->get();
        
        // Hitung subtotal MAC per header (sama seperti di controller)
        $headerIds = $data->pluck('id')->all();
        $subtotalPerHeader = [];
        
        if ($headerIds && count($headerIds) > 0) {
            $details = DB::table('outlet_internal_use_waste_details as d')
                ->join('outlet_internal_use_waste_headers as h', 'd.header_id', '=', 'h.id')
                ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
                ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
                ->select('d.*', 'h.type as header_type', 'h.date as header_date', 'h.outlet_id as header_outlet_id', 'h.warehouse_outlet_id as header_warehouse_outlet_id', 'i.type as item_type', 'i.small_unit_id', 'i.medium_unit_id', 'i.large_unit_id', 'i.small_conversion_qty', 'i.medium_conversion_qty')
                ->whereIn('d.header_id', $headerIds)
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
                    $header = $headerData->get($detail->header_id);
                    if ($header && isset($inventoryItems[$detail->item_id])) {
                        $inventoryItemId = $inventoryItems[$detail->item_id]->id;
                        $key = "{$inventoryItemId}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
                        if (!isset($macQueryConditions[$key])) {
                            $macQueryConditions[$key] = [
                                'inventory_item_id' => $inventoryItemId,
                                'id_outlet' => $header->outlet_id,
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
                        $macHistories[$macKey] = CategoryCostMacResolver::historyMacPerSmall($macRow);
                    }
                }
            }
            
            foreach ($details as $item) {
                $mac = null;
                if (isset($inventoryItems[$item->item_id])) {
                    $inventoryItem = $inventoryItems[$item->item_id];
                    $header = $data->firstWhere('id', $item->header_id);
                    if ($header) {
                        $macKey = "{$inventoryItem->id}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
                        if (isset($macHistories[$macKey])) {
                            $mac = $macHistories[$macKey];
                        }
                    }
                }
                
                $lineMac = self::categoryCostLineMac(
                    $item,
                    $mac,
                    (int) ($item->header_outlet_id ?? 0),
                    (int) ($item->header_warehouse_outlet_id ?? 0),
                    (string) ($item->header_date ?? '')
                );
                $subtotal_mac = $lineMac['subtotal_mac'];
                
                if (!isset($subtotalPerHeader[$item->header_id])) {
                    $subtotalPerHeader[$item->header_id] = 0;
                }
                $subtotalPerHeader[$item->header_id] += $subtotal_mac;
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
        
        // Ambil detail items untuk setiap header
        $exportData = [];
        $no = 0;
        
        foreach ($data as $header) {
            $details = DB::table('outlet_internal_use_waste_details as d')
                ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
                ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
                ->select(
                    'd.*',
                    'i.name as item_name',
                    'i.type as item_type',
                    'i.small_unit_id',
                    'i.medium_unit_id',
                    'i.large_unit_id',
                    'i.small_conversion_qty',
                    'i.medium_conversion_qty',
                    'u.name as unit_name'
                )
                ->where('d.header_id', $header->id)
                ->get();
            
            // Ambil inventory item dan MAC
            $inventoryItems = [];
            $itemIds = $details->pluck('item_id')->unique()->all();
            if (count($itemIds) > 0) {
                $inventoryItemsData = DB::table('outlet_food_inventory_items')
                    ->whereIn('item_id', $itemIds)
                    ->get()
                    ->keyBy('item_id');
                $inventoryItems = $inventoryItemsData->toArray();
            }
            
            // Ambil MAC histories
            $macHistories = [];
            if (count($inventoryItems) > 0) {
                foreach ($details as $detail) {
                    if (isset($inventoryItems[$detail->item_id])) {
                        $inventoryItem = $inventoryItems[$detail->item_id];
                        $macRow = DB::table('outlet_food_inventory_cost_histories')
                            ->where('inventory_item_id', $inventoryItem->id)
                            ->where('id_outlet', $header->outlet_id)
                            ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                            ->where('date', '<=', $header->date)
                            ->orderByDesc('date')
                            ->orderByDesc('id')
                            ->first();
                        if ($macRow) {
                            $macHistories[$detail->item_id] = CategoryCostMacResolver::historyMacPerSmall($macRow);
                        }
                    }
                }
            }
            
            // Type label
            $typeLabel = $this->getTypeLabel($header->type);
            
            // Format date
            $dateFormatted = Carbon::parse($header->date)->format('d/m/Y');
            
            // Jika ada detail items, buat row untuk setiap item
            if ($details->count() > 0) {
                foreach ($details as $detail) {
                    $no++;
                    
                    $mac = $macHistories[$detail->item_id] ?? null;
                    $lineMac = self::categoryCostLineMac(
                        $detail,
                        $mac,
                        (int) $header->outlet_id,
                        (int) $header->warehouse_outlet_id,
                        (string) $header->date
                    );
                    $mac_converted = $lineMac['mac_converted'] ?? 0;
                    $qtyTimesMac = $lineMac['subtotal_mac'];
                    $subtotalMac = $lineMac['subtotal_mac'];
                    
                    $exportData[] = (object) [
                        'no' => $no,
                        'date' => $dateFormatted,
                        'type' => $typeLabel,
                        'subtotal_mac' => $header->subtotal_mac,
                        'outlet_name' => $header->outlet_name,
                        'warehouse_outlet_name' => $header->warehouse_outlet_name,
                        'notes' => $header->notes ?? '-',
                        'item_name' => $detail->item_name,
                        'qty' => $detail->qty,
                        'unit_name' => $detail->unit_name,
                        'mac_converted' => $mac_converted ?? 0,
                        'qty_times_mac' => $qtyTimesMac,
                        'subtotal_mac_item' => $subtotalMac,
                        'note' => $detail->note ?? '-',
                    ];
                }
            } else {
                // Jika tidak ada detail, tetap buat row untuk header
                $no++;
                $exportData[] = (object) [
                    'no' => $no,
                    'date' => $dateFormatted,
                    'type' => $typeLabel,
                    'subtotal_mac' => $header->subtotal_mac,
                    'outlet_name' => $header->outlet_name,
                    'warehouse_outlet_name' => $header->warehouse_outlet_name,
                    'notes' => $header->notes ?? '-',
                    'item_name' => '-',
                    'qty' => '-',
                    'unit_name' => '-',
                    'mac_converted' => '-',
                    'qty_times_mac' => '-',
                    'subtotal_mac_item' => '-',
                    'note' => '-',
                ];
            }
        }
        
        return collect($exportData);
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Tipe',
            'Subtotal MAC (Header)',
            'Outlet',
            'Warehouse Outlet',
            'Catatan (Header)',
            'Item',
            'Qty',
            'Unit',
            'MAC (per unit)',
            'Qty × MAC',
            'Subtotal MAC (Item)',
            'Catatan (Item)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->no,
            $row->date,
            $row->type,
            $row->subtotal_mac,
            $row->outlet_name,
            $row->warehouse_outlet_name,
            $row->notes,
            $row->item_name,
            $row->qty,
            $row->unit_name,
            $row->mac_converted !== '-' ? number_format($row->mac_converted, 2, ',', '.') : '-',
            $row->qty_times_mac !== '-' ? number_format($row->qty_times_mac, 2, ',', '.') : '-',
            $row->subtotal_mac_item !== '-' ? number_format($row->subtotal_mac_item, 2, ',', '.') : '-',
            $row->note,
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
            'B' => 15,
            'C' => 15,
            'D' => 20,
            'E' => 25,
            'F' => 25,
            'G' => 30,
            'H' => 40,
            'I' => 15,
            'J' => 15,
            'K' => 18,
            'L' => 18,
            'M' => 20,
            'N' => 30,
        ];
    }

    /**
     * @return array{mac_converted: ?float, subtotal_mac: float}
     */
    private static function categoryCostLineMac(
        object $detailRow,
        ?float $historyMac,
        int $outletId,
        int $warehouseOutletId,
        string $asOfDate
    ): array {
        if ($historyMac === null) {
            return ['mac_converted' => null, 'subtotal_mac' => 0.0];
        }

        $itemMaster = (object) [
            'id' => $detailRow->item_id,
            'type' => $detailRow->item_type ?? null,
            'small_unit_id' => $detailRow->small_unit_id,
            'medium_unit_id' => $detailRow->medium_unit_id,
            'large_unit_id' => $detailRow->large_unit_id,
            'small_conversion_qty' => $detailRow->small_conversion_qty,
            'medium_conversion_qty' => $detailRow->medium_conversion_qty,
        ];

        $macPerSmall = CategoryCostMacResolver::resolveMacPerSmallUnit(
            $itemMaster,
            $historyMac,
            $outletId,
            $warehouseOutletId,
            $asOfDate
        );
        $macConverted = CategoryCostMacResolver::convertMacToUnit(
            $macPerSmall,
            $itemMaster,
            (int) $detailRow->unit_id
        );

        return [
            'mac_converted' => $macConverted,
            'subtotal_mac' => $macConverted * (float) ($detailRow->qty ?? 0),
        ];
    }

    private function getTypeLabel($type)
    {
        $labels = [
            'internal_use' => 'Internal Use',
            'spoil' => 'Spoil',
            'waste' => 'Waste',
            'stock_cut' => 'Usage', /* legacy type */
            'r_and_d' => 'R & D',
            'marketing' => 'Marketing',
            'non_commodity' => 'Non Commodity',
            'guest_supplies' => 'Guest Supplies',
            'wrong_maker' => 'Wrong Maker',
            'training' => 'Training',
            'usage' => 'Usage',
        ];
        
        return $labels[$type] ?? $type;
    }
}

