<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

/**
 * Jurnal Report Controller
 * 
 * Handles all journal-related reports including:
 * - Buku Besar (General Ledger)
 * - Neraca Saldo (Trial Balance)
 * - Laporan Laba Rugi (Profit & Loss)
 * - And other journal reports
 * 
 * Created: 2026-01-30
 * 
 * Functions:
 * - bukuBesar: General Ledger report per COA
 * - neracaSaldo: Trial Balance report (saldo per COA on specific date)
 * - arusKas: Cash Flow Statement
 */
class JurnalReportController extends Controller
{
    /**
     * Arus Kas (Cash Flow Statement)
     * 
     * Shows cash inflow and outflow analysis by activity categories
     * 
     * Features:
     * - Filter: Date range, Outlet
     * - Categories: Operating, Investment, Financing activities
     * - Display: Opening balance, Cash flows by category, Closing balance
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
    public function arusKas(Request $request)
    {
        try {
            $loadData = $request->input('load_data', false);
            
            $dateFrom = $request->input('date_from', date('Y-m-01'));
            $dateTo = $request->input('date_to', date('Y-m-d'));
            $outletId = $request->input('outlet_id');
            $perPage = $request->input('per_page', 25);
            
            // If load_data is false, only return filter options
            if (!$loadData) {
                $outlets = $this->getOutlets();
                
                return Inertia::render('Report/ArusKas', [
                    'report' => [],
                    'summary' => null,
                    'outlets' => $outlets,
                    'filters' => [
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'outlet_id' => $outletId,
                        'per_page' => $perPage,
                        'load_data' => false,
                    ],
                ]);
            }
            
            // Define COA codes for cash accounts
            $cashCOAPattern = '%Kas%'; // Match any COA with "Kas" in the name
            
            // Calculate opening balance (saldo awal kas sebelum periode)
            $openingBalance = $this->calculateCashBalanceBeforeDate($dateFrom, $outletId, 'posted', $cashCOAPattern);
            
            // Get all Cash/Bank COAs
            $cashCoas = DB::table('chart_of_accounts')
                ->where('is_active', 1)
                ->where(function($q) use ($cashCOAPattern) {
                    $q->where('name', 'like', $cashCOAPattern)
                      ->orWhere('name', 'like', '%Bank%');
                })
                ->get();
            
            // Build cash flow data
            $operationalRevenue = 0;
            $operationalExpenses = 0;
            $investmentItems = [];
            $financingItems = [];
            
            // Get transactions for the period
            $transactions = DB::table('jurnal_global as j')
                ->whereBetween('j.tanggal', [$dateFrom, $dateTo])
                ->where('j.status', 'posted')
                ->when($outletId, function($q) use ($outletId) {
                    $q->where('j.outlet_id', $outletId);
                })
                ->leftJoin('chart_of_accounts as debit_coa', 'j.coa_debit_id', '=', 'debit_coa.id')
                ->leftJoin('chart_of_accounts as kredit_coa', 'j.coa_kredit_id', '=', 'kredit_coa.id')
                ->select(
                    'j.id',
                    'j.no_jurnal',
                    'j.tanggal',
                    'j.keterangan',
                    'j.coa_debit_id',
                    'j.coa_kredit_id',
                    'j.jumlah_debit',
                    'j.jumlah_kredit',
                    'debit_coa.type as debit_type',
                    'debit_coa.name as debit_name',
                    'kredit_coa.type as kredit_type',
                    'kredit_coa.name as kredit_name'
                )
                ->orderBy('j.tanggal')
                ->get();
            
            // Analyze transactions
            foreach ($transactions as $tx) {
                // Operating activities: involve Revenue and Expense
                if (($tx->debit_type === 'Revenue' || $tx->debit_type === 'Expense') &&
                    ($tx->kredit_type === 'Asset' || str_contains(strtolower($tx->kredit_name), 'kas'))) {
                    // Cash inflow from operations
                    if ($tx->debit_type === 'Revenue') {
                        $operationalRevenue += $tx->jumlah_kredit;
                    }
                }
                
                if (($tx->kredit_type === 'Revenue' || $tx->kredit_type === 'Expense') &&
                    ($tx->debit_type === 'Asset' || str_contains(strtolower($tx->debit_name), 'kas'))) {
                    // Cash outflow or inflow from operations
                    if ($tx->kredit_type === 'Expense') {
                        $operationalExpenses += $tx->jumlah_debit;
                    }
                }
            }
            
            // Calculate closing balance
            $closingBalance = $openingBalance + ($operationalRevenue - $operationalExpenses);
            
            $flowData = [
                'operational' => [
                    'revenue' => $operationalRevenue,
                    'expenses' => $operationalExpenses,
                    'net' => $operationalRevenue - $operationalExpenses,
                ],
                'investment' => [
                    'items' => $investmentItems,
                    'net' => 0,
                ],
                'financing' => [
                    'items' => $financingItems,
                    'net' => 0,
                ],
            ];
            
            $summary = [
                'opening_balance' => $openingBalance,
                'total_inflow' => $operationalRevenue,
                'total_outflow' => $operationalExpenses,
                'closing_balance' => $closingBalance,
            ];
            
            $outlets = $this->getOutlets();
            
            return Inertia::render('Report/ArusKas', [
                'report' => $flowData,
                'summary' => $summary,
                'outlets' => $outlets,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_id' => $outletId,
                    'per_page' => $perPage,
                    'load_data' => true,
                ],
                'error' => null,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in arusKas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            
            return Inertia::render('Report/ArusKas', [
                'error' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'report' => [],
                'summary' => null,
                'outlets' => [],
                'filters' => array_merge($request->all(), ['load_data' => false]),
            ]);
        }
    }

    /**
     * Calculate cash balance before a specific date
     * 
     * @param string $dateFrom
     * @param int|null $outletId
     * @param string $status
     * @param string $cashPattern
     * @return float
     */
    private function calculateCashBalanceBeforeDate($dateFrom, $outletId = null, $status = 'posted', $cashPattern = '%Kas%')
    {
        // Get all cash/bank COAs
        $cashCoas = DB::table('chart_of_accounts')
            ->where('is_active', 1)
            ->where(function($q) use ($cashPattern) {
                $q->where('name', 'like', $cashPattern)
                  ->orWhere('name', 'like', '%Bank%');
            })
            ->pluck('id')
            ->toArray();
        
        if (empty($cashCoas)) {
            return 0;
        }
        
        $query = DB::table('jurnal_global as j')
            ->where('j.tanggal', '<', $dateFrom)
            ->where('j.status', $status)
            ->where(function($q) use ($cashCoas) {
                $q->whereIn('j.coa_debit_id', $cashCoas)
                  ->orWhereIn('j.coa_kredit_id', $cashCoas);
            });
        
        if ($outletId) {
            $query->where('j.outlet_id', $outletId);
        }
        
        $transactions = $query->get();
        
        $balance = 0;
        foreach ($transactions as $transaction) {
            if (in_array($transaction->coa_debit_id, $cashCoas)) {
                $balance += $transaction->jumlah_debit;
            }
            if (in_array($transaction->coa_kredit_id, $cashCoas)) {
                $balance -= $transaction->jumlah_kredit;
            }
        }
        
        return $balance;
    }

