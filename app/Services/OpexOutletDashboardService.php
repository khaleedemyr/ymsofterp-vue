<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Outlet spend & revenue summary for Opex Outlet Dashboard.
 * Sources: Revenue (orders), GSR/RO (food GR + serial GSR), Retail Food, Retail Non Food.
 * RWS tidak ditampilkan/dijumlah ke spend/purchased — inlet outlet lewat Retail Food (Justus Group).
 * No PR / non-food payment.
 */
class OpexOutletDashboardService
{
    /** Total purchase budget pool = 43% × forecast (treated as 100% for split). */
    private const PURCHASE_BUDGET_POOL_RATIO = 0.43;

    /** Shares of the 43% pool. */
    private const KITCHEN_SHARE_OF_POOL = 0.70;

    private const BAR_SHARE_OF_POOL = 0.20;

    private const SERVICE_SHARE_OF_POOL = 0.10;

    /** @var array<string, string> */
    public const CATEGORY_COST_TYPE_LABELS = [
        'internal_use' => 'Internal Use',
        'spoil' => 'Spoil',
        'waste' => 'Waste',
        'usage' => 'Usage',
        'r_and_d' => 'R & D',
        'marketing' => 'Marketing',
        'non_commodity' => 'Non Commodity',
        'guest_supplies' => 'Guest Supplies',
        'wrong_maker' => 'Wrong Maker',
        'training' => 'Training',
    ];

    /** @var list<string> */
    private const CATEGORY_COST_APPROVAL_TYPES = ['r_and_d', 'marketing', 'wrong_maker', 'training'];

    /** @var array<string, string> */
    public const MCS_SUB_CATEGORY_LABELS = [
        'Marketing' => 'Marketing',
        'Chemical' => 'Chemical',
        'Stationary' => 'Stationary',
    ];

