<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Exports\OpexByCategoryExport;
use Maatwebsite\Excel\Facades\Excel;

class OpexByCategoryController extends Controller
{
    public function index(Request $request)
    {
        // Get filter parameters
        $dateFrom = $request->input('date_from', date('Y-m-01')); // Default to first day of current month
        $dateTo = $request->input('date_to', date('Y-m-t')); // Default to last day of current month
        $categoryId = $request->input('category_id');

        // Get all categories
        $categories = DB::table('purchase_requisition_categories')
            ->select('id', 'name', 'division', 'subcategory', 'budget_type', 'budget_limit')
            ->orderBy('name')
            ->get();

        // Build data by category
        $categoryData = [];

        foreach ($categories as $category) {
            // Skip if category filter is set and doesn't match
            if ($categoryId && $category->id != $categoryId) {
                continue;
            }

            $categoryTransactions = [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'category_division' => $category->division,
                'category_subcategory' => $category->subcategory,
                'budget_type' => $category->budget_type,
                'budget_limit' => $category->budget_limit ?? 0,
                'transactions' => [
                    'rnf' => [], // Retail Non Food
                    'pr' => [],  // Purchase Requisition
                    'nfp' => []  // Non Food Payment
                ],
                'totals' => [
                    'rnf_total' => 0,
                    'pr_total' => 0,
                    'nfp_total' => 0,
                    'grand_total' => 0
                ]
            ];

            // 1. Get Retail Non Food (RNF) transactions
            // OPEX Report hanya menghitung yang status 'approved' (bukan 'pending')
            $rnfTransactions = DB::table('retail_non_food as rnf')
                ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
                ->where('rnf.category_budget_id', $category->id)
                ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
                ->where('rnf.status', 'approved') // Hanya approved, sama seperti OPEX Report
                ->select(
                    'rnf.id',
                    'rnf.retail_number as transaction_number',
                    'rnf.transaction_date',
                    'o.nama_outlet as outlet_name',
                    'rnf.total_amount',
                    'rnf.status',
                    DB::raw('"retail_non_food" as source_type')
                )
                ->orderBy('rnf.transaction_date', 'desc')
                ->get();

            foreach ($rnfTransactions as $rnf) {
                // Get RNF items
                $rnfItems = DB::table('retail_non_food_items')
                    ->where('retail_non_food_id', $rnf->id)
                    ->select('id', 'item_name', 'qty as quantity', 'unit', 'price', 'subtotal')
                    ->get();

                // Get RNF attachments (if table exists)
                $rnfAttachments = [];
                try {
                    $rnfAttachments = DB::table('retail_non_food_attachments')
                        ->where('retail_non_food_id', $rnf->id)
                        ->select('id', 'file_name', 'file_path', 'description', 'created_at')
                        ->get()
                        ->toArray();
                } catch (\Exception $e) {
                    // Table might not exist, continue without attachments
                }

                $categoryTransactions['transactions']['rnf'][] = [
                    'id' => $rnf->id,
                    'transaction_number' => $rnf->transaction_number,
                    'date' => $rnf->transaction_date,
                    'outlet_name' => $rnf->outlet_name,
                    'amount' => $rnf->total_amount,
                    'status' => $rnf->status,
                    'source_type' => $rnf->source_type,
                    'items' => $rnfItems,
                    'attachments' => $rnfAttachments
                ];
                $categoryTransactions['totals']['rnf_total'] += $rnf->total_amount;
            }

            // 2. Get Purchase Requisition (PR) transactions
            // EXCLUDE PR yang sudah ada di NFP (untuk menghindari double counting)
            // Get PR IDs in this category first (untuk menghitung PR yang sudah di NFP)
            $prIdsInCategory = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($category) {
                    $q->where('pr.category_id', $category->id)
                      ->orWhere('pri.category_id', $category->id);
                })
                ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('pr.is_held', false)
                ->distinct()
                ->pluck('pr.id')
                ->toArray();

            // Get PO IDs that are linked to PRs in this category
            $poIdsInCategory = [];
            if (count($prIdsInCategory) > 0) {
                $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->whereIn('poi.source_id', $prIdsInCategory)
                    ->distinct()
                    ->pluck('poi.purchase_order_ops_id')
                    ->toArray();
            }

            // Get PR IDs yang sudah ada di NFP (untuk exclude dari PR list)
            $prIdsInNFP = [];
            if (count($poIdsInCategory) > 0) {
                $prIdsInNFP = DB::table('non_food_payments as nfp')
                    ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                    ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->distinct()
                    ->pluck('poi.source_id')
                    ->toArray();
            }

            // Get Purchase Requisition (PR) transactions - UNPAID ONLY
            // Menggunakan logika yang sama dengan OPEX Report:
            // - Jika PR sudah jadi PO: gunakan PO total - payment amount
            // - Jika PR belum jadi PO: gunakan PR amount - payment amount
            // - Hanya menghitung yang unpaid amount > 0
            
            // Get all PRs in category
            $allPrs = DB::table('purchase_requisitions as pr')
                ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($category) {
                    $q->where('pr.category_id', $category->id)
                      ->orWhere('pri.category_id', $category->id);
                })
                ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('pr.is_held', false)
                ->groupBy('pr.id', 'pr.pr_number', 'pr.created_at', 'pr.status', 'pr.amount', 'o.id_outlet', 'o.nama_outlet')
                ->select(
                    'pr.id as pr_id',
                    'pr.pr_number',
                    'pr.created_at as transaction_date',
                    'pr.amount as pr_amount',
                    'pr.status',
                    'o.id_outlet as outlet_id',
                    'o.nama_outlet as outlet_name'
                )
                ->get();