    /**
     * Neraca Saldo (Trial Balance)
     * 
     * Shows balance summary of all Chart of Accounts on a specific date
     * 
     * Features:
     * - Filter: Date, Outlet, COA Type
     * - Display: COA Code, Name, Type, Debit Balance, Credit Balance
     * - Summary: Total Debit, Total Kredit, Difference
     * - Validation: Total Debit must equal Total Kredit
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
    public function neracaSaldo(Request $request)
    {
        try {
            // Check if user has clicked "Load Data"
            $loadData = $request->input('load_data', false);
            
            // Default filters
            $dateAs = $request->input('date_as', date('Y-m-d')); // Today
            $outletId = $request->input('outlet_id');
            $coaType = $request->input('coa_type');
            $perPage = $request->input('per_page', 25);
            $page = $request->input('page', 1);
            
            // If load_data is false, only return filter options without data
            if (!$loadData) {
                $outlets = $this->getOutlets();
                
                return Inertia::render('Report/NeracaSaldo', [
                    'report' => [
                        'data' => [],
                        'current_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                    ],
                    'summary' => null,
                    'outlets' => $outlets,
                    'filters' => [
                        'date_as' => $dateAs,
                        'outlet_id' => $outletId,
                        'coa_type' => $coaType,
                        'per_page' => $perPage,
                        'page' => $page,
                        'load_data' => false,
                    ],
                ]);
            }
            
            // Get all active COAs
            $coasQuery = DB::table('chart_of_accounts')
                ->where('is_active', 1);
            
            if ($coaType) {
                $coasQuery->where('type', $coaType);
            }
            
            $coas = $coasQuery->orderBy('code')->get();
            
            // Build report data
            $reportData = [];
            $totalDebit = 0;
            $totalCredit = 0;
            $totalAsset = 0;
            $totalLiability = 0;
            $totalEquity = 0;
            
            foreach ($coas as $coa) {
                // Calculate balance as of the given date
                $balanceData = $this->calculateBalanceAsOfDate($coa->id, $dateAs, $outletId, 'posted');
                
                // Determine saldo debit and kredit using net balance (debit - kredit)
                // balanceData = total_debit - total_kredit
                // If positive => net debit; if negative => net credit
                $saldoDebit = $balanceData >= 0 ? $balanceData : 0;
                $saldoKredit = $balanceData < 0 ? abs($balanceData) : 0;
                
                $reportData[] = [
                    'coa_id' => $coa->id,
                    'coa_code' => $coa->code,
                    'coa_name' => $coa->name,
                    'coa_type' => $coa->type,
                    'saldo_debit' => $saldoDebit,
                    'saldo_kredit' => $saldoKredit,
                ];
                
                $totalDebit += $saldoDebit;
                $totalCredit += $saldoKredit;
                
                // Accumulate by type for summary cards
                if ($coa->type === 'Asset') {
                    $totalAsset += $balanceData;
                } elseif ($coa->type === 'Liability') {
                    $totalLiability += $balanceData;
                } elseif ($coa->type === 'Equity') {
                    $totalEquity += $balanceData;
                }
            }
            
            // Pagination
            $total = count($reportData);
            $items = collect($reportData)->slice(($page - 1) * $perPage, $perPage)->values()->all();
            
            $paginatedData = [
                'data' => $items,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total),
            ];
            
            $outlets = $this->getOutlets();
            
            return Inertia::render('Report/NeracaSaldo', [
                'report' => $paginatedData,
                'summary' => [
                    'total_debit' => $totalDebit,
                    'total_kredit' => $totalCredit,
                    'difference' => $totalDebit - $totalCredit,
                    'total_asset' => $totalAsset,
                    'total_liability' => $totalLiability,
                    'total_equity' => $totalEquity,
                ],
                'outlets' => $outlets,
                'filters' => [
                    'date_as' => $dateAs,
                    'outlet_id' => $outletId,
                    'coa_type' => $coaType,
                    'per_page' => $perPage,
                    'page' => $page,
                    'load_data' => true,
                ],
                'error' => null,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in neracaSaldo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            
            return Inertia::render('Report/NeracaSaldo', [
                'error' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'report' => [
                    'data' => [],
                    'current_page' => 1,
                    'per_page' => 25,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
                'summary' => null,
                'outlets' => [],
                'filters' => array_merge($request->all(), ['load_data' => false]),
            ]);
        }
    }

    /**
     * Calculate balance for a COA as of a specific date
     * 
     * @param int $coaId
     * @param string $dateAs
     * @param int|null $outletId
     * @param string $status
     * @return float
     */
    private function calculateBalanceAsOfDate($coaId, $dateAs, $outletId = null, $status = 'posted')
    {
        $query = DB::table('jurnal_global as j')
            ->where('j.tanggal', '<=', $dateAs)
            ->where('j.status', $status)
            ->where(function($q) use ($coaId) {
                $q->where('j.coa_debit_id', $coaId)
                  ->orWhere('j.coa_kredit_id', $coaId);
            });
        
        if ($outletId) {
            $query->where('j.outlet_id', $outletId);
        }
        
        $transactions = $query->get();
        
        $balance = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->coa_debit_id == $coaId) {
                $balance += $transaction->jumlah_debit;
            }
            if ($transaction->coa_kredit_id == $coaId) {
                $balance -= $transaction->jumlah_kredit;
            }
        }
        
        return $balance;
    }

    /**
     * Buku Besar (General Ledger)
     * 
     * Shows detailed transactions per Chart of Account in a specific period
     * 
     * Features:
     * - Filter: Date range, Outlet, COA, Status
     * - Grouping: Per COA, Per Date
     * - Display: Opening balance, Debit, Credit, Closing balance
     * - Sorting: Date, No Jurnal, COA
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
    public function bukuBesar(Request $request)
    {
        try {
            // Check if user has clicked "Load Data" (has load_data parameter)
            $loadData = $request->input('load_data', false);
            
            // Default filters (only for display, not for query)
            $dateFrom = $request->input('date_from', date('Y-m-01')); // First day of current month
            $dateTo = $request->input('date_to', date('Y-m-d')); // Today
            $outletId = $request->input('outlet_id');
            $onlyWithCredit = $request->input('only_with_credit', false);
            $coaId = $request->input('coa_id');
            $status = $request->input('status', 'posted'); // Default: only posted journals
            $perPage = $request->input('per_page', 25);
            $page = $request->input('page', 1);
            
            // If load_data is false, only return filter options without data
            if (!$loadData) {
                $outlets = $this->getOutlets();
                $coas = $this->getChartOfAccounts();
                
                return Inertia::render('Report/BukuBesar', [
                    'report' => [
                        'data' => [],
                        'current_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                    ],
                    'summary' => null,
                    'outlets' => $outlets,
                    'coas' => $coas,
                    'filters' => [
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'outlet_id' => $outletId,
                        'coa_id' => $coaId,
                        'status' => $status,
                        'only_with_credit' => $onlyWithCredit,
                        'per_page' => $perPage,
                        'page' => $page,
                        'load_data' => false,
                    ],
                ]);
            }
            
            // Determine all COA IDs involved in the period (both debit and kredit)
            $baseQuery = DB::table('jurnal_global as j')
                ->whereBetween('j.tanggal', [$dateFrom, $dateTo])
                ->where('j.status', $status);

            if ($outletId) {
                $baseQuery->where('j.outlet_id', $outletId);
            }

            if ($coaId) {
                $baseQuery->where(function($q) use ($coaId) {
                    $q->where('j.coa_debit_id', $coaId)
                      ->orWhere('j.coa_kredit_id', $coaId);
                });
            }

            // Collect distinct debit and kredit COA ids
            $debitCoaIds = (clone $baseQuery)->whereNotNull('j.coa_debit_id')->distinct()->pluck('coa_debit_id')->toArray();
            $kreditCoaIds = (clone $baseQuery)->whereNotNull('j.coa_kredit_id')->distinct()->pluck('coa_kredit_id')->toArray();

            $allCoaIds = array_values(array_unique(array_filter(array_merge($debitCoaIds, $kreditCoaIds))));

            // If onlyWithCredit filter is set, restrict to COAs that have kredit in period
            if ($onlyWithCredit) {
                $kreditOnly = (clone $baseQuery)->select(DB::raw('coa_kredit_id, SUM(j.jumlah_kredit) as total_kredit'))
                    ->whereNotNull('j.coa_kredit_id')
                    ->groupBy('coa_kredit_id')
                    ->havingRaw('SUM(j.jumlah_kredit) > 0')
                    ->pluck('coa_kredit_id')
                    ->toArray();

                $allCoaIds = array_values(array_intersect($allCoaIds, $kreditOnly));
            }

            // Fetch COA metadata ordered by code
            $coas = DB::table('chart_of_accounts')
                ->whereIn('id', $allCoaIds ?: [0])
                ->orderBy('code')
                ->get();
            
            // Build detail query untuk setiap COA
            $reportData = [];

            // Compute summary totals from jurnal_global matching the current filters
            $summaryAgg = DB::table('jurnal_global as j')
                ->whereBetween('j.tanggal', [$dateFrom, $dateTo])
                ->where('j.status', $status)
                ->when($outletId, function($q) use ($outletId) {
                    $q->where('j.outlet_id', $outletId);
                })
                ->when($coaId, function($q) use ($coaId) {
                    $q->where(function($q2) use ($coaId) {
                        $q2->where('j.coa_debit_id', $coaId)
                           ->orWhere('j.coa_kredit_id', $coaId);
                    });
                })
                ->select(DB::raw('COALESCE(SUM(j.jumlah_debit), 0) as total_debit, COALESCE(SUM(j.jumlah_kredit), 0) as total_credit'))
                ->first();

            $totalDebit = $summaryAgg->total_debit ?? 0;
            $totalCredit = $summaryAgg->total_credit ?? 0;
            
            foreach ($coas as $coa) {
                // Calculate opening balance (saldo sebelum periode)
                $openingBalance = $this->calculateOpeningBalance($coa->id, $dateFrom, $outletId, $status);
                
                // Get transactions for this COA in the period
                $transactionsQuery = DB::table('jurnal_global as j')
                    ->select(
                        'j.id',
                        'j.no_jurnal',
                        'j.tanggal',
                        'j.keterangan',
                        'j.coa_debit_id',
                        'j.coa_kredit_id',
                        'j.jumlah_debit',
                        'j.jumlah_kredit',
                        'j.outlet_id',
                        'j.reference_type',
                        'j.reference_id',
                        'o.nama_outlet',
                        'coa_debit.code as debit_coa_code',
                        'coa_debit.name as debit_coa_name',
                        'coa_kredit.code as kredit_coa_code',
                        'coa_kredit.name as kredit_coa_name'
                    )
                    ->leftJoin('chart_of_accounts as coa_debit', 'j.coa_debit_id', '=', 'coa_debit.id')
                    ->leftJoin('chart_of_accounts as coa_kredit', 'j.coa_kredit_id', '=', 'coa_kredit.id')
                    ->leftJoin('tbl_data_outlet as o', 'j.outlet_id', '=', 'o.id_outlet')
                    ->whereBetween('j.tanggal', [$dateFrom, $dateTo])
                    ->where('j.status', $status)
                                        ->where(function($q) use ($coa) {
                                                $q->where('j.coa_debit_id', $coa->id)
                                                    ->orWhere('j.coa_kredit_id', $coa->id);
                                        });
                
                if ($outletId) {
                    $transactionsQuery->where('j.outlet_id', $outletId);
                }
                
                $transactionsQuery->orderBy('j.tanggal')
                    ->orderBy('j.no_jurnal')
                    ->orderBy('j.id');
                
                $transactions = $transactionsQuery->get();
                
                // Calculate totals for this COA
                $coaDebit = 0;
                $coaCredit = 0;
                $runningBalance = $openingBalance;
                
                foreach ($transactions as $transaction) {
                    if ($transaction->coa_debit_id == $coa->id) {
                        $coaDebit += $transaction->jumlah_debit;
                        $runningBalance += $transaction->jumlah_debit;
                    }
                    if ($transaction->coa_kredit_id == $coa->id) {
                        $coaCredit += $transaction->jumlah_kredit;
                        $runningBalance -= $transaction->jumlah_kredit;
                    }
                    
                    // Add running balance to transaction
                    $transaction->saldo = $runningBalance;
                }
                
                $closingBalance = $openingBalance + $coaDebit - $coaCredit;
                
                $reportData[] = [
                    'coa_id' => $coa->id,
                    'coa_code' => $coa->code,
                    'coa_name' => $coa->name,
                    'coa_type' => $coa->type,
                    'opening_balance' => $openingBalance,
                    'total_debit' => $coaDebit,
                    'total_credit' => $coaCredit,
                    'closing_balance' => $closingBalance,
                    'transaction_count' => $transactions->count(),
                    'transactions' => $transactions->toArray(),
                ];
                
                // totals are computed above via aggregate query; do not re-accumulate here
            }
            
            // Pagination - paginate COAs, not transactions
            $total = count($reportData);
            $items = collect($reportData)->slice(($page - 1) * $perPage, $perPage)->values()->all();
            
            // Convert to array for Inertia
            $paginatedData = [
                'data' => $items,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total),
            ];
            
            // Get filter options
            $outlets = $this->getOutlets();
            $coas = $this->getChartOfAccounts();
            
            return Inertia::render('Report/BukuBesar', [
                'report' => $paginatedData,
                'summary' => [
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'difference' => $totalDebit - $totalCredit,
                ],
                'outlets' => $outlets,
                'coas' => $coas,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_id' => $outletId,
                    'coa_id' => $coaId,
                    'status' => $status,
                    'per_page' => $perPage,
                    'page' => $page,
                    'load_data' => true,
                ],
                'error' => null,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in bukuBesar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            
            return Inertia::render('Report/BukuBesar', [
                'error' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'report' => [
                    'data' => [],
                    'current_page' => 1,
                    'per_page' => 25,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
                'summary' => [
                    'total_debit' => 0,
                    'total_credit' => 0,
                    'difference' => 0,
                ],
                'outlets' => [],
                'coas' => [],
                'filters' => array_merge($request->all(), ['load_data' => false]),
            ]);
        }
    }
    
    /**
     * Calculate opening balance for a COA before the given date
     * 
     * @param int $coaId
     * @param string $dateFrom
     * @param int|null $outletId
     * @param string $status
     * @return float
     */
    private function calculateOpeningBalance($coaId, $dateFrom, $outletId = null, $status = 'posted')
    {
        $query = DB::table('jurnal_global as j')
            ->where('j.tanggal', '<', $dateFrom)
            ->where('j.status', $status)
            ->where(function($q) use ($coaId) {
                $q->where('j.coa_debit_id', $coaId)
                  ->orWhere('j.coa_kredit_id', $coaId);
            });
        
        if ($outletId) {
            $query->where('j.outlet_id', $outletId);
        }
        
        $transactions = $query->get();
        
        $balance = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->coa_debit_id == $coaId) {
                $balance += $transaction->jumlah_debit;
            }
            if ($transaction->coa_kredit_id == $coaId) {
                $balance -= $transaction->jumlah_kredit;
            }
        }
        
        return $balance;
    }
    
    /**
     * Get outlets list for filter
     * 
     * @return array
     */
    private function getOutlets()
    {
        try {
            $user = auth()->user();
            
            $query = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->whereNotNull('nama_outlet')
                ->where('nama_outlet', '!=', '');
            
            // If user is not superuser (id_outlet != 1), only show their outlet
            if ($user && $user->id_outlet != 1) {
                $query->where('id_outlet', $user->id_outlet);
            }
            
            return $query->orderBy('nama_outlet')
                ->get(['id_outlet as id', 'nama_outlet as name'])
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Error getting outlets', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get Chart of Accounts list for filter
     * 
     * @return array
     */
    private function getChartOfAccounts()
    {
        try {
            return DB::table('chart_of_accounts')
                ->where('is_active', 1)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type'])
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Error getting COAs', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
