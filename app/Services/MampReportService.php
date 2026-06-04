<?php

namespace App\Services;

use App\Models\PurchaseRequisitionCategory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MampReportService
{
    /**
     * @return array{
     *   category: object,
     *   period: array{year:int,month:int,date_from:string,date_to:string,label:string,prev_label:string},
     *   rows: array<int, array<string, mixed>>,
     *   summary: array<string, float>
     * }
     */
    public function build(int $categoryId, int $year, int $month): array
    {
        $category = PurchaseRequisitionCategory::query()->findOrFail($categoryId);

        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $dateFrom = $periodStart->format('Y-m-d');
        $dateTo = $periodStart->copy()->endOfMonth()->format('Y-m-d');

        $monthlyBudget = (float) ($category->budget_limit ?? 0);

        $rows = [];
        $no = 1;

        $rows[] = $this->debitRow(
            $no++,
            $periodStart->format('Y-m-d'),
            'SALDO AWAL',
            $this->saldoAwalLabel($category, $periodStart),
            $monthlyBudget
        );

        $credits = $this->fetchCreditLines($categoryId, $dateFrom, $dateTo);
        foreach ($credits as $line) {
            $rows[] = [
                'no' => $no++,
                'row_key' => $line->row_key,
                'date' => $line->transaction_date,
                'date_label' => $this->formatDateLabel($line->transaction_date),
                'outlet' => $line->outlet_name ?? '-',
                'description' => $line->description ?? '-',
                'debit' => null,
                'credit' => (float) $line->amount,
                'row_type' => 'credit',
                'source_type' => $line->source_type,
                'items' => $line->items,
                'expandable' => count($line->items) > 0,
            ];
        }

        $totalDebit = collect($rows)->sum(fn ($r) => (float) ($r['debit'] ?? 0));
        $totalCredit = collect($rows)->sum(fn ($r) => (float) ($r['credit'] ?? 0));
        $endingBalance = $monthlyBudget - $totalCredit;
        $outletSummary = $this->buildOutletSummary($credits);

        return [
            'category' => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'division' => $category->division,
                'subcategory' => $category->subcategory,
                'budget_limit' => $monthlyBudget,
            ],
            'period' => [
                'year' => $year,
                'month' => $month,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'label' => strtoupper($this->monthNameId($month)) . ' ' . $year,
            ],
            'rows' => $rows,
            'outlet_summary' => $outletSummary,
            'summary' => [
                'monthly_budget' => round($monthlyBudget, 2),
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'ending_balance' => round($endingBalance, 2),
            ],
        ];
    }

    private function fetchCreditLines(int $categoryId, string $dateFrom, string $dateTo): Collection
    {
        $rnfRows = DB::table('retail_non_food as rnf')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'rnf.outlet_id')
            ->where('rnf.category_budget_id', $categoryId)
            ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
            ->where('rnf.status', 'approved')
            ->select(
                'rnf.id',
                'rnf.outlet_id',
                'rnf.transaction_date',
                'o.nama_outlet as outlet_name',
                DB::raw('COALESCE(rnf.notes, rnf.retail_number, CONCAT("Retail Non Food ", rnf.retail_number)) as description'),
                'rnf.total_amount as amount'
            )
            ->get();

        $rnfItemsById = $this->fetchRetailNonFoodItems(
            $rnfRows->pluck('id')->filter()->all()
        );

        $rnf = $rnfRows->map(function ($row) use ($rnfItemsById) {
            $items = $rnfItemsById[$row->id] ?? [];

            return (object) [
                'row_key' => 'rnf_' . $row->id,
                'source_type' => 'retail_non_food',
                'outlet_id' => $row->outlet_id ? (int) $row->outlet_id : null,
                'transaction_date' => $row->transaction_date,
                'outlet_name' => $row->outlet_name,
                'description' => $row->description,
                'amount' => (float) $row->amount,
                'items' => $items,
                'sort_key' => $row->transaction_date . '_1_' . $row->outlet_name,
            ];
        });

        $nfpRows = DB::table('non_food_payment_outlets as nfpo')
            ->join('non_food_payments as nfp', 'nfp.id', '=', 'nfpo.non_food_payment_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'nfpo.outlet_id')
            ->where('nfpo.category_id', $categoryId)
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->select(
                'nfpo.id as payment_outlet_id',
                'nfp.purchase_order_ops_id',
                'nfpo.outlet_id',
                'nfp.payment_date as transaction_date',
                'o.nama_outlet as outlet_name',
                DB::raw("(
                    SELECT pr.title
                    FROM purchase_order_ops_items poi
                    INNER JOIN purchase_requisitions pr ON pr.id = poi.source_id
                        AND poi.source_type = 'purchase_requisition_ops'
                    WHERE poi.purchase_order_ops_id = nfp.purchase_order_ops_id
                    LIMIT 1
                ) as pr_title"),
                'nfp.notes',
                'nfp.payment_number',
                'nfpo.amount'
            )
            ->get();

        $nfpItemsByKey = $this->fetchNonFoodPaymentItems(
            $categoryId,
            $nfpRows->map(fn ($row) => [
                'purchase_order_ops_id' => $row->purchase_order_ops_id,
                'outlet_id' => $row->outlet_id,
            ])->all()
        );

        $nfp = $nfpRows->map(function ($row) use ($nfpItemsByKey) {
            $description = $row->pr_title ?: $row->notes;
            if (! $description) {
                $description = 'Pembayaran ' . ($row->payment_number ?? 'NFP');
            }

            $itemKey = $row->purchase_order_ops_id . '_' . ($row->outlet_id ?? 'null');
            $items = $nfpItemsByKey[$itemKey] ?? [];

            return (object) [
                'row_key' => 'nfp_' . $row->payment_outlet_id,
                'source_type' => 'non_food_payment',
                'outlet_id' => $row->outlet_id ? (int) $row->outlet_id : null,
                'transaction_date' => $row->transaction_date,
                'outlet_name' => $row->outlet_name,
                'description' => $description,
                'amount' => (float) $row->amount,
                'items' => $items,
                'sort_key' => $row->transaction_date . '_2_' . $row->outlet_name,
            ];
        });

        return $rnf->concat($nfp)
            ->sortBy([
                ['transaction_date', 'asc'],
                ['sort_key', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  array<int>  $retailNonFoodIds
     * @return array<int, array<int, array{item: string, qty: float, unit: string|null, price: float, subtotal: float}>>
     */
    private function fetchRetailNonFoodItems(array $retailNonFoodIds): array
    {
        if ($retailNonFoodIds === []) {
            return [];
        }

        $grouped = [];

        DB::table('retail_non_food_items')
            ->whereIn('retail_non_food_id', $retailNonFoodIds)
            ->orderBy('id')
            ->get(['retail_non_food_id', 'item_name', 'qty', 'unit', 'price', 'subtotal'])
            ->each(function ($row) use (&$grouped) {
                $grouped[(int) $row->retail_non_food_id][] = [
                    'item' => $row->item_name ?? '-',
                    'qty' => (float) ($row->qty ?? 0),
                    'unit' => $row->unit,
                    'price' => (float) ($row->price ?? 0),
                    'subtotal' => (float) ($row->subtotal ?? 0),
                ];
            });

        return $grouped;
    }

    /**
     * @param  array<int, array{purchase_order_ops_id: mixed, outlet_id: mixed}>  $paymentRows
     * @return array<string, array<int, array{item: string, qty: float, unit: string|null, price: float, subtotal: float}>>
     */
    private function fetchNonFoodPaymentItems(int $categoryId, array $paymentRows): array
    {
        $poIds = collect($paymentRows)
            ->pluck('purchase_order_ops_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($poIds === []) {
            return [];
        }

        $items = DB::table('purchase_order_ops_items as poi')
            ->leftJoin('purchase_requisition_items as pri', 'poi.pr_ops_item_id', '=', 'pri.id')
            ->whereIn('poi.purchase_order_ops_id', $poIds)
            ->where('pri.category_id', $categoryId)
            ->orderBy('poi.id')
            ->get([
                'poi.purchase_order_ops_id',
                DB::raw('COALESCE(poi.outlet_id, pri.outlet_id) as outlet_id'),
                'poi.item_name',
                'poi.quantity',
                'poi.unit',
                'poi.price',
                'poi.total',
            ]);

        $grouped = [];

        foreach ($items as $row) {
            $key = $row->purchase_order_ops_id . '_' . ($row->outlet_id ?? 'null');
            $grouped[$key][] = [
                'item' => $row->item_name ?? '-',
                'qty' => (float) ($row->quantity ?? 0),
                'unit' => $row->unit,
                'price' => (float) ($row->price ?? 0),
                'subtotal' => (float) ($row->total ?? 0),
            ];
        }

        return $grouped;
    }

    /**
     * @return array{
     *   rows: array<int, array{outlet_id: int|null, outlet: string, total: float}>,
     *   total: float
     * }
     */
    private function buildOutletSummary(Collection $credits): array
    {
        $totalsByOutletId = $credits
            ->groupBy(fn ($line) => $line->outlet_id ?? 0)
            ->map(fn ($group) => round($group->sum('amount'), 2));

        $rows = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet'])
            ->map(fn ($outlet) => [
                'outlet_id' => (int) $outlet->id_outlet,
                'outlet' => (string) $outlet->nama_outlet,
                'total' => (float) ($totalsByOutletId[(int) $outlet->id_outlet] ?? 0),
            ])
            ->values()
            ->all();

        $unknownTotal = (float) ($totalsByOutletId[0] ?? 0);
        if ($unknownTotal > 0) {
            $rows[] = [
                'outlet_id' => null,
                'outlet' => '-',
                'total' => $unknownTotal,
            ];
        }

        return [
            'rows' => $rows,
            'total' => round(collect($rows)->sum('total'), 2),
        ];
    }

    private function debitRow(int $no, string $date, string $outlet, string $description, float $amount): array
    {
        return [
            'no' => $no,
            'row_key' => 'debit_' . $no,
            'date' => $date,
            'date_label' => $this->formatDateLabel($date),
            'outlet' => $outlet,
            'description' => $description,
            'debit' => $amount,
            'credit' => null,
            'row_type' => 'debit',
            'source_type' => null,
            'items' => [],
            'expandable' => false,
        ];
    }

    private function saldoAwalLabel(object $category, Carbon $periodStart): string
    {
        $monthName = strtoupper($this->monthNameId($periodStart->month));

        return trim(sprintf(
            'DANA %s %s %d',
            strtoupper($category->name),
            $monthName,
            $periodStart->year
        ));
    }

    private function formatDateLabel(string $date): string
    {
        return Carbon::parse($date)->format('d-M-y');
    }

    private function monthNameId(int $month): string
    {
        $names = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $names[$month] ?? (string) $month;
    }
}
