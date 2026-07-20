<?php

namespace App\Services;

use App\Models\PurchaseRequisitionCategory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                'reference' => $line->reference ?? '',
                'description' => $line->description ?? '-',
                'debit' => null,
                'credit' => (float) $line->amount,
                'row_type' => 'credit',
                'source_type' => $line->source_type,
                'items' => $line->items ?? [],
                'expandable' => (bool) ($line->expandable ?? true),
            ];
        }

        $totalDebit = collect($rows)->sum(fn ($r) => (float) ($r['debit'] ?? 0));
        $totalCredit = collect($rows)->sum(fn ($r) => (float) ($r['credit'] ?? 0));
        $endingBalance = $monthlyBudget - $totalCredit;

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
            'summary' => [
                'monthly_budget' => round($monthlyBudget, 2),
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'ending_balance' => round($endingBalance, 2),
            ],
        ];
    }

    /**
     * @return array{
     *   outlet: array{id: int, name: string},
     *   period: array{date_from: string, date_to: string, label: string},
     *   rows: array<int, array{category_id: int, category: string, division: string, total: float}>,
     *   total: float
     * }
     */
    public function buildOutletCategorySummary(int $outletId, string $dateFrom, string $dateTo): array
    {
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->where('status', 'A')
            ->first(['id_outlet', 'nama_outlet']);

        if (! $outlet) {
            abort(404, 'Outlet tidak ditemukan.');
        }

        $totalsByCategoryId = [];

        DB::table('retail_non_food')
            ->where('outlet_id', $outletId)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereNotNull('category_budget_id')
            ->select('category_budget_id', DB::raw('SUM(total_amount) as total'))
            ->groupBy('category_budget_id')
            ->get()
            ->each(function ($row) use (&$totalsByCategoryId) {
                $key = (int) $row->category_budget_id;
                $totalsByCategoryId[$key] = ($totalsByCategoryId[$key] ?? 0) + (float) $row->total;
            });

        DB::table('non_food_payment_outlets as nfpo')
            ->join('non_food_payments as nfp', 'nfp.id', '=', 'nfpo.non_food_payment_id')
            ->where('nfpo.outlet_id', $outletId)
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->whereNotNull('nfpo.category_id')
            ->select('nfpo.category_id', DB::raw('SUM(nfpo.amount) as total'))
            ->groupBy('nfpo.category_id')
            ->get()
            ->each(function ($row) use (&$totalsByCategoryId) {
                $key = (int) $row->category_id;
                $totalsByCategoryId[$key] = ($totalsByCategoryId[$key] ?? 0) + (float) $row->total;
            });

        $marcommCategory = $this->findMarcommMarketingCategory();
        if ($marcommCategory) {
            $marketingRow = $this->fetchRekapFjMarketingByOutlet($dateFrom, $dateTo)
                ->firstWhere('outlet_id', $outletId);
            $marketingTotal = (float) ($marketingRow->amount ?? 0);
            if ($marketingTotal > 0) {
                $key = (int) $marcommCategory->id;
                $totalsByCategoryId[$key] = ($totalsByCategoryId[$key] ?? 0) + $marketingTotal;
            }
        }

        foreach ($totalsByCategoryId as $categoryId => $total) {
            $totalsByCategoryId[$categoryId] = round($total, 2);
        }

        $categoryIds = array_keys(array_filter(
            $totalsByCategoryId,
            fn ($total) => $total > 0
        ));

        $rows = PurchaseRequisitionCategory::query()
            ->active()
            ->whereIn('id', $categoryIds)
            ->orderBy('division')
            ->orderBy('name')
            ->get(['id', 'name', 'division'])
            ->map(fn ($category) => [
                'category_id' => (int) $category->id,
                'category' => (string) $category->name,
                'division' => (string) $category->division,
                'total' => (float) ($totalsByCategoryId[(int) $category->id] ?? 0),
            ])
            ->values()
            ->all();

        $fromLabel = Carbon::parse($dateFrom)->format('d M Y');
        $toLabel = Carbon::parse($dateTo)->format('d M Y');

        return [
            'outlet' => [
                'id' => (int) $outlet->id_outlet,
                'name' => (string) $outlet->nama_outlet,
            ],
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'label' => $fromLabel . ' — ' . $toLabel,
            ],
            'rows' => $rows,
            'total' => round(collect($rows)->sum('total'), 2),
        ];
    }

    /**
     * @return array{
     *   period: array{year:int,month:int,date_from:string,date_to:string,label:string},
     *   rows: array<int, array{outlet_id: int|null, outlet: string, total: float}>,
     *   total: float
     * }
     */
    public function buildOutletSummary(int $year, int $month, ?int $categoryId = null): array
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $dateFrom = $periodStart->format('Y-m-d');
        $dateTo = $periodStart->copy()->endOfMonth()->format('Y-m-d');

        $category = null;
        if ($categoryId !== null && $categoryId > 0) {
            $category = PurchaseRequisitionCategory::query()->find($categoryId);
        }

        $totalsByOutletId = [];

        $rnfQuery = DB::table('retail_non_food')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->where('status', 'approved')
            ->whereNull('deleted_at');

        if ($categoryId !== null && $categoryId > 0) {
            $rnfQuery->where('category_budget_id', $categoryId);
        }

        $rnfQuery
            ->select('outlet_id', DB::raw('SUM(total_amount) as total'))
            ->groupBy('outlet_id')
            ->get()
            ->each(function ($row) use (&$totalsByOutletId) {
                $key = $row->outlet_id ? (int) $row->outlet_id : 0;
                $totalsByOutletId[$key] = ($totalsByOutletId[$key] ?? 0) + (float) $row->total;
            });

        $nfpQuery = DB::table('non_food_payment_outlets as nfpo')
            ->join('non_food_payments as nfp', 'nfp.id', '=', 'nfpo.non_food_payment_id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved']);

        if ($categoryId !== null && $categoryId > 0) {
            $nfpQuery->where('nfpo.category_id', $categoryId);
        }

        $nfpQuery
            ->select('nfpo.outlet_id', DB::raw('SUM(nfpo.amount) as total'))
            ->groupBy('nfpo.outlet_id')
            ->get()
            ->each(function ($row) use (&$totalsByOutletId) {
                $key = $row->outlet_id ? (int) $row->outlet_id : 0;
                $totalsByOutletId[$key] = ($totalsByOutletId[$key] ?? 0) + (float) $row->total;
            });

        if ($this->isMarcommMarketingCategory($category)) {
            $this->fetchRekapFjMarketingByOutlet($dateFrom, $dateTo)
                ->each(function ($row) use (&$totalsByOutletId) {
                    $key = $row->outlet_id ? (int) $row->outlet_id : 0;
                    $totalsByOutletId[$key] = ($totalsByOutletId[$key] ?? 0) + (float) $row->amount;
                });
        }

        foreach ($totalsByOutletId as $outletId => $total) {
            $totalsByOutletId[$outletId] = round($total, 2);
        }

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

        $result = [
            'period' => [
                'year' => $year,
                'month' => $month,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'label' => strtoupper($this->monthNameId($month)) . ' ' . $year,
            ],
            'rows' => $rows,
            'total' => round(collect($rows)->sum('total'), 2),
        ];

        if ($category) {
            $result['category'] = [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'division' => (string) $category->division,
            ];
        }

        return $result;
    }

    private function fetchCreditLines(int $categoryId, string $dateFrom, string $dateTo): Collection
    {
        $rnfRows = DB::table('retail_non_food as rnf')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'rnf.outlet_id')
            ->where('rnf.category_budget_id', $categoryId)
            ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
            ->where('rnf.status', 'approved')
            ->whereNull('rnf.deleted_at')
            ->select(
                'rnf.id',
                'rnf.retail_number',
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
                'reference' => $this->buildReferenceLabel(rnf: $row->retail_number),
                'description' => $row->description,
                'amount' => (float) $row->amount,
                'items' => $items,
                'sort_key' => $row->transaction_date . '_1_' . $row->outlet_name,
            ];
        });

        $nfpRows = DB::table('non_food_payment_outlets as nfpo')
            ->join('non_food_payments as nfp', 'nfp.id', '=', 'nfpo.non_food_payment_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'nfpo.outlet_id')
            ->leftJoin('purchase_order_ops as poo', 'poo.id', '=', 'nfp.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr_direct', 'pr_direct.id', '=', 'nfp.purchase_requisition_id')
            ->leftJoin('purchase_requisitions as pr_po', function ($join) {
                $join->on('pr_po.id', '=', 'poo.source_id')
                    ->where('poo.source_type', '=', 'purchase_requisition_ops');
            })
            ->where('nfpo.category_id', $categoryId)
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->select(
                'nfpo.id as payment_outlet_id',
                'nfp.id as non_food_payment_id',
                'nfp.purchase_order_ops_id',
                'nfp.purchase_requisition_id',
                'nfpo.outlet_id',
                'nfpo.category_id as payment_category_id',
                'nfp.payment_date as transaction_date',
                'o.nama_outlet as outlet_name',
                DB::raw('COALESCE(pr_direct.pr_number, pr_po.pr_number) as pr_number'),
                'poo.number as po_number',
                'nfp.payment_number',
                DB::raw("COALESCE(
                    pr_direct.title,
                    pr_po.title,
                    (
                        SELECT pr.title
                        FROM purchase_order_ops_items poi
                        INNER JOIN purchase_requisitions pr ON pr.id = poi.source_id
                            AND poi.source_type = 'purchase_requisition_ops'
                        WHERE poi.purchase_order_ops_id = nfp.purchase_order_ops_id
                        LIMIT 1
                    )
                ) as pr_title"),
                'nfp.notes',
                'nfpo.amount'
            )
            ->get();

        $paymentRowsPayload = $nfpRows->map(fn ($row) => [
            'payment_outlet_id' => $row->payment_outlet_id,
            'purchase_order_ops_id' => $row->purchase_order_ops_id,
            'purchase_requisition_id' => $row->purchase_requisition_id,
            'outlet_id' => $row->outlet_id,
            'category_id' => $row->payment_category_id ?? $categoryId,
        ])->all();

        $nfpItemsByKey = $this->fetchNonFoodPaymentItems($categoryId, $paymentRowsPayload);
        $prItemsByKey = $this->fetchPrPaymentItemsBatch($categoryId, $paymentRowsPayload);

        $nfp = $nfpRows->map(function ($row) use ($nfpItemsByKey, $prItemsByKey) {
            $description = $row->pr_title ?: $row->notes;
            if (! $description) {
                $description = 'Pembayaran ' . ($row->payment_number ?? 'NFP');
            }

            $items = [];
            if ($row->purchase_order_ops_id) {
                $itemKey = $this->paymentItemKey(
                    $row->purchase_order_ops_id,
                    $row->outlet_id
                );
                $items = $nfpItemsByKey[$itemKey] ?? [];
            } elseif ($row->purchase_requisition_id) {
                $itemKey = $this->prPaymentItemKey(
                    $row->purchase_requisition_id,
                    $row->outlet_id
                );
                $items = $prItemsByKey[$itemKey] ?? [];
            }

            return (object) [
                'row_key' => 'nfp_' . $row->payment_outlet_id,
                'source_type' => 'non_food_payment',
                'outlet_id' => $row->outlet_id ? (int) $row->outlet_id : null,
                'transaction_date' => $row->transaction_date,
                'outlet_name' => $row->outlet_name,
                'reference' => $this->buildReferenceLabel(
                    pr: $row->pr_number ?? null,
                    po: $row->po_number ?? null,
                    nfp: $row->payment_number ?? null
                ),
                'description' => $description,
                'amount' => (float) $row->amount,
                'items' => $items,
                'sort_key' => $row->transaction_date . '_2_' . $row->outlet_name,
            ];
        });

        $lines = $rnf->concat($nfp);

        $category = PurchaseRequisitionCategory::query()->find($categoryId);
        if ($this->isMarcommMarketingCategory($category)) {
            $lines = $lines->concat(
                $this->fetchRekapFjMarketingCreditLines($dateFrom, $dateTo)
            );
        }

        return $lines
            ->sortBy([
                ['transaction_date', 'asc'],
                ['sort_key', 'asc'],
            ])
            ->values();
    }

    private function isMarcommMarketingCategory(?object $category): bool
    {
        if (! $category) {
            return false;
        }

        $name = strtolower(trim((string) ($category->name ?? '')));
        $division = strtolower(trim((string) ($category->division ?? '')));

        // DB name: "Marcomm"; UI label: "Marcomm (MARKETING)"
        if ($name === 'marcomm' || $name === 'marcomm (marketing)') {
            return true;
        }

        return str_contains($name, 'marcomm')
            && (str_contains($name, 'marketing') || $division === 'marketing');
    }

    private function findMarcommMarketingCategory(): ?PurchaseRequisitionCategory
    {
        return PurchaseRequisitionCategory::query()
            ->active()
            ->get(['id', 'name', 'division', 'subcategory', 'budget_limit'])
            ->first(fn ($category) => $this->isMarcommMarketingCategory($category));
    }

    /**
     * Nilai Marketing Rekap FJ (GR MAIN STORE + sub category Marketing), agregat per outlet.
     *
     * @return Collection<int, object{outlet_id: int, outlet_name: string, amount: float}>
     */
    private function fetchRekapFjMarketingByOutlet(string $dateFrom, string $dateTo): Collection
    {
        $foodPrice = 'COALESCE(fo.price, 0)';

        $foodRows = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->whereNull('gr.deleted_at')
            ->whereNotNull('w.name')
            ->whereRaw('UPPER(TRIM(w.name)) = ?', ['MAIN STORE'])
            ->whereRaw('UPPER(TRIM(sc.name)) = ?', ['MARKETING'])
            ->whereDate('gr.receive_date', '>=', $dateFrom)
            ->whereDate('gr.receive_date', '<=', $dateTo)
            ->select(
                'gr.outlet_id',
                'o.nama_outlet as outlet_name',
                DB::raw("SUM(i.received_qty * {$foodPrice}) as amount")
            )
            ->groupBy('gr.outlet_id', 'o.nama_outlet')
            ->get();

        $serialRows = collect();
        if ($this->hasSerialGrTables()) {
            $costSmall = 'COALESCE(si.cost_small, 0)';
            $smallConv = 'COALESCE(it.small_conversion_qty, 1)';
            $mediumConv = 'COALESCE(it.medium_conversion_qty, 1)';
            $serialPrice = "(CASE
                WHEN si.unit_id = it.large_unit_id THEN {$costSmall} * {$smallConv} * {$mediumConv}
                WHEN si.unit_id = it.medium_unit_id THEN {$costSmall} * {$smallConv}
                ELSE {$costSmall}
            END)";

            $serialRows = DB::table('outlet_serial_receive_headers as h')
                ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
                ->whereNull('h.deleted_at')
                ->whereNotNull('w.name')
                ->whereRaw('UPPER(TRIM(w.name)) = ?', ['MAIN STORE'])
                ->whereRaw('UPPER(TRIM(sc.name)) = ?', ['MARKETING'])
                ->whereDate('h.receive_date', '>=', $dateFrom)
                ->whereDate('h.receive_date', '<=', $dateTo)
                ->select(
                    'h.outlet_id',
                    'o.nama_outlet as outlet_name',
                    DB::raw("SUM(si.qty * {$serialPrice}) as amount")
                )
                ->groupBy('h.outlet_id', 'o.nama_outlet')
                ->get();
        }

        $byOutlet = [];
        foreach ($foodRows->concat($serialRows) as $row) {
            $outletId = (int) $row->outlet_id;
            if (! isset($byOutlet[$outletId])) {
                $byOutlet[$outletId] = (object) [
                    'outlet_id' => $outletId,
                    'outlet_name' => $row->outlet_name,
                    'amount' => 0.0,
                ];
            }
            $byOutlet[$outletId]->amount += (float) $row->amount;
        }

        return collect(array_values($byOutlet))
            ->filter(fn ($row) => (float) $row->amount > 0)
            ->map(function ($row) {
                $row->amount = round((float) $row->amount, 2);

                return $row;
            })
            ->sortBy('outlet_name')
            ->values();
    }

    /**
     * Credit lines agregat per outlet untuk Marcomm (Marketing).
     */
    private function fetchRekapFjMarketingCreditLines(string $dateFrom, string $dateTo): Collection
    {
        return $this->fetchRekapFjMarketingByOutlet($dateFrom, $dateTo)
            ->map(function ($row) use ($dateTo) {
                return (object) [
                    'row_key' => 'fj_mkt_' . $row->outlet_id,
                    'source_type' => 'rekap_fj_marketing',
                    'outlet_id' => (int) $row->outlet_id,
                    'transaction_date' => $dateTo,
                    'outlet_name' => $row->outlet_name,
                    'reference' => 'Rekap FJ',
                    'description' => 'GR Marketing (Rekap FJ)',
                    'amount' => (float) $row->amount,
                    'items' => [],
                    'expandable' => true,
                    'sort_key' => $dateTo . '_3_' . $row->outlet_name,
                ];
            });
    }

    /**
     * Detail item GR Marketing per outlet (agregat per item+unit dalam periode).
     *
     * @return array<int, array{item: string, qty: float, unit: string|null, price: float, subtotal: float}>
     */
    private function fetchRekapFjMarketingItemsForOutlet(int $outletId, string $dateFrom, string $dateTo): array
    {
        if ($outletId <= 0) {
            return [];
        }

        $foodPrice = 'COALESCE(fo.price, 0)';

        $foodRows = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->where('gr.outlet_id', $outletId)
            ->whereNull('gr.deleted_at')
            ->whereNotNull('w.name')
            ->whereRaw('UPPER(TRIM(w.name)) = ?', ['MAIN STORE'])
            ->whereRaw('UPPER(TRIM(sc.name)) = ?', ['MARKETING'])
            ->whereDate('gr.receive_date', '>=', $dateFrom)
            ->whereDate('gr.receive_date', '<=', $dateTo)
            ->select(
                'it.id as item_id',
                'it.name as item_name',
                'u.name as unit',
                DB::raw("SUM(i.received_qty) as qty"),
                DB::raw("SUM(i.received_qty * {$foodPrice}) as subtotal")
            )
            ->groupBy('it.id', 'it.name', 'u.name')
            ->get();

        $serialRows = collect();
        if ($this->hasSerialGrTables()) {
            $costSmall = 'COALESCE(si.cost_small, 0)';
            $smallConv = 'COALESCE(it.small_conversion_qty, 1)';
            $mediumConv = 'COALESCE(it.medium_conversion_qty, 1)';
            $serialPrice = "(CASE
                WHEN si.unit_id = it.large_unit_id THEN {$costSmall} * {$smallConv} * {$mediumConv}
                WHEN si.unit_id = it.medium_unit_id THEN {$costSmall} * {$smallConv}
                ELSE {$costSmall}
            END)";

            $serialRows = DB::table('outlet_serial_receive_headers as h')
                ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->where('h.outlet_id', $outletId)
                ->whereNull('h.deleted_at')
                ->whereNotNull('w.name')
                ->whereRaw('UPPER(TRIM(w.name)) = ?', ['MAIN STORE'])
                ->whereRaw('UPPER(TRIM(sc.name)) = ?', ['MARKETING'])
                ->whereDate('h.receive_date', '>=', $dateFrom)
                ->whereDate('h.receive_date', '<=', $dateTo)
                ->select(
                    'it.id as item_id',
                    'it.name as item_name',
                    'u.name as unit',
                    DB::raw('SUM(si.qty) as qty'),
                    DB::raw("SUM(si.qty * {$serialPrice}) as subtotal")
                )
                ->groupBy('it.id', 'it.name', 'u.name')
                ->get();
        }

        $merged = [];
        foreach ($foodRows->concat($serialRows) as $row) {
            $key = ((int) $row->item_id) . '|' . strtolower(trim((string) ($row->unit ?? '')));
            if (! isset($merged[$key])) {
                $merged[$key] = [
                    'item' => $row->item_name ?? '-',
                    'qty' => 0.0,
                    'unit' => $row->unit,
                    'subtotal' => 0.0,
                ];
            }
            $merged[$key]['qty'] += (float) $row->qty;
            $merged[$key]['subtotal'] += (float) $row->subtotal;
        }

        return collect(array_values($merged))
            ->filter(fn ($row) => (float) $row['subtotal'] > 0 || (float) $row['qty'] > 0)
            ->map(function ($row) {
                $qty = (float) $row['qty'];
                $subtotal = round((float) $row['subtotal'], 2);

                return [
                    'item' => $row['item'],
                    'qty' => round($qty, 4),
                    'unit' => $row['unit'],
                    'price' => $qty > 0 ? round($subtotal / $qty, 2) : 0.0,
                    'subtotal' => $subtotal,
                ];
            })
            ->sortBy('item')
            ->values()
            ->all();
    }

    private function hasSerialGrTables(): bool
    {
        return Schema::hasTable('outlet_serial_receive_headers')
            && Schema::hasTable('outlet_serial_receive_items');
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
     * @param  array<int, array{payment_outlet_id: mixed, purchase_order_ops_id: mixed, purchase_requisition_id?: mixed, outlet_id: mixed, category_id: mixed}>  $paymentRows
     * @return array<string, array<int, array{item: string, qty: float, unit: string|null, price: float, subtotal: float}>>
     */
    private function fetchPrPaymentItemsBatch(int $categoryId, array $paymentRows): array
    {
        $prIds = collect($paymentRows)
            ->filter(fn ($row) => empty($row['purchase_order_ops_id']) && ! empty($row['purchase_requisition_id']))
            ->pluck('purchase_requisition_id')
            ->unique()
            ->values()
            ->all();

        if ($prIds === []) {
            return [];
        }

        $grouped = [];

        DB::table('purchase_requisition_items')
            ->whereIn('purchase_requisition_id', $prIds)
            ->where('category_id', $categoryId)
            ->orderBy('id')
            ->get()
            ->each(function ($row) use (&$grouped) {
                $key = $this->prPaymentItemKey($row->purchase_requisition_id, $row->outlet_id);
                $grouped[$key][] = $this->mapPrItemRow($row);
            });

        return $grouped;
    }

    /**
     * @return array<int, array{item: string, qty: float, unit: string|null, price: float, subtotal: float}>
     */
    private function fetchPrPaymentItemsForOutlet(int $purchaseRequisitionId, $outletId, int $categoryId): array
    {
        $query = DB::table('purchase_requisition_items')
            ->where('purchase_requisition_id', $purchaseRequisitionId)
            ->where('category_id', $categoryId)
            ->orderBy('id');

        $normalizedOutlet = $this->normalizeOutletId($outletId);
        if ($normalizedOutlet !== null) {
            $query->where('outlet_id', $normalizedOutlet);
        } else {
            $query->where(function ($builder) {
                $builder->whereNull('outlet_id')
                    ->orWhere('outlet_id', 0);
            });
        }

        return $query->get()
            ->map(fn ($row) => $this->mapPrItemRow($row))
            ->values()
            ->all();
    }

    private function prPaymentItemKey($purchaseRequisitionId, $outletId): string
    {
        $prId = (int) ($purchaseRequisitionId ?? 0);
        $normalizedOutlet = $this->normalizeOutletId($outletId);

        return 'pr_' . $prId . '_' . ($normalizedOutlet ?? 'null');
    }

    /**
     * @return array{item: string, qty: float, unit: string|null, price: float, subtotal: float}
     */
    private function mapPrItemRow(object $row): array
    {
        $qty = (float) ($row->qty ?? 0);
        if ($qty <= 0) {
            $qty = 1;
        }

        $subtotal = (float) ($row->subtotal ?? 0);
        $price = (float) ($row->unit_price ?? 0);
        if ($price <= 0 && $subtotal > 0) {
            $price = $subtotal / $qty;
        }

        return [
            'item' => $row->item_name ?? '-',
            'qty' => $qty,
            'unit' => $row->unit ?? '-',
            'price' => $price,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * @param  array<int, array{payment_outlet_id: mixed, purchase_order_ops_id: mixed, purchase_requisition_id?: mixed, outlet_id: mixed, category_id: mixed}>  $paymentRows
     * @return array<string, array<int, array{item: string, qty: float, unit: string|null, price: float, subtotal: float}>>
     */
    private function fetchNonFoodPaymentItems(int $categoryId, array $paymentRows): array
    {
        $poIds = collect($paymentRows)
            ->filter(fn ($row) => ! empty($row['purchase_order_ops_id']))
            ->pluck('purchase_order_ops_id')
            ->unique()
            ->values()
            ->all();

        if ($poIds === []) {
            return [];
        }

        $items = DB::table('purchase_order_ops_items as poi')
            ->leftJoin('purchase_requisition_items as pri', 'poi.pr_ops_item_id', '=', 'pri.id')
            ->whereIn('poi.purchase_order_ops_id', $poIds)
            ->orderBy('poi.id')
            ->get([
                'poi.purchase_order_ops_id',
                DB::raw('COALESCE(poi.outlet_id, pri.outlet_id) as outlet_id'),
                'pri.category_id',
                'poi.item_name',
                'poi.quantity',
                'poi.unit',
                'poi.price',
                'poi.total',
            ]);

        $grouped = [];

        foreach ($items as $row) {
            if (! $this->poItemMatchesCategory($row->category_id, $categoryId)) {
                continue;
            }

            $key = $this->paymentItemKey($row->purchase_order_ops_id, $row->outlet_id);
            $grouped[$key][] = $this->mapPoItemRow($row);
        }

        foreach ($paymentRows as $paymentRow) {
            $key = $this->paymentItemKey(
                $paymentRow['purchase_order_ops_id'] ?? null,
                $paymentRow['outlet_id'] ?? null
            );

            if (($grouped[$key] ?? []) !== []) {
                continue;
            }

            $fallbackItems = $items
                ->filter(function ($row) use ($paymentRow, $categoryId) {
                    if ((int) $row->purchase_order_ops_id !== (int) ($paymentRow['purchase_order_ops_id'] ?? 0)) {
                        return false;
                    }

                    if (! $this->poItemMatchesCategory($row->category_id, $categoryId)) {
                        return false;
                    }

                    return $this->normalizeOutletId($row->outlet_id) === $this->normalizeOutletId($paymentRow['outlet_id'] ?? null);
                })
                ->map(fn ($row) => $this->mapPoItemRow($row))
                ->values()
                ->all();

            if ($fallbackItems !== []) {
                $grouped[$key] = $fallbackItems;
            }
        }

        $categoryItemsByPo = [];
        foreach ($items as $row) {
            if (! $this->poItemMatchesCategory($row->category_id, $categoryId)) {
                continue;
            }

            $poId = (int) $row->purchase_order_ops_id;
            $categoryItemsByPo[$poId][] = $this->mapPoItemRow($row);
        }

        foreach ($paymentRows as $paymentRow) {
            $key = $this->paymentItemKey(
                $paymentRow['purchase_order_ops_id'] ?? null,
                $paymentRow['outlet_id'] ?? null
            );

            if (($grouped[$key] ?? []) !== []) {
                continue;
            }

            $poId = (int) ($paymentRow['purchase_order_ops_id'] ?? 0);
            if ($poId > 0 && ($categoryItemsByPo[$poId] ?? []) !== []) {
                $grouped[$key] = $categoryItemsByPo[$poId];
            }
        }

        return $grouped;
    }

    private function paymentItemKey($purchaseOrderOpsId, $outletId): string
    {
        $poId = (int) ($purchaseOrderOpsId ?? 0);
        $normalizedOutlet = $this->normalizeOutletId($outletId);

        return $poId . '_' . ($normalizedOutlet ?? 'null');
    }

    private function normalizeOutletId($outletId): ?int
    {
        if ($outletId === null || $outletId === '' || $outletId === 0 || $outletId === '0') {
            return null;
        }

        return (int) $outletId;
    }

    private function poItemMatchesCategory($itemCategoryId, int $reportCategoryId): bool
    {
        if ($itemCategoryId === null || $itemCategoryId === '') {
            return true;
        }

        return (int) $itemCategoryId === $reportCategoryId;
    }

    /**
     * @return array{item: string, qty: float, unit: string|null, price: float, subtotal: float}
     */
    private function mapPoItemRow(object $row): array
    {
        return [
            'item' => $row->item_name ?? '-',
            'qty' => (float) ($row->quantity ?? 0),
            'unit' => $row->unit,
            'price' => (float) ($row->price ?? 0),
            'subtotal' => (float) ($row->total ?? 0),
        ];
    }

    /**
     * @return array<int, array{item: string, qty: float, unit: string|null, price: float, subtotal: float}>
     */
    public function fetchRowItems(string $rowKey, int $categoryId, ?int $year = null, ?int $month = null): array
    {
        if (str_starts_with($rowKey, 'fj_mkt_')) {
            $outletId = (int) substr($rowKey, 7);
            if ($outletId <= 0) {
                return [];
            }

            $year = $year ?: (int) date('Y');
            $month = $month ?: (int) date('n');
            $month = max(1, min(12, $month));
            $year = max(2000, min(2100, $year));

            $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
            $dateFrom = $periodStart->format('Y-m-d');
            $dateTo = $periodStart->copy()->endOfMonth()->format('Y-m-d');

            return $this->fetchRekapFjMarketingItemsForOutlet($outletId, $dateFrom, $dateTo);
        }

        if (str_starts_with($rowKey, 'rnf_')) {
            $retailNonFoodId = (int) substr($rowKey, 4);
            if ($retailNonFoodId <= 0) {
                return [];
            }

            return $this->fetchRetailNonFoodItems([$retailNonFoodId])[$retailNonFoodId] ?? [];
        }

        if (str_starts_with($rowKey, 'nfp_')) {
            $paymentOutletId = (int) substr($rowKey, 4);
            if ($paymentOutletId <= 0) {
                return [];
            }

            $paymentOutlet = DB::table('non_food_payment_outlets as nfpo')
                ->join('non_food_payments as nfp', 'nfp.id', '=', 'nfpo.non_food_payment_id')
                ->where('nfpo.id', $paymentOutletId)
                ->select(
                    'nfpo.id as payment_outlet_id',
                    'nfp.purchase_order_ops_id',
                    'nfp.purchase_requisition_id',
                    'nfpo.outlet_id',
                    'nfpo.category_id'
                )
                ->first();

            if (! $paymentOutlet) {
                return [];
            }

            $rowCategoryId = (int) ($paymentOutlet->category_id ?? $categoryId);

            if ($paymentOutlet->purchase_order_ops_id) {
                $itemsByKey = $this->fetchNonFoodPaymentItems($categoryId, [[
                    'payment_outlet_id' => $paymentOutlet->payment_outlet_id,
                    'purchase_order_ops_id' => $paymentOutlet->purchase_order_ops_id,
                    'outlet_id' => $paymentOutlet->outlet_id,
                    'category_id' => $rowCategoryId,
                ]]);

                $key = $this->paymentItemKey(
                    $paymentOutlet->purchase_order_ops_id,
                    $paymentOutlet->outlet_id
                );

                return $itemsByKey[$key] ?? [];
            }

            if ($paymentOutlet->purchase_requisition_id) {
                return $this->fetchPrPaymentItemsForOutlet(
                    (int) $paymentOutlet->purchase_requisition_id,
                    $paymentOutlet->outlet_id,
                    $rowCategoryId
                );
            }

            return [];
        }

        return [];
    }

    private function debitRow(int $no, string $date, string $outlet, string $description, float $amount): array
    {
        return [
            'no' => $no,
            'row_key' => 'debit_' . $no,
            'date' => $date,
            'date_label' => $this->formatDateLabel($date),
            'outlet' => $outlet,
            'reference' => '',
            'description' => $description,
            'debit' => $amount,
            'credit' => null,
            'row_type' => 'debit',
            'source_type' => null,
            'items' => [],
            'expandable' => false,
        ];
    }

    private function buildReferenceLabel(?string $pr = null, ?string $po = null, ?string $nfp = null, ?string $rnf = null): string
    {
        $parts = [];

        if ($pr && trim($pr) !== '') {
            $parts[] = 'PR: ' . trim($pr);
        }
        if ($po && trim($po) !== '') {
            $parts[] = 'PO: ' . trim($po);
        }
        if ($nfp && trim($nfp) !== '') {
            $parts[] = 'NFP: ' . trim($nfp);
        }
        if ($rnf && trim($rnf) !== '') {
            $parts[] = 'RNF: ' . trim($rnf);
        }

        return $parts !== [] ? implode(' | ', $parts) : '-';
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
