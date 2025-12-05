<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class TravelKasbonReportController extends Controller
{
    public function index(Request $request)
    {
        \Log::info('TravelKasbonReportController::index called', [
            'all_params' => $request->all(),
            'has_filter' => $request->input('has_filter'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ]);
        
        // Get filter parameters
        $mode = $request->input('mode', 'all'); // all, travel_application, kasbon
        $status = $request->input('status', 'all'); // all, DRAFT, SUBMITTED, APPROVED, REJECTED, etc.
        $divisionId = $request->input('division_id');
        $outletId = $request->input('outlet_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10);
        $hasFilter = $request->input('has_filter', false); // Flag untuk menandai bahwa filter sudah diklik
        
        // Set default date range (current month) jika tidak ada
        // Ini memastikan data selalu dimuat saat pertama kali halaman dibuka
        if (!$dateFrom) {
            $dateFrom = now()->startOfMonth()->format('Y-m-d');
        }
        if (!$dateTo) {
            $dateTo = now()->endOfMonth()->format('Y-m-d');
        }
        
        \Log::info('TravelKasbonReportController::index - After setting defaults', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
        
        // Data akan selalu dimuat karena kita sudah set default date range

        // Query untuk PR dengan mode travel_application atau kasbon
        // Exclude PR yang payment-nya rejected
        // Use subquery untuk mendapatkan PR yang punya payment rejected
        $excludedPrIds = DB::table('non_food_payments')
            ->where('status', 'rejected')
            ->whereNotNull('purchase_requisition_id') // Only exclude PRs, not PO payments
            ->pluck('purchase_requisition_id')
            ->filter(function($id) {
                return $id !== null; // Remove null values
            })
            ->unique()
            ->values()
            ->toArray();
            
        \Log::info('TravelKasbonReportController::index - Excluded PR IDs', [
            'excluded_count' => count($excludedPrIds),
            'excluded_ids' => $excludedPrIds,
        ]);
        
        // Debug: Check if there are any PRs with travel_application or kasbon mode
        $totalPrsWithMode = DB::table('purchase_requisitions')
            ->whereIn('mode', ['travel_application', 'kasbon'])
            ->count();
            
        $totalPrsWithModeAndDate = DB::table('purchase_requisitions')
            ->whereIn('mode', ['travel_application', 'kasbon'])
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->count();
            
        \Log::info('TravelKasbonReportController::index - Debug PR counts', [
            'total_prs_with_mode' => $totalPrsWithMode,
            'total_prs_with_mode_and_date' => $totalPrsWithModeAndDate,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        $query = DB::table('purchase_requisitions as pr')
            ->leftJoin('tbl_data_divisi as d', 'pr.division_id', '=', 'd.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as creator', 'pr.created_by', '=', 'creator.id')
            ->leftJoin('users as requester', 'pr.requested_by', '=', 'requester.id')
            ->leftJoin('users as approved_ssd', 'pr.approved_ssd_by', '=', 'approved_ssd.id')
            ->leftJoin('users as approved_cc', 'pr.approved_cc_by', '=', 'approved_cc.id')
            ->leftJoin('users as held_by_user', 'pr.held_by', '=', 'held_by_user.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereIn('pr.mode', ['travel_application', 'kasbon'])
            ->where('pr.status', '!=', 'REJECTED') // Exclude REJECTED status
            ->where('pr.status', '!=', 'DRAFT') // Exclude DRAFT status
            ->select(
                'pr.id',
                'pr.pr_number',
                'pr.date',
                'pr.mode',
                'pr.title',
                'pr.description',
                'pr.amount',
                'pr.status',
                'pr.priority',
                'pr.is_held',
                'pr.hold_reason',
                'pr.notes',
                'pr.created_at',
                'pr.updated_at',
                'pr.approved_ssd_at',
                'pr.approved_cc_at',
                'pr.held_at',
                'd.nama_divisi as division_name',
                'o.nama_outlet as outlet_name',
                'creator.nama_lengkap as creator_name',
                'requester.nama_lengkap as requester_name',
                'approved_ssd.nama_lengkap as approved_ssd_name',
                'approved_cc.nama_lengkap as approved_cc_name',
                'held_by_user.nama_lengkap as held_by_name',
                'prc.name as category_name'
            );
            
        // Only exclude PRs if there are excluded IDs
        if (!empty($excludedPrIds)) {
            $query->whereNotIn('pr.id', $excludedPrIds); // Exclude PR yang payment-nya rejected
        }

        // Apply filters
        if ($mode !== 'all') {
            $query->where('pr.mode', $mode);
        }

        if ($status !== 'all') {
            $query->where('pr.status', $status);
        }

        if ($divisionId) {
            $query->where('pr.division_id', $divisionId);
        }

        if ($outletId) {
            $query->where('pr.outlet_id', $outletId);
        }

        if ($dateFrom) {
            $query->whereDate('pr.date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('pr.date', '<=', $dateTo);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('pr.pr_number', 'like', "%{$search}%")
                  ->orWhere('pr.title', 'like', "%{$search}%")
                  ->orWhere('pr.description', 'like', "%{$search}%")
                  ->orWhere('creator.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('requester.nama_lengkap', 'like', "%{$search}%");
            });
        }

        // Calculate summary from all filtered data (before pagination)
        // Create a new query for summary with same filters including payment rejection check
        $summaryQuery = DB::table('purchase_requisitions as pr')
            ->whereIn('pr.mode', ['travel_application', 'kasbon'])
            ->where('pr.status', '!=', 'REJECTED') // Exclude REJECTED status
            ->where('pr.status', '!=', 'DRAFT'); // Exclude DRAFT status
            
        // Only exclude PRs if there are excluded IDs
        if (!empty($excludedPrIds)) {
            $summaryQuery->whereNotIn('pr.id', $excludedPrIds); // Exclude PR yang payment-nya rejected
        }
        
        // Apply same filters for summary
        if ($mode !== 'all') {
            $summaryQuery->where('pr.mode', $mode);
        }
        if ($status !== 'all') {
            $summaryQuery->where('pr.status', $status);
        }
        if ($divisionId) {
            $summaryQuery->where('pr.division_id', $divisionId);
        }
        if ($outletId) {
            $summaryQuery->where('pr.outlet_id', $outletId);
        }
        if ($dateFrom) {
            $summaryQuery->whereDate('pr.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $summaryQuery->whereDate('pr.date', '<=', $dateTo);
        }
        if ($search) {
            $summaryQuery->where(function($q) use ($search) {
                $q->where('pr.pr_number', 'like', "%{$search}%")
                  ->orWhere('pr.title', 'like', "%{$search}%")
                  ->orWhere('pr.description', 'like', "%{$search}%");
            });
        }
        
        $allPrsForSummary = $summaryQuery->select('pr.mode', 'pr.amount', 'pr.status')->distinct()->get();

        // Get paginated results
        $prs = $query->orderBy('pr.date', 'desc')
            ->orderBy('pr.created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
            
        \Log::info('TravelKasbonReportController::index - Query results', [
            'total' => $prs->total(),
            'count' => $prs->count(),
            'current_page' => $prs->currentPage(),
        ]);

        // Get additional data for each PR
        $reportData = [];
        foreach ($prs->items() as $pr) {
            // Get items
            $items = DB::table('purchase_requisition_items')
                ->where('purchase_requisition_id', $pr->id)
                ->select(
                    'id',
                    'item_name',
                    'qty as quantity',
                    'unit',
                    'unit_price',
                    'subtotal',
                    'outlet_id',
                    'category_id',
                    'item_type',
                    'allowance_recipient_name',
                    'allowance_account_number',
                    'others_notes'
                )
                ->get();

            // Get attachments
            $attachments = DB::table('purchase_requisition_attachments')
                ->leftJoin('users', 'purchase_requisition_attachments.uploaded_by', '=', 'users.id')
                ->where('purchase_requisition_attachments.purchase_requisition_id', $pr->id)
                ->select(
                    'purchase_requisition_attachments.*',
                    'users.nama_lengkap as uploader_name'
                )
                ->get();

            // Get payment info (if any) - exclude rejected
            $payment = DB::table('non_food_payments')
                ->where('purchase_requisition_id', $pr->id)
                ->where('status', '!=', 'cancelled')
                ->where('status', '!=', 'rejected')
                ->select('id', 'payment_number', 'amount', 'payment_date', 'status', 'payment_method')
                ->first();

            // Get approval history
            $approvalHistory = DB::table('purchase_requisition_approval_flows as praf')
                ->leftJoin('users', 'praf.approver_id', '=', 'users.id')
                ->where('praf.purchase_requisition_id', $pr->id)
                ->select(
                    'praf.*',
                    'users.nama_lengkap as approver_name'
                )
                ->orderBy('praf.approval_level', 'asc')
                ->get();

            // Get comments
            $comments = DB::table('purchase_requisition_comments as prc')
                ->leftJoin('users', 'prc.user_id', '=', 'users.id')
                ->where('prc.purchase_requisition_id', $pr->id)
                ->select(
                    'prc.*',
                    'users.nama_lengkap as commenter_name'
                )
                ->orderBy('prc.created_at', 'asc')
                ->get();

            // Parse notes untuk travel application
            $parsedNotes = null;
            $outletNamesFromNotes = [];
            if ($pr->mode === 'travel_application' && $pr->notes) {
                $notesData = json_decode($pr->notes, true);
                if (is_array($notesData)) {
                    // Extract outlet names dari outlet_ids
                    if (isset($notesData['outlet_ids']) && is_array($notesData['outlet_ids']) && count($notesData['outlet_ids']) > 0) {
                        $outletIds = $notesData['outlet_ids'];
                        $outletNames = DB::table('tbl_data_outlet')
                            ->whereIn('id_outlet', $outletIds)
                            ->pluck('nama_outlet', 'id_outlet')
                            ->toArray();
                        $outletNamesFromNotes = array_values($outletNames);
                    }
                    // Format notes untuk display
                    $parsedNotes = [];
                    if (isset($notesData['agenda']) && $notesData['agenda']) {
                        $parsedNotes['agenda'] = $notesData['agenda'];
                    }
                    if (isset($notesData['notes']) && $notesData['notes']) {
                        $parsedNotes['notes'] = $notesData['notes'];
                    }
                    if (count($outletNamesFromNotes) > 0) {
                        $parsedNotes['outlets'] = $outletNamesFromNotes;
                    }
                }
            }

            $reportData[] = [
                'id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'date' => $pr->date,
                'mode' => $pr->mode,
                'mode_label' => $pr->mode === 'travel_application' ? 'Travel Application' : 'Kasbon',
                'title' => $pr->title,
                'description' => $pr->description,
                'amount' => (float) $pr->amount,
                'status' => $pr->status,
                'priority' => $pr->priority,
                'is_held' => (bool) $pr->is_held,
                'hold_reason' => $pr->hold_reason,
                'notes' => $pr->notes, // Keep raw for reference
                'parsed_notes' => $parsedNotes, // Parsed notes for display
                'outlet_names_from_notes' => $outletNamesFromNotes,
                'created_at' => $pr->created_at,
                'updated_at' => $pr->updated_at,
                'approved_ssd_at' => $pr->approved_ssd_at,
                'approved_cc_at' => $pr->approved_cc_at,
                'held_at' => $pr->held_at,
                'division_name' => $pr->division_name,
                'outlet_name' => $pr->outlet_name,
                'creator_name' => $pr->creator_name,
                'requester_name' => $pr->requester_name,
                'approved_ssd_name' => $pr->approved_ssd_name,
                'approved_cc_name' => $pr->approved_cc_name,
                'held_by_name' => $pr->held_by_name,
                'category_name' => $pr->category_name,
                'items' => $items,
                'attachments' => $attachments,
                'payment' => $payment,
                'approval_history' => $approvalHistory,
                'comments' => $comments,
            ];
        }

        // Get filter options
        $divisions = DB::table('tbl_data_divisi')
            ->select('id', 'nama_divisi as name')
            ->orderBy('nama_divisi')
            ->get();

        $outlets = DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        // Calculate summary from all filtered data (not just paginated)
        $summary = [
            'total_count' => $allPrsForSummary->count(),
            'total_amount' => $allPrsForSummary->sum('amount'),
            'by_status' => [],
            'by_mode' => [
                'travel_application' => [
                    'count' => 0,
                    'amount' => 0,
                    'by_status' => [],
                ],
                'kasbon' => [
                    'count' => 0,
                    'amount' => 0,
                    'by_status' => [],
                ],
            ],
        ];

        foreach ($allPrsForSummary as $pr) {
            // Count by status (overall)
            if (!isset($summary['by_status'][$pr->status])) {
                $summary['by_status'][$pr->status] = [
                    'count' => 0,
                    'amount' => 0,
                ];
            }
            $summary['by_status'][$pr->status]['count']++;
            $summary['by_status'][$pr->status]['amount'] += (float) $pr->amount;

            // Count and sum by mode
            $summary['by_mode'][$pr->mode]['count']++;
            $summary['by_mode'][$pr->mode]['amount'] += (float) $pr->amount;

            // Count by status per mode
            if (!isset($summary['by_mode'][$pr->mode]['by_status'][$pr->status])) {
                $summary['by_mode'][$pr->mode]['by_status'][$pr->status] = [
                    'count' => 0,
                    'amount' => 0,
                ];
            }
            $summary['by_mode'][$pr->mode]['by_status'][$pr->status]['count']++;
            $summary['by_mode'][$pr->mode]['by_status'][$pr->status]['amount'] += (float) $pr->amount;
        }

        return Inertia::render('Reports/TravelKasbonReport', [
            'reportData' => $reportData,
            'summary' => $summary,
            'divisions' => $divisions,
            'outlets' => $outlets,
            'pagination' => [
                'current_page' => $prs->currentPage(),
                'last_page' => $prs->lastPage(),
                'per_page' => $prs->perPage(),
                'total' => $prs->total(),
                'from' => $prs->firstItem(),
                'to' => $prs->lastItem(),
            ],
            'filters' => $request->only(['mode', 'status', 'division_id', 'outlet_id', 'date_from', 'date_to', 'search', 'per_page']),
        ]);
    }
}

