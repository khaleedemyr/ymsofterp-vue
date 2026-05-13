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

class PosVoidBillFromPosExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected ?string $kodeOutlet;

    protected ?string $status;

    protected string $dateFrom;

    protected string $dateTo;

    protected int $userOutletId;

    public function __construct(?string $kodeOutlet, ?string $status, string $dateFrom, string $dateTo, int $userOutletId)
    {
        $this->kodeOutlet = $kodeOutlet;
        $this->status = $status;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->userOutletId = $userOutletId;
    }

    private function baseQuery()
    {
        $query = DB::table('pos_void_item_requests as r')
            ->leftJoin('tbl_data_outlet as ou', 'ou.qr_code', '=', 'r.kode_outlet')
            ->leftJoin('users as appr_user', 'appr_user.id', '=', 'r.approved_by_user_id')
            ->leftJoin('users as req_user', 'req_user.id', '=', 'r.requester_user_id')
            ->select(
                'r.id',
                'r.kode_outlet',
                'r.order_id',
                'r.order_nomor',
                'r.order_item_id',
                'r.void_entire_order',
                'r.requester_user_id',
                'r.requester_username',
                'r.reason',
                'r.item_snapshot',
                'r.status',
                'r.approved_at',
                'r.approved_by_user_id',
                'r.rejected_at',
                'r.rejection_note',
                'r.created_at',
                'ou.nama_outlet as outlet_name',
                'appr_user.nama_lengkap as approved_by_name',
                'req_user.nama_lengkap as requester_user_nama',
            );

        if ($this->userOutletId !== 1) {
            $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $this->userOutletId)->first();
            if ($outlet && $outlet->qr_code) {
                $query->where('r.kode_outlet', $outlet->qr_code);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($this->kodeOutlet) {
            $query->where('r.kode_outlet', $this->kodeOutlet);
        }

        if ($this->status) {
            $query->where('r.status', $this->status);
        }
        $query->whereDate('r.created_at', '>=', $this->dateFrom)
            ->whereDate('r.created_at', '<=', $this->dateTo);

        return $query->orderByDesc('r.created_at')->orderByDesc('r.id')->limit(8000);
    }

    public function collection()
    {
        $rows = $this->baseQuery()->get();
        if ($rows->isEmpty()) {
            return collect([]);
        }

        $ids = $rows->pluck('id')->all();
        $approverMap = DB::table('pos_void_item_request_approvers as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->whereIn('a.pos_void_item_request_id', $ids)
            ->select('a.pos_void_item_request_id', DB::raw("GROUP_CONCAT(DISTINCT u.nama_lengkap ORDER BY u.nama_lengkap SEPARATOR '; ') as names"))
            ->groupBy('a.pos_void_item_request_id')
            ->pluck('names', 'pos_void_item_request_id')
            ->all();

        return $rows->map(function ($row) use ($approverMap) {
            $snap = is_string($row->item_snapshot ?? null) ? json_decode($row->item_snapshot, true) : [];
            $snap = is_array($snap) ? $snap : [];
            $itemLabel = $snap['name'] ?? $snap['item_name'] ?? '-';
            $entire = (int) ($row->void_entire_order ?? 0) === 1;
            $paidVoid = ! empty($snap['paid_void']);
            if ($entire && $paidVoid) {
                $voidType = 'Void bill paid (POS)';
            } elseif ($entire) {
                $voidType = 'Void order / seluruh unpaid (POS)';
            } else {
                $voidType = 'Void item baris (POS)';
            }
            $requester = trim((string) ($row->requester_user_nama ?: '')) !== ''
                ? $row->requester_user_nama
                : ($row->requester_username ?: '-');

            return (object) [
                'id' => $row->id,
                'created_at' => $row->created_at,
                'outlet_name' => $row->outlet_name ?: $row->kode_outlet,
                'kode_outlet' => $row->kode_outlet,
                'order_nomor' => $row->order_nomor ?: $row->order_id,
                'order_id' => $row->order_id,
                'order_item_id' => $row->order_item_id,
                'void_type' => $voidType,
                'item_label' => $itemLabel,
                'requester' => $requester,
                'requester_user_id' => $row->requester_user_id,
                'designated_approvers' => $approverMap[$row->id] ?? $approverMap[(string) $row->id] ?? '-',
                'approved_by' => $row->approved_by_name ?: '-',
                'status' => $row->status,
                'approved_at' => $row->approved_at,
                'rejected_at' => $row->rejected_at,
                'reason' => $row->reason,
                'rejection_note' => $row->rejection_note,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tanggal ajuan',
            'Outlet',
            'Kode outlet (POS)',
            'No. order',
            'Order ID',
            'Order item ID',
            'Tipe void',
            'Item / label (snapshot)',
            'Pemohon void',
            'ID user pemohon',
            'Approver (ditunjuk di POS)',
            'User HO yang approve/reject',
            'Status',
            'Waktu approve',
            'Waktu tolak',
            'Alasan void',
            'Catatan penolakan',
        ];
    }

    /**
     * @param  object  $row
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->created_at ? Carbon::parse($row->created_at)->format('d/m/Y H:i') : '-',
            $row->outlet_name,
            $row->kode_outlet,
            $row->order_nomor,
            $row->order_id,
            $row->order_item_id ?? '-',
            $row->void_type,
            $row->item_label,
            $row->requester,
            $row->requester_user_id ?? '-',
            $row->designated_approvers,
            $row->approved_by,
            $row->status,
            $row->approved_at ? Carbon::parse($row->approved_at)->format('d/m/Y H:i') : '-',
            $row->rejected_at ? Carbon::parse($row->rejected_at)->format('d/m/Y H:i') : '-',
            $row->reason,
            $row->rejection_note ?: '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8, 'B' => 18, 'C' => 26, 'D' => 14, 'E' => 22, 'F' => 12, 'G' => 12, 'H' => 26,
            'I' => 32, 'J' => 22, 'K' => 12, 'L' => 36, 'M' => 26, 'N' => 12, 'O' => 18, 'P' => 18, 'Q' => 36, 'R' => 28,
        ];
    }
}
