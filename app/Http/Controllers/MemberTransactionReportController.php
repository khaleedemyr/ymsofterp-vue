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
            ->selectRaw('COUNT(DISTINCT pt.member_id) as total_members')
            ->selectRaw('COALESCE(SUM(CASE WHEN pt.point_amount > 0 THEN pt.point_amount ELSE 0 END), 0) as total_earned')
            ->selectRaw('COALESCE(SUM(CASE WHEN pt.point_amount < 0 THEN ABS(pt.point_amount) ELSE 0 END), 0) as total_redeemed')
            ->selectRaw('COALESCE(SUM(CASE WHEN pt.transaction_amount > 0 THEN pt.transaction_amount ELSE 0 END), 0) as total_amount')
            ->first();

        $paginator = (clone $base)
            ->select($this->selectColumns())
            ->orderByDesc('pt.transaction_date')
            ->orderByDesc('pt.id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $records = $this->mapRows(collect($paginator->items()));
        $paginator->setCollection($records);

        return Inertia::render('Crm/MemberTransactionReport', [
            'records' => $paginator,
            'totals' => [
                'total_transactions' => (int) ($totals->total_transactions ?? 0),
                'total_members' => (int) ($totals->total_members ?? 0),
                'total_earned' => (int) ($totals->total_earned ?? 0),
                'total_redeemed' => (int) ($totals->total_redeemed ?? 0),
                'total_amount' => (float) ($totals->total_amount ?? 0),
                'total_amount_formatted' => 'Rp '.number_format((float) ($totals->total_amount ?? 0), 0, ',', '.'),
            ],
            'filters' => $filters,
            'outlets' => $this->outletOptions(),
            'channelOptions' => $this->channelOptions(),
            'typeOptions' => [
                '' => 'Semua Tipe',
                'earn' => 'Earned (POS)',
                'bonus' => 'Bonus',
                'redeem' => 'Redeemed',
                'adjustment' => 'Adjustment',
                'reward_redemption' => 'Reward Redemption',
                'voucher_purchase' => 'Voucher Purchase',
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorizeView($request);

        $filters = $this->normalizeFilters($request);
        $rows = $this->filteredQuery($filters)
            ->select($this->selectColumns())
            ->orderByDesc('pt.transaction_date')
            ->orderByDesc('pt.id')
            ->limit(self::EXPORT_LIMIT)
            ->get();

        $mapped = $this->mapRows($rows);
        $from = $filters['date_from'] ?: 'all';
        $to = $filters['date_to'] ?: 'all';
        $filename = 'member-transaction-report_'.$from.'_'.$to.'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new MemberTransactionReportExport($mapped), $filename);
    }

    public function orderDetails(Request $request, int $transactionId)
    {
        $this->authorizeView($request);

        $pt = DB::table('member_apps_point_transactions as pt')
            ->leftJoin('member_apps_members as m', 'm.id', '=', 'pt.member_id')
            ->where('pt.id', $transactionId)
            ->select([
                'pt.id',
                'pt.reference_id',
                'pt.point_amount',
                'pt.transaction_amount',
                'pt.transaction_type',
                'pt.channel',
                'pt.description',
                'm.member_id',
            ])
            ->first();

        if (! $pt) {
            return response()->json(['status' => 'error', 'message' => 'Transaksi tidak ditemukan'], 404);
        }

        $ref = trim((string) ($pt->reference_id ?? ''));
        $memberCode = trim((string) ($pt->member_id ?? ''));
        $txType = strtolower((string) ($pt->transaction_type ?? ''));
        $channel = strtolower((string) ($pt->channel ?? ''));
        $isBonusLike = $txType === 'bonus'
            || in_array($channel, ['registration', 'birthday', 'challenge_reward'], true);
        $refIsMemberId = $ref !== '' && $memberCode !== '' && strcasecmp($ref, $memberCode) === 0;

        if ($isBonusLike || $refIsMemberId) {
            return response()->json([
                'status' => 'success',
                'transaction' => [
                    'id' => (int) $pt->id,
                    'reference_id' => $ref !== '' ? $ref : null,
                    'point_amount' => (int) $pt->point_amount,
                    'transaction_amount' => (float) $pt->transaction_amount,
                    'transaction_type' => (string) $pt->transaction_type,
                    'description' => (string) ($pt->description ?? ''),
                ],
                'order' => null,
                'items' => [],
                'message' => 'Ini transaksi bonus/non-POS. Nilai di reference adalah member ID, bukan paid number bill.',
            ]);
        }

        $order = null;
        if ($ref !== '') {
            $order = DB::connection('db_justus')
                ->table('orders as o')
                ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
                ->select([
                    'o.id',
                    'o.paid_number',
                    'o.nomor',
                    'o.grand_total',
                    'o.status',
                    'o.created_at',
                    'o.kode_outlet',
                    'outlet.nama_outlet',
                ])
                ->where(function ($q) use ($ref) {
                    $q->where('o.id', $ref)->orWhere('o.paid_number', $ref);
                })
                ->first();
        }

        $items = [];
        if ($order) {
            $items = DB::connection('db_justus')
                ->table('order_items')
                ->where('order_id', $order->id)
                ->select(['item_name', 'qty', 'price', 'modifiers', 'notes'])
                ->get()
                ->map(function ($item) {
                    $qty = (float) ($item->qty ?? 0);
                    $price = (float) ($item->price ?? 0);
                    $modifiers = [];
                    if (! empty($item->modifiers)) {
                        try {
                            $modifiers = json_decode($item->modifiers, true) ?: [];
                        } catch (\Throwable $e) {
                            $modifiers = [];
                        }
                    }

                    return [
                        'item_name' => (string) ($item->item_name ?? '-'),
                        'qty' => $qty,
                        'price' => $price,
                        'price_formatted' => 'Rp '.number_format($price, 0, ',', '.'),
                        'subtotal' => $qty * $price,
                        'subtotal_formatted' => 'Rp '.number_format($qty * $price, 0, ',', '.'),
                        'modifiers' => $modifiers,
                        'notes' => $item->notes,
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json([
            'status' => 'success',
            'transaction' => [
                'id' => (int) $pt->id,
                'reference_id' => $ref !== '' ? $ref : null,
                'point_amount' => (int) $pt->point_amount,
                'transaction_amount' => (float) $pt->transaction_amount,
                'transaction_type' => (string) $pt->transaction_type,
                'description' => (string) ($pt->description ?? ''),
            ],
            'order' => $order ? [
                'id' => $order->id,
                'nomor' => $order->nomor ?? null,
                'paid_number' => $order->paid_number ?? null,
                'outlet_name' => $order->nama_outlet ?? '-',
                'grand_total' => (float) ($order->grand_total ?? 0),
                'grand_total_formatted' => 'Rp '.number_format((float) ($order->grand_total ?? 0), 0, ',', '.'),
                'status' => $order->status ?? null,
                'created_at' => $order->created_at
                    ? Carbon::parse($order->created_at)->format('Y-m-d H:i')
                    : null,
            ] : null,
            'items' => $items,
            'message' => $order ? null : 'Tidak ada bill POS yang terhubung ke transaksi ini.',
        ]);
    }

    /**
     * @return array{
     *   search: string,
     *   type: string,
     *   channel: string,
     *   outlet_id: int|string,
     *   date_from: string,
     *   date_to: string,
     *   per_page: int
     * }
     */
    private function normalizeFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->get('search', '')),
            'type' => trim((string) $request->get('type', '')),
            'channel' => trim((string) $request->get('channel', '')),
            'outlet_id' => $request->filled('outlet_id') ? (int) $request->get('outlet_id') : '',
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
        $type = (string) ($filters['type'] ?? '');
        $channel = (string) ($filters['channel'] ?? '');
        $outletId = $filters['outlet_id'] !== '' ? (int) $filters['outlet_id'] : null;
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');

        return DB::table('member_apps_point_transactions as pt')
            ->leftJoin('member_apps_members as m', 'm.id', '=', 'pt.member_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'pt.outlet_id')
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('pt.transaction_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('pt.transaction_date', '<=', $dateTo))
            ->when($outletId, fn ($q) => $q->where('pt.outlet_id', $outletId))
            ->when($channel !== '', fn ($q) => $q->where('pt.channel', $channel))
            ->when($type !== '', function ($q) use ($type) {
                if ($type === 'earn') {
                    $q->where('pt.transaction_type', 'earn')
                        ->where('pt.point_amount', '>', 0);
                } elseif ($type === 'redeem') {
                    $q->where(function ($inner) {
                        $inner->whereIn('pt.transaction_type', ['redeem', 'reward_redemption', 'voucher_purchase'])
                            ->orWhere('pt.point_amount', '<', 0);
                    });
                } else {
                    $q->where('pt.transaction_type', $type);
                }
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('m.member_id', 'like', "%{$search}%")
                        ->orWhere('m.nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('m.email', 'like', "%{$search}%")
                        ->orWhere('m.mobile_phone', 'like', "%{$search}%")
                        ->orWhere('pt.reference_id', 'like', "%{$search}%")
                        ->orWhere('pt.description', 'like', "%{$search}%");
                });
            });
    }

    /**
     * @return list<string>
     */
    private function selectColumns(): array
    {
        return [
            'pt.id',
            'pt.member_id as member_pk',
            'pt.outlet_id',
            'pt.transaction_type',
            'pt.transaction_date',
            'pt.point_amount',
            'pt.transaction_amount',
            'pt.earning_rate',
            'pt.channel',
            'pt.reference_id',
            'pt.description',
            'pt.expires_at',
            'pt.created_at',
            'm.member_id',
            'm.nama_lengkap',
            'm.mobile_phone',
            'm.email',
            'm.just_points',
            'o.nama_outlet',
            'o.qr_code',
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function mapRows(Collection $rows): Collection
    {
        $orderMeta = $this->resolveOrderMeta($rows);

        return $rows->map(function ($row) use ($orderMeta) {
            $pointAmount = (int) ($row->point_amount ?? 0);
            $isEarn = $pointAmount > 0;
            $ref = trim((string) ($row->reference_id ?? ''));
            $memberCode = trim((string) ($row->member_id ?? ''));
            $txType = strtolower((string) ($row->transaction_type ?? ''));
            $channel = strtolower((string) ($row->channel ?? ''));
            $meta = $ref !== '' ? ($orderMeta[$ref] ?? null) : null;

            $paidNumber = $meta['paid_number'] ?? null;
            $isBonusLike = in_array($txType, ['bonus'], true)
                || in_array($channel, ['registration', 'birthday', 'challenge_reward'], true);
            $refIsMemberId = $ref !== '' && $memberCode !== '' && strcasecmp($ref, $memberCode) === 0;

            if (! $paidNumber && ! $isBonusLike && ! $refIsMemberId && $ref !== '') {
                $desc = (string) ($row->description ?? '');
                $looksManual = str_contains($desc, 'ERP manual inject')
                    || str_contains($desc, '[ERP manual');
                $looksPaidNumber = (bool) preg_match('/^[A-Z]{2,}\d{5,}[A-Z0-9]*$/i', $ref)
                    && ! str_contains($ref, '-');
                if ($looksManual || ($looksPaidNumber && (float) ($row->transaction_amount ?? 0) > 0)) {
                    $paidNumber = $ref;
                }
            }

            $outletName = $row->nama_outlet
                ?: ($meta['outlet_name'] ?? null)
                ?: '-';

            $typeText = 'EARNED';
            if (! $isEarn) {
                $typeText = 'REDEEMED';
            } elseif ($isBonusLike) {
                $typeText = 'BONUS';
            }

            return [
                'id' => (int) $row->id,
                'member_pk' => (int) $row->member_pk,
                'member_id' => (string) ($row->member_id ?? '-'),
                'member_name' => (string) ($row->nama_lengkap ?? '-'),
                'member_phone' => (string) ($row->mobile_phone ?? ''),
                'member_email' => (string) ($row->email ?? ''),
                'member_balance' => (int) ($row->just_points ?? 0),
                'transaction_type' => (string) ($row->transaction_type ?? ''),
                'type_text' => $typeText,
                'is_earn' => $isEarn,
                'is_bonus' => $isBonusLike,
                'point_amount' => $pointAmount,
                'point_abs' => abs($pointAmount),
                'transaction_amount' => (float) ($row->transaction_amount ?? 0),
                'transaction_amount_formatted' => 'Rp '.number_format((float) ($row->transaction_amount ?? 0), 0, ',', '.'),
                'earning_rate' => (float) ($row->earning_rate ?? 0),
                'channel' => (string) ($row->channel ?? '-'),
                'reference_id' => $ref !== '' ? $ref : null,
                'paid_number' => $paidNumber,
                'order_id' => $meta['order_id'] ?? null,
                'description' => (string) ($row->description ?? ''),
                'outlet_id' => $row->outlet_id ? (int) $row->outlet_id : null,
                'outlet_name' => $outletName,
                'transaction_date' => $row->transaction_date
                    ? Carbon::parse($row->transaction_date)->format('Y-m-d')
                    : null,
                'expires_at' => $row->expires_at
                    ? Carbon::parse($row->expires_at)->format('Y-m-d')
                    : null,
                'created_at' => $row->created_at
                    ? Carbon::parse($row->created_at)->format('Y-m-d H:i')
                    : null,
                'has_order_details' => ! empty($meta['order_id']),
            ];
        })->values();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, array{order_id?: string, id?: string, paid_number?: string|null, outlet_name?: string|null}>
     */
    private function resolveOrderMeta(Collection $rows): array
    {
        $refs = $rows->pluck('reference_id')->filter()->unique()->values()->all();
        if ($refs === []) {
            return [];
        }

        $orders = DB::connection('db_justus')
            ->table('orders as o')
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->where(function ($q) use ($refs) {
                $q->whereIn('o.id', $refs)->orWhereIn('o.paid_number', $refs);
            })
            ->select([
                'o.id',
                'o.paid_number',
                'o.member_id',
                'outlet.nama_outlet',
            ])
            ->get();

        $meta = [];
        foreach ($orders as $order) {
            $payload = [
                'order_id' => $order->id,
                'id' => $order->id,
                'paid_number' => $order->paid_number,
                'outlet_name' => $order->nama_outlet,
            ];
            $meta[(string) $order->id] = $payload;
            if (! empty($order->paid_number)) {
                $meta[(string) $order->paid_number] = $payload;
            }
        }

        return $meta;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function outletOptions(): array
    {
        return DB::table('tbl_data_outlet')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet'])
            ->map(fn ($o) => [
                'id' => (int) $o->id_outlet,
                'name' => (string) $o->nama_outlet,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function channelOptions(): array
    {
        return DB::table('member_apps_point_transactions')
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->distinct()
            ->orderBy('channel')
            ->pluck('channel')
            ->map(fn ($c) => (string) $c)
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
