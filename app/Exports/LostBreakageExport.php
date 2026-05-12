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

class LostBreakageExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $outletId;
    protected $status;
    protected $type;
    protected $from;
    protected $to;
    protected $userOutletId;

    public function __construct($outletId, $status, $type, $from, $to, $userOutletId)
    {
        $this->outletId = $outletId;
        $this->status = $status;
        $this->type = $type;
        $this->from = $from;
        $this->to = $to;
        $this->userOutletId = $userOutletId;
    }

    public function collection()
    {
        $query = DB::table('lost_breakage_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select('h.*', 'o.nama_outlet as outlet_name', 'u.nama_lengkap as creator_name');

        if ($this->userOutletId != 1) {
            $query->where('h.outlet_id', $this->userOutletId);
        } elseif ($this->outletId) {
            $query->where('h.outlet_id', $this->outletId);
        }

        if ($this->status) {
            $query->where('h.status', $this->status);
        }

        if ($this->from) {
            $query->whereDate('h.date', '>=', $this->from);
        }
        if ($this->to) {
            $query->whereDate('h.date', '<=', $this->to);
        }

        $headers = $query->orderByDesc('h.date')->orderByDesc('h.id')->get();
        $headerIds = $headers->pluck('id')->toArray();

        if (empty($headerIds)) {
            return collect([]);
        }

        $details = DB::table('lost_breakage_details as d')
            ->join('items as i', 'd.item_id', '=', 'i.id')
            ->join('units as un', 'd.unit_id', '=', 'un.id')
            ->whereIn('d.header_id', $headerIds)
            ->select('d.*', 'i.name as item_name', 'un.name as unit_name')
            ->get()
            ->groupBy('header_id');

        $approvalFlows = DB::table('lost_breakage_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->whereIn('af.header_id', $headerIds)
            ->orderBy('af.approval_level')
            ->select('af.*', 'u.nama_lengkap as approver_name')
            ->get()
            ->groupBy('header_id');

        $exportData = [];
        $no = 0;

        foreach ($headers as $header) {
            $items = $details[$header->id] ?? collect([]);
            $flows = $approvalFlows[$header->id] ?? collect([]);

            $approvalInfo = $flows->map(function ($f) {
                $status = $f->status;
                $date = $f->approved_at ?? $f->rejected_at;
                $dateStr = $date ? Carbon::parse($date)->format('d/m/Y H:i') : '-';
                return "L{$f->approval_level}: {$f->approver_name} ({$status}" . ($date ? " {$dateStr}" : '') . ")";
            })->implode(' | ');

            if ($items->count() > 0) {
                foreach ($items as $item) {
                    $no++;
                    $typeFilter = $this->type;
                    if ($typeFilter && $item->type !== $typeFilter) continue;

                    $exportData[] = (object) [
                        'no' => $no,
                        'number' => $header->number,
                        'date' => Carbon::parse($header->date)->format('d/m/Y'),
                        'outlet_name' => $header->outlet_name,
                        'creator_name' => $header->creator_name,
                        'status' => $header->status,
                        'item_type' => ucfirst($item->type ?? 'lost'),
                        'item_name' => $item->item_name,
                        'qty' => $item->qty,
                        'unit_name' => $item->unit_name,
                        'note' => $item->note ?? '-',
                        'has_photo' => $item->photo ? 'Ya' : 'Tidak',
                        'header_notes' => $header->notes ?? '-',
                        'approval' => $approvalInfo ?: '-',
                    ];
                }
            } else {
                $no++;
                $exportData[] = (object) [
                    'no' => $no,
                    'number' => $header->number,
                    'date' => Carbon::parse($header->date)->format('d/m/Y'),
                    'outlet_name' => $header->outlet_name,
                    'creator_name' => $header->creator_name,
                    'status' => $header->status,
                    'item_type' => '-',
                    'item_name' => '-',
                    'qty' => '-',
                    'unit_name' => '-',
                    'note' => '-',
                    'has_photo' => '-',
                    'header_notes' => $header->notes ?? '-',
                    'approval' => $approvalInfo ?: '-',
                ];
            }
        }

        return collect($exportData);
    }

    public function headings(): array
    {
        return ['No', 'Nomor', 'Tanggal', 'Outlet', 'Dibuat Oleh', 'Status', 'Tipe Item', 'Nama Item', 'Qty', 'Unit', 'Keterangan Item', 'Foto', 'Catatan Header', 'Approval'];
    }

    public function map($row): array
    {
        return [$row->no, $row->number, $row->date, $row->outlet_name, $row->creator_name, $row->status, $row->item_type, $row->item_name, $row->qty, $row->unit_name, $row->note, $row->has_photo, $row->header_notes, $row->approval];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F97316']],
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 22, 'C' => 14, 'D' => 28, 'E' => 22, 'F' => 14, 'G' => 12, 'H' => 35, 'I' => 10, 'J' => 12, 'K' => 30, 'L' => 8, 'M' => 30, 'N' => 50];
    }
}
