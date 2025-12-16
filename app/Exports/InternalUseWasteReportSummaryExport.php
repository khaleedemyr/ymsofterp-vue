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
        
        foreach ($outlets as $outlet) {
            // Query untuk mengambil data internal use waste dengan type RnD, Marketing, Wrong Maker per outlet
            $query = DB::table('outlet_internal_use_waste_headers as h')
                ->join('outlet_internal_use_waste_details as d', 'h.id', '=', 'd.header_id')
                ->join('items as item', 'd.item_id', '=', 'item.id')
                ->leftJoin('outlet_food_inventory_items as fi', 'item.id', '=', 'fi.item_id')
                ->where('h.outlet_id', $outlet->id_outlet)
                ->whereIn('h.type', ['r_and_d', 'marketing', 'wrong_maker'])
                ->where('h.status', 'APPROVED')
                ->whereBetween('h.date', [$this->startDate, $this->endDate])
                ->select(
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
                );
            
            $items = $query->get();
            $totalMac = 0;
            
            foreach ($items as $row) {
                // Ambil MAC dari cost history pada tanggal transaksi
                $mac = 0;
                if ($row->inventory_item_id) {
                    $macRow = DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $row->inventory_item_id)
                        ->where('id_outlet', $outlet->id_outlet)
                        ->where('warehouse_outlet_id', $row->warehouse_outlet_id)
                        ->where('date', '<=', $row->date)
                        ->orderByDesc('date')
                        ->orderByDesc('id')
                        ->select('mac')
                        ->first();
                    
                    if ($macRow) {
                        $mac = (float) ($macRow->mac ?? 0);
                    } else {
                        $stockRow = DB::table('outlet_food_inventory_stocks')
                            ->where('inventory_item_id', $row->inventory_item_id)
                            ->where('id_outlet', $outlet->id_outlet)
                            ->where('warehouse_outlet_id', $row->warehouse_outlet_id)
                            ->select('last_cost_small')
                            ->first();
                        
                        if ($stockRow) {
                            $mac = (float) ($stockRow->last_cost_small ?? 0);
                        }
                    }
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

