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
        $openingCarry = $this->recursiveCarryBeforeMonth($categoryId, $year, $month);

        $rows = [];
        $no = 1;

        $rows[] = $this->debitRow(
            $no++,
            $periodStart->format('Y-m-d'),
            'SALDO AWAL',
            $this->saldoAwalLabel($category, $periodStart),
            $monthlyBudget
        );

        if (abs($openingCarry) > 0.0001) {
            $prevMonth = $periodStart->copy()->subMonth();
            $rows[] = $this->debitRow(
                $no++,
                $periodStart->format('Y-m-d'),
                'SALDO AWAL',
                'SISA SALDO ' . strtoupper($this->monthNameId($prevMonth->month)) . ' ' . $prevMonth->year,
                $openingCarry
            );
        }

        $credits = $this->fetchCreditLines($categoryId, $dateFrom, $dateTo);
        foreach ($credits as $line) {
            $rows[] = [
                'no' => $no++,
                'date' => $line->transaction_date,
                'date_label' => $this->formatDateLabel($line->transaction_date),
                'outlet' => $line->outlet_name ?? '-',
                'description' => $line->description ?? '-',
                'debit' => null,
                'credit' => (float) $line->amount,
                'row_type' => 'credit',
            ];
        }

        $totalDebit = collect($rows)->sum(fn ($r) => (float) ($r['debit'] ?? 0));
        $totalCredit = collect($rows)->sum(fn ($r) => (float) ($r['credit'] ?? 0));
        $endingBalance = $openingCarry + $monthlyBudget - $totalCredit;

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
                'prev_label' => strtoupper($this->monthNameId($periodStart->copy()->subMonth()->month)) . ' ' . $periodStart->copy()->subMonth()->year,
            ],
            'rows' => $rows,
            'summary' => [
                'opening_carry' => round($openingCarry, 2),
                'monthly_budget' => round($monthlyBudget, 2),
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'ending_balance' => round($endingBalance, 2),
            ],
        ];
    }

    /**
     * Saldo akhir bulan terakhir sebelum periode laporan (rekursif dari awal data).
     */
    public function recursiveCarryBeforeMonth(int $categoryId, int $year, int $month): float
    {
        $category = PurchaseRequisitionCategory::query()->find($categoryId);
        if (! $category) {
            return 0.0;
        }

        $monthlyBudget = (float) ($category->budget_limit ?? 0);
        $firstActivity = $this->firstActivityMonth($categoryId);
        if (! $firstActivity) {
            return 0.0;
        }

        $cursor = Carbon::create($firstActivity['year'], $firstActivity['month'], 1)->startOfMonth();
        $target = Carbon::create($year, $month, 1)->startOfMonth();

        $balance = 0.0;
        while ($cursor->lt($target)) {
            $from = $cursor->copy()->startOfMonth()->format('Y-m-d');
            $to = $cursor->copy()->endOfMonth()->format('Y-m-d');
            $credits = $this->sumCreditsForPeriod($categoryId, $from, $to);
            $balance = $balance + $monthlyBudget - $credits;
            $cursor->addMonth();
        }

        return $balance;
    }

    private function firstActivityMonth(int $categoryId): ?array
    {
        $dates = [];

        $rnf = DB::table('retail_non_food')
            ->where('category_budget_id', $categoryId)
            ->where('status', 'approved')
            ->min('transaction_date');

        if ($rnf) {
            $dates[] = $rnf;
        }

        $nfp = DB::table('non_food_payment_outlets as nfpo')
            ->join('non_food_payments as nfp', 'nfp.id', '=', 'nfpo.non_food_payment_id')
            ->where('nfpo.category_id', $categoryId)
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->min('nfp.payment_date');

        if ($nfp) {
            $dates[] = $nfp;
        }

        $category = PurchaseRequisitionCategory::find($categoryId);
        if ($category?->created_at) {
            $dates[] = Carbon::parse($category->created_at)->format('Y-m-d');
        }

        if (empty($dates)) {
            return null;
        }

        $min = collect($dates)->filter()->min();
        $c = Carbon::parse($min);

        return ['year' => (int) $c->year, 'month' => (int) $c->month];
    }

    private function sumCreditsForPeriod(int $categoryId, string $dateFrom, string $dateTo): float
    {
        return (float) $this->fetchCreditLines($categoryId, $dateFrom, $dateTo)->sum('amount');
    }

    private function fetchCreditLines(int $categoryId, string $dateFrom, string $dateTo): Collection
    {
        $rnf = DB::table('retail_non_food as rnf')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'rnf.outlet_id')
            ->where('rnf.category_budget_id', $categoryId)
            ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
            ->where('rnf.status', 'approved')
            ->select(
                'rnf.transaction_date',
                'o.nama_outlet as outlet_name',
                DB::raw('COALESCE(rnf.notes, rnf.retail_number, CONCAT("Retail Non Food ", rnf.retail_number)) as description'),
                'rnf.total_amount as amount'
            )
            ->get()
            ->map(fn ($row) => (object) [
                'transaction_date' => $row->transaction_date,
                'outlet_name' => $row->outlet_name,
                'description' => $row->description,
                'amount' => (float) $row->amount,
                'sort_key' => $row->transaction_date . '_1_' . $row->outlet_name,
            ]);

        $nfp = DB::table('non_food_payment_outlets as nfpo')
            ->join('non_food_payments as nfp', 'nfp.id', '=', 'nfpo.non_food_payment_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'nfpo.outlet_id')
            ->where('nfpo.category_id', $categoryId)
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->select(
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
            ->get()
            ->map(function ($row) {
                $description = $row->pr_title ?: $row->notes;
                if (! $description) {
                    $description = 'Pembayaran ' . ($row->payment_number ?? 'NFP');
                }

                return (object) [
                    'transaction_date' => $row->transaction_date,
                    'outlet_name' => $row->outlet_name,
                    'description' => $description,
                    'amount' => (float) $row->amount,
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

    private function debitRow(int $no, string $date, string $outlet, string $description, float $amount): array
    {
        return [
            'no' => $no,
            'date' => $date,
            'date_label' => $this->formatDateLabel($date),
            'outlet' => $outlet,
            'description' => $description,
            'debit' => $amount,
            'credit' => null,
            'row_type' => 'debit',
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