            // Get PO totals by PR (if PR has been converted to PO)
            $poTotalsByPr = [];
            if (count($allPrs) > 0) {
                $prIds = $allPrs->pluck('pr_id')->toArray();
                $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->leftJoin('purchase_requisition_items as pri', function($join) {
                        $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                             ->where(function($q) {
                                 $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                                   ->orWhere(function($q2) {
                                       $q2->whereNull('poi.pr_ops_item_id')
                                          ->whereColumn('poi.item_name', 'pri.item_name');
                                   });
                             });
                    })
                    ->where(function($q) use ($category) {
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereIn('pr.id', $prIds)
                    ->where('pr.is_held', false)
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->where('poo.status', 'approved')
                    ->whereBetween('poo.date', [$dateFrom, $dateTo])
                    ->groupBy('pr.id')
                    ->select(
                        'pr.id as pr_id',
                        DB::raw('SUM(poi.total) as po_total')
                    )
                    ->pluck('po_total', 'pr_id')
                    ->toArray();
            }

            // Get total paid per PR
            $paidTotalsByPr = [];
            if (count($allPrs) > 0) {
                $prIds = $allPrs->pluck('pr_id')->toArray();
                $paidTotalsByPr = DB::table('non_food_payments as nfp')
                    ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->leftJoin('purchase_requisition_items as pri', function($join) {
                        $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                             ->where(function($q) {
                                 $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                                   ->orWhere(function($q2) {
                                       $q2->whereNull('poi.pr_ops_item_id')
                                          ->whereColumn('poi.item_name', 'pri.item_name');
                                   });
                             });
                    })
                    ->where(function($q) use ($category) {
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereIn('pr.id', $prIds)
                    ->where('pr.is_held', false)
                    ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->groupBy('pr.id')
                    ->select(
                        'pr.id as pr_id',
                        DB::raw('SUM(nfp.amount) as total_paid')
                    )
                    ->pluck('total_paid', 'pr_id')
                    ->toArray();
            }

            // Calculate unpaid for each PR (only include if unpaid > 0)
            $prTransactions = [];
            foreach ($allPrs as $pr) {
                $prId = $pr->pr_id;
                $poTotal = $poTotalsByPr[$prId] ?? 0;
                $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                
                // If PR hasn't been converted to PO, use PR amount
                // If PR has been converted to PO, use PO total
                $totalAmount = $poTotal > 0 ? $poTotal : $pr->pr_amount;
                $unpaidAmount = $totalAmount - $totalPaid;
                
                // Only include if there's unpaid amount
                if ($unpaidAmount > 0) {
                    $prTransactions[] = (object)[
                        'id' => $prId,
                        'transaction_number' => $pr->pr_number,
                        'transaction_date' => $pr->transaction_date,
                        'outlet_name' => $pr->outlet_name,
                        'total_amount' => $unpaidAmount,
                        'status' => $pr->status,
                        'source_type' => 'purchase_requisition'
                    ];
                }
            }

            foreach ($prTransactions as $pr) {
                // Get PR items
                $prItems = DB::table('purchase_requisition_items')
                    ->where('purchase_requisition_id', $pr->id)
                    ->select('id', 'item_name', 'qty as quantity', 'unit', 'unit_price as price', 'subtotal', 'category_id', 'outlet_id')
                    ->get();

                // Get PR attachments
                $prAttachments = [];
                try {
                    $prAttachments = DB::table('purchase_requisition_attachments')
                        ->where('purchase_requisition_id', $pr->id)
                        ->select('id', 'file_name', 'file_path', 'description', 'created_at')
                        ->get()
                        ->toArray();
                } catch (\Exception $e) {
                    // Table might not exist, continue without attachments
                }

                // Get PR items
                $prItems = DB::table('purchase_requisition_items')
                    ->where('purchase_requisition_id', $pr->id)
                    ->select('id', 'item_name', 'qty as quantity', 'unit', 'unit_price as price', 'subtotal', 'category_id', 'outlet_id')
                    ->get();

                // Get PR attachments
                $prAttachments = [];
                try {
                    $prAttachments = DB::table('purchase_requisition_attachments')
                        ->where('purchase_requisition_id', $pr->id)
                        ->select('id', 'file_name', 'file_path', 'description', 'created_at')
                        ->get()
                        ->toArray();
                } catch (\Exception $e) {
                    // Table might not exist, continue without attachments
                }

                $categoryTransactions['transactions']['pr'][] = [
                    'id' => $pr->id,
                    'transaction_number' => $pr->transaction_number,
                    'date' => $pr->transaction_date,
                    'outlet_name' => $pr->outlet_name,
                    'amount' => $pr->total_amount,
                    'status' => $pr->status,
                    'source_type' => $pr->source_type,
                    'items' => $prItems,
                    'attachments' => $prAttachments
                ];
                $categoryTransactions['totals']['pr_total'] += $pr->total_amount;
            }

            // 3. Get Non Food Payment (NFP) transactions
            // Use $prIdsInCategory and $poIdsInCategory yang sudah dihitung di atas

            // Get Non Food Payments
            $nfpTransactions = collect();
            if (count($poIdsInCategory) > 0) {
                $nfpTransactions = DB::table('non_food_payments as nfp')
                    ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->leftJoin('purchase_requisition_items as pri', function($join) {
                        $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                             ->where(function($q) {
                                 $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                                   ->orWhere(function($q2) {
                                       $q2->whereNull('poi.pr_ops_item_id')
                                          ->whereColumn('poi.item_name', 'pri.item_name');
                                   });
                             });
                    })
                    ->leftJoin('tbl_data_outlet as o', function($join) {
                        $join->on(DB::raw('COALESCE(pri.outlet_id, pr.outlet_id)'), '=', 'o.id_outlet');
                    })
                    ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                    ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->groupBy('nfp.id', 'nfp.payment_number', 'nfp.payment_date', 'nfp.amount', 'nfp.status', 'o.id_outlet', 'o.nama_outlet')
                    ->select(
                        'nfp.id',
                        'nfp.payment_number as transaction_number',
                        'nfp.payment_date as transaction_date',
                        'o.nama_outlet as outlet_name',
                        'nfp.amount as total_amount',
                        'nfp.status',
                        DB::raw('"non_food_payment" as source_type')
                    )
                    ->orderBy('nfp.payment_date', 'desc')
                    ->get();
            }

            foreach ($nfpTransactions as $nfp) {
                // Get NFP items from PO
                $nfpItems = [];
                $nfpPoId = DB::table('non_food_payments')
                    ->where('id', $nfp->id)
                    ->value('purchase_order_ops_id');
                
                if ($nfpPoId) {
                    $nfpItems = DB::table('purchase_order_ops_items as poi')
                        ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                        ->where('poi.purchase_order_ops_id', $nfpPoId)
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->select(
                            'poi.id',
                            'poi.item_name',
                            'poi.quantity',
                            'poi.unit',
                            'poi.price',
                            'poi.total as subtotal'
                        )
                        ->get();
                }

                // Get NFP attachments from PO
                $nfpAttachments = [];
                if ($nfpPoId) {
                    try {
                        $nfpAttachments = DB::table('purchase_order_ops_attachments')
                            ->where('purchase_order_ops_id', $nfpPoId)
                            ->select('id', 'file_name', 'file_path', 'description', 'created_at')
                            ->get()
                            ->toArray();
                    } catch (\Exception $e) {
                        // Table might not exist, continue without attachments
                    }
                }

                $categoryTransactions['transactions']['nfp'][] = [
                    'id' => $nfp->id,
                    'transaction_number' => $nfp->transaction_number,
                    'date' => $nfp->transaction_date,
                    'outlet_name' => $nfp->outlet_name,
                    'amount' => $nfp->total_amount,
                    'status' => $nfp->status,
                    'source_type' => $nfp->source_type,
                    'items' => $nfpItems,
                    'attachments' => $nfpAttachments
                ];
                $categoryTransactions['totals']['nfp_total'] += $nfp->total_amount;
            }

            // Calculate grand total
            $categoryTransactions['totals']['grand_total'] = 
                $categoryTransactions['totals']['rnf_total'] + 
                $categoryTransactions['totals']['pr_total'] + 
                $categoryTransactions['totals']['nfp_total'];

            // Only add category if it has transactions
            if (count($categoryTransactions['transactions']['rnf']) > 0 || 
                count($categoryTransactions['transactions']['pr']) > 0 || 
                count($categoryTransactions['transactions']['nfp']) > 0) {
                $categoryData[] = $categoryTransactions;
            }
        }

        // Calculate summary
        $totalRnf = 0;
        $totalPr = 0;
        $totalNfp = 0;
        
        foreach ($categoryData as $cat) {
            $totalRnf += $cat['totals']['rnf_total'];
            $totalPr += $cat['totals']['pr_total'];
            $totalNfp += $cat['totals']['nfp_total'];
        }
        
        $summary = [
            'total_categories' => count($categoryData),
            'total_rnf' => $totalRnf,
            'total_pr' => $totalPr,
            'total_nfp' => $totalNfp,
            'grand_total' => $totalRnf + $totalPr + $totalNfp
        ];

        return Inertia::render('OpexByCategory/Index', [
            'categoryData' => $categoryData,
            'categories' => $categories,
            'filters' => $request->only(['date_from', 'date_to', 'category_id']),
            'summary' => $summary
        ]);
    }

    public function export(Request $request)
    {
        // Get filter parameters (same as index)
        $dateFrom = $request->input('date_from', date('Y-m-01'));
        $dateTo = $request->input('date_to', date('Y-m-t'));
        $categoryId = $request->input('category_id');

        // Get all categories
        $categories = DB::table('purchase_requisition_categories')
            ->select('id', 'name', 'division', 'subcategory', 'budget_type', 'budget_limit')
            ->orderBy('name')
            ->get();

        // Build export data (same logic as index but simplified for Excel)
        $exportData = [];
        $summary = [
            'total_rnf' => 0,
            'total_pr' => 0,
            'total_nfp' => 0,
            'grand_total' => 0
        ];

        foreach ($categories as $category) {
            if ($categoryId && $category->id != $categoryId) {
                continue;
            }

            // Get RNF transactions (hanya approved, sama seperti OPEX Report)
            $rnfTotal = DB::table('retail_non_food as rnf')
                ->where('rnf.category_budget_id', $category->id)
                ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
                ->where('rnf.status', 'approved') // Hanya approved, sama seperti OPEX Report
                ->sum('rnf.total_amount');

            // Get PR IDs in category
            $prIdsInCategory = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($category) {
                    $q->where('pr.category_id', $category->id)
                      ->orWhere('pri.category_id', $category->id);
                })
                ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('pr.is_held', false)
                ->distinct()
                ->pluck('pr.id')
                ->toArray();

            // Get PO IDs
            $poIdsInCategory = [];
            if (count($prIdsInCategory) > 0) {
                $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->whereIn('poi.source_id', $prIdsInCategory)
                    ->distinct()
                    ->pluck('poi.purchase_order_ops_id')
                    ->toArray();
            }

            // Get PR unpaid total (menggunakan logika yang sama dengan index method)
            // Get all PRs in category
            $allPrs = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($category) {
                    $q->where('pr.category_id', $category->id)
                      ->orWhere('pri.category_id', $category->id);
                })
                ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('pr.is_held', false)
                ->groupBy('pr.id', 'pr.amount')
                ->select('pr.id as pr_id', 'pr.amount as pr_amount')
                ->get();

            // Get PO totals by PR
            $poTotalsByPr = [];
            if (count($allPrs) > 0) {
                $prIds = $allPrs->pluck('pr_id')->toArray();
                $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->leftJoin('purchase_requisition_items as pri', function($join) {
                        $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                             ->where(function($q) {
                                 $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                                   ->orWhere(function($q2) {
                                       $q2->whereNull('poi.pr_ops_item_id')
                                          ->whereColumn('poi.item_name', 'pri.item_name');
                                   });
                             });
                    })
                    ->where(function($q) use ($category) {
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereIn('pr.id', $prIds)
                    ->where('pr.is_held', false)
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->where('poo.status', 'approved')
                    ->whereBetween('poo.date', [$dateFrom, $dateTo])
                    ->groupBy('pr.id')
                    ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                    ->pluck('po_total', 'pr_id')
                    ->toArray();
            }

            // Get total paid per PR
            $paidTotalsByPr = [];
            if (count($allPrs) > 0) {
                $prIds = $allPrs->pluck('pr_id')->toArray();
                $paidTotalsByPr = DB::table('non_food_payments as nfp')
                    ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->leftJoin('purchase_requisition_items as pri', function($join) {
                        $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                             ->where(function($q) {
                                 $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                                   ->orWhere(function($q2) {
                                       $q2->whereNull('poi.pr_ops_item_id')
                                          ->whereColumn('poi.item_name', 'pri.item_name');
                                   });
                             });
                    })
                    ->where(function($q) use ($category) {
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereIn('pr.id', $prIds)
                    ->where('pr.is_held', false)
                    ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->groupBy('pr.id')
                    ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                    ->pluck('total_paid', 'pr_id')
                    ->toArray();
            }

            // Calculate unpaid total
            $prTotal = 0;
            foreach ($allPrs as $pr) {
                $prId = $pr->pr_id;
                $poTotal = $poTotalsByPr[$prId] ?? 0;
                $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                
                // If PR hasn't been converted to PO, use PR amount
                // If PR has been converted to PO, use PO total
                $totalAmount = $poTotal > 0 ? $poTotal : $pr->pr_amount;
                $unpaidAmount = $totalAmount - $totalPaid;
                
                // Only include if there's unpaid amount
                if ($unpaidAmount > 0) {
                    $prTotal += $unpaidAmount;
                }
            }

            // Get NFP total
            $nfpTotal = 0;
            if (count($poIdsInCategory) > 0) {
                $nfpTotal = DB::table('non_food_payments as nfp')
                    ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                    ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->sum('nfp.amount');
            }

            $categoryTotal = $rnfTotal + $prTotal + $nfpTotal;

            if ($categoryTotal > 0) {
                $exportData[] = [
                    $category->name,
                    $category->division ?? '',
                    $category->subcategory ?? '',
                    (float)$rnfTotal,
                    (float)$prTotal,
                    (float)$nfpTotal,
                    (float)$categoryTotal
                ];

                $summary['total_rnf'] += $rnfTotal;
                $summary['total_pr'] += $prTotal;
                $summary['total_nfp'] += $nfpTotal;
                $summary['grand_total'] += $categoryTotal;
            }
        }

        // Add summary row
        $exportData[] = [
            'GRAND TOTAL',
            '',
            '',
            (float)$summary['total_rnf'],
            (float)$summary['total_pr'],
            (float)$summary['total_nfp'],
            (float)$summary['grand_total']
        ];

        $filename = 'OPEX_By_Category_' . date('Y-m-d', strtotime($dateFrom)) . '_to_' . date('Y-m-d', strtotime($dateTo)) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new OpexByCategoryExport($exportData, $dateFrom, $dateTo),
            $filename
        );
    }
}

