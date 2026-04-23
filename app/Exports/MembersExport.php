<?php

namespace App\Exports;

use App\Models\MemberAppsMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MembersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection(): Collection
    {
        $query = MemberAppsMember::query();

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile_phone', 'like', "%{$search}%")
                    ->orWhere('member_id', 'like', "%{$search}%");
            });
        }

        if (!empty($this->filters['status'])) {
            if ($this->filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($this->filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if (!empty($this->filters['tier'])) {
            $query->whereRaw('LOWER(member_level) = ?', [strtolower($this->filters['tier'])]);
        }

        if (!empty($this->filters['point_balance'])) {
            switch ($this->filters['point_balance']) {
                case 'positive':
                    $query->where('just_points', '>', 0);
                    break;
                case 'negative':
                    $query->where('just_points', '<', 0);
                    break;
                case 'zero':
                    $query->where('just_points', 0);
                    break;
                case 'high':
                    $query->where('just_points', '>=', 1000);
                    break;
            }
        }

        $sort = $this->filters['sort'] ?? 'created_at';
        $direction = strtolower($this->filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $sortMap = [
            'member_id' => 'member_id',
            'name' => 'nama_lengkap',
            'email' => 'email',
            'mobile_phone' => 'mobile_phone',
            'telepon' => 'mobile_phone',
            'status_aktif' => 'is_active',
            'tier' => 'member_level',
            'created_at' => 'created_at',
        ];

        if (array_key_exists($sort, $sortMap)) {
            $query->orderBy($sortMap[$sort], $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $exportLimit = $this->filters['export_limit'] ?? '1000';
        if ($exportLimit !== 'all') {
            $limit = (int) $exportLimit;
            if ($limit > 0) {
                $query->limit($limit);
            }
        }

        $members = $query->get();

        $memberIds = $members->pluck('member_id')->filter()->values()->all();
        $spendingLastYearByMemberId = [];
        $totalSpendingByMemberId = [];
        $lastSpendingByMemberId = [];

        if (!empty($memberIds)) {
            $oneYearAgo = now()->subYear();

            $spendingLastYearByMemberId = DB::connection('db_justus')
                ->table('orders')
                ->whereIn('member_id', $memberIds)
                ->where('created_at', '>=', $oneYearAgo)
                ->where('status', 'paid')
                ->select('member_id', DB::raw('SUM(grand_total) as total'))
                ->groupBy('member_id')
                ->pluck('total', 'member_id')
                ->toArray();

            $totalSpendingByMemberId = DB::connection('db_justus')
                ->table('orders')
                ->whereIn('member_id', $memberIds)
                ->where('status', 'paid')
                ->select('member_id', DB::raw('SUM(grand_total) as total'))
                ->groupBy('member_id')
                ->pluck('total', 'member_id')
                ->toArray();

            $latestOrderSub = DB::connection('db_justus')
                ->table('orders')
                ->whereIn('member_id', $memberIds)
                ->where('status', 'paid')
                ->select('member_id', DB::raw('MAX(created_at) as max_created_at'))
                ->groupBy('member_id');

            $latestOrders = DB::connection('db_justus')
                ->table('orders as o1')
                ->joinSub($latestOrderSub, 'latest', function ($join) {
                    $join->on('o1.member_id', '=', 'latest.member_id')
                        ->on('o1.created_at', '=', 'latest.max_created_at');
                })
                ->leftJoin('tbl_data_outlet as o', 'o1.kode_outlet', '=', 'o.qr_code')
                ->select('o1.member_id', 'o1.grand_total', 'o1.created_at', 'o.nama_outlet')
                ->orderBy('o1.created_at', 'desc')
                ->get();

            foreach ($latestOrders as $order) {
                if (!isset($lastSpendingByMemberId[$order->member_id])) {
                    $lastSpendingByMemberId[$order->member_id] = [
                        'amount' => (float) ($order->grand_total ?? 0),
                        'outlet_name' => $order->nama_outlet ?: 'Outlet Tidak Diketahui',
                        'created_at' => $order->created_at,
                    ];
                }
            }
        }

        return $members->map(function ($member) use ($spendingLastYearByMemberId, $totalSpendingByMemberId, $lastSpendingByMemberId) {
            $memberId = $member->member_id;
            $member->point_balance = (float) ($member->just_points ?? 0);
            $member->spending_last_year = (float) ($spendingLastYearByMemberId[$memberId] ?? 0);
            $member->total_spending_amount = (float) ($totalSpendingByMemberId[$memberId] ?? 0);
            $member->last_spending_data = $lastSpendingByMemberId[$memberId] ?? null;
            return $member;
        });
    }

    public function headings(): array
    {
        return [
            'Member ID',
            'Nama Lengkap',
            'Email',
            'No Telepon',
            'Status',
            'Tier',
            'Saldo Point',
            'Total Spending',
            'Spending Setahun Terakhir',
            'Last Spending',
            'Last Spending Outlet',
            'Last Spending Date',
            'Tanggal Daftar',
        ];
    }

    public function map($member): array
    {
        $lastData = $member->last_spending_data;

        return [
            $member->member_id,
            $member->nama_lengkap,
            $member->email,
            $member->mobile_phone,
            $member->is_active ? 'Aktif' : 'Nonaktif',
            ucfirst(strtolower($member->member_level ?? 'silver')),
            number_format((float) ($member->point_balance ?? 0), 0, ',', '.'),
            number_format((float) ($member->total_spending_amount ?? 0), 0, ',', '.'),
            number_format((float) ($member->spending_last_year ?? 0), 0, ',', '.'),
            number_format((float) (($lastData['amount'] ?? 0)), 0, ',', '.'),
            $lastData['outlet_name'] ?? '-',
            !empty($lastData['created_at']) ? date('d/m/Y H:i', strtotime($lastData['created_at'])) : '-',
            $member->created_at ? $member->created_at->format('d/m/Y H:i') : '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 28,
            'C' => 28,
            'D' => 18,
            'E' => 12,
            'F' => 12,
            'G' => 14,
            'H' => 18,
            'I' => 24,
            'J' => 16,
            'K' => 24,
            'L' => 20,
            'M' => 20,
        ];
    }
}
