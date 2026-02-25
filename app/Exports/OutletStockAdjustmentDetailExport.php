<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletStockAdjustmentDetailExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $search;
    protected $from;
    protected $to;
    protected $userOutletId;

    public function __construct($search, $from, $to, $userOutletId)
    {
        $this->search = $search;
        $this->from = $from;
        $this->to = $to;
        $this->userOutletId = $userOutletId;
    }

    public function collection()
    {
        $query = DB::table('outlet_food_inventory_adjustment_items as i')
            ->join('outlet_food_inventory_adjustments as adj', 'i.adjustment_id', '=', 'adj.id')
            ->leftJoin('tbl_data_outlet as o', 'adj.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'adj.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'adj.created_by', '=', 'u.id')
            ->leftJoin('items as it', 'i.item_id', '=', 'it.id')
            ->select(
                'adj.number',
                'adj.date',
                'adj.type',
                'adj.reason',
                'adj.status',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name',
                'it.name as item_name',
                'i.qty',
                'i.unit',
                'i.note'
            );

        if ($this->userOutletId != 1) {
            $query->where('adj.id_outlet', $this->userOutletId);
        }

        if ($this->search) {
            $query->where('i.item_id', $this->search);
        }

        if ($this->from) {
            $query->whereDate('adj.date', '>=', $this->from);
        }

        if ($this->to) {
            $query->whereDate('adj.date', '<=', $this->to);
        }

        return $query->orderByDesc('adj.date')->orderByDesc('adj.id')->get();
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
            'Status',
            'Dibuat Oleh',
            'Item',
            'Qty',
            'Unit',
            'Catatan Item',
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
            $row->status ?? '-',
            $row->creator_name ?? '-',
            $row->item_name ?? '-',
            $row->qty ?? 0,
            $row->unit ?? '-',
            $row->note ?? '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F5E9'],
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
            'A' => 20,
            'B' => 14,
            'C' => 12,
            'D' => 24,
            'E' => 24,
            'F' => 36,
            'G' => 20,
            'H' => 24,
            'I' => 30,
            'J' => 12,
            'K' => 12,
            'L' => 36,
        ];
    }
}