    /**
     * @return array<string, mixed>
     */
    public function buildDashboard(?int $outletId, string $dateFrom, string $dateTo): array
    {
        if (! $outletId) {
            return $this->emptyDashboard();
        }

        $meta = $this->buildSectionMeta($outletId);
        $overview = $this->buildSectionOverview($outletId, $dateFrom, $dateTo);
        $member = $this->buildSectionMember($outletId, $dateFrom, $dateTo);
        $charts = $this->buildSectionCharts($outletId, $dateFrom, $dateTo);
        $payments = $this->buildSectionPayments($outletId, $dateFrom, $dateTo);
        $ro = $this->buildSectionRoForecast($outletId, $dateFrom, $dateTo);

        return array_merge($meta, [
            'overview' => array_merge($overview['overview'] ?? [], $member['overview'] ?? []),
            'trend' => $charts['trend'] ?? [],
            'spend_mix' => $charts['spend_mix'] ?? [],
            'mcs_mix' => $charts['mcs_mix'] ?? [],
            'purchase_category_mix' => $charts['purchase_category_mix'] ?? [],
            'payment_methods' => $payments['payment_methods'] ?? [],
            'ro_forecast' => $ro['ro_forecast'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSection(string $section, int $outletId, string $dateFrom, string $dateTo): array
    {
        return match ($section) {
            'meta' => $this->buildSectionMeta($outletId),
            'overview' => $this->buildSectionOverview($outletId, $dateFrom, $dateTo),
            'member' => $this->buildSectionMember($outletId, $dateFrom, $dateTo),
            'ro_forecast' => $this->buildSectionRoForecast($outletId, $dateFrom, $dateTo),
            'payments' => $this->buildSectionPayments($outletId, $dateFrom, $dateTo),
            'charts' => $this->buildSectionCharts($outletId, $dateFrom, $dateTo),
            'attendance' => $this->buildSectionAttendance($outletId, $dateFrom, $dateTo),
            default => ['error' => 'Unknown section'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyDashboard(): array
    {
        return [
            'overview' => null,
            'trend' => [],
            'spend_mix' => [],
            'mcs_mix' => [],
            'purchase_category_mix' => [],
            'payment_methods' => [],
            'ro_forecast' => null,
            'outlet_name' => null,
            'attendance' => null,
        ];
    }

    /**
     * Kartu OT / Telat / Leave (logika Attendance Report per outlet).
     *
     * @return array{attendance: array<string, mixed>}
     */
    public function buildSectionAttendance(int $outletId, string $dateFrom, string $dateTo): array
    {
        /** @var \App\Http\Controllers\AttendanceReportController $attendance */
        $attendance = app(\App\Http\Controllers\AttendanceReportController::class);

        return [
            'attendance' => $attendance->buildOpexAttendanceBundle($outletId, $dateFrom, $dateTo),
        ];
    }

    /**
     * @return array{outlet_name: string|null}
     */
    public function buildSectionMeta(int $outletId): array
    {
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->first(['nama_outlet']);

        return ['outlet_name' => $outlet?->nama_outlet];
    }

    /**
     * KPI utama tanpa CRM member (lebih cepat).
     *
     * @return array{overview: array<string, mixed>}
     */
    public function buildSectionOverview(int $outletId, string $dateFrom, string $dateTo): array
    {
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->first(['qr_code', 'nama_outlet']);

        $current = $this->buildOverviewMetrics($outletId, $outlet?->qr_code, $dateFrom, $dateTo);
        [$prevFrom, $prevTo] = $this->previousMonthRange($dateFrom, $dateTo);
        $previous = $this->buildOverviewMetrics($outletId, $outlet?->qr_code, $prevFrom, $prevTo);

        $current['vs_last_month'] = [
            'period_from' => $prevFrom,
            'period_to' => $prevTo,
            'label' => Carbon::parse($prevTo)->locale('id')->translatedFormat('M Y'),
            'revenue' => $this->vsMetric($current['revenue'], $previous['revenue']),
            'total_spend' => $this->vsMetric($current['total_spend'], $previous['total_spend']),
            'net' => $this->vsMetric($current['net'], $previous['net']),
            'cover' => $this->vsMetric($current['cover'], $previous['cover']),
            'avg_pax' => $this->vsMetric($current['avg_pax'], $previous['avg_pax']),
            'avg_check' => $this->vsMetric($current['avg_check'], $previous['avg_check']),
            'discount' => $this->vsMetric($current['discount'], $previous['discount']),
            'discount_compliment' => $this->vsMetric($current['discount_compliment'], $previous['discount_compliment']),
            'discount_guest_satisfaction' => $this->vsMetric(
                $current['discount_guest_satisfaction'],
                $previous['discount_guest_satisfaction']
            ),
            'officer_check' => $this->vsMetric($current['officer_check'], $previous['officer_check']),
            'gsr_ro' => $this->vsMetric($current['gsr_ro'], $previous['gsr_ro']),
            'rws' => $this->vsMetric($current['rws'], $previous['rws']),
            'retail_food' => $this->vsMetric($current['retail_food'], $previous['retail_food']),
            'retail_non_food' => $this->vsMetric($current['retail_non_food'], $previous['retail_non_food']),
            'petty_cash' => $this->vsMetric($current['petty_cash'], $previous['petty_cash']),
            'stock_cut' => $this->vsMetric($current['stock_cut'], $previous['stock_cut']),
            'category_cost' => $this->vsMetric($current['category_cost'], $previous['category_cost']),
            'begin_inventory' => $this->vsMetric($current['begin_inventory'], $previous['begin_inventory']),
            'ending_inventory' => $this->vsMetric(
                (float) ($current['ending_inventory'] ?? 0),
                (float) ($previous['ending_inventory'] ?? 0)
            ),
            'cogs_pct' => $this->vsMetric(
                (float) ($current['cogs_pct'] ?? 0),
                (float) ($previous['cogs_pct'] ?? 0)
            ),
            'outlet_transfer_in' => $this->vsMetric(
                (float) ($current['outlet_transfer_in'] ?? 0),
                (float) ($previous['outlet_transfer_in'] ?? 0)
            ),
            'outlet_transfer_out' => $this->vsMetric(
                (float) ($current['outlet_transfer_out'] ?? 0),
                (float) ($previous['outlet_transfer_out'] ?? 0)
            ),
            'outlet_adjustment' => $this->vsMetric(
                (float) ($current['outlet_adjustment'] ?? 0),
                (float) ($previous['outlet_adjustment'] ?? 0)
            ),
            'stock_opname_cutoff' => $this->vsMetric(
                (float) ($current['stock_opname_cutoff'] ?? 0),
                (float) ($previous['stock_opname_cutoff'] ?? 0)
            ),
            'wip_finished_cost' => $this->vsMetric(
                (float) ($current['wip_finished_cost'] ?? 0),
                (float) ($previous['wip_finished_cost'] ?? 0)
            ),
            'mcs_purchase' => $this->vsMetric($current['mcs_purchase'], $previous['mcs_purchase']),
            'outlet_city_ledger' => $this->vsMetric($current['outlet_city_ledger'], $previous['outlet_city_ledger']),
        ];

        return ['overview' => $current];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOverviewMetrics(int $outletId, ?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $revenue = $this->sumRevenue($qrCode, $dateFrom, $dateTo);
        $avgDailyRevenue = $this->buildAvgDailyRevenueByWeekday($qrCode, $dateFrom, $dateTo);
        $gsrRo = $this->sumGsrRo($outletId, $dateFrom, $dateTo);
        $rws = $this->sumRws($outletId, $dateFrom, $dateTo);
        $rf = $this->sumRetailFood($outletId, $dateFrom, $dateTo);
        $rfJustus = $this->sumRetailFoodJustusGroup($outletId, $dateFrom, $dateTo);
        $rnf = $this->sumRetailNonFood($outletId, $dateFrom, $dateTo);

        // Total spend tanpa RWS: inlet outlet sudah di Retail Food (mirror penjualan gudang).
        $totalSpend = round($gsrRo['total'] + $rf['total'] + $rnf['total'], 2);
        $spendRatio = $revenue['total'] > 0 ? round(($totalSpend / $revenue['total']) * 100, 2) : null;
        $discountRatio = $revenue['gross_before_discount'] > 0
            ? round(($revenue['discount'] / $revenue['gross_before_discount']) * 100, 2)
            : null;

        $pettyCash = round($rf['cash_total'] + $rnf['cash_total'], 2);
        $pettyCashCount = $rf['cash_count'] + $rnf['cash_count'];
        $compliment = $this->sumManualDiscountByType($qrCode, $dateFrom, $dateTo, 'compliment');
        $guestSatisfaction = $this->sumManualDiscountByType($qrCode, $dateFrom, $dateTo, 'guest_satisfaction');
        $officerCheck = $this->sumOfficerCheck($qrCode, $dateFrom, $dateTo);
        $stockCut = $this->sumStockCut($outletId, $dateFrom, $dateTo);
        $categoryCost = $this->sumCategoryCost($outletId, $dateFrom, $dateTo);
        $beginInventory = $this->sumBeginInventory($outletId, $dateFrom);
        $inventoryMovement = $this->buildInventoryMovementSummary($outletId, $dateFrom, $dateTo);
        $mcsPurchase = $this->sumMcsPurchase($outletId, $dateFrom, $dateTo);
        $cityLedger = $this->sumOutletCityLedger($qrCode, $dateFrom, $dateTo);
        $monthlyBudget = $this->sumMonthlyRevenueBudget($outletId, $dateFrom, $dateTo);
        $budgetPerf = $monthlyBudget !== null && $monthlyBudget > 0
            ? round(($revenue['total'] / $monthlyBudget) * 100, 1)
            : null;
        $budgetVariance = $monthlyBudget !== null
            ? round($revenue['total'] - $monthlyBudget, 2)
            : null;
        $pctOfRevenue = static function (float $amount) use ($revenue): ?float {
            return $revenue['total'] > 0 ? round(($amount / $revenue['total']) * 100, 2) : null;
        };

        $outletTransferSummary = $this->sumOutletTransferMovements($outletId, $dateFrom, $dateTo);
        $adjustmentSummary = $this->sumOutletAdjustmentMovements($outletId, $dateFrom, $dateTo);
        $iwtSummary = $this->sumInternalWarehouseTransferMovements($outletId, $dateFrom, $dateTo);
        $wipSummary = $this->sumOutletWipMovements($outletId, $dateFrom, $dateTo);

        // Rollforward level outlet (buku):
        // Begin card = IB (Cost Report). Formula + cutoff koreksi fisik tgl 1
        // untuk item tanpa IB (saldo_value opname) supaya selaras stok periode.
        // Opname EOM / mid-month lain = balancing — tidak dijumlah ke formula.
        // IWT net antar gudang ≈ 0 di level outlet.
        // Stock cut di formula = potongan FISIK (kartu), bukan HPP full (detail + shortfall minus).
        $stockCutPhysical = (float) ($stockCut['physical_total'] ?? $stockCut['total']);
        $day1Cutoff = $this->sumDay1OpnameCutoffWithoutIb($outletId, $dateFrom);
        $formulaEnding = round(
            (float) $beginInventory['total']
            + (float) $day1Cutoff['total']
            + (float) $inventoryMovement['purchased_total']
            + (float) $outletTransferSummary['net_total']
            + (float) $adjustmentSummary['total']
            - $stockCutPhysical
            - (float) $categoryCost['total'],
            2
        );
        $endingStock = $this->sumEndingStockSanitized($outletId, $dateTo, $dateFrom);
        $endingInventory = $formulaEnding;

        // COGS % — selaras tab COGS Cost Report (periode filter dashboard).
        // COGS Foods = Stock Cut HPP full
        // Category Cost (pembanding) = spoil+waste+guest+non_commodity (tanpa internal_use)
        // Meal Employees = internal_use
        // COGS Pembanding = Foods + CatCost + Meal Emp
        // COGS Aktual = (Begin + Koreksi tgl1 + Purchased ± Xfer ± Adj) − Ending Stok
        $mealEmployees = 0.0;
        $categoryCostForCogs = 0.0;
        foreach ($categoryCost['by_type'] ?? [] as $row) {
            $type = (string) ($row['type'] ?? '');
            $amount = (float) ($row['amount'] ?? 0);
            if ($type === 'internal_use') {
                $mealEmployees += $amount;
            } elseif (in_array($type, ['spoil', 'waste', 'guest_supplies', 'non_commodity'], true)) {
                $categoryCostForCogs += $amount;
            }
        }
        $mealEmployees = round($mealEmployees, 2);
        $categoryCostForCogs = round($categoryCostForCogs, 2);
        $cogsFoods = round((float) $stockCut['total'], 2);
        $cogsPembanding = round($cogsFoods + $categoryCostForCogs + $mealEmployees, 2);
        $availableGoods = round(
            (float) $beginInventory['total']
            + (float) $day1Cutoff['total']
            + (float) $inventoryMovement['purchased_total']
            + (float) $outletTransferSummary['net_total']
            + (float) $adjustmentSummary['total'],
            2
        );
        $cogsAktual = round($availableGoods - (float) $endingStock['total'], 2);
        // Denominator sama Cost Report: before = Σ(qty×price); after = before − discount.
        // Jangan pakai grand_total (bisa lebih besar karena pajak → % after disc jadi aneh / lebih kecil).
        $salesBefore = $this->sumSalesBeforeDiscountForCogs($qrCode, $dateFrom, $dateTo);
        if ($salesBefore <= 0) {
            $salesBefore = (float) $revenue['gross_before_discount'];
        }
        $salesAfter = max(0.0, round($salesBefore - (float) $revenue['discount'], 2));
        $pctOrNull = static function (float $num, float $den): ?float {
            return $den > 0 ? round(($num / $den) * 100, 2) : null;
        };
        $deviasi = round($cogsPembanding - $cogsAktual, 2);
        $pctDeviasiSigned = $salesAfter > 0 ? round(($deviasi / $salesAfter) * 100, 2) : null;
        $toleransiMaxAmount = round($salesAfter * 0.02, 2);
        $withinToleransi = $pctDeviasiSigned === null
            ? true
            : abs($pctDeviasiSigned) <= 2.0;
        $cogsSummary = [
            'cogs_foods' => $cogsFoods,
            'category_cost' => $categoryCostForCogs,
            'meal_employees' => $mealEmployees,
            'cogs_pembanding' => $cogsPembanding,
            'cogs_aktual' => $cogsAktual,
            'available_goods' => $availableGoods,
            'ending_stock' => round((float) $endingStock['total'], 2),
            'sales_before_discount' => round($salesBefore, 2),
            'sales_after_discount' => round($salesAfter, 2),
            'deviasi' => $deviasi,
            'pct_deviasi' => $pctDeviasiSigned,
            'toleransi_max_pct' => 2.0,
            'toleransi_max_amount' => $toleransiMaxAmount,
            'within_toleransi' => $withinToleransi,
            'pct_cogs_foods' => $pctOrNull($cogsFoods, $salesBefore),
            'pct_cogs_pembanding' => $pctOrNull($cogsPembanding, $salesBefore),
            'pct_cogs_actual_before_disc' => $pctOrNull($cogsAktual, $salesBefore),
            'pct_cogs_actual_after_disc' => $pctOrNull($cogsAktual, $salesAfter),
        ];

        return [
            'revenue' => $revenue['total'],
            'revenue_count' => $revenue['count'],
            'revenue_monthly_budget' => $monthlyBudget,
            'revenue_budget_perf_percent' => $budgetPerf,
            'revenue_budget_variance' => $budgetVariance,
            'avg_daily_revenue' => $avgDailyRevenue['avg_daily'],
            'avg_daily_revenue_day_count' => $avgDailyRevenue['day_count'],
            'avg_daily_revenue_by_weekday' => $avgDailyRevenue['by_weekday'],
            'cover' => $revenue['cover'],
            'avg_pax' => $revenue['avg_pax'],
            'avg_check' => $revenue['avg_check'],
            'discount' => $revenue['discount'],
            'discount_count' => $revenue['discount_count'],
            'discount_ratio_percent' => $discountRatio,
            'discount_compliment' => $compliment['discount'],
            'discount_compliment_bill' => $compliment['bill'],
            'discount_compliment_count' => $compliment['count'],
            'discount_guest_satisfaction' => $guestSatisfaction['discount'],
            'discount_guest_satisfaction_bill' => $guestSatisfaction['bill'],
            'discount_guest_satisfaction_count' => $guestSatisfaction['count'],
            'officer_check' => $officerCheck['amount'],
            'officer_check_bill' => $officerCheck['bill'],
            'officer_check_count' => $officerCheck['count'],
            'gsr_ro' => $gsrRo['total'],
            'gsr_ro_count' => $gsrRo['count'],
            'gsr_ro_gr' => $gsrRo['gr_total'],
            'gsr_ro_gsr' => $gsrRo['gsr_total'],
            'rws' => $rws['total'],
            'rws_count' => $rws['count'],
            'retail_food' => $rf['total'],
            'retail_food_count' => $rf['count'],
            'retail_food_cash_total' => $rf['cash_total'],
            'retail_food_cash_count' => $rf['cash_count'],
            'retail_food_contra_bon_total' => $rf['contra_bon_total'],
            'retail_food_contra_bon_count' => $rf['contra_bon_count'],
            'retail_food_justus_group_total' => $rfJustus['total'],
            'retail_food_justus_group_count' => $rfJustus['count'],
            'retail_food_revenue_pct' => $pctOfRevenue($rf['total']),
            'retail_non_food' => $rnf['total'],
            'retail_non_food_count' => $rnf['count'],
            'retail_non_food_cash_total' => $rnf['cash_total'],
            'retail_non_food_cash_count' => $rnf['cash_count'],
            'retail_non_food_contra_bon_total' => $rnf['contra_bon_total'],
            'retail_non_food_contra_bon_count' => $rnf['contra_bon_count'],
            'retail_non_food_revenue_pct' => $pctOfRevenue($rnf['total']),
            'petty_cash' => $pettyCash,
            'petty_cash_count' => $pettyCashCount,
            'petty_cash_rf' => $rf['cash_total'],
            'petty_cash_rnf' => $rnf['cash_total'],
            'petty_cash_revenue_pct' => $pctOfRevenue($pettyCash),
            'stock_cut' => $stockCut['total'],
            'stock_cut_count' => $stockCut['count'],
            'stock_cut_physical' => $stockCut['physical_total'] ?? $stockCut['total'],
            'stock_cut_shortfall' => $stockCut['shortfall_total'] ?? 0,
            'stock_cut_revenue_pct' => $pctOfRevenue($stockCut['total']),
            // Breakdown kartu KPI = HPP full (selaras total card), bukan fisik.
            'stock_cut_by_warehouse' => $this->mapWarehouseAmounts(
                $outletId,
                $this->stockCutHppAmountByWarehouse($outletId, $dateFrom, $dateTo)
            ),
            'category_cost' => $categoryCost['total'],
            'category_cost_count' => $categoryCost['count'],
            'category_cost_by_type' => $categoryCost['by_type'],
            'category_cost_revenue_pct' => $pctOfRevenue($categoryCost['total']),
            'category_cost_by_warehouse' => $inventoryMovement['category_cost_by_warehouse'],
            'begin_inventory' => $beginInventory['total'],
            'begin_inventory_count' => $beginInventory['count'],
            'begin_inventory_source' => $beginInventory['source'],
            'begin_inventory_revenue_pct' => $pctOfRevenue($beginInventory['total']),
            'begin_inventory_by_warehouse' => $inventoryMovement['begin_by_warehouse'],
            'purchased_inventory' => $inventoryMovement['purchased_total'],
            'purchased_inventory_by_warehouse' => $inventoryMovement['purchased_by_warehouse'],
            'opname_inventory' => $inventoryMovement['opname_total'] ?? 0,
            'opname_inventory_by_warehouse' => $inventoryMovement['opname_by_warehouse'] ?? [],
            'stock_opname_cutoff' => round((float) $day1Cutoff['total'], 2),
            'stock_opname_cutoff_count' => (int) $day1Cutoff['count'],
            'stock_opname_cutoff_by_warehouse' => $this->mapWarehouseAmounts(
                $outletId,
                $day1Cutoff['by_warehouse'] ?? []
            ),
            'stock_opname_period_net' => round((float) ($inventoryMovement['opname_total'] ?? 0), 2),
            'stock_opname_period_by_warehouse' => $inventoryMovement['opname_by_warehouse'] ?? [],
            'stock_opname_count' => $this->countStockOpnameTransactions($outletId, $dateFrom, $dateTo),
            'stock_opname_revenue_pct' => $pctOfRevenue((float) $day1Cutoff['total']),
            'transfer_inventory' => $inventoryMovement['transfer_total'] ?? 0,
            'transfer_inventory_by_warehouse' => $inventoryMovement['transfer_by_warehouse'] ?? [],
            'ending_inventory' => $endingInventory,
            'ending_inventory_revenue_pct' => $pctOfRevenue($endingInventory),
            // Per warehouse di card = nilai stok (bukan formula movement)
            'ending_inventory_by_warehouse' => $endingStock['by_warehouse'],
            'ending_inventory_stock' => $endingStock['total'],
            'ending_inventory_stock_by_warehouse' => $endingStock['by_warehouse'],
            'ending_inventory_formula' => [
                'begin' => round((float) $beginInventory['total'], 2),
                'day1_opname_cutoff' => round((float) $day1Cutoff['total'], 2),
                'day1_opname_cutoff_count' => (int) $day1Cutoff['count'],
                'purchased' => round((float) $inventoryMovement['purchased_total'], 2),
                'outlet_transfer_net' => round((float) $outletTransferSummary['net_total'], 2),
                'outlet_adjustment' => round((float) $adjustmentSummary['total'], 2),
                'opname' => round((float) ($inventoryMovement['opname_total'] ?? 0), 2),
                'opname_in_formula' => false,
                'stock_cut' => round($stockCutPhysical, 2),
                'stock_cut_hpp' => round((float) $stockCut['total'], 2),
                'stock_cut_shortfall' => round((float) ($stockCut['shortfall_total'] ?? 0), 2),
                'category_cost' => round((float) $categoryCost['total'], 2),
                'formula_ending' => $formulaEnding,
                'stock_ending' => round((float) $endingStock['total'], 2),
                'variance' => round($formulaEnding - (float) $endingStock['total'], 2),
                'ending' => $endingInventory,
            ],
            'cogs_pct' => $cogsSummary['pct_cogs_actual_after_disc'],
            'cogs' => $cogsSummary,
            'outlet_transfer_in' => $outletTransferSummary['in_total'],
            'outlet_transfer_out' => $outletTransferSummary['out_total'],
            'outlet_transfer_net' => $outletTransferSummary['net_total'],
            'outlet_transfer_count' => $outletTransferSummary['count'],
            'outlet_adjustment' => $adjustmentSummary['total'],
            'outlet_adjustment_count' => $adjustmentSummary['count'],
            'outlet_adjustment_by_warehouse' => $adjustmentSummary['by_warehouse'],
            'internal_warehouse_transfer_total' => $iwtSummary['total'],
            'internal_warehouse_transfer_count' => $iwtSummary['count'],
            'internal_warehouse_transfer_flows' => $iwtSummary['flows'],
            'wip_material_cost' => $wipSummary['material_total'],
            'wip_finished_cost' => $wipSummary['finished_total'],
            'wip_count' => $wipSummary['count'],
            'wip_by_warehouse' => $wipSummary['by_warehouse'],
            'mcs_purchase' => $mcsPurchase['total'],
            'mcs_purchase_count' => $mcsPurchase['count'],
            'mcs_purchase_by_category' => $mcsPurchase['by_category'],
            'mcs_purchase_revenue_pct' => $pctOfRevenue($mcsPurchase['total']),
            'outlet_city_ledger' => $cityLedger['amount'],
            'outlet_city_ledger_count' => $cityLedger['count'],
            'outlet_city_ledger_bill' => $cityLedger['bill'],
            'total_spend' => $totalSpend,
            'spend_ratio_percent' => $spendRatio,
            'net' => round($revenue['total'] - $totalSpend, 2),
        ];
    }

    /**
     * @return array{overview: array<string, mixed>}
     */
    public function buildSectionMember(int $outletId, string $dateFrom, string $dateTo): array
    {
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->first(['qr_code', 'nama_outlet']);

        $member = $this->sumMemberActivity($outlet?->qr_code, $outlet?->nama_outlet, $dateFrom, $dateTo);
        [$prevFrom, $prevTo] = $this->previousMonthRange($dateFrom, $dateTo);
        $prevMember = $this->sumMemberActivity($outlet?->qr_code, $outlet?->nama_outlet, $prevFrom, $prevTo);

        return [
            'overview' => [
                'member_bills' => $member['member_bills'],
                'member_revenue' => $member['member_revenue'],
                'member_top_up' => $member['top_up_value'],
                'member_top_up_count' => $member['top_up_count'],
                'member_top_up_points' => $member['top_up_points'],
                'member_redeem' => $member['redeem_value'],
                'member_redeem_count' => $member['redeem_count'],
                'member_redeem_points' => $member['redeem_points'],
                'member_source' => $member['source'],
                'vs_last_month_member' => [
                    'period_from' => $prevFrom,
                    'period_to' => $prevTo,
                    'label' => Carbon::parse($prevTo)->locale('id')->translatedFormat('M Y'),
                    'member_bills' => $this->vsMetric($member['member_bills'], $prevMember['member_bills']),
                    'member_revenue' => $this->vsMetric($member['member_revenue'], $prevMember['member_revenue']),
                    'member_top_up_points' => $this->vsMetric($member['top_up_points'], $prevMember['top_up_points']),
                    'member_redeem' => $this->vsMetric($member['redeem_value'], $prevMember['redeem_value']),
                ],
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function previousMonthRange(string $dateFrom, string $dateTo): array
    {
        return [
            Carbon::parse($dateFrom)->subMonthNoOverflow()->format('Y-m-d'),
            Carbon::parse($dateTo)->subMonthNoOverflow()->format('Y-m-d'),
        ];
    }

    /**
     * @return array{previous: float|null, diff: float|null, pct: float|null}
     */
    private function vsMetric(float|int|null $current, float|int|null $previous): array
    {
        $curr = $current === null ? null : (float) $current;
        $prev = $previous === null ? null : (float) $previous;
        if ($curr === null || $prev === null) {
            return ['previous' => $prev, 'diff' => null, 'pct' => null];
        }

        $diff = round($curr - $prev, 2);
        $pct = null;
        if ($prev != 0.0) {
            $pct = round(($diff / abs($prev)) * 100, 1);
        } elseif ($curr == 0.0) {
            $pct = 0.0;
        } else {
            $pct = 100.0;
        }

        return [
            'previous' => round($prev, 2),
            'diff' => $diff,
            'pct' => $pct,
        ];
    }

    /**
     * @return array{ro_forecast: array<string, mixed>}
     */
    public function buildSectionRoForecast(int $outletId, string $dateFrom, string $dateTo): array
    {
        return [
            'ro_forecast' => $this->buildRoForecastSummary($outletId, $dateFrom, $dateTo),
        ];
    }

    /**
     * @return array{payment_methods: list<array<string, mixed>>}
     */
    public function buildSectionPayments(int $outletId, string $dateFrom, string $dateTo): array
    {
        $qrCode = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('qr_code');

        return [
            'payment_methods' => $this->sumPaymentMethods($qrCode, $dateFrom, $dateTo),
        ];
    }

    /**
     * @return array{
     *   trend: list<array<string, mixed>>,
     *   spend_mix: list<array<string, mixed>>,
     *   mcs_mix: list<array<string, mixed>>,
     *   purchase_category_mix: list<array<string, mixed>>
     * }
     */
    public function buildSectionCharts(int $outletId, string $dateFrom, string $dateTo): array
    {
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->first(['qr_code']);

        $trend = $this->buildTrend($outletId, $outlet?->qr_code, $dateFrom, $dateTo);

        // Spend mix tanpa RWS (mirror Retail Food / Justus Group).
        $mix = [
            'gsr_ro' => 0.0,
            'retail_food' => 0.0,
            'retail_non_food' => 0.0,
        ];
        foreach ($trend as $row) {
            $mix['gsr_ro'] += (float) ($row['gsr_ro'] ?? 0);
            $mix['retail_food'] += (float) ($row['retail_food'] ?? 0);
            $mix['retail_non_food'] += (float) ($row['retail_non_food'] ?? 0);
        }

        $mcs = $this->sumMcsPurchase($outletId, $dateFrom, $dateTo);
        $purchaseCats = $this->sumPurchaseByCategory($outletId, $dateFrom, $dateTo);

        return [
            'trend' => $trend,
            'spend_mix' => [
                ['key' => 'gsr_ro', 'label' => 'GSR / RO', 'amount' => round($mix['gsr_ro'], 2)],
                ['key' => 'retail_food', 'label' => 'Retail Food', 'amount' => round($mix['retail_food'], 2)],
                ['key' => 'retail_non_food', 'label' => 'Retail Non Food', 'amount' => round($mix['retail_non_food'], 2)],
            ],
            'mcs_mix' => $mcs['by_category'],
            'purchase_category_mix' => $purchaseCats['by_category'],
        ];
    }

    /**
     * Ringkas kolom RO Forecast (Floor Order vs Forecast):
     * Forecast = Rolling Auto Forecast skenario Realistis (projected EOM),
     * fallback ke sum daily revenue target jika rolling belum tersedia.
     * Pool budget 43% × Forecast, dibagi Kitchen 70% / Bar 20% / Service 10%.
     * Selalu dihitung full calendar month (bukan MTD dari filter tanggal).
     * Purchased = GSR + GR (nilai diterima) + Retail Food, bucket per warehouse Kitchen / Bar / Service.
     * RWS tidak dijumlah: outflow gudang; inlet outlet sudah lewat Retail Food (supplier Justus Group).
     *
     * @return array<string, mixed>
     */
    public function buildRoForecastSummary(int $outletId, string $dateFrom, string $dateTo): array
    {
        [$monthFrom, $monthTo] = $this->fullMonthBounds($dateFrom, $dateTo);
        $monthKey = Carbon::parse($monthFrom)->format('Y-m');

        $rolling = app(OutletRollingForecastService::class)->build($outletId, $monthKey);
        $storedForecast = $this->sumForecastRevenue($outletId, $monthFrom, $monthTo);

        $forecastSource = 'revenue_target';
        $forecastTotal = $storedForecast;
        $monthlyTarget = null;
        $paceFactor = null;
        $scenarios = null;

        if (($rolling['success'] ?? true) !== false
            && ($rolling['has_target'] ?? false)
            && (float) ($rolling['projected_eom'] ?? 0) > 0
        ) {
            // projected_eom = skenario Realistis (patokan utama rolling forecast)
            $forecastTotal = (float) $rolling['projected_eom'];
            $forecastSource = 'rolling_realistic';
            $monthlyTarget = isset($rolling['monthly_target']) ? (float) $rolling['monthly_target'] : null;
            $paceFactor = isset($rolling['pace_factor']) ? (float) $rolling['pace_factor'] : null;
            $scenarios = $rolling['scenarios'] ?? null;
        }

        $purchased = $this->sumPurchasedByBucket($outletId, $monthFrom, $monthTo);
        $outstanding = $this->sumOutstandingRoByBucket($outletId, $monthFrom, $monthTo);
        $sources = $purchased['by_source'] ?? [];

        $poolBudget = round($forecastTotal * self::PURCHASE_BUDGET_POOL_RATIO, 2);
        $kitchen = $this->buildPurchaseBudgetBucket(
            $poolBudget,
            self::KITCHEN_SHARE_OF_POOL,
            (float) ($purchased['kitchen'] ?? 0),
            (float) ($outstanding['kitchen'] ?? 0),
            $sources['kitchen'] ?? []
        );
        $bar = $this->buildPurchaseBudgetBucket(
            $poolBudget,
            self::BAR_SHARE_OF_POOL,
            (float) ($purchased['bar'] ?? 0),
            (float) ($outstanding['bar'] ?? 0),
            $sources['bar'] ?? []
        );
        $service = $this->buildPurchaseBudgetBucket(
            $poolBudget,
            self::SERVICE_SHARE_OF_POOL,
            (float) ($purchased['service'] ?? 0),
            (float) ($outstanding['service'] ?? 0),
            $sources['service'] ?? []
        );

        $hasForecast = $forecastTotal > 0
            || $this->hasForecastHeaderForRange($outletId, $monthFrom, $monthTo)
            || (($rolling['has_target'] ?? false) === true);

        return [
            'has_forecast' => $hasForecast,
            'period_from' => $monthFrom,
            'period_to' => $monthTo,
            'is_full_month' => true,
            'forecast' => round($forecastTotal, 2),
            'forecast_source' => $forecastSource,
            'stored_forecast' => round($storedForecast, 2),
            'monthly_target' => $monthlyTarget !== null ? round($monthlyTarget, 2) : null,
            'pace_factor' => $paceFactor !== null ? round($paceFactor, 4) : null,
            'scenarios' => $scenarios,
            'budget_pool_ratio_pct' => (int) round(self::PURCHASE_BUDGET_POOL_RATIO * 100),
            'budget_pool' => $poolBudget,
            'kitchen' => $kitchen,
            'bar' => $bar,
            'service' => $service,
            'ro_outstanding_total' => round(
                (float) $kitchen['ro_outstanding'] + (float) $bar['ro_outstanding'] + (float) $service['ro_outstanding'],
                2
            ),
        ];
    }

    /**
     * @param  array{gsr?: float, rws?: float, rf?: float}  $sources
     * @return array{
     *   share_of_pool_pct: int,
     *   of_forecast_pct: float,
     *   budget: float,
     *   purchased: float,
     *   gsr: float,
     *   rws: float,
     *   rf: float,
     *   ro_outstanding: float,
     *   remaining: float,
     *   remaining_after_commit: float,
     *   variance: float,
     *   pct: float|null
     * }
     */
    private function buildPurchaseBudgetBucket(
        float $poolBudget,
        float $shareOfPool,
        float $purchased,
        float $outstanding,
        array $sources = []
    ): array {
        $budget = round($poolBudget * $shareOfPool, 2);
        $remaining = round($budget - $purchased, 2);
        $remainingAfterCommit = round($budget - $purchased - $outstanding, 2);

        return [
            'share_of_pool_pct' => (int) round($shareOfPool * 100),
            'of_forecast_pct' => round(self::PURCHASE_BUDGET_POOL_RATIO * $shareOfPool * 100, 1),
            'budget' => $budget,
            'purchased' => round($purchased, 2),
            'gsr' => round((float) ($sources['gsr'] ?? 0), 2),
            'rws' => round((float) ($sources['rws'] ?? 0), 2),
            'rf' => round((float) ($sources['rf'] ?? 0), 2),
            'ro_outstanding' => round($outstanding, 2),
            'remaining' => $remaining,
            'remaining_after_commit' => $remainingAfterCommit,
            'variance' => round($purchased - $budget, 2),
            'pct' => $budget > 0 ? round(($purchased / $budget) * 100, 1) : null,
        ];
    }

    /**
     * RO Forecast selalu 1 bulan kalender label (bulan dari date_to / periode 26–25).
     *
     * @return array{0: string, 1: string}
     */
    public function fullMonthBounds(string $dateFrom, string $dateTo): array
    {
        $anchor = Carbon::parse($dateTo);

        return [
            $anchor->copy()->startOfMonth()->format('Y-m-d'),
            $anchor->copy()->endOfMonth()->format('Y-m-d'),
        ];
    }

    /**
     * Transaksi Purchased (GSR/GR + Retail Food) untuk satu warehouse bucket.
     * Dipakai drill-down card Budget Kitchen / Bar / Service.
     * RWS sengaja tidak di-list (sudah terwakili Retail Food).
     *
     * @param  'kitchen'|'bar'|'service'  $bucket
     * @return list<object{
     *   id: string,
     *   number: string,
     *   date: string,
     *   source: string,
     *   type: string,
     *   creator_name: string,
     *   warehouse: string,
     *   amount: float,
     *   items: list<array{name: string, qty: float, unit: string, price: float, subtotal: float}>
     * }>
     */
    public function listPurchasedBucketTransactions(
        int $outletId,
        string $dateFrom,
        string $dateTo,
        string $bucket
    ): array {
        $bucket = $this->normalizePurchaseBudgetBucket($bucket);
        if ($bucket === null) {
            return [];
        }

        [$monthFrom, $monthTo] = $this->fullMonthBounds($dateFrom, $dateTo);
        $grouped = [];

        $addLine = function (
            string $key,
            string $number,
            string $date,
            string $source,
            string $creator,
            string $warehouse,
            array $item
        ) use (&$grouped, $bucket): void {
            if (! isset($grouped[$key])) {
                $grouped[$key] = (object) [
                    'id' => $key,
                    'number' => $number,
                    'date' => $date,
                    'source' => $source,
                    'type' => $bucket.'_purchase',
                    'creator_name' => $creator !== '' ? $creator : '-',
                    'warehouse' => $warehouse,
                    'amount' => 0.0,
                    'items' => [],
                ];
            }
            $subtotal = round((float) ($item['subtotal'] ?? 0), 2);
            $grouped[$key]->amount = round((float) $grouped[$key]->amount + $subtotal, 2);

            // GSR/RWS serial: 1 baris per SN — gabungkan item sama (nama+unit+harga).
            $itemKey = strtolower(implode('|', [
                (string) ($item['name'] ?? '-'),
                (string) ($item['unit'] ?? '-'),
                number_format((float) ($item['price'] ?? 0), 4, '.', ''),
            ]));
            if (! isset($grouped[$key]->items[$itemKey])) {
                $grouped[$key]->items[$itemKey] = [
                    'name' => (string) ($item['name'] ?? '-'),
                    'qty' => round((float) ($item['qty'] ?? 0), 4),
                    'unit' => (string) ($item['unit'] ?? '-'),
                    'price' => round((float) ($item['price'] ?? 0), 2),
                    'subtotal' => $subtotal,
                ];
            } else {
                $grouped[$key]->items[$itemKey]['qty'] = round(
                    (float) $grouped[$key]->items[$itemKey]['qty'] + (float) ($item['qty'] ?? 0),
                    4
                );
                $grouped[$key]->items[$itemKey]['subtotal'] = round(
                    (float) $grouped[$key]->items[$itemKey]['subtotal'] + $subtotal,
                    2
                );
            }
        };

        // —— GSR ——
        if ($this->hasSerialGrTables()) {
            $priceSql = $this->serialGrPriceSql('it');
            $gsrRows = DB::table('outlet_serial_receive_items as si')
                ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->join('warehouse_outlets as wo', 'wo.id', '=', 'si.warehouse_outlet_id')
                ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
                ->leftJoin('users as usr', 'h.created_by', '=', 'usr.id')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->where('h.outlet_id', $outletId)
                ->whereRaw('LOWER(TRIM(wo.name)) = ?', [$bucket])
                ->whereBetween(DB::raw('DATE(h.receive_date)'), [$monthFrom, $monthTo])
                ->orderBy('it.name')
                ->get([
                    'h.id as header_id',
                    'h.number',
                    'h.receive_date as date',
                    'wo.name as warehouse_name',
                    'usr.nama_lengkap as creator_name',
                    'it.name as item_name',
                    'u.name as unit_name',
                    'si.qty',
                    DB::raw("({$priceSql}) as price"),
                    DB::raw("si.qty * ({$priceSql}) as subtotal"),
                ]);

            foreach ($gsrRows as $row) {
                $addLine(
                    'gsr-'.$row->header_id,
                    (string) ($row->number ?? '-'),
                    (string) $row->date,
                    'GSR',
                    (string) ($row->creator_name ?? ''),
                    (string) ($row->warehouse_name ?? $bucket),
                    [
                        'name' => $row->item_name,
                        'qty' => $row->qty,
                        'unit' => $row->unit_name,
                        'price' => $row->price,
                        'subtotal' => $row->subtotal,
                    ]
                );
            }
        }

        // —— Outlet GR ——
        $grRows = DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as ffoi', function ($join) {
                $join->on('ofgri.item_id', '=', 'ffoi.item_id')
                    ->where(function ($q) {
                        $q->whereColumn('ffoi.floor_order_id', 'do.floor_order_id')
                            ->orWhereColumn('ffoi.floor_order_id', 'ffo_ro.id');
                    });
            })
            ->leftJoin('warehouse_outlets as wo_ffo', 'wo_ffo.id', '=', 'ffo.warehouse_outlet_id')
            ->leftJoin('warehouse_outlets as wo_ro', 'wo_ro.id', '=', 'ffo_ro.warehouse_outlet_id')
            ->leftJoin('items as it', 'ofgri.item_id', '=', 'it.id')
            ->leftJoin('units as un', 'ofgri.unit_id', '=', 'un.id')
            ->leftJoin('users as usr', 'ofgr.created_by', '=', 'usr.id')
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereBetween(DB::raw('DATE(ofgr.receive_date)'), [$monthFrom, $monthTo])
            ->whereRaw('LOWER(TRIM(COALESCE(wo_ffo.name, wo_ro.name))) = ?', [$bucket])
            ->orderBy('it.name')
            ->get([
                'ofgr.id as header_id',
                'ofgr.number',
                'ofgr.receive_date as date',
                DB::raw('COALESCE(wo_ffo.name, wo_ro.name) as warehouse_name'),
                'usr.nama_lengkap as creator_name',
                'it.name as item_name',
                'un.name as unit_name',
                'ofgri.received_qty as qty',
                DB::raw('COALESCE(ffoi.price, 0) as price'),
                DB::raw('(ofgri.received_qty * COALESCE(ffoi.price, 0)) as subtotal'),
            ]);

        foreach ($grRows as $row) {
            $addLine(
                'gr-'.$row->header_id,
                (string) ($row->number ?? '-'),
                (string) $row->date,
                'GR',
                (string) ($row->creator_name ?? ''),
                (string) ($row->warehouse_name ?? $bucket),
                [
                    'name' => $row->item_name,
                    'qty' => $row->qty,
                    'unit' => $row->unit_name,
                    'price' => $row->price,
                    'subtotal' => $row->subtotal,
                ]
            );
        }

        // —— Retail Food ——
        $rfHeaders = DB::table('retail_food as rf')
            ->join('warehouse_outlets as wo', 'wo.id', '=', 'rf.warehouse_outlet_id')
            ->leftJoin('users as usr', 'rf.created_by', '=', 'usr.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereBetween(DB::raw('DATE(rf.transaction_date)'), [$monthFrom, $monthTo])
            ->whereRaw('LOWER(TRIM(wo.name)) = ?', [$bucket])
            ->get([
                'rf.id',
                'rf.retail_number as number',
                'rf.transaction_date as date',
                'rf.total_amount',
                'wo.name as warehouse_name',
                'usr.nama_lengkap as creator_name',
            ]);

        $rfIds = $rfHeaders->pluck('id')->all();
        $rfItemsByHeader = collect();
        if ($rfIds !== [] && Schema::hasTable('retail_food_items')) {
            $rfItemsByHeader = DB::table('retail_food_items')
                ->whereIn('retail_food_id', $rfIds)
                ->orderBy('item_name')
                ->get([
                    'retail_food_id as header_id',
                    'item_name',
                    'unit as unit_name',
                    'qty',
                    DB::raw('COALESCE(price, 0) as price'),
                    DB::raw('COALESCE(subtotal, qty * price, 0) as subtotal'),
                ])
                ->groupBy('header_id');
        }

        foreach ($rfHeaders as $header) {
            $items = $rfItemsByHeader->get($header->id) ?? collect();
            if ($items->isEmpty()) {
                $addLine(
                    'rf-'.$header->id,
                    (string) ($header->number ?? '-'),
                    (string) $header->date,
                    'Retail Food',
                    (string) ($header->creator_name ?? ''),
                    (string) ($header->warehouse_name ?? $bucket),
                    [
                        'name' => 'Total transaksi',
                        'qty' => 1,
                        'unit' => '-',
                        'price' => $header->total_amount,
                        'subtotal' => $header->total_amount,
                    ]
                );
                continue;
            }
            foreach ($items as $item) {
                $addLine(
                    'rf-'.$header->id,
                    (string) ($header->number ?? '-'),
                    (string) $header->date,
                    'Retail Food',
                    (string) ($header->creator_name ?? ''),
                    (string) ($header->warehouse_name ?? $bucket),
                    [
                        'name' => $item->item_name,
                        'qty' => $item->qty,
                        'unit' => $item->unit_name,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                    ]
                );
            }
        }

        // RWS tidak di-list di purchased drill-down (sudah terwakili Retail Food).

        $rows = array_values($grouped);
        foreach ($rows as $row) {
            $row->items = array_values($row->items);
        }
        usort($rows, function ($a, $b) {
            $cmp = strcmp((string) $b->date, (string) $a->date);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $b->number, (string) $a->number);
        });

        return $rows;
    }

    /**
     * @return array<string, float>
     */
    public function purchasedBucketByDate(
        int $outletId,
        string $dateFrom,
        string $dateTo,
        string $bucket
    ): array {
        $map = [];
        foreach ($this->listPurchasedBucketTransactions($outletId, $dateFrom, $dateTo, $bucket) as $txn) {
            $d = substr((string) $txn->date, 0, 10);
            $map[$d] = round(((float) ($map[$d] ?? 0)) + (float) $txn->amount, 2);
        }

        return $map;
    }

    private function normalizePurchaseBudgetBucket(string $bucket): ?string
    {
        $b = strtolower(trim($bucket));
        if (str_ends_with($b, '_purchase')) {
            $b = substr($b, 0, -strlen('_purchase'));
        }

        return in_array($b, ['kitchen', 'bar', 'service'], true) ? $b : null;
    }

    private function hasForecastHeaderForRange(int $outletId, string $dateFrom, string $dateTo): bool
    {
        $months = $this->monthsCovered($dateFrom, $dateTo);
        foreach ($months as $monthStart) {
            $exists = DB::table('outlet_revenue_target_headers')
                ->where('outlet_id', $outletId)
                ->where('target_month', $monthStart)
                ->exists();
            if ($exists) {
                return true;
            }
        }

        return false;
    }

    private function sumForecastRevenue(int $outletId, string $dateFrom, string $dateTo): float
    {
        $months = $this->monthsCovered($dateFrom, $dateTo);
        if ($months === []) {
            return 0.0;
        }

        $headerIds = DB::table('outlet_revenue_target_headers')
            ->where('outlet_id', $outletId)
            ->whereIn('target_month', $months)
            ->pluck('id');

        if ($headerIds->isEmpty()) {
            return 0.0;
        }

        return round((float) DB::table('outlet_revenue_target_details')
            ->whereIn('header_id', $headerIds)
            ->whereDate('forecast_date', '>=', $dateFrom)
            ->whereDate('forecast_date', '<=', $dateTo)
            ->sum('forecast_revenue'), 2);
    }

    /**
     * Monthly budget dari outlet_revenue_target_headers.monthly_target
     * untuk bulan yang diliputi filter.
     */
    private function sumMonthlyRevenueBudget(int $outletId, string $dateFrom, string $dateTo): ?float
    {
        $months = $this->monthsCovered($dateFrom, $dateTo);
        if ($months === []) {
            return null;
        }

        $total = (float) DB::table('outlet_revenue_target_headers')
            ->where('outlet_id', $outletId)
            ->whereIn('target_month', $months)
            ->sum('monthly_target');

        return $total > 0 ? round($total, 2) : null;
    }

    /**
     * Kitchen / Bar / Service purchase from received goods + Retail Food.
     * Received = GSR (serial receive) + outlet GR, by receive_date and warehouse_outlet.
     * Retail Food by warehouse_outlet.
     * RWS tidak dijumlah (double dengan RF: gudang keluar RWS, outlet masuk RF).
     *
     * @return array{
     *   kitchen: float,
     *   bar: float,
     *   service: float,
     *   by_source: array{
     *     kitchen: array{gsr: float, rws: float, rf: float},
     *     bar: array{gsr: float, rws: float, rf: float},
     *     service: array{gsr: float, rws: float, rf: float}
     *   }
     * }
     */
    private function sumPurchasedByBucket(int $outletId, string $dateFrom, string $dateTo): array
    {
        $warehouseBucketById = DB::table('warehouse_outlets')
            ->select('id', 'name')
            ->get()
            ->mapWithKeys(function ($w) {
                return [(int) $w->id => $this->warehouseOutletBucketName($w->name ?? null)];
            })
            ->all();

        $bucketExpr = "CASE
            WHEN LOWER(TRIM(wo.name)) = 'kitchen' THEN 'kitchen'
            WHEN LOWER(TRIM(wo.name)) = 'bar' THEN 'bar'
            WHEN LOWER(TRIM(wo.name)) = 'service' THEN 'service'
            ELSE 'other'
        END";

        $totals = [
            'kitchen' => 0.0,
            'bar' => 0.0,
            'service' => 0.0,
        ];
        $bySource = [
            'kitchen' => ['gsr' => 0.0, 'rws' => 0.0, 'rf' => 0.0],
            'bar' => ['gsr' => 0.0, 'rws' => 0.0, 'rf' => 0.0],
            'service' => ['gsr' => 0.0, 'rws' => 0.0, 'rf' => 0.0],
        ];

        $addBucket = function (string $bucket, float $total, string $source = 'gsr') use (&$totals, &$bySource): void {
            if (! isset($totals[$bucket])) {
                return;
            }
            $totals[$bucket] += $total;
            if (isset($bySource[$bucket][$source])) {
                $bySource[$bucket][$source] += $total;
            } elseif ($source === 'gr') {
                // GR digabung ke GSR di tampilan card (sama pool purchased receive)
                $bySource[$bucket]['gsr'] += $total;
            }
        };

        // GSR — nilai diterima aktual
        if ($this->hasSerialGrTables()) {
            $priceSql = $this->serialGrPriceSql('it');
            $gsrRows = DB::table('outlet_serial_receive_items as si')
                ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->join('warehouse_outlets as wo', 'wo.id', '=', 'si.warehouse_outlet_id')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->where('h.outlet_id', $outletId)
                ->whereBetween(DB::raw('DATE(h.receive_date)'), [$dateFrom, $dateTo])
                ->selectRaw($bucketExpr.' as bucket, SUM(si.qty * ('.$priceSql.')) as total')
                ->groupBy(DB::raw($bucketExpr))
                ->get();

            foreach ($gsrRows as $row) {
                $addBucket((string) $row->bucket, (float) $row->total, 'gsr');
            }
        }

        // Outlet GR — nilai diterima (jika masih dipakai)
        $grBucketExpr = "CASE
            WHEN LOWER(TRIM(COALESCE(wo_ffo.name, wo_ro.name))) = 'kitchen' THEN 'kitchen'
            WHEN LOWER(TRIM(COALESCE(wo_ffo.name, wo_ro.name))) = 'bar' THEN 'bar'
            WHEN LOWER(TRIM(COALESCE(wo_ffo.name, wo_ro.name))) = 'service' THEN 'service'
            ELSE 'other'
        END";

        $grRows = DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as ffoi', function ($join) {
                $join->on('ofgri.item_id', '=', 'ffoi.item_id')
                    ->where(function ($q) {
                        $q->whereColumn('ffoi.floor_order_id', 'do.floor_order_id')
                            ->orWhereColumn('ffoi.floor_order_id', 'ffo_ro.id');
                    });
            })
            ->leftJoin('warehouse_outlets as wo_ffo', 'wo_ffo.id', '=', 'ffo.warehouse_outlet_id')
            ->leftJoin('warehouse_outlets as wo_ro', 'wo_ro.id', '=', 'ffo_ro.warehouse_outlet_id')
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereBetween(DB::raw('DATE(ofgr.receive_date)'), [$dateFrom, $dateTo])
            ->whereRaw('COALESCE(wo_ffo.id, wo_ro.id) IS NOT NULL')
            ->selectRaw($grBucketExpr.' as bucket, SUM(ofgri.received_qty * COALESCE(ffoi.price, 0)) as total')
            ->groupBy(DB::raw($grBucketExpr))
            ->get();

        foreach ($grRows as $row) {
            $addBucket((string) $row->bucket, (float) $row->total, 'gr');
        }

        // Retail Food — pembelian langsung supplier
        $retailFoodRows = DB::table('retail_food as rf')
            ->join('warehouse_outlets as wo', 'wo.id', '=', 'rf.warehouse_outlet_id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereBetween(DB::raw('DATE(rf.transaction_date)'), [$dateFrom, $dateTo])
            ->selectRaw('wo.id as warehouse_outlet_id, SUM(rf.total_amount) as total')
            ->groupBy('wo.id')
            ->get();

        foreach ($retailFoodRows as $rfRow) {
            $bucket = $warehouseBucketById[(int) $rfRow->warehouse_outlet_id] ?? 'other';
            $addBucket($bucket, (float) $rfRow->total, 'rf');
        }

        // RWS tidak dijumlah ke purchased: mirror Retail Food (supplier Justus Group).
        // Kartu RWS standalone tetap ada di summary metrics.

        foreach (['kitchen', 'bar', 'service'] as $b) {
            $bySource[$b] = [
                'gsr' => round($bySource[$b]['gsr'], 2),
                'rws' => 0.0,
                'rf' => round($bySource[$b]['rf'], 2),
            ];
        }

        return [
            'kitchen' => round($totals['kitchen'], 2),
            'bar' => round($totals['bar'], 2),
            'service' => round($totals['service'], 2),
            'by_source' => $bySource,
        ];
    }

    /**
     * RO / Floor Order belum diterima penuh (qty ordered − qty GR − qty GSR), nilai di harga RO.
     * Filter FO by arrival_date full month; penerimaan GSR/GR dihitung tanpa batasan tanggal
     * (supaya RO yang sudah diterima belakangan tidak tetap outstanding).
     *
     * @return array{kitchen: float, bar: float, service: float}
     */
    private function sumOutstandingRoByBucket(int $outletId, string $dateFrom, string $dateTo): array
    {
        $foLines = DB::table('food_floor_orders as ffo')
            ->join('warehouse_outlets as wo', 'wo.id', '=', 'ffo.warehouse_outlet_id')
            ->join('food_floor_order_items as ffoi', 'ffoi.floor_order_id', '=', 'ffo.id')
            ->leftJoin('items as it', 'it.id', '=', 'ffoi.item_id')
            ->where('ffo.id_outlet', $outletId)
            ->whereNotNull('ffo.arrival_date')
            ->whereBetween(DB::raw('DATE(ffo.arrival_date)'), [$dateFrom, $dateTo])
            ->whereNotIn('ffo.status', ['draft', 'rejected'])
            ->whereRaw("LOWER(TRIM(wo.name)) IN ('kitchen', 'bar', 'service')")
            ->select(
                'ffo.id as floor_order_id',
                'ffoi.item_id',
                'ffoi.qty',
                'ffoi.price',
                'ffoi.subtotal',
                'ffoi.unit as fo_unit_name',
                'wo.name as warehouse_name',
                'it.large_unit_id',
                'it.medium_unit_id',
                'it.small_unit_id',
                'it.small_conversion_qty',
                'it.medium_conversion_qty'
            )
            ->get();

        if ($foLines->isEmpty()) {
            return ['kitchen' => 0.0, 'bar' => 0.0, 'service' => 0.0];
        }

        $unitIdByName = DB::table('units')
            ->select('id', 'name')
            ->get()
            ->mapWithKeys(fn ($u) => [strtolower(trim((string) $u->name)) => (int) $u->id])
            ->all();

        foreach ($foLines as $line) {
            $unitKey = strtolower(trim((string) ($line->fo_unit_name ?? '')));
            $line->fo_unit_id = $unitIdByName[$unitKey] ?? null;
        }

        $foIds = $foLines->pluck('floor_order_id')->unique()->values()->all();

        // GR received qty by FO + item (assume same unit as FO line when unit missing)
        $grByKey = DB::table('outlet_food_good_receive_items as gri')
            ->join('outlet_food_good_receives as gr', function ($join) {
                $join->on('gri.outlet_food_good_receive_id', '=', 'gr.id')
                    ->whereNull('gr.deleted_at')
                    ->where('gr.status', 'completed');
            })
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->whereIn('do.floor_order_id', $foIds)
            ->groupBy('do.floor_order_id', 'gri.item_id')
            ->select(
                'do.floor_order_id',
                'gri.item_id',
                DB::raw('SUM(gri.received_qty) as qty_received')
            )
            ->get()
            ->mapWithKeys(fn ($r) => [$r->floor_order_id.'|'.$r->item_id => (float) $r->qty_received])
            ->all();

        // GSR received — convert to small qty first, then to FO unit per line
        $gsrRows = collect();
        if ($this->hasSerialGrTables()) {
            $gsrRows = DB::table('outlet_serial_receive_items as si')
                ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
                ->join('delivery_orders as do', 'si.delivery_order_id', '=', 'do.id')
                ->leftJoin('items as it', 'it.id', '=', 'si.item_id')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->whereIn('do.floor_order_id', $foIds)
                ->select(
                    'do.floor_order_id',
                    'si.item_id',
                    'si.qty',
                    'si.unit_id',
                    'it.large_unit_id',
                    'it.medium_unit_id',
                    'it.small_unit_id',
                    'it.small_conversion_qty',
                    'it.medium_conversion_qty'
                )
                ->get();
        }

        $gsrSmallByKey = [];
        foreach ($gsrRows as $row) {
            $key = $row->floor_order_id.'|'.$row->item_id;
            $gsrSmallByKey[$key] = ($gsrSmallByKey[$key] ?? 0.0) + $this->qtyToSmall(
                (float) $row->qty,
                $row->unit_id !== null ? (int) $row->unit_id : null,
                $row->large_unit_id !== null ? (int) $row->large_unit_id : null,
                $row->medium_unit_id !== null ? (int) $row->medium_unit_id : null,
                (float) ($row->small_conversion_qty ?? 1),
                (float) ($row->medium_conversion_qty ?? 1)
            );
        }

        $kitchen = 0.0;
        $bar = 0.0;
        $service = 0.0;

        foreach ($foLines as $line) {
            $bucket = $this->warehouseOutletBucketName($line->warehouse_name);
            if ($bucket === 'other') {
                continue;
            }

            $key = $line->floor_order_id.'|'.$line->item_id;
            $orderedQty = (float) $line->qty;
            $price = (float) $line->price;
            if ($orderedQty <= 0) {
                continue;
            }

            $receivedQty = (float) ($grByKey[$key] ?? 0);

            $gsrSmall = (float) ($gsrSmallByKey[$key] ?? 0);
            if ($gsrSmall > 0) {
                $receivedQty += $this->qtyFromSmall(
                    $gsrSmall,
                    $line->fo_unit_id !== null ? (int) $line->fo_unit_id : null,
                    $line->large_unit_id !== null ? (int) $line->large_unit_id : null,
                    $line->medium_unit_id !== null ? (int) $line->medium_unit_id : null,
                    (float) ($line->small_conversion_qty ?? 1),
                    (float) ($line->medium_conversion_qty ?? 1)
                );
            }

            $outstandingQty = max(0.0, $orderedQty - $receivedQty);
            if ($outstandingQty <= 0) {
                continue;
            }

            // Cap at subtotal to avoid float overshoot
            $outstandingValue = min((float) $line->subtotal, $outstandingQty * $price);
            if ($bucket === 'kitchen') {
                $kitchen += $outstandingValue;
            } elseif ($bucket === 'bar') {
                $bar += $outstandingValue;
            } else {
                $service += $outstandingValue;
            }
        }

        return [
            'kitchen' => round($kitchen, 2),
            'bar' => round($bar, 2),
            'service' => round($service, 2),
        ];
    }

    private function warehouseOutletBucketName(?string $name): string
    {
        $n = strtolower(trim((string) $name));
        if ($n === 'kitchen') {
            return 'kitchen';
        }
        if ($n === 'bar') {
            return 'bar';
        }
        if ($n === 'service') {
            return 'service';
        }

        return 'other';
    }

    /**
     * RWS memakai warehouses (Main Store / MK*), bukan warehouse_outlets.
     * Default ke Kitchen; nama bar → Bar; nama service → Service.
     */
    private function rwsPurchaseBucketName(?string $warehouseName): string
    {
        $n = strtolower(trim((string) $warehouseName));
        if ($n !== '' && (str_contains($n, 'service') || $n === 'svc')) {
            return 'service';
        }
        if ($n !== '' && str_contains($n, 'bar')) {
            return 'bar';
        }

        return 'kitchen';
    }

    private function qtyToSmall(
        float $qty,
        ?int $unitId,
        ?int $largeUnitId,
        ?int $mediumUnitId,
        float $smallConv,
        float $mediumConv
    ): float {
        $smallConv = $smallConv > 0 ? $smallConv : 1.0;
        $mediumConv = $mediumConv > 0 ? $mediumConv : 1.0;
        if ($unitId !== null && $largeUnitId !== null && $unitId === $largeUnitId) {
            return $qty * $smallConv * $mediumConv;
        }
        if ($unitId !== null && $mediumUnitId !== null && $unitId === $mediumUnitId) {
            return $qty * $smallConv;
        }

        return $qty;
    }

    private function qtyFromSmall(
        float $qtySmall,
        ?int $unitId,
        ?int $largeUnitId,
        ?int $mediumUnitId,
        float $smallConv,
        float $mediumConv
    ): float {
        $smallConv = $smallConv > 0 ? $smallConv : 1.0;
        $mediumConv = $mediumConv > 0 ? $mediumConv : 1.0;
        if ($unitId !== null && $largeUnitId !== null && $unitId === $largeUnitId) {
            return $qtySmall / ($smallConv * $mediumConv);
        }
        if ($unitId !== null && $mediumUnitId !== null && $unitId === $mediumUnitId) {
            return $qtySmall / $smallConv;
        }

        return $qtySmall;
    }

    /**
     * @deprecated Use sumPurchasedByBucket()
     *
     * @return array{kitchen: float, bar: float, service: float}
     */
    private function sumRoPurchasedByBucket(int $outletId, string $dateFrom, string $dateTo): array
    {
        return $this->sumPurchasedByBucket($outletId, $dateFrom, $dateTo);
    }

    /**
     * @return list<string> Y-m-01
     */
    private function monthsCovered(string $dateFrom, string $dateTo): array
    {
        $months = [];
        $cursor = Carbon::parse($dateFrom)->startOfMonth();
        $end = Carbon::parse($dateTo)->startOfMonth();
        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m-01');
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * @return array{
     *   total: float,
     *   count: int,
     *   cover: float,
     *   avg_pax: float|null,
     *   avg_check: float|null,
     *   discount: float,
     *   discount_count: int,
     *   gross_before_discount: float
     * }
     */
    public function sumRevenue(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $empty = [
            'total' => 0.0,
            'count' => 0,
            'cover' => 0.0,
            'avg_pax' => null,
            'avg_check' => null,
            'discount' => 0.0,
            'discount_count' => 0,
            'gross_before_discount' => 0.0,
        ];

        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return $empty;
        }

        $row = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('
                COALESCE(SUM(grand_total), 0) as total,
                COUNT(*) as cnt,
                COALESCE(SUM(pax), 0) as cover,
                COALESCE(SUM(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)), 0) as discount,
                SUM(CASE WHEN (COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) > 0 THEN 1 ELSE 0 END) as discount_count,
                COALESCE(SUM(COALESCE(total, 0)), 0) as gross_before_discount
            ')
            ->first();

        $total = round((float) ($row->total ?? 0), 2);
        $count = (int) ($row->cnt ?? 0);
        $cover = round((float) ($row->cover ?? 0), 2);
        $discount = round((float) ($row->discount ?? 0), 2);
        $gross = round((float) ($row->gross_before_discount ?? 0), 2);

        return [
            'total' => $total,
            'count' => $count,
            'cover' => $cover,
            'avg_pax' => $count > 0 ? round($cover / $count, 2) : null,
            'avg_check' => $cover > 0 ? round($total / $cover) : null,
            'discount' => $discount,
            'discount_count' => (int) ($row->discount_count ?? 0),
            'gross_before_discount' => $gross,
        ];
    }

    /**
     * Sales before discount untuk % COGS — sama Cost Report / Item Engineering: Σ(qty × price).
     */
    private function sumSalesBeforeDiscountForCogs(?string $qrCode, string $dateFrom, string $dateTo): float
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '' || ! Schema::hasTable('order_items')) {
            return 0.0;
        }

        $total = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.kode_outlet', $qrCode)
            ->whereDate('orders.created_at', '>=', $dateFrom)
            ->whereDate('orders.created_at', '<=', $dateTo)
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.grand_total', '>', 0)
            ->selectRaw('COALESCE(SUM(order_items.qty * order_items.price), 0) as total_sales')
            ->value('total_sales');

        return round((float) ($total ?? 0), 2);
    }

    /**
     * Manual discount by reason type (Compliment / Guest Satisfaction).
     * Bill = orders.total (nilai bill sebelum discount).
     *
     * @return array{discount: float, bill: float, count: int}
     */
    public function sumManualDiscountByType(?string $qrCode, string $dateFrom, string $dateTo, string $type): array
    {
        $empty = ['discount' => 0.0, 'bill' => 0.0, 'count' => 0];
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return $empty;
        }

        $query = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('manual_discount_reason')
            ->where('manual_discount_reason', '!=', '')
            ->whereRaw('(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) > 0');

        $this->applyManualDiscountReasonFilter($query, $type);

        $row = $query->selectRaw('
                COALESCE(SUM(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)), 0) as discount,
                COALESCE(SUM(COALESCE(total, 0)), 0) as bill,
                COUNT(*) as cnt
            ')
            ->first();

        return [
            'discount' => round((float) ($row->discount ?? 0), 2),
            'bill' => round((float) ($row->bill ?? 0), 2),
            'count' => (int) ($row->cnt ?? 0),
        ];
    }

    /**
     * Apply reason filter aligned with Sales Outlet Dashboard categorization.
     */
    public function applyManualDiscountReasonFilter($query, string $type): void
    {
        if ($type === 'compliment') {
            $query->whereRaw('LOWER(manual_discount_reason) LIKE ?', ['%compliment%']);

            return;
        }

        // guest_satisfaction: reason mengandung guest (incl. typo), exclude compliment
        $query->where(function ($q) {
            $q->whereRaw('LOWER(manual_discount_reason) LIKE ?', ['%guest satisfaction%'])
                ->orWhereRaw('LOWER(manual_discount_reason) LIKE ?', ['%guest satic%'])
                ->orWhereRaw('LOWER(manual_discount_reason) LIKE ?', ['%guest statis%'])
                ->orWhere(function ($qq) {
                    $qq->whereRaw('LOWER(manual_discount_reason) LIKE ?', ['%guest%'])
                        ->whereRaw('LOWER(manual_discount_reason) NOT LIKE ?', ['%compliment%']);
                });
        });
    }

    /**
     * Pembayaran OFFICER_CHECK dari order_payment.
     *
     * @return array{amount: float, bill: float, count: int}
     */
    public function sumOfficerCheck(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $empty = ['amount' => 0.0, 'bill' => 0.0, 'count' => 0];
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return $empty;
        }

        $row = DB::table('order_payment as op')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->where('o.kode_outlet', $qrCode)
            ->whereDate('o.created_at', '>=', $dateFrom)
            ->whereDate('o.created_at', '<=', $dateTo)
            ->where('o.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('op.payment_code', 'OFFICER_CHECK')
                    ->orWhere('op.payment_type', 'OFFICER_CHECK');
            })
            ->selectRaw('
                COALESCE(SUM(op.amount), 0) as amount,
                COUNT(*) as cnt,
                COUNT(DISTINCT o.id) as bill_count
            ')
            ->first();

        return [
            'amount' => round((float) ($row->amount ?? 0), 2),
            'bill' => round((float) ($row->amount ?? 0), 2),
            'count' => (int) ($row->cnt ?? 0),
        ];
    }

    /**
     * Member activity outlet-scoped:
     * - bills/revenue dari orders.member_id
     * - Point Earn / Redeem dari member_apps_* (link via orders.id = reference_id)
     *
     * @return array{
     *   member_bills: int,
     *   member_revenue: float,
     *   top_up_value: float,
     *   top_up_count: int,
     *   top_up_points: float,
     *   redeem_value: float,
     *   redeem_count: int,
     *   redeem_points: float,
     *   source: string
     * }
     */
    public function sumMemberActivity(?string $qrCode, ?string $outletName, string $dateFrom, string $dateTo): array
    {
        $empty = [
            'member_bills' => 0,
            'member_revenue' => 0.0,
            'top_up_value' => 0.0,
            'top_up_count' => 0,
            'top_up_points' => 0.0,
            'redeem_value' => 0.0,
            'redeem_count' => 0,
            'redeem_points' => 0.0,
            'source' => 'member_apps',
        ];

        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return $empty;
        }

        $memberRow = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->selectRaw('COUNT(*) as bills, COALESCE(SUM(grand_total), 0) as revenue')
            ->first();

        $empty['member_bills'] = (int) ($memberRow->bills ?? 0);
        $empty['member_revenue'] = round((float) ($memberRow->revenue ?? 0), 2);

        $points = $this->sumMemberAppsPointsByOutlet($qrCode, $dateFrom, $dateTo);
        $empty['top_up_value'] = $points['earn_bill_amount'];
        $empty['top_up_count'] = $points['earn_count'];
        $empty['top_up_points'] = $points['earn_points'];
        $empty['redeem_value'] = $points['redeem_value'];
        $empty['redeem_count'] = $points['redeem_count'];
        $empty['redeem_points'] = $points['redeem_points'];

        return $empty;
    }

    /**
     * Point earn/redeem member apps, di-scope ke outlet via orders.id.
     *
     * @return array{
     *   earn_count: int,
     *   earn_points: float,
     *   earn_bill_amount: float,
     *   redeem_count: int,
     *   redeem_points: float,
     *   redeem_value: float
     * }
     */
    public function sumMemberAppsPointsByOutlet(string $qrCode, string $dateFrom, string $dateTo): array
    {
        $result = [
            'earn_count' => 0,
            'earn_points' => 0.0,
            'earn_bill_amount' => 0.0,
            'redeem_count' => 0,
            'redeem_points' => 0.0,
            'redeem_value' => 0.0,
        ];

        if (! Schema::hasTable('member_apps_point_transactions')) {
            return $result;
        }

        $orderIds = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($orderIds === []) {
            return $result;
        }

        foreach (array_chunk($orderIds, 500) as $chunk) {
            $earn = DB::table('member_apps_point_transactions')
                ->whereIn('reference_id', $chunk)
                ->where('transaction_type', 'earn')
                ->whereDate('transaction_date', '>=', $dateFrom)
                ->whereDate('transaction_date', '<=', $dateTo)
                ->selectRaw('
                    COUNT(*) as cnt,
                    COALESCE(SUM(point_amount), 0) as points,
                    COALESCE(SUM(transaction_amount), 0) as bill_amount
                ')
                ->first();

            $result['earn_count'] += (int) ($earn->cnt ?? 0);
            $result['earn_points'] += (float) ($earn->points ?? 0);
            $result['earn_bill_amount'] += (float) ($earn->bill_amount ?? 0);

            if (Schema::hasTable('member_apps_point_redemptions')) {
                $redeem = DB::table('member_apps_point_redemptions')
                    ->where('status', 'completed')
                    ->whereDate('redemption_date', '>=', $dateFrom)
                    ->whereDate('redemption_date', '<=', $dateTo)
                    ->whereIn(DB::raw("SUBSTRING_INDEX(reference_id, '|', -1)"), $chunk)
                    ->selectRaw('
                        COUNT(*) as cnt,
                        COALESCE(SUM(point_amount), 0) as points,
                        COALESCE(SUM(COALESCE(product_price, cash_value, 0)), 0) as value
                    ')
                    ->first();

                $result['redeem_count'] += (int) ($redeem->cnt ?? 0);
                $result['redeem_points'] += (float) ($redeem->points ?? 0);
                $result['redeem_value'] += (float) ($redeem->value ?? 0);
            }
        }

        $result['earn_points'] = round($result['earn_points'], 2);
        $result['earn_bill_amount'] = round($result['earn_bill_amount'], 2);
        $result['redeem_points'] = round($result['redeem_points'], 2);
        $result['redeem_value'] = round($result['redeem_value'], 2);

        return $result;
    }

    /**
     * @return array<string, float>
     */
    public function memberAppsEarnByDate(string $qrCode, string $dateFrom, string $dateTo): array
    {
        return $this->memberAppsMetricByDate($qrCode, $dateFrom, $dateTo, 'earn');
    }

    /**
     * @return array<string, float>
     */
    public function memberAppsRedeemByDate(string $qrCode, string $dateFrom, string $dateTo): array
    {
        return $this->memberAppsMetricByDate($qrCode, $dateFrom, $dateTo, 'redeem');
    }

    /**
     * @return array<string, float>
     */
    private function memberAppsMetricByDate(string $qrCode, string $dateFrom, string $dateTo, string $kind): array
    {
        $orderIds = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($orderIds === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($orderIds, 500) as $chunk) {
            if ($kind === 'earn') {
                $rows = DB::table('member_apps_point_transactions')
                    ->whereIn('reference_id', $chunk)
                    ->where('transaction_type', 'earn')
                    ->whereDate('transaction_date', '>=', $dateFrom)
                    ->whereDate('transaction_date', '<=', $dateTo)
                    ->selectRaw('DATE(transaction_date) as d, SUM(COALESCE(transaction_amount, 0)) as total')
                    ->groupBy(DB::raw('DATE(transaction_date)'))
                    ->get();
            } else {
                $rows = DB::table('member_apps_point_redemptions')
                    ->where('status', 'completed')
                    ->whereDate('redemption_date', '>=', $dateFrom)
                    ->whereDate('redemption_date', '<=', $dateTo)
                    ->whereIn(DB::raw("SUBSTRING_INDEX(reference_id, '|', -1)"), $chunk)
                    ->selectRaw('DATE(redemption_date) as d, SUM(COALESCE(product_price, cash_value, 0)) as total')
                    ->groupBy(DB::raw('DATE(redemption_date)'))
                    ->get();
            }

            foreach ($rows as $row) {
                $d = (string) $row->d;
                $map[$d] = ($map[$d] ?? 0) + (float) $row->total;
            }
        }

        return $map;
    }

    /**
     * Kept for optional CRM fallback (may be unreachable).
     *
     * @return array{
     *   available: bool,
     *   top_up_value: float,
     *   top_up_count: int,
     *   top_up_points: float,
     *   redeem_value: float,
     *   redeem_count: int,
     *   redeem_points: float
     * }
     */
    private function sumCrmPointActivity(?string $outletName, string $dateFrom, string $dateTo): array
    {
        $empty = [
            'available' => false,
            'top_up_value' => 0.0,
            'top_up_count' => 0,
            'top_up_points' => 0.0,
            'redeem_value' => 0.0,
            'redeem_count' => 0,
            'redeem_points' => 0.0,
        ];

        $cabangId = $this->resolveCabangId($outletName);
        if (! $cabangId) {
            return $empty;
        }

        try {
            $row = DB::connection('mysql_second')
                ->table('point')
                ->where('cabang_id', $cabangId)
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo)
                ->selectRaw('
                    SUM(CASE WHEN type = "1" THEN 1 ELSE 0 END) as top_up_count,
                    SUM(CASE WHEN type = "2" THEN 1 ELSE 0 END) as redeem_count,
                    SUM(CASE WHEN type = "1" THEN point ELSE 0 END) as top_up_points,
                    SUM(CASE WHEN type = "2" THEN point ELSE 0 END) as redeem_points,
                    SUM(CASE WHEN type = "1" THEN jml_trans ELSE 0 END) as top_up_value,
                    SUM(CASE WHEN type = "2" THEN jml_trans ELSE 0 END) as redeem_value
                ')
                ->first();

            return [
                'available' => true,
                'top_up_value' => round((float) ($row->top_up_value ?? 0), 2),
                'top_up_count' => (int) ($row->top_up_count ?? 0),
                'top_up_points' => round((float) ($row->top_up_points ?? 0), 2),
                'redeem_value' => round((float) ($row->redeem_value ?? 0), 2),
                'redeem_count' => (int) ($row->redeem_count ?? 0),
                'redeem_points' => round((float) ($row->redeem_points ?? 0), 2),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    private function resolveCabangId(?string $outletName): ?int
    {
        $name = trim((string) $outletName);
        if ($name === '') {
            return null;
        }

        try {
            $exact = DB::connection('mysql_second')
                ->table('cabangs')
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                ->value('id');
            if ($exact) {
                return (int) $exact;
            }

            $fuzzy = DB::connection('mysql_second')
                ->table('cabangs')
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%'])
                ->orderBy('id')
                ->value('id');

            return $fuzzy ? (int) $fuzzy : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Metode pembayaran dari order_payment (periode filter).
     *
     * @return list<array{payment_code: string, payment_type: string|null, amount: float, count: int, pct: float|null}>
     */
    public function sumPaymentMethods(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return [];
        }

        $rows = DB::table('order_payment as op')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->where('o.kode_outlet', $qrCode)
            ->whereDate('o.created_at', '>=', $dateFrom)
            ->whereDate('o.created_at', '<=', $dateTo)
            ->where('o.status', '!=', 'cancelled')
            ->where('o.grand_total', '>', 0)
            ->groupBy('op.payment_code', 'op.payment_type')
            ->orderByDesc(DB::raw('SUM(op.amount)'))
            ->selectRaw("
                COALESCE(NULLIF(TRIM(op.payment_code), ''), 'Other') as payment_code,
                NULLIF(TRIM(op.payment_type), '') as payment_type,
                COALESCE(SUM(op.amount), 0) as amount,
                COUNT(*) as cnt
            ")
            ->get();

        $grand = 0.0;
        foreach ($rows as $row) {
            $grand += (float) $row->amount;
        }

        $result = [];
        foreach ($rows as $row) {
            $amount = round((float) $row->amount, 2);
            $result[] = [
                'payment_code' => (string) $row->payment_code,
                'payment_type' => $row->payment_type !== null ? (string) $row->payment_type : null,
                'amount' => $amount,
                'count' => (int) $row->cnt,
                'pct' => $grand > 0 ? round(($amount / $grand) * 100, 1) : null,
            ];
        }

        return $result;
    }

    /**
     * Food GR (RO/FO) + Serial GSR.
     *
     * @return array{total: float, count: int, gr_total: float, gsr_total: float}
     */
    public function sumGsrRo(int $outletId, string $dateFrom, string $dateTo): array
    {
        $grTotal = (float) DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as ffoi', function ($join) {
                $join->on('ofgri.item_id', '=', 'ffoi.item_id')
                    ->where(function ($q) {
                        $q->whereColumn('ffoi.floor_order_id', 'do.floor_order_id')
                            ->orWhereColumn('ffoi.floor_order_id', 'ffo_ro.id');
                    });
            })
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereDate('ofgr.receive_date', '>=', $dateFrom)
            ->whereDate('ofgr.receive_date', '<=', $dateTo)
            ->sum(DB::raw('ofgri.received_qty * COALESCE(ffoi.price, 0)'));

        $grCount = (int) DB::table('outlet_food_good_receives as ofgr')
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereDate('ofgr.receive_date', '>=', $dateFrom)
            ->whereDate('ofgr.receive_date', '<=', $dateTo)
            ->count('ofgr.id');

        $gsrTotal = 0.0;
        $gsrCount = 0;
        if ($this->hasSerialGrTables()) {
            $priceSql = $this->serialGrPriceSql('it');
            $gsrTotal = (float) DB::table('outlet_serial_receive_items as si')
                ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->where('h.outlet_id', $outletId)
                ->whereDate('h.receive_date', '>=', $dateFrom)
                ->whereDate('h.receive_date', '<=', $dateTo)
                ->sum(DB::raw("si.qty * ({$priceSql})"));

            $gsrCount = (int) DB::table('outlet_serial_receive_headers as h')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->where('h.outlet_id', $outletId)
                ->whereDate('h.receive_date', '>=', $dateFrom)
                ->whereDate('h.receive_date', '<=', $dateTo)
                ->count('h.id');
        }

        return [
            'total' => round($grTotal + $gsrTotal, 2),
            'count' => $grCount + $gsrCount,
            'gr_total' => round($grTotal, 2),
            'gsr_total' => round($gsrTotal, 2),
        ];
    }

    /**
     * @return array{total: float, count: int}
     */
    public function sumRws(int $outletId, string $dateFrom, string $dateTo): array
    {
        $q = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->where('c.id_outlet', $outletId)
            ->whereDate('rws.sale_date', '>=', $dateFrom)
            ->whereDate('rws.sale_date', '<=', $dateTo);

        return [
            'total' => round((float) (clone $q)->sum(DB::raw('COALESCE(rws.total_amount, 0)')), 2),
            'count' => (int) (clone $q)->count('rws.id'),
        ];
    }

    /**
     * @return array{
     *   total: float,
     *   count: int,
     *   cash_total: float,
     *   cash_count: int,
     *   contra_bon_total: float,
     *   contra_bon_count: int
     * }
     */
    public function sumRetailFood(int $outletId, string $dateFrom, string $dateTo): array
    {
        return $this->sumRetailByPaymentMethod('retail_food', $outletId, $dateFrom, $dateTo);
    }

    /**
     * Retail Food ke supplier Justus Group / Yuditama (mirror RWS gudang → outlet).
     *
     * @return array{total: float, count: int}
     */
    public function sumRetailFoodJustusGroup(int $outletId, string $dateFrom, string $dateTo): array
    {
        $row = DB::table('retail_food as rf')
            ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereDate('rf.transaction_date', '>=', $dateFrom)
            ->whereDate('rf.transaction_date', '<=', $dateTo)
            ->where(function ($q) {
                $q->where('s.name', 'like', '%Justus%')
                    ->orWhere('s.name', 'like', '%Yuditama%');
            })
            ->selectRaw('COALESCE(SUM(rf.total_amount), 0) as total, COUNT(*) as cnt')
            ->first();

        return [
            'total' => round((float) ($row->total ?? 0), 2),
            'count' => (int) ($row->cnt ?? 0),
        ];
    }

    /**
     * @return array{
     *   total: float,
     *   count: int,
     *   cash_total: float,
     *   cash_count: int,
     *   contra_bon_total: float,
     *   contra_bon_count: int
     * }
     */
    public function sumRetailNonFood(int $outletId, string $dateFrom, string $dateTo): array
    {
        return $this->sumRetailByPaymentMethod('retail_non_food', $outletId, $dateFrom, $dateTo);
    }

    /**
     * @return array{
     *   total: float,
     *   count: int,
     *   cash_total: float,
     *   cash_count: int,
     *   contra_bon_total: float,
     *   contra_bon_count: int
     * }
     */
    private function sumRetailByPaymentMethod(string $table, int $outletId, string $dateFrom, string $dateTo): array
    {
        $row = DB::table($table)
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->selectRaw("
                COALESCE(SUM(total_amount), 0) as total,
                COUNT(*) as cnt,
                COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash_total,
                SUM(CASE WHEN payment_method = 'cash' THEN 1 ELSE 0 END) as cash_count,
                COALESCE(SUM(CASE WHEN payment_method = 'contra_bon' THEN total_amount ELSE 0 END), 0) as contra_bon_total,
                SUM(CASE WHEN payment_method = 'contra_bon' THEN 1 ELSE 0 END) as contra_bon_count
            ")
            ->first();

        return [
            'total' => round((float) ($row->total ?? 0), 2),
            'count' => (int) ($row->cnt ?? 0),
            'cash_total' => round((float) ($row->cash_total ?? 0), 2),
            'cash_count' => (int) ($row->cash_count ?? 0),
            'contra_bon_total' => round((float) ($row->contra_bon_total ?? 0), 2),
            'contra_bon_count' => (int) ($row->contra_bon_count ?? 0),
        ];
    }

    /**
     * Petty cash = RF cash + RNF cash.
     *
     * @return array<string, float>
     */
    public function pettyCashByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        return $this->mergeDateMaps(
            $this->retailCashByDate('retail_food', $outletId, $dateFrom, $dateTo),
            $this->retailCashByDate('retail_non_food', $outletId, $dateFrom, $dateTo)
        );
    }

    /**
     * @return array<string, float>
     */
    private function retailCashByDate(string $table, int $outletId, string $dateFrom, string $dateTo): array
    {
        return DB::table($table)
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->where('payment_method', 'cash')
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->selectRaw('DATE(transaction_date) as d, SUM(total_amount) as total')
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildTrend(int $outletId, ?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $dates = $this->dateRange($dateFrom, $dateTo);
        $revenueByDate = $this->revenueByDate($qrCode, $dateFrom, $dateTo);
        $grByDate = $this->foodGrByDate($outletId, $dateFrom, $dateTo);
        $gsrByDate = $this->gsrByDate($outletId, $dateFrom, $dateTo);
        $rwsByDate = $this->rwsByDate($outletId, $dateFrom, $dateTo);
        $rfByDate = $this->retailFoodByDate($outletId, $dateFrom, $dateTo);
        $rnfByDate = $this->retailNonFoodByDate($outletId, $dateFrom, $dateTo);

        $rows = [];
        foreach ($dates as $date) {
            $gsrRo = (float) ($grByDate[$date] ?? 0) + (float) ($gsrByDate[$date] ?? 0);
            $rws = (float) ($rwsByDate[$date] ?? 0);
            $rf = (float) ($rfByDate[$date] ?? 0);
            $rnf = (float) ($rnfByDate[$date] ?? 0);
            // Spend tanpa RWS: RWS = outflow gudang, inlet outlet sudah di Retail Food.
            $spend = $gsrRo + $rf + $rnf;
            $revenue = (float) ($revenueByDate[$date] ?? 0);
            $rows[] = [
                'date' => $date,
                'revenue' => $revenue,
                'gsr_ro' => round($gsrRo, 2),
                'rws' => round($rws, 2),
                'retail_food' => round($rf, 2),
                'retail_non_food' => round($rnf, 2),
                'total_spend' => round($spend, 2),
            ];
        }

        return $rows;
    }

    /**
     * Rata-rata revenue harian, dikelompokkan per hari (Senin–Minggu).
     * Hanya hari yang ada penjualan (sama sumber revenueByDate).
     *
     * @return array{
     *   avg_daily: ?float,
     *   day_count: int,
     *   total_revenue: float,
     *   by_weekday: list<array{
     *     dow: int,
     *     day_name: string,
     *     day_count: int,
     *     total: float,
     *     average: ?float,
     *     dates: list<array{date: string, revenue: float, is_weekend: bool}>
     *   }>
     * }
     */
    public function buildAvgDailyRevenueByWeekday(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $byDate = $this->revenueByDate($qrCode, $dateFrom, $dateTo);
        $dayNames = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];
        // Urutan bisnis: Senin → Minggu
        $order = [1, 2, 3, 4, 5, 6, 0];

        $buckets = [];
        foreach ($order as $dow) {
            $buckets[$dow] = [
                'dow' => $dow,
                'day_name' => $dayNames[$dow],
                'day_count' => 0,
                'total' => 0.0,
                'average' => null,
                'dates' => [],
            ];
        }

        ksort($byDate);
        foreach ($byDate as $date => $revenue) {
            $carbon = Carbon::parse((string) $date);
            $dow = (int) $carbon->dayOfWeek;
            if (! isset($buckets[$dow])) {
                continue;
            }
            $amount = round((float) $revenue, 2);
            $buckets[$dow]['dates'][] = [
                'date' => (string) $date,
                'revenue' => $amount,
                'is_weekend' => in_array($dow, [0, 6], true),
            ];
            $buckets[$dow]['total'] += $amount;
            $buckets[$dow]['day_count']++;
        }

        $byWeekday = [];
        foreach ($order as $dow) {
            $row = $buckets[$dow];
            $row['total'] = round((float) $row['total'], 2);
            $row['average'] = $row['day_count'] > 0
                ? round($row['total'] / $row['day_count'], 2)
                : null;
            $byWeekday[] = $row;
        }

        $dayCount = count($byDate);
        $totalRevenue = round(array_sum(array_map('floatval', $byDate)), 2);

        return [
            'avg_daily' => $dayCount > 0 ? round($totalRevenue / $dayCount, 2) : null,
            'day_count' => $dayCount,
            'total_revenue' => $totalRevenue,
            'by_weekday' => $byWeekday,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function revenueByDate(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return [];
        }

        return DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('DATE(created_at) as d, SUM(grand_total) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function foodGrByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        return DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as ffoi', function ($join) {
                $join->on('ofgri.item_id', '=', 'ffoi.item_id')
                    ->where(function ($q) {
                        $q->whereColumn('ffoi.floor_order_id', 'do.floor_order_id')
                            ->orWhereColumn('ffoi.floor_order_id', 'ffo_ro.id');
                    });
            })
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereDate('ofgr.receive_date', '>=', $dateFrom)
            ->whereDate('ofgr.receive_date', '<=', $dateTo)
            ->selectRaw('DATE(ofgr.receive_date) as d, SUM(ofgri.received_qty * COALESCE(ffoi.price, 0)) as total')
            ->groupBy(DB::raw('DATE(ofgr.receive_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function gsrByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        if (! $this->hasSerialGrTables()) {
            return [];
        }

        $priceSql = $this->serialGrPriceSql('it');

        return DB::table('outlet_serial_receive_items as si')
            ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
            ->join('items as it', 'si.item_id', '=', 'it.id')
            ->whereNull('h.deleted_at')
            ->where('h.status', 'completed')
            ->where('h.outlet_id', $outletId)
            ->whereDate('h.receive_date', '>=', $dateFrom)
            ->whereDate('h.receive_date', '<=', $dateTo)
            ->selectRaw("DATE(h.receive_date) as d, SUM(si.qty * ({$priceSql})) as total")
            ->groupBy(DB::raw('DATE(h.receive_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function rwsByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        return DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->where('c.id_outlet', $outletId)
            ->whereDate('rws.sale_date', '>=', $dateFrom)
            ->whereDate('rws.sale_date', '<=', $dateTo)
            ->selectRaw('DATE(rws.sale_date) as d, SUM(COALESCE(rws.total_amount, 0)) as total')
            ->groupBy(DB::raw('DATE(rws.sale_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function retailFoodByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        return DB::table('retail_food')
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->selectRaw('DATE(transaction_date) as d, SUM(total_amount) as total')
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function retailNonFoodByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        return DB::table('retail_non_food')
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->selectRaw('DATE(transaction_date) as d, SUM(total_amount) as total')
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return list<array{date: string, amount: float}>
     */
    public function cardTrend(int $outletId, ?string $qrCode, string $dateFrom, string $dateTo, string $type): array
    {
        $dates = $this->dateRange($dateFrom, $dateTo);
        $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');

        $map = match ($type) {
            'revenue' => $this->revenueByDate($qrCode, $dateFrom, $dateTo),
            'discount' => $this->discountByDate($qrCode, $dateFrom, $dateTo),
            'discount_compliment' => $this->manualDiscountByTypeByDate($qrCode, $dateFrom, $dateTo, 'compliment'),
            'discount_guest_satisfaction' => $this->manualDiscountByTypeByDate($qrCode, $dateFrom, $dateTo, 'guest_satisfaction'),
            'officer_check' => $this->officerCheckByDate($qrCode, $dateFrom, $dateTo),
            'member_top_up' => $qrCode
                ? $this->memberAppsEarnByDate((string) $qrCode, $dateFrom, $dateTo)
                : [],
            'member_redeem' => $qrCode
                ? $this->memberAppsRedeemByDate((string) $qrCode, $dateFrom, $dateTo)
                : [],
            'gsr_ro' => $this->mergeDateMaps(
                $this->foodGrByDate($outletId, $dateFrom, $dateTo),
                $this->gsrByDate($outletId, $dateFrom, $dateTo)
            ),
            'rws' => $this->rwsByDate($outletId, $dateFrom, $dateTo),
            'retail_food' => $this->retailFoodByDate($outletId, $dateFrom, $dateTo),
            'retail_non_food' => $this->retailNonFoodByDate($outletId, $dateFrom, $dateTo),
            'petty_cash' => $this->pettyCashByDate($outletId, $dateFrom, $dateTo),
            'stock_cut' => $this->stockCutByDate($outletId, $dateFrom, $dateTo),
            'category_cost' => $this->categoryCostByDate($outletId, $dateFrom, $dateTo),
            'mcs_purchase' => $this->mcsPurchaseByDate($outletId, $dateFrom, $dateTo),
            'kitchen_purchase' => $this->purchasedBucketByDate($outletId, $dateFrom, $dateTo, 'kitchen'),
            'bar_purchase' => $this->purchasedBucketByDate($outletId, $dateFrom, $dateTo, 'bar'),
            'service_purchase' => $this->purchasedBucketByDate($outletId, $dateFrom, $dateTo, 'service'),
            'outlet_city_ledger' => $this->outletCityLedgerByDate($qrCode, $dateFrom, $dateTo),
            'total_spend' => $this->mergeDateMaps(
                $this->foodGrByDate($outletId, $dateFrom, $dateTo),
                $this->gsrByDate($outletId, $dateFrom, $dateTo),
                $this->rwsByDate($outletId, $dateFrom, $dateTo),
                $this->retailFoodByDate($outletId, $dateFrom, $dateTo),
                $this->retailNonFoodByDate($outletId, $dateFrom, $dateTo)
            ),
            default => [],
        };

        return array_map(fn ($d) => [
            'date' => $d,
            'amount' => round((float) ($map[$d] ?? 0), 2),
        ], $dates);
    }

    /**
     * @return array<string, float>
     */
    public function discountByDate(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return [];
        }

        return DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('DATE(created_at) as d, SUM(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function manualDiscountByTypeByDate(?string $qrCode, string $dateFrom, string $dateTo, string $type): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return [];
        }

        $query = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('manual_discount_reason')
            ->where('manual_discount_reason', '!=', '')
            ->whereRaw('(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) > 0');

        $this->applyManualDiscountReasonFilter($query, $type);

        return $query
            ->selectRaw('DATE(created_at) as d, SUM(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function officerCheckByDate(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return [];
        }

        return DB::table('order_payment as op')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->where('o.kode_outlet', $qrCode)
            ->whereDate('o.created_at', '>=', $dateFrom)
            ->whereDate('o.created_at', '<=', $dateTo)
            ->where('o.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('op.payment_code', 'OFFICER_CHECK')
                    ->orWhere('op.payment_type', 'OFFICER_CHECK');
            })
            ->selectRaw('DATE(o.created_at) as d, SUM(op.amount) as total')
            ->groupBy(DB::raw('DATE(o.created_at)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function posRedeemByDate(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return [];
        }

        return DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('DATE(created_at) as d, SUM(COALESCE(redeem_amount, 0)) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function crmPointByDate(?string $outletName, string $dateFrom, string $dateTo, string $type): array
    {
        $cabangId = $this->resolveCabangId($outletName);
        if (! $cabangId) {
            return [];
        }

        try {
            return DB::connection('mysql_second')
                ->table('point')
                ->where('cabang_id', $cabangId)
                ->where('type', $type)
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo)
                ->selectRaw('DATE(created_at) as d, SUM(COALESCE(jml_trans, 0)) as total')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->pluck('total', 'd')
                ->map(fn ($v) => (float) $v)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Prefer CRM redeem trend; fallback POS redeem_amount.
     *
     * @return array<string, float>
     */
    public function memberRedeemByDate(?string $qrCode, ?string $outletName, string $dateFrom, string $dateTo): array
    {
        $crm = $this->crmPointByDate($outletName, $dateFrom, $dateTo, '2');
        if ($crm !== []) {
            return $crm;
        }

        return $this->posRedeemByDate($qrCode, $dateFrom, $dateTo);
    }

    private function hasSerialGrTables(): bool
    {
        return Schema::hasTable('outlet_serial_receive_headers')
            && Schema::hasTable('outlet_serial_receive_items');
    }

    /**
     * Daily spend ala Receiving Sheet: Main Store / MK1 / MK2 + supplier Retail Food.
     * Tanpa omzet / % cost.
     *
     * @return array{
     *   rows: list<object>,
     *   warehouse_columns: list<array{key: string, name: string}>,
     *   suppliers: list<array{id: int|string, name: string}>
     * }
     */
    public function buildReceivingSheetStyleDaily(int $outletId, string $dateFrom, string $dateTo): array
    {
        $warehouseColumns = [
            ['key' => 'main_store', 'name' => 'Main Store'],
            ['key' => 'mk1', 'name' => 'MK1 Hot Kitchen'],
            ['key' => 'mk2', 'name' => 'MK2 Cold Kitchen'],
        ];

        $warehouseSpendByDate = [];
        $addWarehouseSpend = function (array &$warehouseSpendByDate, $date, $warehouseName, $amount): void {
            $date = (string) $date;
            $amount = (float) $amount;
            $bucket = $this->receivingSheetWarehouseBucket($warehouseName);
            if ($date === '' || $amount == 0.0 || $bucket === null) {
                return;
            }
            if (! isset($warehouseSpendByDate[$date])) {
                $warehouseSpendByDate[$date] = [
                    'main_store' => 0.0,
                    'mk1' => 0.0,
                    'mk2' => 0.0,
                ];
            }
            $warehouseSpendByDate[$date][$bucket] += $amount;
        };

        $grSpendQuery = DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as fpl', 'do.packing_list_id', '=', 'fpl.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('food_floor_order_items as ffoi', function ($join) {
                $join->on('ffoi.floor_order_id', '=', 'ffo.id')
                    ->on('ffoi.item_id', '=', 'ofgri.item_id');
            })
            ->leftJoin('warehouse_division as wd', 'fpl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereDate('ofgr.receive_date', '>=', $dateFrom)
            ->whereDate('ofgr.receive_date', '<=', $dateTo)
            ->whereNotNull('w.id')
            ->select(
                'ofgr.receive_date as tanggal',
                'w.name as warehouse_name',
                DB::raw('SUM(ofgri.received_qty * COALESCE(ffoi.price, 0)) as total')
            )
            ->groupBy('ofgr.receive_date', 'w.name')
            ->get();

        foreach ($grSpendQuery as $row) {
            $addWarehouseSpend($warehouseSpendByDate, $row->tanggal, $row->warehouse_name, $row->total);
        }

        if ($this->hasSerialGrTables()) {
            $gsrPriceExpr = $this->serialGrPriceSql('it');
            $gsrSpendQuery = DB::table('outlet_serial_receive_items as si')
                ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->where('h.outlet_id', $outletId)
                ->whereDate('h.receive_date', '>=', $dateFrom)
                ->whereDate('h.receive_date', '<=', $dateTo)
                ->whereNotNull('w.id')
                ->select(
                    'h.receive_date as tanggal',
                    'w.name as warehouse_name',
                    DB::raw("SUM(si.qty * ({$gsrPriceExpr})) as total")
                )
                ->groupBy('h.receive_date', 'w.name')
                ->get();

            foreach ($gsrSpendQuery as $row) {
                $addWarehouseSpend($warehouseSpendByDate, $row->tanggal, $row->warehouse_name, $row->total);
            }
        }

        // RWS tidak masuk warehouse spend (sudah terwakili Retail Food / supplier Justus).

        $retailSupplierData = DB::table('retail_food as rf')
            ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereNotNull('rf.supplier_id')
            ->where('rf.outlet_id', $outletId)
            ->whereDate('rf.transaction_date', '>=', $dateFrom)
            ->whereDate('rf.transaction_date', '<=', $dateTo)
            ->select(
                'rf.transaction_date as tanggal',
                's.id as supplier_id',
                's.name as supplier_name',
                DB::raw('SUM(COALESCE(rf.total_amount, 0)) as total')
            )
            ->groupBy('rf.transaction_date', 's.id', 's.name')
            ->get();

        $suppliers = $retailSupplierData->map(fn ($row) => [
            'id' => $row->supplier_id,
            'name' => $row->supplier_name,
        ])->unique('id')->sortBy('name')->values()->all();

        $supplierSpendByDate = [];
        foreach ($retailSupplierData as $row) {
            $date = (string) $row->tanggal;
            $sid = $row->supplier_id;
            if (! isset($supplierSpendByDate[$date])) {
                $supplierSpendByDate[$date] = [];
            }
            $supplierSpendByDate[$date][$sid] = (float) $row->total;
        }

        $dayNames = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];

        $allDates = collect(array_keys($warehouseSpendByDate))
            ->merge(array_keys($supplierSpendByDate))
            ->unique()
            ->sortDesc()
            ->values();

        $rows = [];
        foreach ($allDates as $date) {
            $date = (string) $date;
            $mainStore = round((float) ($warehouseSpendByDate[$date]['main_store'] ?? 0), 2);
            $mk1 = round((float) ($warehouseSpendByDate[$date]['mk1'] ?? 0), 2);
            $mk2 = round((float) ($warehouseSpendByDate[$date]['mk2'] ?? 0), 2);
            $supplierValues = [];
            $supplierTotal = 0.0;
            foreach ($suppliers as $sp) {
                $sid = $sp['id'];
                $val = round((float) ($supplierSpendByDate[$date][$sid] ?? 0), 2);
                $supplierValues['supplier_'.$sid] = $val;
                $supplierTotal += $val;
            }
            $cost = round($mainStore + $mk1 + $mk2 + $supplierTotal, 2);
            $carbon = Carbon::parse($date);
            $dow = (int) $carbon->dayOfWeek;

            $rows[] = (object) array_merge([
                'id' => $date,
                'type' => 'total_spend',
                'source' => 'Receiving Sheet',
                'date' => $date,
                'number' => $date,
                'day_name' => $dayNames[$dow] ?? $carbon->format('l'),
                'is_weekend' => in_array($dow, [0, 6], true),
                'main_store' => $mainStore,
                'mk1' => $mk1,
                'mk2' => $mk2,
                'amount' => $cost,
                'total_spend' => $cost,
                'creator_name' => $dayNames[$dow] ?? '',
            ], $supplierValues);
        }

        return [
            'rows' => $rows,
            'warehouse_columns' => $warehouseColumns,
            'suppliers' => $suppliers,
        ];
    }

    private function receivingSheetWarehouseBucket(?string $warehouseName): ?string
    {
        $whName = strtoupper(trim((string) $warehouseName));
        if ($whName === '') {
            return null;
        }
        if ($whName === 'MAIN STORE' || str_contains($whName, 'MAIN STORE')) {
            return 'main_store';
        }
        if ($whName === 'MK1 HOT KITCHEN' || str_starts_with($whName, 'MK1')) {
            return 'mk1';
        }
        if ($whName === 'MK2 COLD KITCHEN' || str_starts_with($whName, 'MK2')) {
            return 'mk2';
        }

        return null;
    }

    private function serialGrPriceSql(string $itemAlias = 'it'): string
    {
        $costSmall = 'COALESCE(si.cost_small, 0)';
        $smallConv = "COALESCE({$itemAlias}.small_conversion_qty, 1)";
        $mediumConv = "COALESCE({$itemAlias}.medium_conversion_qty, 1)";

        return "(CASE
            WHEN si.unit_id = {$itemAlias}.large_unit_id THEN {$costSmall} * {$smallConv} * {$mediumConv}
            WHEN si.unit_id = {$itemAlias}.medium_unit_id THEN {$costSmall} * {$smallConv}
            ELSE {$costSmall}
        END)";
    }

    /**
     * Active warehouse outlet IDs for an outlet.
     *
     * @return list<int>
     */
    private function activeWarehouseOutletIds(int $outletId): array
    {
        return DB::table('warehouse_outlets')
            ->where('outlet_id', $outletId)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Latest initial_balance card id per item+warehouse on day-1 of the month.
     * Satu kartu saja — jangan SUM semua upload IB di tgl 1 (bisa 2×).
     * Sama aturan Cost Report kolom Begin Inventory.
     *
     * @param  list<int>  $warehouseOutletIds
     * @return list<int>
     */
    private function latestInitialBalanceCardIds(int $outletId, string $tanggal1, array $warehouseOutletIds): array
    {
        if ($warehouseOutletIds === []) {
            return [];
        }

        return DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $outletId)
            ->whereIn('warehouse_outlet_id', $warehouseOutletIds)
            ->where('reference_type', 'initial_balance')
            ->whereDate('date', $tanggal1)
            ->groupBy('inventory_item_id', 'warehouse_outlet_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Latest stock_opname (koreksi fisik) card id per item+warehouse on day-1.
     *
     * @param  list<int>  $warehouseOutletIds
     * @return list<int>
     */
    private function latestDay1StockOpnameCardIds(int $outletId, string $tanggal1, array $warehouseOutletIds): array
    {
        if ($warehouseOutletIds === []) {
            return [];
        }

        return DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $outletId)
            ->whereIn('warehouse_outlet_id', $warehouseOutletIds)
            ->where('reference_type', 'stock_opname')
            ->whereDate('date', $tanggal1)
            ->groupBy('inventory_item_id', 'warehouse_outlet_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Cutoff koreksi fisik tgl 1 untuk item TANPA IB — dipakai di formula ending saja.
     * Begin Inventory card tetap IB-only (parity Cost Report).
     *
     * @return array{total: float, count: int, by_warehouse: array<int, float>}
     */
    private function sumDay1OpnameCutoffWithoutIb(int $outletId, string $dateFrom): array
    {
        $bulan = Carbon::parse($dateFrom)->format('Y-m');
        $tanggal1 = $bulan.'-01';
        $warehouseOutletIds = $this->activeWarehouseOutletIds($outletId);
        $empty = ['total' => 0.0, 'count' => 0, 'by_warehouse' => []];

        if ($warehouseOutletIds === []) {
            return $empty;
        }

        $ibIds = $this->latestInitialBalanceCardIds($outletId, $tanggal1, $warehouseOutletIds);
        $opIds = $this->latestDay1StockOpnameCardIds($outletId, $tanggal1, $warehouseOutletIds);
        if ($opIds === []) {
            return $empty;
        }

        $ibKeySet = [];
        if ($ibIds !== []) {
            foreach (DB::table('outlet_food_inventory_cards')
                ->whereIn('id', $ibIds)
                ->get(['warehouse_outlet_id', 'inventory_item_id']) as $row) {
                $ibKeySet[((int) $row->warehouse_outlet_id).'|'.((int) $row->inventory_item_id)] = true;
            }
        }

        $byWh = [];
        $total = 0.0;
        $count = 0;
        foreach (DB::table('outlet_food_inventory_cards')
            ->whereIn('id', $opIds)
            ->get(['warehouse_outlet_id', 'inventory_item_id', 'saldo_value']) as $row) {
            $wid = (int) $row->warehouse_outlet_id;
            $key = $wid.'|'.((int) $row->inventory_item_id);
            if (isset($ibKeySet[$key])) {
                continue;
            }
            $value = (float) ($row->saldo_value ?? 0);
            $byWh[$wid] = ($byWh[$wid] ?? 0.0) + $value;
            $total += $value;
            $count++;
        }

        foreach ($byWh as $wid => $amount) {
            $byWh[$wid] = round((float) $amount, 2);
        }

        return [
            'total' => round($total, 2),
            'count' => $count,
            'by_warehouse' => $byWh,
        ];
    }

    /**
     * Begin Inventory (Total MAC) — sama formula Cost Report kolom Begin Inventory.
     * Hanya initial_balance tgl 1 (latest per item+WH); tanpa stock_opname.
     * Fast path: aggregate SQL + cache (tanpa load semua stock rows ke PHP).
     *
     * @return array{total: float, count: int, source: string}
     */
    public function sumBeginInventory(int $outletId, string $dateFrom): array
    {
        $bulan = Carbon::parse($dateFrom)->format('Y-m');
        // v3: begin = IB only (parity Cost Report); day1 opname tidak dijumlah ke begin
        $cacheKey = "opex_outlet:begin_inv:{$outletId}:{$bulan}:v3";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($outletId, $bulan) {
            $tanggal1BulanIni = $bulan.'-01';
            $warehouseOutletIds = $this->activeWarehouseOutletIds($outletId);

            if ($warehouseOutletIds === []) {
                return ['total' => 0.0, 'count' => 0, 'source' => 'none'];
            }

            $cardIds = $this->latestInitialBalanceCardIds($outletId, $tanggal1BulanIni, $warehouseOutletIds);

            if ($cardIds !== []) {
                // Hanya saldo_value kartu IB terbaru — JANGAN value_in (IB biasanya value_in ≈ saldo_value → 2×).
                $agg = DB::table('outlet_food_inventory_cards')
                    ->whereIn('id', $cardIds)
                    ->selectRaw('
                        COALESCE(SUM(COALESCE(saldo_value, 0)), 0) as total_value,
                        COUNT(*) as item_count
                    ')
                    ->first();

                return [
                    'total' => round((float) ($agg->total_value ?? 0), 2),
                    'count' => (int) ($agg->item_count ?? 0),
                    'source' => 'initial_balance',
                ];
            }

            // Tanpa saldo awal: SUM(qty_small * last_cost_small) dari stok aktif
            $agg = DB::table('outlet_food_inventory_stocks as s')
                ->where('s.id_outlet', $outletId)
                ->whereIn('s.warehouse_outlet_id', $warehouseOutletIds)
                ->selectRaw('
                    COALESCE(SUM(COALESCE(s.qty_small, 0) * COALESCE(s.last_cost_small, 0)), 0) as total_value,
                    SUM(CASE WHEN COALESCE(s.qty_small, 0) != 0 OR COALESCE(s.last_cost_small, 0) != 0 THEN 1 ELSE 0 END) as item_count
                ')
                ->first();

            return [
                'total' => round((float) ($agg->total_value ?? 0), 2),
                'count' => (int) ($agg->item_count ?? 0),
                'source' => 'current_stock',
            ];
        });
    }

    /**
     * Detail Begin Inventory: item + qty + MAC, dikelompokkan per category.
     * Total MAC = sama persis sumBeginInventory (card) — satu sumber kartu IB terbaru.
     *
     * @return array{
     *   source: string,
     *   initial_balance_date: string,
     *   total_value: float,
     *   groups: list<array{category: string, item_count: int, total_value: float, items: list<array<string, mixed>>}>
     * }
     */
    public function buildBeginInventoryDetail(int $outletId, string $dateFrom, string $search = ''): array
    {
        $bulan = Carbon::parse($dateFrom)->format('Y-m');
        $initialBalanceDate = $bulan.'-01';
        $search = trim($search);

        // Total header selalu ikut card (sumBeginInventory) agar tidak bisa drift.
        $cardSummary = $this->sumBeginInventory($outletId, $dateFrom);

        $warehouseOutletIds = $this->activeWarehouseOutletIds($outletId);

        if ($warehouseOutletIds === []) {
            return [
                'source' => 'none',
                'initial_balance_date' => $initialBalanceDate,
                'total_value' => 0.0,
                'groups' => [],
            ];
        }

        $cardIds = $this->latestInitialBalanceCardIds($outletId, $initialBalanceDate, $warehouseOutletIds);
        $hasInitialBalance = $cardIds !== [];

        if ($hasInitialBalance) {
            $query = DB::table('outlet_food_inventory_cards as card')
                ->whereIn('card.id', $cardIds)
                ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
                ->join('items as i', 'fi.item_id', '=', 'i.id')
                ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
                ->join('warehouse_outlets as wo', 'card.warehouse_outlet_id', '=', 'wo.id')
                ->where('wo.status', 'active')
                ->where(function ($q) {
                    $q->where('card.saldo_value', '!=', 0)
                        ->orWhere('card.saldo_qty_small', '!=', 0);
                })
                ->selectRaw("
                    card.id as card_id,
                    COALESCE(c.name, 'Tanpa Kategori') as category_name,
                    i.name as item_name,
                    i.sku as item_sku,
                    wo.name as warehouse_name,
                    COALESCE(card.saldo_qty_small, 0) as qty,
                    COALESCE(card.cost_per_small, 0) as mac,
                    COALESCE(card.saldo_value, 0) as value
                ");
        } else {
            $query = DB::table('outlet_food_inventory_stocks as s')
                ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
                ->join('items as i', 'fi.item_id', '=', 'i.id')
                ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
                ->join('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
                ->where('s.id_outlet', $outletId)
                ->whereIn('s.warehouse_outlet_id', $warehouseOutletIds)
                ->where('wo.status', 'active')
                ->where(function ($q) {
                    $q->whereRaw('COALESCE(s.qty_small, 0) * COALESCE(s.last_cost_small, 0) != 0')
                        ->orWhere('s.qty_small', '!=', 0);
                })
                ->selectRaw("
                    CONCAT('s-', s.id) as card_id,
                    COALESCE(c.name, 'Tanpa Kategori') as category_name,
                    i.name as item_name,
                    i.sku as item_sku,
                    wo.name as warehouse_name,
                    COALESCE(s.qty_small, 0) as qty,
                    COALESCE(s.last_cost_small, 0) as mac,
                    COALESCE(s.qty_small, 0) * COALESCE(s.last_cost_small, 0) as value
                ");
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('i.name', 'like', '%'.$search.'%')
                    ->orWhere('i.sku', 'like', '%'.$search.'%')
                    ->orWhere('c.name', 'like', '%'.$search.'%')
                    ->orWhere('wo.name', 'like', '%'.$search.'%');
            });
        }

        $rows = $query
            ->orderBy('category_name')
            ->orderBy('item_name')
            ->get();

        $groupsMap = [];
        $seenCardIds = [];
        $listedTotal = 0.0;
        foreach ($rows as $row) {
            $cardKey = (string) ($row->card_id ?? '');
            if ($cardKey !== '' && isset($seenCardIds[$cardKey])) {
                continue;
            }
            if ($cardKey !== '') {
                $seenCardIds[$cardKey] = true;
            }

            $value = round((float) ($row->value ?? 0), 2);
            $qty = round((float) ($row->qty ?? 0), 4);
            $mac = round((float) ($row->mac ?? 0), 4);

            $category = (string) ($row->category_name ?: 'Tanpa Kategori');
            if (! isset($groupsMap[$category])) {
                $groupsMap[$category] = [
                    'category' => $category,
                    'item_count' => 0,
                    'total_value' => 0.0,
                    'items' => [],
                ];
            }

            $groupsMap[$category]['items'][] = [
                'item_name' => $row->item_name,
                'item_sku' => $row->item_sku,
                'warehouse_name' => $row->warehouse_name,
                'qty' => $qty,
                'mac' => $mac,
                'value' => $value,
            ];
            $groupsMap[$category]['item_count']++;
            $groupsMap[$category]['total_value'] = round($groupsMap[$category]['total_value'] + $value, 2);
            $listedTotal = round($listedTotal + $value, 2);
        }

        $groups = array_values($groupsMap);
        usort($groups, fn ($a, $b) => $b['total_value'] <=> $a['total_value']);

        // Tanpa search: pakai total card. Dengan search: pakai jumlah baris terfilter.
        $totalValue = $search !== ''
            ? $listedTotal
            : (float) $cardSummary['total'];

        return [
            'source' => $hasInitialBalance ? 'initial_balance' : ($cardSummary['source'] ?? 'current_stock'),
            'initial_balance_date' => $initialBalanceDate,
            'total_value' => $totalValue,
            'groups' => $groups,
        ];
    }

    /**
     * Ending = begin + purchased + opname + transfer − (stock_cut + category_cost), per warehouse.
     * Opname/transfer dari kartu inventory (value_in − value_out) pada periode.
     *
     * @return array{
     *   begin_by_warehouse: list<array{warehouse_id: int, warehouse_name: string, amount: float}>,
     *   purchased_total: float,
     *   purchased_by_warehouse: list<array{warehouse_id: int, warehouse_name: string, amount: float}>,
     *   opname_total: float,
     *   opname_by_warehouse: list<array{warehouse_id: int, warehouse_name: string, amount: float}>,
     *   transfer_total: float,
     *   transfer_by_warehouse: list<array{warehouse_id: int, warehouse_name: string, amount: float}>,
     *   stock_cut_by_warehouse: list<array{warehouse_id: int, warehouse_name: string, amount: float}>,
     *   category_cost_by_warehouse: list<array{warehouse_id: int, warehouse_name: string, amount: float}>,
     *   ending_total: float,
     *   ending_by_warehouse: list<array{warehouse_id: int, warehouse_name: string, amount: float}>,
     *   formula: array{begin: float, purchased: float, opname: float, transfer: float, stock_cut: float, category_cost: float, ending: float}
     * }
     */
    public function buildInventoryMovementSummary(int $outletId, string $dateFrom, string $dateTo): array
    {
        $warehouses = DB::table('warehouse_outlets')
            ->where('outlet_id', $outletId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all();
        $emptyList = [];
        $emptyFormula = [
            'begin' => 0.0,
            'purchased' => 0.0,
            'opname' => 0.0,
            'transfer' => 0.0,
            'stock_cut' => 0.0,
            'category_cost' => 0.0,
            'ending' => 0.0,
        ];

        if ($warehouseIds === []) {
            return [
                'begin_by_warehouse' => $emptyList,
                'purchased_total' => 0.0,
                'purchased_by_warehouse' => $emptyList,
                'opname_total' => 0.0,
                'opname_by_warehouse' => $emptyList,
                'transfer_total' => 0.0,
                'transfer_by_warehouse' => $emptyList,
                'stock_cut_by_warehouse' => $emptyList,
                'category_cost_by_warehouse' => $emptyList,
                'ending_total' => 0.0,
                'ending_by_warehouse' => $emptyList,
                'formula' => $emptyFormula,
            ];
        }

        $beginMap = $this->beginInventoryAmountByWarehouse($outletId, $dateFrom, $warehouseIds);
        $day1CutoffMap = $this->sumDay1OpnameCutoffWithoutIb($outletId, $dateFrom)['by_warehouse'];
        $purchasedMap = $this->purchasedAmountByWarehouse($outletId, $dateFrom, $dateTo, $warehouses);
        $opnameMap = $this->cardMovementNetByWarehouse(
            $outletId,
            $dateFrom,
            $dateTo,
            ['stock_opname']
        );
        $transferMap = $this->cardMovementNetByWarehouse(
            $outletId,
            $dateFrom,
            $dateTo,
            ['internal_warehouse_transfer', 'outlet_transfer', 'outlet_stock_adjustment']
        );
        $stockCutMap = $this->stockCutAmountByWarehouse($outletId, $dateFrom, $dateTo);
        $categoryCostMap = $this->categoryCostAmountByWarehouse($outletId, $dateFrom, $dateTo);

        $beginByWh = [];
        $purchasedByWh = [];
        $opnameByWh = [];
        $transferByWh = [];
        $stockCutByWh = [];
        $categoryCostByWh = [];
        $endingByWh = [];
        $beginTotal = 0.0;
        $purchasedTotal = 0.0;
        $opnameTotal = 0.0;
        $transferTotal = 0.0;
        $stockCutTotal = 0.0;
        $categoryCostTotal = 0.0;
        $endingTotal = 0.0;
        $day1CutoffTotal = 0.0;

        foreach ($warehouses as $wh) {
            $id = (int) $wh->id;
            $name = (string) $wh->name;
            $begin = round((float) ($beginMap[$id] ?? 0), 2);
            $day1Cutoff = round((float) ($day1CutoffMap[$id] ?? 0), 2);
            $purchased = round((float) ($purchasedMap[$id] ?? 0), 2);
            $opname = round((float) ($opnameMap[$id] ?? 0), 2);
            $transfer = round((float) ($transferMap[$id] ?? 0), 2);
            $stockCut = round((float) ($stockCutMap[$id] ?? 0), 2);
            $categoryCost = round((float) ($categoryCostMap[$id] ?? 0), 2);
            // Formula WH: IB + cutoff koreksi tgl 1 (tanpa IB) + purchased + transfer − cut − cat.
            // Full-period opname net tetap di-report, tidak dijumlah ke ending formula.
            $ending = round($begin + $day1Cutoff + $purchased + $transfer - $stockCut - $categoryCost, 2);

            $beginByWh[] = ['warehouse_id' => $id, 'warehouse_name' => $name, 'amount' => $begin];
            $purchasedByWh[] = ['warehouse_id' => $id, 'warehouse_name' => $name, 'amount' => $purchased];
            $opnameByWh[] = ['warehouse_id' => $id, 'warehouse_name' => $name, 'amount' => $opname];
            $transferByWh[] = ['warehouse_id' => $id, 'warehouse_name' => $name, 'amount' => $transfer];
            $stockCutByWh[] = ['warehouse_id' => $id, 'warehouse_name' => $name, 'amount' => $stockCut];
            $categoryCostByWh[] = ['warehouse_id' => $id, 'warehouse_name' => $name, 'amount' => $categoryCost];
            $endingByWh[] = ['warehouse_id' => $id, 'warehouse_name' => $name, 'amount' => $ending];

            $beginTotal += $begin;
            $day1CutoffTotal += $day1Cutoff;
            $purchasedTotal += $purchased;
            $opnameTotal += $opname;
            $transferTotal += $transfer;
            $stockCutTotal += $stockCut;
            $categoryCostTotal += $categoryCost;
            $endingTotal += $ending;
        }

        return [
            'begin_by_warehouse' => $beginByWh,
            'purchased_total' => round($purchasedTotal, 2),
            'purchased_by_warehouse' => $purchasedByWh,
            'opname_total' => round($opnameTotal, 2),
            'opname_by_warehouse' => $opnameByWh,
            'transfer_total' => round($transferTotal, 2),
            'transfer_by_warehouse' => $transferByWh,
            'stock_cut_by_warehouse' => $stockCutByWh,
            'category_cost_by_warehouse' => $categoryCostByWh,
            'ending_total' => round($endingTotal, 2),
            'ending_by_warehouse' => $endingByWh,
            'formula' => [
                'begin' => round($beginTotal, 2),
                'day1_opname_cutoff' => round($day1CutoffTotal, 2),
                'purchased' => round($purchasedTotal, 2),
                'opname' => round($opnameTotal, 2),
                'transfer' => round($transferTotal, 2),
                'stock_cut' => round($stockCutTotal, 2),
                'category_cost' => round($categoryCostTotal, 2),
                'ending' => round($endingTotal, 2),
            ],
        ];
    }

    /**
     * Ending inventory stock report: per warehouse → categories (expand) → items.
     *
     * @return array{
     *   as_of: string,
     *   source: string,
     *   total_value: float,
     *   formula: array<string, float>,
     *   warehouse_options: list<array{id: int, name: string}>,
     *   warehouses: list<array{
     *     warehouse_id: int,
     *     warehouse_name: string,
     *     total_value: float,
     *     item_count: int,
     *     categories: list<array{category: string, item_count: int, total_value: float, items: list<array<string, mixed>>}>
     *   }>
     * }
     */
    public function buildEndingInventoryDetail(
        int $outletId,
        string $dateFrom,
        string $dateTo,
        string $search = '',
        ?int $warehouseId = null
    ): array {
        $search = trim($search);
        $movement = $this->buildInventoryMovementSummary($outletId, $dateFrom, $dateTo);
        $beginTotal = (float) $this->sumBeginInventory($outletId, $dateFrom)['total'];
        $day1Cutoff = $this->sumDay1OpnameCutoffWithoutIb($outletId, $dateFrom);
        $stockCutHpp = $this->sumStockCut($outletId, $dateFrom, $dateTo);
        $stockCutPhysical = (float) ($stockCutHpp['physical_total'] ?? $stockCutHpp['total']);
        $categoryCostTotal = (float) $this->sumCategoryCost($outletId, $dateFrom, $dateTo)['total'];
        $purchasedTotal = (float) $movement['purchased_total'];
        $transferNet = (float) $this->sumOutletTransferMovements($outletId, $dateFrom, $dateTo)['net_total'];
        $adjustmentNet = (float) $this->sumOutletAdjustmentMovements($outletId, $dateFrom, $dateTo)['total'];
        $opnameTotal = (float) ($movement['opname_total'] ?? 0);
        // Begin = IB (Cost Report). + cutoff koreksi fisik tgl 1 item tanpa IB.
        // Opname EOM/mid-month lain = balancing — tidak dijumlah ke formula buku.
        $formulaEnding = round(
            $beginTotal
            + (float) $day1Cutoff['total']
            + $purchasedTotal
            + $transferNet
            + $adjustmentNet
            - $stockCutPhysical
            - $categoryCostTotal,
            2
        );
        $formula = [
            'begin' => round($beginTotal, 2),
            'day1_opname_cutoff' => round((float) $day1Cutoff['total'], 2),
            'day1_opname_cutoff_count' => (int) $day1Cutoff['count'],
            'purchased' => round($purchasedTotal, 2),
            'outlet_transfer_net' => round($transferNet, 2),
            'outlet_adjustment' => round($adjustmentNet, 2),
            'opname' => round($opnameTotal, 2),
            'opname_in_formula' => false,
            'stock_cut' => round($stockCutPhysical, 2),
            'stock_cut_hpp' => round((float) $stockCutHpp['total'], 2),
            'stock_cut_shortfall' => round((float) ($stockCutHpp['shortfall_total'] ?? 0), 2),
            'category_cost' => round($categoryCostTotal, 2),
            'ending' => $formulaEnding,
        ];

        $warehouses = DB::table('warehouse_outlets')
            ->where('outlet_id', $outletId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $warehouseOptions = $warehouses->map(fn ($w) => [
            'id' => (int) $w->id,
            'name' => (string) $w->name,
        ])->values()->all();

        $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($warehouseId !== null && $warehouseId > 0) {
            $warehouseIds = array_values(array_filter($warehouseIds, fn ($id) => $id === $warehouseId));
        }

        $empty = [
            'as_of' => $dateTo,
            'source' => 'none',
            'total_value' => 0.0,
            'stock_total' => 0.0,
            'formula' => $formula,
            'warehouse_options' => $warehouseOptions,
            'warehouses' => [],
        ];

        if ($warehouseIds === []) {
            return $empty;
        }

        // Seed warehouse shells from formula breakdown (for side-by-side compare), but item totals = stock.
        $byWarehouse = [];
        foreach ($movement['ending_by_warehouse'] as $row) {
            $wid = (int) ($row['warehouse_id'] ?? 0);
            if ($wid <= 0 || ! in_array($wid, $warehouseIds, true)) {
                continue;
            }
            $byWarehouse[$wid] = [
                'warehouse_id' => $wid,
                'warehouse_name' => (string) ($row['warehouse_name'] ?? '-'),
                'formula_ending' => round((float) ($row['amount'] ?? 0), 2),
                'total_value' => 0.0,
                'item_count' => 0,
                'categories' => [],
            ];
        }
        foreach ($warehouses as $wh) {
            $wid = (int) $wh->id;
            if (! in_array($wid, $warehouseIds, true)) {
                continue;
            }
            if (! isset($byWarehouse[$wid])) {
                $byWarehouse[$wid] = [
                    'warehouse_id' => $wid,
                    'warehouse_name' => (string) $wh->name,
                    'formula_ending' => 0.0,
                    'total_value' => 0.0,
                    'item_count' => 0,
                    'categories' => [],
                ];
            }
        }

        // Kartu terbaru dalam periode [dateFrom, dateTo] — tidak menarik saldo bulan sebelumnya.
        $latest = DB::table('outlet_food_inventory_cards as card')
            ->where('card.id_outlet', $outletId)
            ->whereIn('card.warehouse_outlet_id', $warehouseIds)
            ->whereDate('card.date', '>=', $dateFrom)
            ->whereDate('card.date', '<=', $dateTo)
            ->selectRaw("card.inventory_item_id, card.warehouse_outlet_id, MAX(CONCAT(DATE(card.date), ' ', LPAD(card.id, 20, '0'))) as latest_key")
            ->groupBy('card.inventory_item_id', 'card.warehouse_outlet_id');

        $cardRows = DB::table('outlet_food_inventory_cards as card')
            ->joinSub($latest, 'latest_card', function ($join) {
                $join->on('latest_card.inventory_item_id', '=', 'card.inventory_item_id')
                    ->on('latest_card.warehouse_outlet_id', '=', 'card.warehouse_outlet_id');
            })
            ->whereRaw("CONCAT(DATE(card.date), ' ', LPAD(card.id, 20, '0')) = latest_card.latest_key")
            ->where('card.id_outlet', $outletId)
            ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->join('warehouse_outlets as wo', 'card.warehouse_outlet_id', '=', 'wo.id')
            ->whereIn('card.warehouse_outlet_id', $warehouseIds)
            ->where(function ($q) {
                $q->where('card.saldo_value', '!=', 0)
                    ->orWhere('card.saldo_qty_small', '!=', 0);
            })
            ->selectRaw("
                card.warehouse_outlet_id as warehouse_id,
                wo.name as warehouse_name,
                card.inventory_item_id,
                COALESCE(c.name, 'Tanpa Kategori') as category_name,
                i.name as item_name,
                i.sku as item_sku,
                COALESCE(card.saldo_qty_small, 0) as qty,
                COALESCE(card.cost_per_small, 0) as mac,
                COALESCE(card.saldo_value, 0) as value
            ");

        if ($search !== '') {
            $cardRows->where(function ($q) use ($search) {
                $q->where('i.name', 'like', '%'.$search.'%')
                    ->orWhere('i.sku', 'like', '%'.$search.'%')
                    ->orWhere('c.name', 'like', '%'.$search.'%')
                    ->orWhere('wo.name', 'like', '%'.$search.'%');
            });
        }

        $rows = $cardRows
            ->orderBy('warehouse_name')
            ->orderBy('category_name')
            ->orderBy('item_name')
            ->get();

        $usedCards = $rows->isNotEmpty();

        $stockGrandTotal = 0.0;
        $orphanSkipped = 0;
        foreach ($rows as $row) {
            $wid = (int) $row->warehouse_id;
            if (! isset($byWarehouse[$wid])) {
                continue;
            }
            $category = (string) ($row->category_name ?: 'Tanpa Kategori');
            $qty = round((float) ($row->qty ?? 0), 4);
            $mac = round((float) ($row->mac ?? 0), 4);
            $rawValue = round((float) ($row->value ?? 0), 2);

            // Value yatim: qty ≈ 0 tapi saldo_value ≠ 0 → abaikan dari report ending
            if (abs($qty) < 0.00005) {
                if (abs($rawValue) >= 0.01) {
                    $orphanSkipped++;
                }
                continue;
            }

            $value = $rawValue;

            if (! isset($byWarehouse[$wid]['categories'][$category])) {
                $byWarehouse[$wid]['categories'][$category] = [
                    'category' => $category,
                    'item_count' => 0,
                    'total_value' => 0.0,
                    'items' => [],
                ];
            }

            $byWarehouse[$wid]['categories'][$category]['items'][] = [
                'item_name' => $row->item_name,
                'item_sku' => $row->item_sku,
                'qty' => $qty,
                'mac' => $mac,
                'value' => $value,
            ];
            $byWarehouse[$wid]['categories'][$category]['item_count']++;
            $byWarehouse[$wid]['categories'][$category]['total_value'] = round(
                $byWarehouse[$wid]['categories'][$category]['total_value'] + $value,
                2
            );
            $byWarehouse[$wid]['item_count']++;
            $byWarehouse[$wid]['total_value'] = round($byWarehouse[$wid]['total_value'] + $value, 2);
            $stockGrandTotal = round($stockGrandTotal + $value, 2);
        }

        $warehouseList = [];
        foreach ($byWarehouse as $wh) {
            // Saat search: sembunyikan gudang tanpa item match (kecuali filter warehouse spesifik)
            if ($search !== '' && $wh['item_count'] === 0 && ($warehouseId === null || $warehouseId <= 0)) {
                continue;
            }
            $cats = array_values($wh['categories']);
            usort($cats, fn ($a, $b) => $b['total_value'] <=> $a['total_value']);
            $wh['categories'] = $cats;
            $wh['variance'] = round(((float) $wh['formula_ending']) - ((float) $wh['total_value']), 2);
            $warehouseList[] = $wh;
        }
        usort($warehouseList, fn ($a, $b) => $b['total_value'] <=> $a['total_value']);

        return [
            'as_of' => $dateTo,
            'source' => $usedCards ? 'inventory_cards' : 'none',
            // total_value = stok fisik (detail), formula.ending = nilai card (formula)
            'total_value' => $stockGrandTotal,
            'stock_total' => $stockGrandTotal,
            'orphan_skipped' => $orphanSkipped,
            'formula' => array_merge($formula, [
                'formula_ending' => $formulaEnding,
                'stock_ending' => $stockGrandTotal,
                'variance' => round($formulaEnding - $stockGrandTotal, 2),
            ]),
            'warehouse_options' => $warehouseOptions,
            'warehouses' => $warehouseList,
        ];
    }

    /**
     * Ending stock as-of dateTo dari kartu terbaru dalam periode (≥ dateFrom).
     * Tidak menarik saldo bulan sebelumnya; abaikan value yatim (qty≈0).
     *
     * @return array{
     *   total: float,
     *   by_warehouse: list<array{warehouse_id: int, warehouse_name: string, amount: float}>,
     *   orphan_skipped: int
     * }
     */
    private function sumEndingStockSanitized(int $outletId, string $dateTo, ?string $dateFrom = null): array
    {
        $warehouses = DB::table('warehouse_outlets')
            ->where('outlet_id', $outletId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($warehouseIds === []) {
            return ['total' => 0.0, 'by_warehouse' => [], 'orphan_skipped' => 0];
        }

        $periodFrom = $dateFrom ?: $dateTo;
        $latest = DB::table('outlet_food_inventory_cards as card')
            ->where('card.id_outlet', $outletId)
            ->whereIn('card.warehouse_outlet_id', $warehouseIds)
            ->whereDate('card.date', '>=', $periodFrom)
            ->whereDate('card.date', '<=', $dateTo)
            ->selectRaw("card.inventory_item_id, card.warehouse_outlet_id, MAX(CONCAT(DATE(card.date), ' ', LPAD(card.id, 20, '0'))) as latest_key")
            ->groupBy('card.inventory_item_id', 'card.warehouse_outlet_id');

        $cardRows = DB::table('outlet_food_inventory_cards as card')
            ->joinSub($latest, 'latest_card', function ($join) {
                $join->on('latest_card.inventory_item_id', '=', 'card.inventory_item_id')
                    ->on('latest_card.warehouse_outlet_id', '=', 'card.warehouse_outlet_id');
            })
            ->whereRaw("CONCAT(DATE(card.date), ' ', LPAD(card.id, 20, '0')) = latest_card.latest_key")
            ->where('card.id_outlet', $outletId)
            ->whereIn('card.warehouse_outlet_id', $warehouseIds)
            ->get([
                'card.warehouse_outlet_id',
                'card.inventory_item_id',
                'card.saldo_qty_small',
                'card.saldo_value',
            ]);

        $byWh = [];
        foreach ($warehouses as $wh) {
            $byWh[(int) $wh->id] = 0.0;
        }
        $orphanSkipped = 0;

        foreach ($cardRows as $row) {
            $wid = (int) $row->warehouse_outlet_id;
            $qty = (float) ($row->saldo_qty_small ?? 0);
            $value = (float) ($row->saldo_value ?? 0);
            if (abs($qty) < 0.00005) {
                if (abs($value) >= 0.01) {
                    $orphanSkipped++;
                }
                continue;
            }
            $byWh[$wid] = ($byWh[$wid] ?? 0) + $value;
        }

        $list = [];
        $total = 0.0;
        foreach ($warehouses as $wh) {
            $id = (int) $wh->id;
            $amount = round((float) ($byWh[$id] ?? 0), 2);
            $list[] = [
                'warehouse_id' => $id,
                'warehouse_name' => (string) $wh->name,
                'amount' => $amount,
            ];
            $total += $amount;
        }

        return [
            'total' => round($total, 2),
            'by_warehouse' => $list,
            'orphan_skipped' => $orphanSkipped,
        ];
    }

    /**
     * Net kartu inventory (value_in − value_out) per warehouse untuk reference_type tertentu.
     *
     * @param  list<string>  $referenceTypes
     * @return array<int, float>
     */
    private function cardMovementNetByWarehouse(
        int $outletId,
        string $dateFrom,
        string $dateTo,
        array $referenceTypes
    ): array {
        if ($referenceTypes === [] || ! Schema::hasTable('outlet_food_inventory_cards')) {
            return [];
        }

        $rows = DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $outletId)
            ->whereIn('reference_type', $referenceTypes)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->whereNotNull('warehouse_outlet_id')
            ->selectRaw('
                warehouse_outlet_id as warehouse_id,
                COALESCE(SUM(COALESCE(value_in, 0) - COALESCE(value_out, 0)), 0) as net
            ')
            ->groupBy('warehouse_outlet_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->warehouse_id] = round((float) $row->net, 2);
        }

        return $out;
    }

    /**
     * @param  list<int>  $warehouseIds
     * @return array<int, float>
     */
    private function beginInventoryAmountByWarehouse(int $outletId, string $dateFrom, array $warehouseIds): array
    {
        $bulan = Carbon::parse($dateFrom)->format('Y-m');
        $tanggal1BulanIni = $bulan.'-01';
        $out = array_fill_keys($warehouseIds, 0.0);

        $cardIds = $this->latestInitialBalanceCardIds($outletId, $tanggal1BulanIni, $warehouseIds);

        if ($cardIds !== []) {
            $rows = DB::table('outlet_food_inventory_cards')
                ->whereIn('id', $cardIds)
                ->selectRaw('warehouse_outlet_id as warehouse_id, COALESCE(SUM(COALESCE(saldo_value, 0)), 0) as total_value')
                ->groupBy('warehouse_outlet_id')
                ->get();

            foreach ($rows as $row) {
                $out[(int) $row->warehouse_id] = round((float) $row->total_value, 2);
            }

            return $out;
        }

        $rows = DB::table('outlet_food_inventory_stocks as s')
            ->where('s.id_outlet', $outletId)
            ->whereIn('s.warehouse_outlet_id', $warehouseIds)
            ->selectRaw('s.warehouse_outlet_id as warehouse_id, COALESCE(SUM(COALESCE(s.qty_small, 0) * COALESCE(s.last_cost_small, 0)), 0) as total_value')
            ->groupBy('s.warehouse_outlet_id')
            ->get();

        foreach ($rows as $row) {
            $out[(int) $row->warehouse_id] = round((float) $row->total_value, 2);
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $warehouses
     * @return array<int, float>
     */
    private function purchasedAmountByWarehouse(int $outletId, string $dateFrom, string $dateTo, $warehouses): array
    {
        $out = [];
        foreach ($warehouses as $wh) {
            $out[(int) $wh->id] = 0.0;
        }

        $add = function (int $warehouseId, float $amount) use (&$out): void {
            if (! array_key_exists($warehouseId, $out)) {
                $out[$warehouseId] = 0.0;
            }
            $out[$warehouseId] = round($out[$warehouseId] + $amount, 2);
        };

        if ($this->hasSerialGrTables()) {
            $priceSql = $this->serialGrPriceSql('it');
            $gsrRows = DB::table('outlet_serial_receive_items as si')
                ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->where('h.outlet_id', $outletId)
                ->whereBetween(DB::raw('DATE(h.receive_date)'), [$dateFrom, $dateTo])
                ->whereNotNull('si.warehouse_outlet_id')
                ->selectRaw('si.warehouse_outlet_id as warehouse_id, SUM(si.qty * ('.$priceSql.')) as total')
                ->groupBy('si.warehouse_outlet_id')
                ->get();

            foreach ($gsrRows as $row) {
                $add((int) $row->warehouse_id, (float) $row->total);
            }
        }

        $grRows = DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as ffoi', function ($join) {
                $join->on('ofgri.item_id', '=', 'ffoi.item_id')
                    ->where(function ($q) {
                        $q->whereColumn('ffoi.floor_order_id', 'do.floor_order_id')
                            ->orWhereColumn('ffoi.floor_order_id', 'ffo_ro.id');
                    });
            })
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereBetween(DB::raw('DATE(ofgr.receive_date)'), [$dateFrom, $dateTo])
            ->whereRaw('COALESCE(ffo.warehouse_outlet_id, ffo_ro.warehouse_outlet_id) IS NOT NULL')
            ->selectRaw('COALESCE(ffo.warehouse_outlet_id, ffo_ro.warehouse_outlet_id) as warehouse_id, SUM(ofgri.received_qty * COALESCE(ffoi.price, 0)) as total')
            ->groupBy(DB::raw('COALESCE(ffo.warehouse_outlet_id, ffo_ro.warehouse_outlet_id)'))
            ->get();

        foreach ($grRows as $row) {
            $add((int) $row->warehouse_id, (float) $row->total);
        }

        $retailFoodRows = DB::table('retail_food as rf')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereBetween(DB::raw('DATE(rf.transaction_date)'), [$dateFrom, $dateTo])
            ->whereNotNull('rf.warehouse_outlet_id')
            ->selectRaw('rf.warehouse_outlet_id as warehouse_id, SUM(rf.total_amount) as total')
            ->groupBy('rf.warehouse_outlet_id')
            ->get();

        foreach ($retailFoodRows as $row) {
            $add((int) $row->warehouse_id, (float) $row->total);
        }

        // RWS tidak dijumlah: inlet stok outlet sudah lewat Retail Food (mirror RWS).

        return $out;
    }

    /**
     * @param  array<int, float>  $amountByWarehouseId
     * @return list<array{warehouse_id: int, warehouse_name: string, amount: float}>
     */
    private function mapWarehouseAmounts(int $outletId, array $amountByWarehouseId): array
    {
        $warehouses = DB::table('warehouse_outlets')
            ->where('outlet_id', $outletId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $list = [];
        foreach ($warehouses as $wh) {
            $id = (int) $wh->id;
            $amount = round((float) ($amountByWarehouseId[$id] ?? 0), 2);
            if (abs($amount) < 0.005) {
                continue;
            }
            $list[] = [
                'warehouse_id' => $id,
                'warehouse_name' => (string) $wh->name,
                'amount' => $amount,
            ];
        }

        return $list;
    }

    /**
     * Potongan fisik per warehouse (kartu order_items) — untuk ending inventory.
     *
     * @return array<int, float>
     */
    private function stockCutAmountByWarehouse(int $outletId, string $dateFrom, string $dateTo): array
    {
        if (! Schema::hasTable('outlet_food_inventory_cards')) {
            return [];
        }

        $rows = DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $outletId)
            ->where('reference_type', 'order_items')
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->whereNotNull('warehouse_outlet_id')
            ->selectRaw('warehouse_outlet_id as warehouse_id, COALESCE(SUM(value_out), 0) as total')
            ->groupBy('warehouse_outlet_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->warehouse_id] = round((float) $row->total, 2);
        }

        return $out;
    }

    /**
     * HPP full Stock Cut per warehouse (stock_cut_details) — untuk kartu KPI.
     *
     * @return array<int, float>
     */
    private function stockCutHppAmountByWarehouse(int $outletId, string $dateFrom, string $dateTo): array
    {
        if (! Schema::hasTable('stock_cut_details') || ! Schema::hasTable('stock_cut_logs')) {
            return [];
        }

        $rows = DB::table('stock_cut_details as d')
            ->join('stock_cut_logs as l', 'd.stock_cut_log_id', '=', 'l.id')
            ->where('l.outlet_id', $outletId)
            ->where('l.status', 'success')
            ->whereDate('l.tanggal', '>=', $dateFrom)
            ->whereDate('l.tanggal', '<=', $dateTo)
            ->whereNotNull('d.warehouse_outlet_id')
            ->selectRaw('d.warehouse_outlet_id as warehouse_id, COALESCE(SUM(d.value_out), 0) as total')
            ->groupBy('d.warehouse_outlet_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->warehouse_id] = round((float) $row->total, 2);
        }

        return $out;
    }

    /**
     * @return array<int, float>
     */
    private function categoryCostAmountByWarehouse(int $outletId, string $dateFrom, string $dateTo): array
    {
        if (! Schema::hasTable('outlet_internal_use_waste_headers')) {
            return [];
        }

        $rows = $this->categoryCostHeaderQuery($outletId, $dateFrom, $dateTo)
            ->whereNotNull('h.warehouse_outlet_id')
            ->selectRaw('h.warehouse_outlet_id as warehouse_id, COALESCE(SUM(h.subtotal_mac), 0) as total')
            ->groupBy('h.warehouse_outlet_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->warehouse_id] = round((float) $row->total, 2);
        }

        return $out;
    }

    /**
     * Stock Cut (kartu KPI) = HPP teoritis full BOM dari stock_cut_details.
     * Saat stok kurang: fisik dipotong sampai 0, shortfall ke stock_cut_variances (Laporan Minus),
     * tapi value_out detail tetap full — ini sengaja, bukan kelebihan potong.
     *
     * @return array{total: float, count: int, physical_total: float, shortfall_total: float}
     */
    public function sumStockCut(int $outletId, string $dateFrom, string $dateTo): array
    {
        if (! Schema::hasTable('stock_cut_details') || ! Schema::hasTable('stock_cut_logs')) {
            return [
                'total' => 0.0,
                'count' => 0,
                'physical_total' => 0.0,
                'shortfall_total' => 0.0,
            ];
        }

        $row = DB::table('stock_cut_details as d')
            ->join('stock_cut_logs as l', 'd.stock_cut_log_id', '=', 'l.id')
            ->where('l.outlet_id', $outletId)
            ->where('l.status', 'success')
            ->whereDate('l.tanggal', '>=', $dateFrom)
            ->whereDate('l.tanggal', '<=', $dateTo)
            ->selectRaw('COALESCE(SUM(d.value_out), 0) as total, COUNT(DISTINCT l.id) as cnt')
            ->first();

        $physical = $this->sumStockCutPhysical($outletId, $dateFrom, $dateTo);
        $hpp = round((float) ($row->total ?? 0), 2);

        return [
            'total' => $hpp,
            'count' => (int) ($row->cnt ?? 0),
            'physical_total' => $physical,
            'shortfall_total' => round(max(0.0, $hpp - $physical), 2),
        ];
    }

    /**
     * Potongan fisik yang benar-benar keluar kartu stok (order_items).
     * Dipakai ending inventory formula supaya selaras dengan cost di stok.
     */
    public function sumStockCutPhysical(int $outletId, string $dateFrom, string $dateTo): float
    {
        if (! Schema::hasTable('outlet_food_inventory_cards')) {
            return 0.0;
        }

        $total = DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $outletId)
            ->where('reference_type', 'order_items')
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->sum('value_out');

        return round((float) $total, 2);
    }

    /**
     * @return array<string, float>
     */
    public function stockCutByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        $daily = $this->stockCutDailyBreakdown($outletId, $dateFrom, $dateTo);
        $out = [];
        foreach ($daily as $date => $row) {
            $out[$date] = (float) ($row['amount'] ?? 0);
        }

        return $out;
    }

    /**
     * Daily Stock Cut rows for modal detail (Food / Beverage / Total).
     *
     * @return list<object>
     */
    public function buildStockCutDaily(int $outletId, string $dateFrom, string $dateTo): array
    {
        $byDate = $this->stockCutDailyBreakdown($outletId, $dateFrom, $dateTo);
        $rows = [];
        foreach ($this->dateRange($dateFrom, $dateTo) as $date) {
            $carbon = Carbon::parse($date);
            $food = round((float) ($byDate[$date]['food'] ?? 0), 2);
            $beverage = round((float) ($byDate[$date]['beverage'] ?? 0), 2);
            $amount = round((float) ($byDate[$date]['amount'] ?? ($food + $beverage)), 2);
            $rows[] = (object) [
                'id' => $date,
                'date' => $date,
                'day_name' => $carbon->locale('id')->translatedFormat('l'),
                'is_weekend' => $carbon->isWeekend(),
                'food' => $food,
                'beverage' => $beverage,
                'amount' => $amount,
            ];
        }

        return $rows;
    }

    /**
     * Detail item Stock Cut untuk 1 tanggal (opsional filter food/beverage/all).
     *
     * @return array{
     *   title: string,
     *   type: string,
     *   date: string,
     *   transactions: list<array<string, mixed>>,
     *   grand_total: float
     * }
     */
    public function buildStockCutCellDetail(int $outletId, string $date, string $bucket = 'all'): array
    {
        $bucket = strtolower(trim($bucket));
        if (! in_array($bucket, ['food', 'beverage', 'all'], true)) {
            $bucket = 'all';
        }

        $labels = [
            'food' => 'Food',
            'beverage' => 'Beverage',
            'all' => 'Semua',
        ];
        $empty = [
            'title' => 'Stock Cut · '.($labels[$bucket] ?? $bucket).' — '.$date,
            'type' => $bucket,
            'date' => $date,
            'transactions' => [],
            'grand_total' => 0.0,
        ];

        if (! Schema::hasTable('stock_cut_details') || ! Schema::hasTable('stock_cut_logs')) {
            return $empty;
        }

        $rows = DB::table('stock_cut_details as d')
            ->join('stock_cut_logs as l', 'd.stock_cut_log_id', '=', 'l.id')
            ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('units as u', 'i.small_unit_id', '=', 'u.id')
            ->leftJoin('warehouse_outlets as wo', 'd.warehouse_outlet_id', '=', 'wo.id')
            ->where('l.outlet_id', $outletId)
            ->where('l.status', 'success')
            ->whereDate('l.tanggal', $date)
            ->orderBy('l.id')
            ->orderByRaw("COALESCE(c.name, 'Tanpa Category')")
            ->orderBy('i.name')
            ->get([
                'l.id as log_id',
                'l.type_filter',
                'l.created_at as cut_at',
                'wo.name as warehouse_name',
                'i.name as item_name',
                DB::raw("COALESCE(c.name, 'Tanpa Category') as category_name"),
                'u.name as unit_name',
                'd.qty_small',
                'd.cost_per_small',
                'd.value_out',
            ]);

        if ($rows->isEmpty()) {
            return $empty;
        }

        $grouped = [];
        $grandTotal = 0.0;
        foreach ($rows as $row) {
            $fb = $this->stockCutFoodBeverageBucket(
                (string) ($row->type_filter ?? ''),
                (string) ($row->warehouse_name ?? '')
            );
            if ($bucket !== 'all' && $fb !== $bucket) {
                continue;
            }

            $logId = (int) $row->log_id;
            if (! isset($grouped[$logId])) {
                $tf = (string) ($row->type_filter ?? '');
                $source = match (true) {
                    $tf === 'food' => 'Food',
                    $tf === 'beverages', $tf === 'beverage' => 'Beverage',
                    $bucket === 'food' => 'Food',
                    $bucket === 'beverage' => 'Beverage',
                    default => 'Semua Type',
                };

                $grouped[$logId] = [
                    'source' => $source,
                    'number' => 'SC-'.$logId,
                    'ordered_by' => '-',
                    'warehouse' => (string) ($row->warehouse_name ?? '-'),
                    'total' => 0.0,
                    'items' => [],
                ];
            }

            $qty = (float) ($row->qty_small ?? 0);
            $mac = (float) ($row->cost_per_small ?? 0);
            $subtotal = (float) ($row->value_out ?? ($qty * $mac));
            $grouped[$logId]['total'] = round($grouped[$logId]['total'] + $subtotal, 2);
            $grouped[$logId]['items'][] = [
                'name' => (string) ($row->item_name ?? '-'),
                'category' => (string) ($row->category_name ?? 'Tanpa Category'),
                'qty' => $qty,
                'unit' => (string) ($row->unit_name ?? 'small'),
                'price' => $mac,
                'subtotal' => round($subtotal, 2),
            ];
            $grandTotal += $subtotal;
        }

        // When filtering food/beverage on a "Semua" log, split warehouse display
        $transactions = [];
        foreach ($grouped as $txn) {
            if ($txn['items'] === []) {
                continue;
            }
            $transactions[] = $txn;
        }

        return [
            'title' => $empty['title'],
            'type' => $bucket,
            'date' => $date,
            'transactions' => array_values($transactions),
            'grand_total' => round($grandTotal, 2),
        ];
    }

    /**
     * @return array<string, array{food: float, beverage: float, amount: float}>
     */
    private function stockCutDailyBreakdown(int $outletId, string $dateFrom, string $dateTo): array
    {
        if (! Schema::hasTable('stock_cut_details') || ! Schema::hasTable('stock_cut_logs')) {
            return [];
        }

        $rows = DB::table('stock_cut_details as d')
            ->join('stock_cut_logs as l', 'd.stock_cut_log_id', '=', 'l.id')
            ->leftJoin('warehouse_outlets as wo', 'd.warehouse_outlet_id', '=', 'wo.id')
            ->where('l.outlet_id', $outletId)
            ->where('l.status', 'success')
            ->whereDate('l.tanggal', '>=', $dateFrom)
            ->whereDate('l.tanggal', '<=', $dateTo)
            ->get([
                DB::raw('DATE(l.tanggal) as d'),
                'l.type_filter',
                'wo.name as warehouse_name',
                'd.value_out',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $date = (string) $row->d;
            if (! isset($out[$date])) {
                $out[$date] = ['food' => 0.0, 'beverage' => 0.0, 'amount' => 0.0];
            }
            $value = (float) ($row->value_out ?? 0);
            $bucket = $this->stockCutFoodBeverageBucket(
                (string) ($row->type_filter ?? ''),
                (string) ($row->warehouse_name ?? '')
            );
            if ($bucket === 'food') {
                $out[$date]['food'] += $value;
            } elseif ($bucket === 'beverage') {
                $out[$date]['beverage'] += $value;
            }
            $out[$date]['amount'] += $value;
        }

        foreach ($out as $date => $row) {
            $out[$date]['food'] = round($row['food'], 2);
            $out[$date]['beverage'] = round($row['beverage'], 2);
            $out[$date]['amount'] = round($row['amount'], 2);
        }

        return $out;
    }

    private function stockCutFoodBeverageBucket(string $typeFilter, string $warehouseName): string
    {
        $typeFilter = strtolower(trim($typeFilter));
        if ($typeFilter === 'food') {
            return 'food';
        }
        if ($typeFilter === 'beverages' || $typeFilter === 'beverage') {
            return 'beverage';
        }

        $wh = strtolower(trim($warehouseName));
        if ($wh === 'kitchen' || str_contains($wh, 'kitchen') || str_contains($wh, 'mk')) {
            return 'food';
        }
        if ($wh === 'bar' || str_contains($wh, 'bar')) {
            return 'beverage';
        }

        return 'other';
    }

    /**
     * Category Cost Outlet (outlet_internal_use_waste) using stored subtotal_mac.
     * Status rules mirror Report Universal: approval types = APPROVED only.
     *
     * @return array{total: float, count: int, by_type: list<array{type: string, label: string, amount: float}>}
     */
    public function sumCategoryCost(int $outletId, string $dateFrom, string $dateTo): array
    {
        $empty = ['total' => 0.0, 'count' => 0, 'by_type' => $this->emptyCategoryCostByType()];
        if (! Schema::hasTable('outlet_internal_use_waste_headers')) {
            return $empty;
        }

        $rows = $this->categoryCostHeaderQuery($outletId, $dateFrom, $dateTo)
            ->selectRaw("
                CASE WHEN h.type = 'stock_cut' THEN 'usage' ELSE h.type END as type_key,
                COALESCE(SUM(h.subtotal_mac), 0) as total,
                COUNT(*) as cnt
            ")
            ->groupBy(DB::raw("CASE WHEN h.type = 'stock_cut' THEN 'usage' ELSE h.type END"))
            ->get();

        $map = [];
        $total = 0.0;
        $count = 0;
        foreach ($rows as $row) {
            $type = (string) ($row->type_key ?? '');
            if ($type === '' || ! isset(self::CATEGORY_COST_TYPE_LABELS[$type])) {
                continue;
            }
            $amount = round((float) ($row->total ?? 0), 2);
            $map[$type] = $amount;
            $total += $amount;
            $count += (int) ($row->cnt ?? 0);
        }

        $byType = [];
        foreach (self::CATEGORY_COST_TYPE_LABELS as $type => $label) {
            $byType[] = [
                'type' => $type,
                'label' => $label,
                'amount' => round((float) ($map[$type] ?? 0), 2),
            ];
        }

        return [
            'total' => round($total, 2),
            'count' => $count,
            'by_type' => $byType,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function categoryCostByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        if (! Schema::hasTable('outlet_internal_use_waste_headers')) {
            return [];
        }

        return $this->categoryCostHeaderQuery($outletId, $dateFrom, $dateTo)
            ->selectRaw('DATE(h.date) as d, COALESCE(SUM(h.subtotal_mac), 0) as total')
            ->groupBy(DB::raw('DATE(h.date)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Daily Category Cost per type + total.
     *
     * @return array{rows: list<object>, type_columns: list<array{key: string, label: string}>}
     */
    public function buildCategoryCostDaily(int $outletId, string $dateFrom, string $dateTo): array
    {
        $typeColumns = [];
        foreach (self::CATEGORY_COST_TYPE_LABELS as $key => $label) {
            $typeColumns[] = ['key' => $key, 'label' => $label];
        }

        $byDateType = [];
        if (Schema::hasTable('outlet_internal_use_waste_headers')) {
            $agg = $this->categoryCostHeaderQuery($outletId, $dateFrom, $dateTo)
                ->selectRaw("
                    DATE(h.date) as d,
                    CASE WHEN h.type = 'stock_cut' THEN 'usage' ELSE h.type END as type_key,
                    COALESCE(SUM(h.subtotal_mac), 0) as total
                ")
                ->groupBy(DB::raw('DATE(h.date)'), DB::raw("CASE WHEN h.type = 'stock_cut' THEN 'usage' ELSE h.type END"))
                ->get();

            foreach ($agg as $row) {
                $d = (string) $row->d;
                $type = (string) ($row->type_key ?? '');
                if ($type === '' || ! isset(self::CATEGORY_COST_TYPE_LABELS[$type])) {
                    continue;
                }
                $byDateType[$d][$type] = round((float) ($row->total ?? 0), 2);
            }
        }

        $rows = [];
        foreach ($this->dateRange($dateFrom, $dateTo) as $date) {
            $carbon = Carbon::parse($date);
            $payload = [
                'id' => $date,
                'date' => $date,
                'day_name' => $carbon->locale('id')->translatedFormat('l'),
                'is_weekend' => $carbon->isWeekend(),
            ];
            $total = 0.0;
            foreach (self::CATEGORY_COST_TYPE_LABELS as $type => $_label) {
                $amount = (float) ($byDateType[$date][$type] ?? 0);
                $payload[$type] = $amount;
                $total += $amount;
            }
            $payload['total'] = round($total, 2);
            $rows[] = (object) $payload;
        }

        return [
            'rows' => $rows,
            'type_columns' => $typeColumns,
        ];
    }

    /**
     * Detail transaksi + item Category Cost untuk 1 tanggal (opsional filter type).
     *
     * @return array{
     *   title: string,
     *   type: string,
     *   date: string,
     *   transactions: list<array<string, mixed>>,
     *   grand_total: float
     * }
     */
    public function buildCategoryCostCellDetail(int $outletId, string $date, string $typeKey = 'all'): array
    {
        $typeKey = trim($typeKey);
        if ($typeKey === '') {
            $typeKey = 'all';
        }

        $label = $typeKey === 'all'
            ? 'Semua Type'
            : (self::CATEGORY_COST_TYPE_LABELS[$typeKey] ?? $typeKey);

        $empty = [
            'title' => $label.' — '.$date,
            'type' => $typeKey,
            'date' => $date,
            'transactions' => [],
            'grand_total' => 0.0,
        ];

        if (! Schema::hasTable('outlet_internal_use_waste_headers')
            || ! Schema::hasTable('outlet_internal_use_waste_details')) {
            return $empty;
        }

        if ($typeKey !== 'all' && ! isset(self::CATEGORY_COST_TYPE_LABELS[$typeKey])) {
            return $empty;
        }

        $query = $this->categoryCostHeaderQuery($outletId, $date, $date)
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select([
                'h.id',
                'h.number',
                'h.date',
                'h.type',
                'h.outlet_id',
                'h.warehouse_outlet_id',
                DB::raw('COALESCE(h.subtotal_mac, 0) as subtotal_mac'),
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name',
            ])
            ->orderBy('h.number')
            ->orderBy('h.id');

        if ($typeKey === 'usage') {
            $query->whereIn('h.type', ['usage', 'stock_cut']);
        } elseif ($typeKey !== 'all') {
            $query->where('h.type', $typeKey);
        }

        $headers = $query->get();
        if ($headers->isEmpty()) {
            return $empty;
        }

        $headersById = $headers->keyBy('id');
        $headerIds = $headers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $itemsByHeader = $this->fetchCategoryCostDetailItems($headerIds, $headersById);

        $transactions = [];
        $grandTotal = 0.0;
        foreach ($headers as $header) {
            $rawType = (string) ($header->type ?? '');
            $mappedType = $rawType === 'stock_cut' ? 'usage' : $rawType;
            $items = $itemsByHeader[(int) $header->id] ?? [];
            $amount = round((float) ($header->subtotal_mac ?? 0), 2);
            if ($amount <= 0 && $items !== []) {
                $amount = round(array_sum(array_column($items, 'subtotal')), 2);
            }
            $grandTotal += $amount;

            $transactions[] = [
                'source' => self::CATEGORY_COST_TYPE_LABELS[$mappedType] ?? $mappedType,
                'number' => (string) ($header->number ?? '-'),
                'ro_number' => null,
                'ordered_by' => (string) ($header->creator_name ?? '-'),
                'warehouse' => (string) ($header->warehouse_outlet_name ?? '-'),
                'total' => $amount,
                'items' => $items,
            ];
        }

        return [
            'title' => $label.' — '.$date,
            'type' => $typeKey,
            'date' => $date,
            'transactions' => $transactions,
            'grand_total' => round($grandTotal, 2),
        ];
    }

    /**
     * @param  list<int>  $headerIds
     * @param  \Illuminate\Support\Collection<int|string, object>  $headersById
     * @return array<int, list<array{name: string, qty: float, unit: string, price: float, subtotal: float}>>
     */
    private function fetchCategoryCostDetailItems(array $headerIds, $headersById): array
    {
        if ($headerIds === []) {
            return [];
        }

        $details = DB::table('outlet_internal_use_waste_details as d')
            ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
            ->whereIn('d.header_id', $headerIds)
            ->orderBy('d.id')
            ->get([
                'd.header_id',
                'd.item_id',
                'd.qty',
                'd.unit_id',
                'i.name as item_name',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'u.name as unit_name',
            ]);

        $itemIds = $details->pluck('item_id')->unique()->filter()->all();
        $inventoryItems = [];
        if ($itemIds !== []) {
            $inventoryItems = DB::table('outlet_food_inventory_items')
                ->whereIn('item_id', $itemIds)
                ->get()
                ->keyBy('item_id')
                ->all();
        }

        $macCache = [];
        $grouped = [];
        foreach ($details as $detail) {
            $header = $headersById->get($detail->header_id);
            $macConverted = null;

            if ($header && isset($inventoryItems[$detail->item_id])) {
                $inventoryItem = $inventoryItems[$detail->item_id];
                $macKey = "{$inventoryItem->id}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
                if (! array_key_exists($macKey, $macCache)) {
                    $macCache[$macKey] = \App\Support\CategoryCostMacResolver::resolveHistoryMacAtDate(
                        (int) $inventoryItem->id,
                        (int) $header->outlet_id,
                        (int) $header->warehouse_outlet_id,
                        (string) $header->date
                    );
                }
                $mac = $macCache[$macKey];
                if ($mac !== null) {
                    $converted = (float) $mac;
                    if ((int) $detail->unit_id === (int) ($detail->medium_unit_id ?? 0)
                        && (float) ($detail->small_conversion_qty ?? 0) > 0) {
                        $converted = (float) $mac * (float) $detail->small_conversion_qty;
                    } elseif ((int) $detail->unit_id === (int) ($detail->large_unit_id ?? 0)
                        && (float) ($detail->small_conversion_qty ?? 0) > 0
                        && (float) ($detail->medium_conversion_qty ?? 0) > 0) {
                        $converted = (float) $mac
                            * (float) $detail->small_conversion_qty
                            * (float) $detail->medium_conversion_qty;
                    }
                    $macConverted = $converted;
                }
            }

            $qty = (float) ($detail->qty ?? 0);
            $price = $macConverted !== null ? round($macConverted, 4) : 0.0;
            $subtotal = $macConverted !== null ? round($macConverted * $qty, 2) : 0.0;

            $grouped[(int) $detail->header_id][] = [
                'name' => (string) ($detail->item_name ?? '-'),
                'qty' => $qty,
                'unit' => (string) ($detail->unit_name ?? '-'),
                'price' => $price,
                'subtotal' => $subtotal,
            ];
        }

        return $grouped;
    }

    /**
     * @return list<array{type: string, label: string, amount: float}>
     */
    private function emptyCategoryCostByType(): array
    {
        $out = [];
        foreach (self::CATEGORY_COST_TYPE_LABELS as $type => $label) {
            $out[] = ['type' => $type, 'label' => $label, 'amount' => 0.0];
        }

        return $out;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function categoryCostHeaderQuery(int $outletId, string $dateFrom, string $dateTo)
    {
        $approvalTypes = self::CATEGORY_COST_APPROVAL_TYPES;

        return DB::table('outlet_internal_use_waste_headers as h')
            ->where('h.outlet_id', $outletId)
            ->whereDate('h.date', '>=', $dateFrom)
            ->whereDate('h.date', '<=', $dateTo)
            ->where(function ($q) use ($approvalTypes) {
                $q->whereNotIn('h.type', $approvalTypes)
                    ->orWhere(function ($sub) use ($approvalTypes) {
                        $sub->whereIn('h.type', $approvalTypes)
                            ->where('h.status', 'APPROVED');
                    });
            });
    }

    /**
     * Pembelian item sub-category Marketing / Chemical / Stationary
     * dari GSR + Retail Food (RWS di-skip: mirror RF).
     *
     * @return array{
     *   total: float,
     *   count: int,
     *   by_category: list<array{key: string, label: string, amount: float}>
     * }
     */
    public function sumMcsPurchase(int $outletId, string $dateFrom, string $dateTo): array
    {
        $byCat = [
            'Marketing' => 0.0,
            'Chemical' => 0.0,
            'Stationary' => 0.0,
        ];
        $headerIds = [];

        foreach ($this->mcsAllLines($outletId, $dateFrom, $dateTo) as $line) {
            $cat = $this->normalizeMcsCategory((string) ($line->category ?? ''));
            if ($cat === null) {
                continue;
            }
            $byCat[$cat] += (float) ($line->amount ?? 0);
            $headerIds[(string) ($line->source_prefix ?? 'x').$line->header_id] = true;
        }

        $byCategory = [];
        $total = 0.0;
        foreach (self::MCS_SUB_CATEGORY_LABELS as $key => $label) {
            $amount = round($byCat[$key], 2);
            $byCategory[] = [
                'key' => strtolower($key),
                'label' => $label,
                'amount' => $amount,
            ];
            $total += $amount;
        }

        return [
            'total' => round($total, 2),
            'count' => count($headerIds),
            'by_category' => $byCategory,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function mcsPurchaseByDate(int $outletId, string $dateFrom, string $dateTo): array
    {
        $map = [];
        foreach ($this->mcsAllLines($outletId, $dateFrom, $dateTo) as $line) {
            $d = (string) ($line->date ?? '');
            if ($d === '') {
                continue;
            }
            $map[$d] = ($map[$d] ?? 0) + (float) ($line->amount ?? 0);
        }

        return array_map(fn ($v) => (float) $v, $map);
    }

    /**
     * Transaksi GSR/RWS/RF berisi item MCS + item lines untuk modal.
     *
     * @return list<object>
     */
    public function listMcsPurchaseTransactions(
        int $outletId,
        string $dateFrom,
        string $dateTo,
        ?string $category = null
    ): array {
        $categoryFilter = $category ? $this->normalizeMcsCategory($category) : null;
        $grouped = [];

        $sourceLabels = [
            'gsr-' => 'GSR',
            'rws-' => 'RWS',
            'rf-' => 'Retail Food',
        ];

        foreach ($this->mcsAllLines($outletId, $dateFrom, $dateTo) as $line) {
            $cat = $this->normalizeMcsCategory((string) ($line->category ?? ''));
            if ($cat === null) {
                continue;
            }
            if ($categoryFilter !== null && $cat !== $categoryFilter) {
                continue;
            }

            $prefix = (string) ($line->source_prefix ?? 'gsr-');
            $key = $prefix.$line->header_id;
            if (! isset($grouped[$key])) {
                $grouped[$key] = (object) [
                    'id' => $key,
                    'number' => (string) ($line->number ?? '-'),
                    'date' => (string) ($line->date ?? ''),
                    'source' => $sourceLabels[$prefix] ?? strtoupper(rtrim($prefix, '-')),
                    'type' => 'mcs_purchase',
                    'creator_name' => (string) ($line->creator_name ?? '-'),
                    'amount' => 0.0,
                    'items' => [],
                ];
            }
            $amount = round((float) ($line->amount ?? 0), 2);
            $grouped[$key]->amount = round((float) $grouped[$key]->amount + $amount, 2);
            $this->accumulatePurchaseItem($grouped[$key]->items, [
                'item_name' => (string) ($line->item_name ?? '-'),
                'category' => $cat,
                'qty' => (float) ($line->qty ?? 0),
                'unit' => (string) ($line->unit_name ?? '-'),
                'price' => round((float) ($line->price ?? 0), 2),
                'amount' => $amount,
            ]);
        }

        $rows = array_values($grouped);
        foreach ($rows as $row) {
            $row->items = array_values($row->items);
        }
        usort($rows, function ($a, $b) {
            $cmp = strcmp((string) $b->date, (string) $a->date);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $b->number, (string) $a->number);
        });

        return $rows;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function mcsAllLines(int $outletId, string $dateFrom, string $dateTo)
    {
        return $this->mcsSerialGrLines($outletId, $dateFrom, $dateTo)
            ->concat($this->mcsRetailFoodLines($outletId, $dateFrom, $dateTo));
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function mcsSerialGrLines(int $outletId, string $dateFrom, string $dateTo)
    {
        if (! $this->hasSerialGrTables()) {
            return collect();
        }

        $names = array_keys(self::MCS_SUB_CATEGORY_LABELS);
        $priceSql = $this->serialGrPriceSql('it');

        return DB::table('outlet_serial_receive_items as si')
            ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
            ->join('items as it', 'si.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
            ->leftJoin('users as usr', 'h.created_by', '=', 'usr.id')
            ->whereNull('h.deleted_at')
            ->where('h.status', 'completed')
            ->where('h.outlet_id', $outletId)
            ->whereIn('sc.name', $names)
            ->whereDate('h.receive_date', '>=', $dateFrom)
            ->whereDate('h.receive_date', '<=', $dateTo)
            ->select([
                'h.id as header_id',
                'h.number',
                'h.receive_date as date',
                'sc.name as category',
                'it.name as item_name',
                'u.name as unit_name',
                'usr.nama_lengkap as creator_name',
                DB::raw("'gsr-' as source_prefix"),
                DB::raw('si.qty as qty'),
                DB::raw("({$priceSql}) as price"),
                DB::raw("si.qty * ({$priceSql}) as amount"),
            ])
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function mcsRwsLines(int $outletId, string $dateFrom, string $dateTo)
    {
        if (! Schema::hasTable('retail_warehouse_sale_items')) {
            return collect();
        }

        $names = array_keys(self::MCS_SUB_CATEGORY_LABELS);

        return DB::table('retail_warehouse_sale_items as rwsi')
            ->join('retail_warehouse_sales as rws', 'rwsi.retail_warehouse_sale_id', '=', 'rws.id')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->join('items as it', 'rwsi.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('users as usr', 'rws.created_by', '=', 'usr.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->where('c.id_outlet', $outletId)
            ->whereIn('sc.name', $names)
            ->whereDate('rws.sale_date', '>=', $dateFrom)
            ->whereDate('rws.sale_date', '<=', $dateTo)
            ->select([
                'rws.id as header_id',
                'rws.number',
                'rws.sale_date as date',
                'sc.name as category',
                'it.name as item_name',
                'rwsi.unit as unit_name',
                'usr.nama_lengkap as creator_name',
                DB::raw("'rws-' as source_prefix"),
                DB::raw('rwsi.qty as qty'),
                DB::raw('COALESCE(rwsi.price, 0) as price'),
                DB::raw('COALESCE(rwsi.subtotal, rwsi.qty * rwsi.price, 0) as amount'),
            ])
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function mcsRwsSerialLines(int $outletId, string $dateFrom, string $dateTo)
    {
        if (! Schema::hasTable('retail_warehouse_sale_serial_items')) {
            return collect();
        }

        $names = array_keys(self::MCS_SUB_CATEGORY_LABELS);

        return DB::table('retail_warehouse_sale_serial_items as rwss')
            ->join('retail_warehouse_sales as rws', 'rwss.retail_warehouse_sale_id', '=', 'rws.id')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->join('items as it', 'rwss.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('users as usr', 'rws.created_by', '=', 'usr.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->where('c.id_outlet', $outletId)
            ->whereIn('sc.name', $names)
            ->whereDate('rws.sale_date', '>=', $dateFrom)
            ->whereDate('rws.sale_date', '<=', $dateTo)
            ->select([
                'rws.id as header_id',
                'rws.number',
                'rws.sale_date as date',
                'sc.name as category',
                'it.name as item_name',
                'rwss.unit_name as unit_name',
                'usr.nama_lengkap as creator_name',
                DB::raw("'rws-' as source_prefix"),
                DB::raw('rwss.qty as qty'),
                DB::raw('COALESCE(rwss.price, 0) as price'),
                DB::raw('COALESCE(rwss.subtotal, rwss.qty * rwss.price, 0) as amount'),
            ])
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function mcsRetailFoodLines(int $outletId, string $dateFrom, string $dateTo)
    {
        if (! Schema::hasTable('retail_food_items')) {
            return collect();
        }

        $names = array_keys(self::MCS_SUB_CATEGORY_LABELS);

        return DB::table('retail_food_items as rfi')
            ->join('retail_food as rf', 'rfi.retail_food_id', '=', 'rf.id')
            ->join('items as it', DB::raw('TRIM(it.name)'), '=', DB::raw('TRIM(rfi.item_name)'))
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('users as usr', 'rf.created_by', '=', 'usr.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereIn('sc.name', $names)
            ->whereDate('rf.transaction_date', '>=', $dateFrom)
            ->whereDate('rf.transaction_date', '<=', $dateTo)
            ->select([
                'rf.id as header_id',
                'rf.retail_number as number',
                'rf.transaction_date as date',
                'sc.name as category',
                'rfi.item_name as item_name',
                'rfi.unit as unit_name',
                'usr.nama_lengkap as creator_name',
                DB::raw("'rf-' as source_prefix"),
                DB::raw('rfi.qty as qty'),
                DB::raw('COALESCE(rfi.price, 0) as price'),
                DB::raw('COALESCE(rfi.subtotal, rfi.qty * rfi.price, 0) as amount'),
            ])
            ->get();
    }

    private function normalizeMcsCategory(string $name): ?string
    {
        $name = trim($name);
        foreach (array_keys(self::MCS_SUB_CATEGORY_LABELS) as $key) {
            if (strcasecmp($name, $key) === 0) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Pembelian semua sub-category dari GSR + Retail Food (untuk pie chart).
     *
     * @return array{
     *   total: float,
     *   count: int,
     *   by_category: list<array{key: string, label: string, amount: float}>
     * }
     */
    public function sumPurchaseByCategory(int $outletId, string $dateFrom, string $dateTo): array
    {
        $byCat = [];
        $headerIds = [];

        foreach ($this->allPurchaseLines($outletId, $dateFrom, $dateTo) as $line) {
            $cat = trim((string) ($line->category ?? ''));
            if ($cat === '') {
                $cat = 'Other';
            }
            $byCat[$cat] = ($byCat[$cat] ?? 0) + (float) ($line->amount ?? 0);
            $headerIds[(string) ($line->source_prefix ?? 'x').$line->header_id] = true;
        }

        arsort($byCat);
        $byCategory = [];
        $total = 0.0;
        foreach ($byCat as $label => $amount) {
            $amount = round((float) $amount, 2);
            if ($amount <= 0) {
                continue;
            }
            $byCategory[] = [
                'key' => strtolower(str_replace(' ', '_', $label)),
                'label' => $label,
                'amount' => $amount,
            ];
            $total += $amount;
        }

        return [
            'total' => round($total, 2),
            'count' => count($headerIds),
            'by_category' => $byCategory,
        ];
    }

    /**
     * Transaksi pembelian per category (semua sub-category) untuk modal pie chart.
     *
     * @return list<object>
     */
    public function listPurchaseCategoryTransactions(
        int $outletId,
        string $dateFrom,
        string $dateTo,
        ?string $category = null
    ): array {
        $categoryFilter = $category !== null ? trim($category) : null;
        if ($categoryFilter === '') {
            $categoryFilter = null;
        }

        $grouped = [];
        $sourceLabels = [
            'gsr-' => 'GSR',
            'rws-' => 'RWS',
            'rf-' => 'Retail Food',
        ];

        foreach ($this->allPurchaseLines($outletId, $dateFrom, $dateTo) as $line) {
            $cat = trim((string) ($line->category ?? ''));
            if ($cat === '') {
                $cat = 'Other';
            }
            if ($categoryFilter !== null && strcasecmp($cat, $categoryFilter) !== 0) {
                continue;
            }

            $prefix = (string) ($line->source_prefix ?? 'gsr-');
            $key = $prefix.$line->header_id;
            if (! isset($grouped[$key])) {
                $grouped[$key] = (object) [
                    'id' => $key,
                    'number' => (string) ($line->number ?? '-'),
                    'date' => (string) ($line->date ?? ''),
                    'source' => $sourceLabels[$prefix] ?? strtoupper(rtrim($prefix, '-')),
                    'type' => 'purchase_category',
                    'creator_name' => (string) ($line->creator_name ?? '-'),
                    'amount' => 0.0,
                    'items' => [],
                ];
            }
            $amount = round((float) ($line->amount ?? 0), 2);
            $grouped[$key]->amount = round((float) $grouped[$key]->amount + $amount, 2);
            $this->accumulatePurchaseItem($grouped[$key]->items, [
                'item_name' => (string) ($line->item_name ?? '-'),
                'category' => $cat,
                'qty' => (float) ($line->qty ?? 0),
                'unit' => (string) ($line->unit_name ?? '-'),
                'price' => round((float) ($line->price ?? 0), 2),
                'amount' => $amount,
            ]);
        }

        $rows = array_values($grouped);
        foreach ($rows as $row) {
            $row->items = array_values($row->items);
        }
        usort($rows, function ($a, $b) {
            $cmp = strcmp((string) $b->date, (string) $a->date);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $b->number, (string) $a->number);
        });

        return $rows;
    }

    /**
     * Gabungkan baris item yang sama (nama+category+unit+price) agar GSR serial
     * yang tersimpan per unit tidak tampil berulang di modal.
     *
     * @param  array<string, array{item_name: string, category: string, qty: float, unit: string, price: float, amount: float}>  $items
     * @param  array{item_name: string, category: string, qty: float, unit: string, price: float, amount: float}  $item
     */
    private function accumulatePurchaseItem(array &$items, array $item): void
    {
        $key = strtolower(implode('|', [
            $item['item_name'],
            $item['category'],
            $item['unit'],
            number_format((float) $item['price'], 4, '.', ''),
        ]));

        if (! isset($items[$key])) {
            $items[$key] = [
                'item_name' => $item['item_name'],
                'category' => $item['category'],
                'qty' => round((float) $item['qty'], 4),
                'unit' => $item['unit'],
                'price' => round((float) $item['price'], 2),
                'amount' => round((float) $item['amount'], 2),
            ];

            return;
        }

        $items[$key]['qty'] = round((float) $items[$key]['qty'] + (float) $item['qty'], 4);
        $items[$key]['amount'] = round((float) $items[$key]['amount'] + (float) $item['amount'], 2);
    }

    /**
     * Semua line pembelian GSR + Retail Food (tanpa filter MCS).
     * RWS di-skip agar tidak double dengan Retail Food.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function allPurchaseLines(int $outletId, string $dateFrom, string $dateTo)
    {
        return $this->allPurchaseSerialGrLines($outletId, $dateFrom, $dateTo)
            ->concat($this->allPurchaseRetailFoodLines($outletId, $dateFrom, $dateTo));
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function allPurchaseSerialGrLines(int $outletId, string $dateFrom, string $dateTo)
    {
        if (! $this->hasSerialGrTables()) {
            return collect();
        }

        $priceSql = $this->serialGrPriceSql('it');

        return DB::table('outlet_serial_receive_items as si')
            ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
            ->join('items as it', 'si.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
            ->leftJoin('users as usr', 'h.created_by', '=', 'usr.id')
            ->whereNull('h.deleted_at')
            ->where('h.status', 'completed')
            ->where('h.outlet_id', $outletId)
            ->whereDate('h.receive_date', '>=', $dateFrom)
            ->whereDate('h.receive_date', '<=', $dateTo)
            ->select([
                'h.id as header_id',
                'h.number',
                'h.receive_date as date',
                'sc.name as category',
                'it.name as item_name',
                'u.name as unit_name',
                'usr.nama_lengkap as creator_name',
                DB::raw("'gsr-' as source_prefix"),
                DB::raw('si.qty as qty'),
                DB::raw("({$priceSql}) as price"),
                DB::raw("si.qty * ({$priceSql}) as amount"),
            ])
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function allPurchaseRwsLines(int $outletId, string $dateFrom, string $dateTo)
    {
        if (! Schema::hasTable('retail_warehouse_sale_items')) {
            return collect();
        }

        return DB::table('retail_warehouse_sale_items as rwsi')
            ->join('retail_warehouse_sales as rws', 'rwsi.retail_warehouse_sale_id', '=', 'rws.id')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->join('items as it', 'rwsi.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('users as usr', 'rws.created_by', '=', 'usr.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->where('c.id_outlet', $outletId)
            ->whereDate('rws.sale_date', '>=', $dateFrom)
            ->whereDate('rws.sale_date', '<=', $dateTo)
            ->select([
                'rws.id as header_id',
                'rws.number',
                'rws.sale_date as date',
                'sc.name as category',
                'it.name as item_name',
                'rwsi.unit as unit_name',
                'usr.nama_lengkap as creator_name',
                DB::raw("'rws-' as source_prefix"),
                DB::raw('rwsi.qty as qty'),
                DB::raw('COALESCE(rwsi.price, 0) as price'),
                DB::raw('COALESCE(rwsi.subtotal, rwsi.qty * rwsi.price, 0) as amount'),
            ])
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function allPurchaseRwsSerialLines(int $outletId, string $dateFrom, string $dateTo)
    {
        if (! Schema::hasTable('retail_warehouse_sale_serial_items')) {
            return collect();
        }

        return DB::table('retail_warehouse_sale_serial_items as rwss')
            ->join('retail_warehouse_sales as rws', 'rwss.retail_warehouse_sale_id', '=', 'rws.id')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->join('items as it', 'rwss.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('users as usr', 'rws.created_by', '=', 'usr.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->where('c.id_outlet', $outletId)
            ->whereDate('rws.sale_date', '>=', $dateFrom)
            ->whereDate('rws.sale_date', '<=', $dateTo)
            ->select([
                'rws.id as header_id',
                'rws.number',
                'rws.sale_date as date',
                'sc.name as category',
                'it.name as item_name',
                'rwss.unit_name as unit_name',
                'usr.nama_lengkap as creator_name',
                DB::raw("'rws-' as source_prefix"),
                DB::raw('rwss.qty as qty'),
                DB::raw('COALESCE(rwss.price, 0) as price'),
                DB::raw('COALESCE(rwss.subtotal, rwss.qty * rwss.price, 0) as amount'),
            ])
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function allPurchaseRetailFoodLines(int $outletId, string $dateFrom, string $dateTo)
    {
        if (! Schema::hasTable('retail_food_items')) {
            return collect();
        }

        return DB::table('retail_food_items as rfi')
            ->join('retail_food as rf', 'rfi.retail_food_id', '=', 'rf.id')
            ->join('items as it', DB::raw('TRIM(it.name)'), '=', DB::raw('TRIM(rfi.item_name)'))
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('users as usr', 'rf.created_by', '=', 'usr.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereDate('rf.transaction_date', '>=', $dateFrom)
            ->whereDate('rf.transaction_date', '<=', $dateTo)
            ->select([
                'rf.id as header_id',
                'rf.retail_number as number',
                'rf.transaction_date as date',
                'sc.name as category',
                'rfi.item_name as item_name',
                'rfi.unit as unit_name',
                'usr.nama_lengkap as creator_name',
                DB::raw("'rf-' as source_prefix"),
                DB::raw('rfi.qty as qty'),
                DB::raw('COALESCE(rfi.price, 0) as price'),
                DB::raw('COALESCE(rfi.subtotal, rfi.qty * rfi.price, 0) as amount'),
            ])
            ->get();
    }

    /**
     * Pembayaran OUTLET_CITY_LEDGER dari order_payment.
     *
     * @return array{amount: float, bill: float, count: int}
     */
    public function sumOutletCityLedger(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $empty = ['amount' => 0.0, 'bill' => 0.0, 'count' => 0];
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return $empty;
        }

        $row = DB::table('order_payment as op')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->where('o.kode_outlet', $qrCode)
            ->whereDate('o.created_at', '>=', $dateFrom)
            ->whereDate('o.created_at', '<=', $dateTo)
            ->where('o.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('op.payment_code', 'OUTLET_CITY_LEDGER')
                    ->orWhere('op.payment_type', 'OUTLET_CITY_LEDGER');
            })
            ->selectRaw('
                COALESCE(SUM(op.amount), 0) as amount,
                COUNT(*) as cnt
            ')
            ->first();

        return [
            'amount' => round((float) ($row->amount ?? 0), 2),
            'bill' => round((float) ($row->amount ?? 0), 2),
            'count' => (int) ($row->cnt ?? 0),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function outletCityLedgerByDate(?string $qrCode, string $dateFrom, string $dateTo): array
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return [];
        }

        return DB::table('order_payment as op')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->where('o.kode_outlet', $qrCode)
            ->whereDate('o.created_at', '>=', $dateFrom)
            ->whereDate('o.created_at', '<=', $dateTo)
            ->where('o.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('op.payment_code', 'OUTLET_CITY_LEDGER')
                    ->orWhere('op.payment_type', 'OUTLET_CITY_LEDGER');
            })
            ->selectRaw('DATE(o.created_at) as d, SUM(COALESCE(op.amount, 0)) as total')
            ->groupBy(DB::raw('DATE(o.created_at)'))
            ->pluck('total', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @param  array<string, float>  ...$maps
     * @return array<string, float>
     */
    private function mergeDateMaps(array ...$maps): array
    {
        $out = [];
        foreach ($maps as $map) {
            foreach ($map as $d => $v) {
                $out[$d] = ($out[$d] ?? 0) + (float) $v;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function dateRange(string $dateFrom, string $dateTo): array
    {
        $dates = [];
        $current = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->startOfDay();
        while ($current->lte($end)) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Transfer antar outlet: IN (masuk ke WH outlet ini) / OUT (keluar dari WH outlet ini).
     *
     * @return array{in_total: float, out_total: float, net_total: float, count: int}
     */
    public function sumOutletTransferMovements(int $outletId, string $dateFrom, string $dateTo): array
    {
        $whIds = DB::table('warehouse_outlets')
            ->where('outlet_id', $outletId)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($whIds === []) {
            return ['in_total' => 0.0, 'out_total' => 0.0, 'net_total' => 0.0, 'count' => 0];
        }

        $inTotal = (float) DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $outletId)
            ->whereIn('warehouse_outlet_id', $whIds)
            ->where('reference_type', 'outlet_transfer')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->sum('value_in');
        $outTotal = (float) DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $outletId)
            ->whereIn('warehouse_outlet_id', $whIds)
            ->where('reference_type', 'outlet_transfer')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->sum('value_out');

        $count = (int) DB::table('outlet_transfers as t')
            ->join('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
            ->join('warehouse_outlets as wt', 't.warehouse_outlet_to_id', '=', 'wt.id')
            ->where(function ($q) use ($outletId) {
                $q->where('wf.outlet_id', $outletId)->orWhere('wt.outlet_id', $outletId);
            })
            ->whereBetween('t.transfer_date', [$dateFrom, $dateTo])
            ->count();
        if ($count === 0) {
            $count = (int) DB::table('outlet_food_inventory_cards')
                ->where('id_outlet', $outletId)
                ->where('reference_type', 'outlet_transfer')
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->selectRaw('COUNT(DISTINCT reference_id) as c')
                ->value('c');
        }

        return [
            'in_total' => round($inTotal, 2),
            'out_total' => round($outTotal, 2),
            'net_total' => round($inTotal - $outTotal, 2),
            'count' => $count,
        ];
    }

    /**
     * @return array{total: float, count: int, by_warehouse: list<array{warehouse_id: int, warehouse_name: string, amount: float}>}
     */
    public function sumOutletAdjustmentMovements(int $outletId, string $dateFrom, string $dateTo): array
    {
        $rows = DB::table('outlet_food_inventory_cards as c')
            ->join('warehouse_outlets as wo', 'c.warehouse_outlet_id', '=', 'wo.id')
            ->where('c.id_outlet', $outletId)
            ->where('c.reference_type', 'outlet_stock_adjustment')
            ->whereBetween('c.date', [$dateFrom, $dateTo])
            ->groupBy('c.warehouse_outlet_id', 'wo.name')
            ->orderBy('wo.name')
            ->selectRaw('c.warehouse_outlet_id as warehouse_id, wo.name as warehouse_name, COALESCE(SUM(COALESCE(c.value_in,0) - COALESCE(c.value_out,0)),0) as amount')
            ->get();

        $byWh = [];
        $total = 0.0;
        foreach ($rows as $r) {
            $amt = round((float) $r->amount, 2);
            $byWh[] = [
                'warehouse_id' => (int) $r->warehouse_id,
                'warehouse_name' => (string) $r->warehouse_name,
                'amount' => $amt,
            ];
            $total += $amt;
        }

        $count = (int) DB::table('outlet_food_inventory_adjustments')
            ->where('id_outlet', $outletId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->count();
        if ($count === 0) {
            $count = (int) DB::table('outlet_food_inventory_cards')
                ->where('id_outlet', $outletId)
                ->where('reference_type', 'outlet_stock_adjustment')
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->selectRaw('COUNT(DISTINCT reference_id) as c')
                ->value('c');
        }

        return [
            'total' => round($total, 2),
            'count' => $count,
            'by_warehouse' => $byWh,
        ];
    }

    /**
     * @return array{total: float, count: int, flows: list<array{from_warehouse_id: int, from_warehouse_name: string, to_warehouse_id: int, to_warehouse_name: string, amount: float, count: int}>}
     */
    public function sumInternalWarehouseTransferMovements(int $outletId, string $dateFrom, string $dateTo): array
    {
        $transfers = DB::table('internal_warehouse_transfers as t')
            ->join('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
            ->join('warehouse_outlets as wt', 't.warehouse_outlet_to_id', '=', 'wt.id')
            ->leftJoin('outlet_food_inventory_cards as c', function ($join) use ($outletId) {
                $join->on('c.reference_id', '=', 't.id')
                    ->where('c.reference_type', '=', 'internal_warehouse_transfer')
                    ->where('c.id_outlet', '=', $outletId)
                    ->whereColumn('c.warehouse_outlet_id', 't.warehouse_outlet_from_id');
            })
            ->where('t.outlet_id', $outletId)
            ->whereBetween('t.transfer_date', [$dateFrom, $dateTo])
            ->groupBy('t.warehouse_outlet_from_id', 'wf.name', 't.warehouse_outlet_to_id', 'wt.name')
            ->orderBy('wf.name')
            ->orderBy('wt.name')
            ->selectRaw('
                t.warehouse_outlet_from_id as from_warehouse_id,
                wf.name as from_warehouse_name,
                t.warehouse_outlet_to_id as to_warehouse_id,
                wt.name as to_warehouse_name,
                COALESCE(SUM(COALESCE(c.value_out, 0)), 0) as amount,
                COUNT(DISTINCT t.id) as cnt
            ')
            ->get();

        // Fallback ke total_cost item bila kartu kosong
        if ($transfers->sum(fn ($r) => (float) $r->amount) <= 0) {
            $transfers = DB::table('internal_warehouse_transfers as t')
                ->join('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
                ->join('warehouse_outlets as wt', 't.warehouse_outlet_to_id', '=', 'wt.id')
                ->leftJoin('internal_warehouse_transfer_items as i', 'i.internal_warehouse_transfer_id', '=', 't.id')
                ->where('t.outlet_id', $outletId)
                ->whereBetween('t.transfer_date', [$dateFrom, $dateTo])
                ->groupBy('t.warehouse_outlet_from_id', 'wf.name', 't.warehouse_outlet_to_id', 'wt.name')
                ->orderBy('wf.name')
                ->orderBy('wt.name')
                ->selectRaw('
                    t.warehouse_outlet_from_id as from_warehouse_id,
                    wf.name as from_warehouse_name,
                    t.warehouse_outlet_to_id as to_warehouse_id,
                    wt.name as to_warehouse_name,
                    COALESCE(SUM(COALESCE(i.total_cost, 0)), 0) as amount,
                    COUNT(DISTINCT t.id) as cnt
                ')
                ->get();
        }

        $flows = [];
        $total = 0.0;
        $count = 0;
        foreach ($transfers as $r) {
            $amt = round((float) $r->amount, 2);
            $cnt = (int) $r->cnt;
            $flows[] = [
                'from_warehouse_id' => (int) $r->from_warehouse_id,
                'from_warehouse_name' => (string) $r->from_warehouse_name,
                'to_warehouse_id' => (int) $r->to_warehouse_id,
                'to_warehouse_name' => (string) $r->to_warehouse_name,
                'amount' => $amt,
                'count' => $cnt,
            ];
            $total += $amt;
            $count += $cnt;
        }

        return [
            'total' => round($total, 2),
            'count' => $count,
            'flows' => $flows,
        ];
    }

    /**
     * @return array{
     *   material_total: float,
     *   finished_total: float,
     *   count: int,
     *   by_warehouse: list<array{warehouse_id: int, warehouse_name: string, material_cost: float, finished_cost: float}>
     * }
     */
    public function sumOutletWipMovements(int $outletId, string $dateFrom, string $dateTo): array
    {
        $rows = DB::table('outlet_food_inventory_cards as c')
            ->join('warehouse_outlets as wo', 'c.warehouse_outlet_id', '=', 'wo.id')
            ->where('c.id_outlet', $outletId)
            ->where('c.reference_type', 'outlet_wip_production')
            ->whereBetween('c.date', [$dateFrom, $dateTo])
            ->groupBy('c.warehouse_outlet_id', 'wo.name')
            ->orderBy('wo.name')
            ->selectRaw('
                c.warehouse_outlet_id as warehouse_id,
                wo.name as warehouse_name,
                COALESCE(SUM(COALESCE(c.value_out,0)),0) as material_cost,
                COALESCE(SUM(COALESCE(c.value_in,0)),0) as finished_cost
            ')
            ->get();

        $byWh = [];
        $mat = 0.0;
        $fin = 0.0;
        foreach ($rows as $r) {
            $m = round((float) $r->material_cost, 2);
            $f = round((float) $r->finished_cost, 2);
            $byWh[] = [
                'warehouse_id' => (int) $r->warehouse_id,
                'warehouse_name' => (string) $r->warehouse_name,
                'material_cost' => $m,
                'finished_cost' => $f,
            ];
            $mat += $m;
            $fin += $f;
        }

        $count = (int) DB::table('outlet_wip_production_headers')
            ->where('outlet_id', $outletId)
            ->whereBetween('production_date', [$dateFrom, $dateTo])
            ->count();
        if ($count === 0) {
            $count = (int) DB::table('outlet_food_inventory_cards')
                ->where('id_outlet', $outletId)
                ->where('reference_type', 'outlet_wip_production')
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->selectRaw('COUNT(DISTINCT reference_id) as c')
                ->value('c');
        }

        return [
            'material_total' => round($mat, 2),
            'finished_total' => round($fin, 2),
            'count' => $count,
            'by_warehouse' => $byWh,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOutletTransferTransactions(int $outletId, string $dateFrom, string $dateTo, string $search = ''): array
    {
        $search = trim($search);
        $q = DB::table('outlet_transfers as t')
            ->join('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
            ->join('warehouse_outlets as wt', 't.warehouse_outlet_to_id', '=', 'wt.id')
            ->leftJoin('tbl_data_outlet as ofrom', 'wf.outlet_id', '=', 'ofrom.id_outlet')
            ->leftJoin('tbl_data_outlet as oto', 'wt.outlet_id', '=', 'oto.id_outlet')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->where(function ($qq) use ($outletId) {
                $qq->where('wf.outlet_id', $outletId)->orWhere('wt.outlet_id', $outletId);
            })
            ->whereBetween('t.transfer_date', [$dateFrom, $dateTo])
            ->orderByDesc('t.transfer_date')
            ->orderByDesc('t.id')
            ->select([
                't.id',
                't.transfer_number',
                't.transfer_date',
                't.status',
                't.notes',
                't.created_by',
                'u.nama_lengkap as created_by_name',
                'wf.id as from_warehouse_id',
                'wf.name as from_warehouse_name',
                'wt.id as to_warehouse_id',
                'wt.name as to_warehouse_name',
                'ofrom.id_outlet as from_outlet_id',
                'ofrom.nama_outlet as from_outlet_name',
                'oto.id_outlet as to_outlet_id',
                'oto.nama_outlet as to_outlet_name',
            ]);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(function ($qq) use ($like) {
                $qq->where('t.transfer_number', 'like', $like)
                    ->orWhere('ofrom.nama_outlet', 'like', $like)
                    ->orWhere('oto.nama_outlet', 'like', $like)
                    ->orWhere('u.nama_lengkap', 'like', $like)
                    ->orWhere('wf.name', 'like', $like)
                    ->orWhere('wt.name', 'like', $like);
            });
        }

        $rows = $q->limit(200)->get();
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $valueMap = [];
        if ($ids !== []) {
            $vals = DB::table('outlet_food_inventory_cards')
                ->where('reference_type', 'outlet_transfer')
                ->whereIn('reference_id', $ids)
                ->where('id_outlet', $outletId)
                ->groupBy('reference_id')
                ->selectRaw('reference_id, SUM(COALESCE(value_in,0)) as vin, SUM(COALESCE(value_out,0)) as vout')
                ->get();
            foreach ($vals as $v) {
                $valueMap[(int) $v->reference_id] = [
                    'value_in' => round((float) $v->vin, 2),
                    'value_out' => round((float) $v->vout, 2),
                ];
            }
        }

        return $rows->map(function ($r) use ($valueMap, $outletId) {
            $vals = $valueMap[(int) $r->id] ?? ['value_in' => 0.0, 'value_out' => 0.0];
            $direction = (int) $r->to_outlet_id === $outletId && (int) $r->from_outlet_id !== $outletId
                ? 'in'
                : ((int) $r->from_outlet_id === $outletId && (int) $r->to_outlet_id !== $outletId ? 'out' : 'internal');

            return [
                'id' => (int) $r->id,
                'number' => (string) $r->transfer_number,
                'date' => (string) $r->transfer_date,
                'status' => (string) ($r->status ?? ''),
                'notes' => (string) ($r->notes ?? ''),
                'created_by' => (string) ($r->created_by_name ?? '-'),
                'from_outlet' => (string) ($r->from_outlet_name ?? '-'),
                'to_outlet' => (string) ($r->to_outlet_name ?? '-'),
                'from_warehouse' => (string) ($r->from_warehouse_name ?? '-'),
                'to_warehouse' => (string) ($r->to_warehouse_name ?? '-'),
                'value_in' => $vals['value_in'],
                'value_out' => $vals['value_out'],
                'amount' => round(max($vals['value_in'], $vals['value_out']), 2),
                'direction' => $direction,
            ];
        })->values()->all();
    }

    /**
     * @return array{transaction: array<string, mixed>|null, items: list<array<string, mixed>>}
     */
    public function detailOutletTransferTransaction(int $outletId, int $transferId): array
    {
        $header = DB::table('outlet_transfers as t')
            ->join('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
            ->join('warehouse_outlets as wt', 't.warehouse_outlet_to_id', '=', 'wt.id')
            ->leftJoin('tbl_data_outlet as ofrom', 'wf.outlet_id', '=', 'ofrom.id_outlet')
            ->leftJoin('tbl_data_outlet as oto', 'wt.outlet_id', '=', 'oto.id_outlet')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->where('t.id', $transferId)
            ->where(function ($qq) use ($outletId) {
                $qq->where('wf.outlet_id', $outletId)->orWhere('wt.outlet_id', $outletId);
            })
            ->first([
                't.id',
                't.transfer_number',
                't.transfer_date',
                't.status',
                't.notes',
                'u.nama_lengkap as created_by_name',
                'wf.name as from_warehouse_name',
                'wt.name as to_warehouse_name',
                'ofrom.nama_outlet as from_outlet_name',
                'oto.nama_outlet as to_outlet_name',
                'ofrom.id_outlet as from_outlet_id',
                'oto.id_outlet as to_outlet_id',
            ]);

        $vals = DB::table('outlet_food_inventory_cards')
            ->where('reference_type', 'outlet_transfer')
            ->where('reference_id', $transferId)
            ->where('id_outlet', $outletId)
            ->selectRaw('SUM(COALESCE(value_in,0)) as vin, SUM(COALESCE(value_out,0)) as vout')
            ->first();

        $txn = $header ? [
            'id' => (int) $header->id,
            'number' => (string) $header->transfer_number,
            'date' => (string) $header->transfer_date,
            'status' => (string) ($header->status ?? ''),
            'notes' => (string) ($header->notes ?? ''),
            'created_by' => (string) ($header->created_by_name ?? '-'),
            'from_outlet' => (string) ($header->from_outlet_name ?? '-'),
            'to_outlet' => (string) ($header->to_outlet_name ?? '-'),
            'from_warehouse' => (string) ($header->from_warehouse_name ?? '-'),
            'to_warehouse' => (string) ($header->to_warehouse_name ?? '-'),
            'value_in' => round((float) ($vals->vin ?? 0), 2),
            'value_out' => round((float) ($vals->vout ?? 0), 2),
            'amount' => round(max((float) ($vals->vin ?? 0), (float) ($vals->vout ?? 0)), 2),
        ] : null;

        $items = DB::table('outlet_food_inventory_cards as c')
            ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('warehouse_outlets as wo', 'c.warehouse_outlet_id', '=', 'wo.id')
            ->where('c.reference_type', 'outlet_transfer')
            ->where('c.reference_id', $transferId)
            ->where('c.id_outlet', $outletId)
            ->orderBy('i.name')
            ->get([
                'c.id',
                'i.name as item_name',
                'i.sku',
                'wo.name as warehouse_name',
                'c.in_qty_small',
                'c.out_qty_small',
                'c.cost_per_small',
                'c.value_in',
                'c.value_out',
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'item_name' => (string) $r->item_name,
                'sku' => (string) ($r->sku ?? ''),
                'warehouse_name' => (string) ($r->warehouse_name ?? ''),
                'qty_in' => (float) $r->in_qty_small,
                'qty_out' => (float) $r->out_qty_small,
                'cost_per_small' => (float) $r->cost_per_small,
                'value_in' => (float) $r->value_in,
                'value_out' => (float) $r->value_out,
                'amount' => round((float) $r->value_in + (float) $r->value_out, 2),
            ])
            ->values()
            ->all();

        if ($items === []) {
            $items = DB::table('outlet_transfer_items as ti')
                ->join('items as i', 'ti.item_id', '=', 'i.id')
                ->where('ti.outlet_transfer_id', $transferId)
                ->get(['ti.id', 'i.name as item_name', 'i.sku', 'ti.qty_small', 'ti.quantity'])
                ->map(fn ($r) => [
                    'id' => (int) $r->id,
                    'item_name' => (string) $r->item_name,
                    'sku' => (string) ($r->sku ?? ''),
                    'warehouse_name' => '',
                    'qty_in' => 0.0,
                    'qty_out' => (float) ($r->qty_small ?: $r->quantity),
                    'cost_per_small' => 0.0,
                    'value_in' => 0.0,
                    'value_out' => 0.0,
                    'amount' => 0.0,
                ])
                ->values()
                ->all();
        }

        return ['transaction' => $txn, 'items' => $items];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOutletAdjustmentTransactions(int $outletId, string $dateFrom, string $dateTo, string $search = ''): array
    {
        $search = trim($search);
        $q = DB::table('outlet_food_inventory_adjustments as a')
            ->leftJoin('warehouse_outlets as wo', 'a.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'a.created_by', '=', 'u.id')
            ->where('a.id_outlet', $outletId)
            ->whereBetween('a.date', [$dateFrom, $dateTo])
            ->orderByDesc('a.date')
            ->orderByDesc('a.id')
            ->select([
                'a.id',
                'a.number',
                'a.date',
                'a.type',
                'a.reason',
                'a.status',
                'u.nama_lengkap as created_by_name',
                'wo.id as warehouse_id',
                'wo.name as warehouse_name',
            ]);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(function ($qq) use ($like) {
                $qq->where('a.number', 'like', $like)
                    ->orWhere('a.reason', 'like', $like)
                    ->orWhere('wo.name', 'like', $like)
                    ->orWhere('u.nama_lengkap', 'like', $like);
            });
        }
        $rows = $q->limit(200)->get();
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $valueMap = [];
        if ($ids !== []) {
            foreach (
                DB::table('outlet_food_inventory_cards')
                    ->where('reference_type', 'outlet_stock_adjustment')
                    ->whereIn('reference_id', $ids)
                    ->where('id_outlet', $outletId)
                    ->groupBy('reference_id')
                    ->selectRaw('reference_id, SUM(COALESCE(value_in,0)-COALESCE(value_out,0)) as net, SUM(COALESCE(value_in,0)) as vin, SUM(COALESCE(value_out,0)) as vout')
                    ->get() as $v
            ) {
                $valueMap[(int) $v->reference_id] = [
                    'amount' => round((float) $v->net, 2),
                    'value_in' => round((float) $v->vin, 2),
                    'value_out' => round((float) $v->vout, 2),
                ];
            }
        }

        return $rows->map(function ($r) use ($valueMap) {
            $vals = $valueMap[(int) $r->id] ?? ['amount' => 0.0, 'value_in' => 0.0, 'value_out' => 0.0];

            return [
                'id' => (int) $r->id,
                'number' => (string) $r->number,
                'date' => (string) $r->date,
                'type' => (string) ($r->type ?? ''),
                'reason' => (string) ($r->reason ?? ''),
                'status' => (string) ($r->status ?? ''),
                'created_by' => (string) ($r->created_by_name ?? '-'),
                'warehouse_id' => (int) ($r->warehouse_id ?? 0),
                'warehouse_name' => (string) ($r->warehouse_name ?? '-'),
                'amount' => $vals['amount'],
                'value_in' => $vals['value_in'],
                'value_out' => $vals['value_out'],
            ];
        })->values()->all();
    }

    /**
     * @return array{transaction: array<string, mixed>|null, items: list<array<string, mixed>>}
     */
    public function detailOutletAdjustmentTransaction(int $outletId, int $adjustmentId): array
    {
        $header = DB::table('outlet_food_inventory_adjustments as a')
            ->leftJoin('warehouse_outlets as wo', 'a.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'a.created_by', '=', 'u.id')
            ->where('a.id_outlet', $outletId)
            ->where('a.id', $adjustmentId)
            ->first([
                'a.id', 'a.number', 'a.date', 'a.type', 'a.reason', 'a.status',
                'u.nama_lengkap as created_by_name', 'wo.id as warehouse_id', 'wo.name as warehouse_name',
            ]);
        $vals = DB::table('outlet_food_inventory_cards')
            ->where('reference_type', 'outlet_stock_adjustment')
            ->where('reference_id', $adjustmentId)
            ->where('id_outlet', $outletId)
            ->selectRaw('SUM(COALESCE(value_in,0)-COALESCE(value_out,0)) as net, SUM(COALESCE(value_in,0)) as vin, SUM(COALESCE(value_out,0)) as vout')
            ->first();
        $txn = $header ? [
            'id' => (int) $header->id,
            'number' => (string) $header->number,
            'date' => (string) $header->date,
            'type' => (string) ($header->type ?? ''),
            'reason' => (string) ($header->reason ?? ''),
            'status' => (string) ($header->status ?? ''),
            'created_by' => (string) ($header->created_by_name ?? '-'),
            'warehouse_id' => (int) ($header->warehouse_id ?? 0),
            'warehouse_name' => (string) ($header->warehouse_name ?? '-'),
            'amount' => round((float) ($vals->net ?? 0), 2),
            'value_in' => round((float) ($vals->vin ?? 0), 2),
            'value_out' => round((float) ($vals->vout ?? 0), 2),
        ] : null;

        $items = DB::table('outlet_food_inventory_cards as c')
            ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->where('c.reference_type', 'outlet_stock_adjustment')
            ->where('c.reference_id', $adjustmentId)
            ->where('c.id_outlet', $outletId)
            ->orderBy('i.name')
            ->get([
                'c.id', 'i.name as item_name', 'i.sku',
                'c.in_qty_small', 'c.out_qty_small', 'c.cost_per_small', 'c.value_in', 'c.value_out',
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'item_name' => (string) $r->item_name,
                'sku' => (string) ($r->sku ?? ''),
                'qty_in' => (float) $r->in_qty_small,
                'qty_out' => (float) $r->out_qty_small,
                'cost_per_small' => (float) $r->cost_per_small,
                'value_in' => (float) $r->value_in,
                'value_out' => (float) $r->value_out,
                'amount' => round((float) $r->value_in - (float) $r->value_out, 2),
            ])
            ->values()
            ->all();

        return ['transaction' => $txn, 'items' => $items];
    }

    private function countStockOpnameTransactions(int $outletId, string $dateFrom, string $dateTo): int
    {
        if (! Schema::hasTable('outlet_stock_opnames')) {
            return 0;
        }

        return (int) DB::table('outlet_stock_opnames')
            ->where('outlet_id', $outletId)
            ->whereBetween('opname_date', [$dateFrom, $dateTo])
            ->whereIn('status', ['COMPLETED', 'APPROVED'])
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listStockOpnameTransactions(int $outletId, string $dateFrom, string $dateTo, string $search = ''): array
    {
        if (! Schema::hasTable('outlet_stock_opnames')) {
            return [];
        }

        $search = trim($search);
        $tanggal1 = Carbon::parse($dateFrom)->format('Y-m-01');
        $q = DB::table('outlet_stock_opnames as o')
            ->leftJoin('warehouse_outlets as wo', 'o.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'o.created_by', '=', 'u.id')
            ->where('o.outlet_id', $outletId)
            ->whereBetween('o.opname_date', [$dateFrom, $dateTo])
            ->whereIn('o.status', ['COMPLETED', 'APPROVED'])
            ->orderByDesc('o.opname_date')
            ->orderByDesc('o.id')
            ->select([
                'o.id',
                'o.opname_number',
                'o.opname_date',
                'o.status',
                'o.notes',
                'u.nama_lengkap as created_by_name',
                'wo.id as warehouse_id',
                'wo.name as warehouse_name',
            ]);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($qq) use ($like) {
                $qq->where('o.opname_number', 'like', $like)
                    ->orWhere('o.notes', 'like', $like)
                    ->orWhere('wo.name', 'like', $like)
                    ->orWhere('u.nama_lengkap', 'like', $like);
            });
        }

        $rows = $q->limit(200)->get();
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $valueMap = [];
        if ($ids !== []) {
            foreach (
                DB::table('outlet_food_inventory_cards')
                    ->where('reference_type', 'stock_opname')
                    ->whereIn('reference_id', $ids)
                    ->where('id_outlet', $outletId)
                    ->groupBy('reference_id')
                    ->selectRaw('
                        reference_id,
                        SUM(COALESCE(value_in,0)-COALESCE(value_out,0)) as net,
                        SUM(COALESCE(value_in,0)) as vin,
                        SUM(COALESCE(value_out,0)) as vout,
                        SUM(COALESCE(saldo_value,0)) as saldo_sum,
                        COUNT(*) as item_count
                    ')
                    ->get() as $v
            ) {
                $valueMap[(int) $v->reference_id] = [
                    'amount' => round((float) $v->net, 2),
                    'value_in' => round((float) $v->vin, 2),
                    'value_out' => round((float) $v->vout, 2),
                    'saldo_sum' => round((float) $v->saldo_sum, 2),
                    'item_count' => (int) $v->item_count,
                ];
            }
        }

        return $rows->map(function ($r) use ($valueMap, $tanggal1) {
            $vals = $valueMap[(int) $r->id] ?? [
                'amount' => 0.0,
                'value_in' => 0.0,
                'value_out' => 0.0,
                'saldo_sum' => 0.0,
                'item_count' => 0,
            ];
            $date = (string) $r->opname_date;
            $isDay1 = substr($date, 0, 10) === $tanggal1;

            return [
                'id' => (int) $r->id,
                'number' => (string) $r->opname_number,
                'date' => $date,
                'status' => (string) ($r->status ?? ''),
                'notes' => (string) ($r->notes ?? ''),
                'created_by' => (string) ($r->created_by_name ?? '-'),
                'warehouse_id' => (int) ($r->warehouse_id ?? 0),
                'warehouse_name' => (string) ($r->warehouse_name ?? '-'),
                'amount' => $vals['amount'],
                'value_in' => $vals['value_in'],
                'value_out' => $vals['value_out'],
                'saldo_sum' => $vals['saldo_sum'],
                'item_count' => $vals['item_count'],
                'is_day1' => $isDay1,
            ];
        })->values()->all();
    }

    /**
     * @return array{transaction: array<string, mixed>|null, items: list<array<string, mixed>>}
     */
    public function detailStockOpnameTransaction(int $outletId, int $opnameId): array
    {
        if (! Schema::hasTable('outlet_stock_opnames')) {
            return ['transaction' => null, 'items' => []];
        }

        $header = DB::table('outlet_stock_opnames as o')
            ->leftJoin('warehouse_outlets as wo', 'o.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'o.created_by', '=', 'u.id')
            ->where('o.outlet_id', $outletId)
            ->where('o.id', $opnameId)
            ->first([
                'o.id', 'o.opname_number', 'o.opname_date', 'o.status', 'o.notes',
                'u.nama_lengkap as created_by_name', 'wo.id as warehouse_id', 'wo.name as warehouse_name',
            ]);

        $vals = DB::table('outlet_food_inventory_cards')
            ->where('reference_type', 'stock_opname')
            ->where('reference_id', $opnameId)
            ->where('id_outlet', $outletId)
            ->selectRaw('
                SUM(COALESCE(value_in,0)-COALESCE(value_out,0)) as net,
                SUM(COALESCE(value_in,0)) as vin,
                SUM(COALESCE(value_out,0)) as vout,
                SUM(COALESCE(saldo_value,0)) as saldo_sum
            ')
            ->first();

        $txn = $header ? [
            'id' => (int) $header->id,
            'number' => (string) $header->opname_number,
            'date' => (string) $header->opname_date,
            'status' => (string) ($header->status ?? ''),
            'notes' => (string) ($header->notes ?? ''),
            'created_by' => (string) ($header->created_by_name ?? '-'),
            'warehouse_id' => (int) ($header->warehouse_id ?? 0),
            'warehouse_name' => (string) ($header->warehouse_name ?? '-'),
            'amount' => round((float) ($vals->net ?? 0), 2),
            'value_in' => round((float) ($vals->vin ?? 0), 2),
            'value_out' => round((float) ($vals->vout ?? 0), 2),
            'saldo_sum' => round((float) ($vals->saldo_sum ?? 0), 2),
            'is_day1' => substr((string) $header->opname_date, 0, 10) === Carbon::parse((string) $header->opname_date)->format('Y-m-01'),
        ] : null;

        $items = DB::table('outlet_food_inventory_cards as c')
            ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->where('c.reference_type', 'stock_opname')
            ->where('c.reference_id', $opnameId)
            ->where('c.id_outlet', $outletId)
            ->orderBy('i.name')
            ->get([
                'c.id', 'i.name as item_name', 'i.sku',
                'c.in_qty_small', 'c.out_qty_small', 'c.cost_per_small',
                'c.value_in', 'c.value_out', 'c.saldo_qty_small', 'c.saldo_value',
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'item_name' => (string) $r->item_name,
                'sku' => (string) ($r->sku ?? ''),
                'qty_in' => (float) $r->in_qty_small,
                'qty_out' => (float) $r->out_qty_small,
                'cost_per_small' => (float) $r->cost_per_small,
                'value_in' => (float) $r->value_in,
                'value_out' => (float) $r->value_out,
                'saldo_qty' => (float) $r->saldo_qty_small,
                'saldo_value' => (float) $r->saldo_value,
                'amount' => round((float) $r->value_in - (float) $r->value_out, 2),
            ])
            ->values()
            ->all();

        return ['transaction' => $txn, 'items' => $items];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInternalWarehouseTransferTransactions(
        int $outletId,
        string $dateFrom,
        string $dateTo,
        string $search = '',
        ?int $fromWarehouseId = null,
        ?int $toWarehouseId = null
    ): array {
        $search = trim($search);
        $q = DB::table('internal_warehouse_transfers as t')
            ->join('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
            ->join('warehouse_outlets as wt', 't.warehouse_outlet_to_id', '=', 'wt.id')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->where('t.outlet_id', $outletId)
            ->whereBetween('t.transfer_date', [$dateFrom, $dateTo])
            ->orderByDesc('t.transfer_date')
            ->orderByDesc('t.id')
            ->select([
                't.id',
                't.transfer_number',
                't.transfer_date',
                't.notes',
                'u.nama_lengkap as created_by_name',
                'wf.id as from_warehouse_id',
                'wf.name as from_warehouse_name',
                'wt.id as to_warehouse_id',
                'wt.name as to_warehouse_name',
            ]);
        if ($fromWarehouseId) {
            $q->where('t.warehouse_outlet_from_id', $fromWarehouseId);
        }
        if ($toWarehouseId) {
            $q->where('t.warehouse_outlet_to_id', $toWarehouseId);
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(function ($qq) use ($like) {
                $qq->where('t.transfer_number', 'like', $like)
                    ->orWhere('wf.name', 'like', $like)
                    ->orWhere('wt.name', 'like', $like)
                    ->orWhere('u.nama_lengkap', 'like', $like);
            });
        }
        $rows = $q->limit(200)->get();
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $costMap = [];
        if ($ids !== []) {
            foreach (
                DB::table('internal_warehouse_transfer_items')
                    ->whereIn('internal_warehouse_transfer_id', $ids)
                    ->groupBy('internal_warehouse_transfer_id')
                    ->selectRaw('internal_warehouse_transfer_id, SUM(COALESCE(total_cost,0)) as amount')
                    ->get() as $v
            ) {
                $costMap[(int) $v->internal_warehouse_transfer_id] = round((float) $v->amount, 2);
            }
            foreach (
                DB::table('outlet_food_inventory_cards')
                    ->where('reference_type', 'internal_warehouse_transfer')
                    ->whereIn('reference_id', $ids)
                    ->where('id_outlet', $outletId)
                    ->groupBy('reference_id')
                    ->selectRaw('reference_id, SUM(COALESCE(value_out,0)) as amount')
                    ->get() as $v
            ) {
                $rid = (int) $v->reference_id;
                $cardAmt = round((float) $v->amount, 2);
                if (($costMap[$rid] ?? 0) <= 0 && $cardAmt > 0) {
                    $costMap[$rid] = $cardAmt;
                }
            }
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => (string) $r->transfer_number,
            'date' => (string) $r->transfer_date,
            'notes' => (string) ($r->notes ?? ''),
            'created_by' => (string) ($r->created_by_name ?? '-'),
            'from_warehouse_id' => (int) $r->from_warehouse_id,
            'from_warehouse' => (string) $r->from_warehouse_name,
            'to_warehouse_id' => (int) $r->to_warehouse_id,
            'to_warehouse' => (string) $r->to_warehouse_name,
            'amount' => $costMap[(int) $r->id] ?? 0.0,
        ])->values()->all();
    }

    /**
     * @return array{transaction: array<string, mixed>|null, items: list<array<string, mixed>>}
     */
    public function detailInternalWarehouseTransferTransaction(int $outletId, int $transferId): array
    {
        $header = DB::table('internal_warehouse_transfers as t')
            ->join('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
            ->join('warehouse_outlets as wt', 't.warehouse_outlet_to_id', '=', 'wt.id')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->where('t.outlet_id', $outletId)
            ->where('t.id', $transferId)
            ->first([
                't.id', 't.transfer_number', 't.transfer_date', 't.notes',
                'u.nama_lengkap as created_by_name',
                'wf.id as from_warehouse_id', 'wf.name as from_warehouse_name',
                'wt.id as to_warehouse_id', 'wt.name as to_warehouse_name',
            ]);
        $amount = (float) DB::table('internal_warehouse_transfer_items')
            ->where('internal_warehouse_transfer_id', $transferId)
            ->sum('total_cost');
        $txn = $header ? [
            'id' => (int) $header->id,
            'number' => (string) $header->transfer_number,
            'date' => (string) $header->transfer_date,
            'notes' => (string) ($header->notes ?? ''),
            'created_by' => (string) ($header->created_by_name ?? '-'),
            'from_warehouse_id' => (int) $header->from_warehouse_id,
            'from_warehouse' => (string) $header->from_warehouse_name,
            'to_warehouse_id' => (int) $header->to_warehouse_id,
            'to_warehouse' => (string) $header->to_warehouse_name,
            'amount' => round($amount, 2),
        ] : null;

        $items = DB::table('internal_warehouse_transfer_items as i')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->where('i.internal_warehouse_transfer_id', $transferId)
            ->orderBy('it.name')
            ->get(['i.id', 'it.name as item_name', 'it.sku', 'i.qty_small', 'i.cost_small', 'i.total_cost'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'item_name' => (string) $r->item_name,
                'sku' => (string) ($r->sku ?? ''),
                'qty_small' => (float) $r->qty_small,
                'cost_per_small' => (float) $r->cost_small,
                'amount' => round((float) $r->total_cost, 2),
            ])
            ->values()
            ->all();

        return ['transaction' => $txn, 'items' => $items];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOutletWipTransactions(int $outletId, string $dateFrom, string $dateTo, string $search = ''): array
    {
        $search = trim($search);
        $q = DB::table('outlet_wip_production_headers as h')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->where('h.outlet_id', $outletId)
            ->whereBetween('h.production_date', [$dateFrom, $dateTo])
            ->orderByDesc('h.production_date')
            ->orderByDesc('h.id')
            ->select([
                'h.id',
                'h.number',
                'h.production_date',
                'h.batch_number',
                'h.status',
                'h.notes',
                'u.nama_lengkap as created_by_name',
                'wo.id as warehouse_id',
                'wo.name as warehouse_name',
            ]);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(function ($qq) use ($like) {
                $qq->where('h.number', 'like', $like)
                    ->orWhere('h.batch_number', 'like', $like)
                    ->orWhere('wo.name', 'like', $like)
                    ->orWhere('u.nama_lengkap', 'like', $like);
            });
        }
        $rows = $q->limit(200)->get();
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $costMap = [];
        if ($ids !== []) {
            foreach (
                DB::table('outlet_food_inventory_cards')
                    ->where('reference_type', 'outlet_wip_production')
                    ->whereIn('reference_id', $ids)
                    ->where('id_outlet', $outletId)
                    ->groupBy('reference_id')
                    ->selectRaw('reference_id, SUM(COALESCE(value_out,0)) as material, SUM(COALESCE(value_in,0)) as finished')
                    ->get() as $v
            ) {
                $costMap[(int) $v->reference_id] = [
                    'material_cost' => round((float) $v->material, 2),
                    'finished_cost' => round((float) $v->finished, 2),
                ];
            }
        }

        return $rows->map(function ($r) use ($costMap) {
            $c = $costMap[(int) $r->id] ?? ['material_cost' => 0.0, 'finished_cost' => 0.0];

            return [
                'id' => (int) $r->id,
                'number' => (string) $r->number,
                'date' => (string) $r->production_date,
                'batch_number' => (string) ($r->batch_number ?? ''),
                'status' => (string) ($r->status ?? ''),
                'notes' => (string) ($r->notes ?? ''),
                'created_by' => (string) ($r->created_by_name ?? '-'),
                'warehouse_id' => (int) ($r->warehouse_id ?? 0),
                'warehouse_name' => (string) ($r->warehouse_name ?? '-'),
                'material_cost' => $c['material_cost'],
                'finished_cost' => $c['finished_cost'],
                'amount' => $c['finished_cost'],
            ];
        })->values()->all();
    }

    /**
     * @return array{transaction: array<string, mixed>|null, items: list<array<string, mixed>>}
     */
    public function detailOutletWipTransaction(int $outletId, int $headerId): array
    {
        $header = DB::table('outlet_wip_production_headers as h')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->where('h.outlet_id', $outletId)
            ->where('h.id', $headerId)
            ->first([
                'h.id', 'h.number', 'h.production_date', 'h.batch_number', 'h.status', 'h.notes',
                'u.nama_lengkap as created_by_name', 'wo.id as warehouse_id', 'wo.name as warehouse_name',
            ]);
        $vals = DB::table('outlet_food_inventory_cards')
            ->where('reference_type', 'outlet_wip_production')
            ->where('reference_id', $headerId)
            ->where('id_outlet', $outletId)
            ->selectRaw('SUM(COALESCE(value_out,0)) as material, SUM(COALESCE(value_in,0)) as finished')
            ->first();
        $txn = $header ? [
            'id' => (int) $header->id,
            'number' => (string) $header->number,
            'date' => (string) $header->production_date,
            'batch_number' => (string) ($header->batch_number ?? ''),
            'status' => (string) ($header->status ?? ''),
            'notes' => (string) ($header->notes ?? ''),
            'created_by' => (string) ($header->created_by_name ?? '-'),
            'warehouse_id' => (int) ($header->warehouse_id ?? 0),
            'warehouse_name' => (string) ($header->warehouse_name ?? '-'),
            'material_cost' => round((float) ($vals->material ?? 0), 2),
            'finished_cost' => round((float) ($vals->finished ?? 0), 2),
            'amount' => round((float) ($vals->finished ?? 0), 2),
        ] : null;

        $items = DB::table('outlet_food_inventory_cards as c')
            ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->where('c.reference_type', 'outlet_wip_production')
            ->where('c.reference_id', $headerId)
            ->where('c.id_outlet', $outletId)
            ->orderByDesc('c.value_out')
            ->orderByDesc('c.value_in')
            ->get([
                'c.id', 'i.name as item_name', 'i.sku',
                'c.in_qty_small', 'c.out_qty_small', 'c.cost_per_small', 'c.value_in', 'c.value_out',
            ])
            ->map(function ($r) {
                $role = (float) $r->value_in > 0 && (float) $r->value_out <= 0
                    ? 'finished'
                    : ((float) $r->value_out > 0 ? 'material' : 'other');

                return [
                    'id' => (int) $r->id,
                    'item_name' => (string) $r->item_name,
                    'sku' => (string) ($r->sku ?? ''),
                    'role' => $role,
                    'qty_in' => (float) $r->in_qty_small,
                    'qty_out' => (float) $r->out_qty_small,
                    'cost_per_small' => (float) $r->cost_per_small,
                    'value_in' => (float) $r->value_in,
                    'value_out' => (float) $r->value_out,
                    'amount' => round((float) $r->value_in + (float) $r->value_out, 2),
                ];
            })
            ->values()
            ->all();

        return ['transaction' => $txn, 'items' => $items];
    }
}
