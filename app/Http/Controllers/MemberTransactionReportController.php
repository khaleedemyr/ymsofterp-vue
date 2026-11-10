<?php

namespace App\Http\Controllers;

use App\Exports\MemberTransactionReportExport;
use App\Services\RoleMenuService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MemberTransactionReportController extends Controller
{
    private const EXPORT_LIMIT = 20000;

    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $filters = $this->normalizeFilters($request);
        $base = $this->filteredQuery($filters);

        $totals = (clone $base)
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COUNT(DISTINCT LOWER(o.member_id)) as total_members')
            ->selectRaw('COALESCE(SUM(COALESCE(o.pax, 0)), 0) as total_cover')
            ->selectRaw('COALESCE(SUM(o.grand_total), 0) as total_amount')
            ->first();

        $paginator = (clone $base)
            ->select($this->selectColumns())
            ->orderByDesc('o.created_at')
            ->orderByDesc('o.id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $records = $this->mapRows(collect($paginator->items()));
        $paginator->setCollection($records);

        return Inertia::render('Crm/MemberTransactionReport', [
            'records' => $paginator,
            'totals' => [
                'total_transactions' => (int) ($totals->total_transactions ?? 0),
                'total_members' => (int) ($totals->total_members ?? 0),
                'total_cover' => (int) ($totals->total_cover ?? 0),
                'total_amount' => (float) ($totals->total_amount ?? 0),
                'total_amount_formatted' => 'Rp '.number_format((float) ($totals->total_amount ?? 0), 0, ',', '.'),
            ],
            'filters' => $filters,
            'outlets' => $this->outletOptions(),
            'tierOptions' => [
                '' => 'Semua Tier',
                'silver' => 'Silver',
                'loyal' => 'Loyal',
                'elite' => 'Elite',
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorizeView($request);

        $filters = $this->normalizeFilters($request);
        $rows = $this->filteredQuery($filters)
            ->select($this->selectColumns())
            ->orderByDesc('o.created_at')
            ->orderByDesc('o.id')
            ->limit(self::EXPORT_LIMIT)
            ->get();

        $mapped = $this->mapRows($rows);
        $from = $filters['date_from'] ?: 'all';
        $to = $filters['date_to'] ?: 'all';
        $filename = 'member-transaction-report_'.$from.'_'.$to.'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new MemberTransactionReportExport($mapped), $filename);
    }

    public function orderDetails(Request $request, string $orderId)
    {
        $this->authorizeView($request);

        $order = DB::connection('db_justus')
            ->table('orders as o')
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->where('o.id', $orderId)
            ->where('o.status', 'paid')
            ->whereNotNull('o.member_id')
            ->where('o.member_id', '!=', '')
            ->select([
                'o.id',
                'o.paid_number',
                'o.nomor',
                'o.pax',
                'o.grand_total',
                'o.status',
                'o.created_at',
                'o.kode_outlet',
                'o.member_id',
                'outlet.nama_outlet',
            ])
            ->first();

        if (! $order) {
            return response()->json(['status' => 'error', 'message' => 'Transaksi tidak ditemukan'], 404);
        }

        $items = DB::connection('db_justus')
            ->table('order_items')
            ->where('order_id', $order->id)
            ->select(['item_name', 'qty', 'price', 'modifiers', 'notes'])
            ->get()
            ->map(function ($item) {
                $qty = (float) ($item->qty ?? 0);
                $price = (float) ($item->price ?? 0);

                return [
                    'item_name' => (string) ($item->item_name ?? '-'),
                    'qty' => $qty,
                    'price' => $price,
                    'price_formatted' => 'Rp '.number_format($price, 0, ',', '.'),
                    'subtotal' => $qty * $price,
                    'subtotal_formatted' => 'Rp '.number_format($qty * $price, 0, ',', '.'),
                    'notes' => $item->notes,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'order' => [
                'id' => $order->id,
                'nomor' => $order->nomor,
                'paid_number' => $order->paid_number,
                'outlet_name' => $order->nama_outlet ?: ($order->kode_outlet ?: '-'),
                'pax' => (int) ($order->pax ?? 0),
                'grand_total' => (float) ($order->grand_total ?? 0),
                'grand_total_formatted' => 'Rp '.number_format((float) ($order->grand_total ?? 0), 0, ',', '.'),
                'status' => $order->status,
                'created_at' => $order->created_at
                    ? Carbon::parse($order->created_at)->format('Y-m-d H:i')
                    : null,
                'member_id' => $order->member_id,
            ],
            'items' => $items,
        ]);
    }

    /**
     * @return array{
     *   search: string,
     *   tier: string,
     *   outlet: string,
     *   date_from: string,
     *   date_to: string,
     *   per_page: int
     * }
     */
    private function normalizeFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->get('search', '')),
            'tier' => trim((string) $request->get('tier', '')),
            'outlet' => trim((string) $request->get('outlet', $request->get('outlet_id', ''))),
            'date_from' => trim((string) $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'))),
            'date_to' => trim((string) $request->get('date_to', Carbon::now()->format('Y-m-d'))),
            'per_page' => min(100, max(10, (int) $request->get('per_page', 25))),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters)
    {
        $search = (string) ($filters['search'] ?? '');
        $tier = strtolower((string) ($filters['tier'] ?? ''));
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $outletCandidates = $this->resolveOutletCandidates($filters['outlet'] ?? '');

        $memberKeysForTier = [];
        if ($tier !== '') {
            $memberKeysForTier = DB::table('member_apps_members')
                ->whereRaw("LOWER(COALESCE(member_level, 'silver')) = ?", [$tier])
                ->pluck('member_id')
                ->map(fn ($id) => strtolower((string) $id))
                ->filter()
                ->values()
                ->all();

            // Jika tidak ada member di tier ini, paksa hasil kosong.
            if ($memberKeysForTier === []) {
                $memberKeysForTier = ['__no_member__'];
            }
        }

        $searchMemberKeys = [];
        if ($search !== '') {
            $searchMemberKeys = DB::table('member_apps_members')
                ->where(function ($q) use ($search) {
                    $q->where('member_id', 'like', "%{$search}%")
                        ->orWhere('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('mobile_phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->pluck('member_id')
                ->map(fn ($id) => (string) $id)
                ->filter()
                ->values()
                ->all();
        }

        return DB::connection('db_justus')
            ->table('orders as o')
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->where('o.status', 'paid')
            ->whereNotNull('o.member_id')
            ->where('o.member_id', '!=', '')
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('o.created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('o.created_at', '<=', $dateTo))
            ->when($outletCandidates !== [], fn ($q) => $q->whereIn('o.kode_outlet', $outletCandidates))
            ->when($memberKeysForTier !== [], function ($q) use ($memberKeysForTier) {
                $q->whereIn(DB::raw('LOWER(o.member_id)'), $memberKeysForTier);
            })
            ->when($search !== '', function ($q) use ($search, $searchMemberKeys) {
                $q->where(function ($inner) use ($search, $searchMemberKeys) {
                    $inner->where('o.paid_number', 'like', "%{$search}%")
                        ->orWhere('o.nomor', 'like', "%{$search}%")
                        ->orWhere('o.id', 'like', "%{$search}%")
                        ->orWhere('o.member_id', 'like', "%{$search}%");
                    if ($searchMemberKeys !== []) {
                        $inner->orWhereIn('o.member_id', $searchMemberKeys);
                    }
                });
            });
    }

    /**
     * @return list<string|\Illuminate\Database\Query\Expression>
     */
    private function selectColumns(): array
    {
        return [
            'o.id',
            'o.member_id',
            'o.paid_number',
            'o.nomor',
            'o.pax',
            'o.grand_total',
            'o.kode_outlet',
            'o.created_at',
            'outlet.nama_outlet',
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function mapRows(Collection $rows): Collection
    {
        $memberIds = $rows->pluck('member_id')->filter()->unique()->values()->all();
        $profiles = collect();
        if ($memberIds !== []) {
            $lowerIds = array_map(fn ($id) => strtolower((string) $id), $memberIds);
            $profiles = DB::table('member_apps_members')
                ->where(function ($q) use ($memberIds, $lowerIds) {
                    $q->whereIn('member_id', $memberIds)
                        ->orWhereIn(DB::raw('LOWER(member_id)'), $lowerIds);
                })
                ->get(['member_id', 'nama_lengkap', 'mobile_phone', 'email', 'member_level'])
                ->keyBy(fn ($m) => strtolower((string) $m->member_id));
        }

        return $rows->map(function ($row) use ($profiles) {
            $key = strtolower((string) ($row->member_id ?? ''));
            $profile = $profiles->get($key);
            $tier = strtolower((string) ($profile->member_level ?? 'silver'));
            if ($tier === '') {
                $tier = 'silver';
            }

            return [
                'id' => (string) $row->id,
                'visit_date' => $row->created_at
                    ? Carbon::parse($row->created_at)->format('Y-m-d')
                    : null,
                'visit_at' => $row->created_at
                    ? Carbon::parse($row->created_at)->format('Y-m-d H:i')
                    : null,
                'member_id' => (string) ($profile->member_id ?? $row->member_id ?? '-'),
                'member_name' => (string) ($profile->nama_lengkap ?? '-'),
                'member_phone' => (string) ($profile->mobile_phone ?? ''),
                'tier' => $tier,
                'tier_label' => ucfirst($tier),
                'outlet_name' => (string) ($row->nama_outlet ?: ($row->kode_outlet ?: '-')),
                'kode_outlet' => (string) ($row->kode_outlet ?? ''),
                'check_id' => (string) ($row->paid_number ?: ($row->nomor ?: $row->id)),
                'paid_number' => $row->paid_number ? (string) $row->paid_number : null,
                'nomor' => $row->nomor ? (string) $row->nomor : null,
                'total_cover' => (int) ($row->pax ?? 0),
                'transaction_amount' => (float) ($row->grand_total ?? 0),
                'transaction_amount_formatted' => 'Rp '.number_format((float) ($row->grand_total ?? 0), 0, ',', '.'),
            ];
        })->values();
    }

    /**
     * @return list<string>
     */
    private function resolveOutletCandidates($outlet): array
    {
        $outlet = trim((string) $outlet);
        if ($outlet === '') {
            return [];
        }

        $candidates = [$outlet];
        $matched = DB::table('tbl_data_outlet')
            ->select('qr_code', 'nama_outlet', 'id_outlet')
            ->where('qr_code', $outlet)
            ->orWhere('nama_outlet', $outlet)
            ->when(is_numeric($outlet), fn ($q) => $q->orWhere('id_outlet', (int) $outlet))
            ->first();

        if ($matched && ! empty($matched->qr_code)) {
            $candidates[] = $matched->qr_code;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function outletOptions(): array
    {
        return DB::table('tbl_data_outlet')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet', 'qr_code'])
            ->map(fn ($o) => [
                'id' => (string) ($o->qr_code ?: $o->id_outlet),
                'name' => (string) $o->nama_outlet,
            ])
            ->values()
            ->all();
    }

    private function authorizeView(Request $request): void
    {
        $userId = (int) ($request->user()->id ?? 0);
        $allowed = RoleMenuService::allowedMenuCodesForInternalUser($userId);
        if (! in_array('member_transaction_report', $allowed, true)) {
            abort(403, 'Anda tidak punya akses ke Report Transaksi Member.');
        }
    }
}
