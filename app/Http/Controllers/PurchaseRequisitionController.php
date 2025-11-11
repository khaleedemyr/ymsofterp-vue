<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisitionItem;
use App\Models\PurchaseRequisitionApprovalFlow;
use App\Models\PurchaseRequisitionAttachment;
use App\Models\PurchaseRequisitionOutletBudget;
use App\Models\DivisionBudget;
use App\Models\Divisi;
use App\Models\Outlet;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class PurchaseRequisitionController extends Controller
{
    /**
     * Display a listing of purchase requisitions
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', 'all');
        $division = $request->get('division', 'all');
        $perPage = $request->get('per_page', 15);

        // Check if user has role with id '5af56935b011a' (can see all payments)
        $user = auth()->user();
        $canSeeAllPayments = false;
        
        if ($user) {
            $userRole = DB::table('erp_user_role')
                ->where('user_id', $user->id)
                ->where('role_id', '5af56935b011a')
                ->first();
            
            $canSeeAllPayments = $userRole !== null;
        }

        $query = PurchaseRequisition::with([
            'division',
            'outlet',
            'ticket',
            'category',
            'creator'
        ]);

        // Filter by created_by if user doesn't have special role
        if (!$canSeeAllPayments && $user) {
            $query->where('created_by', $user->id);
        }

        // Apply filters
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('pr_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($division !== 'all') {
            $query->where('division_id', $division);
        }

        $purchaseRequisitions = $query->orderBy('created_at', 'desc')
                                    ->paginate($perPage)
                                    ->withQueryString();

        // Get filter options
        $divisions = Divisi::whereHas('purchaseRequisitions')->active()->orderBy('nama_divisi')->get();
        
        // Statistics - apply same filter for statistics
        $statsQuery = PurchaseRequisition::query();
        if (!$canSeeAllPayments && $user) {
            $statsQuery->where('created_by', $user->id);
        }
        
        $statistics = [
            'total' => (clone $statsQuery)->count(),
            'draft' => (clone $statsQuery)->where('status', 'DRAFT')->count(),
            'submitted' => (clone $statsQuery)->where('status', 'SUBMITTED')->count(),
            'approved' => (clone $statsQuery)->where('status', 'APPROVED')->count(),
        ];

        return Inertia::render('PurchaseRequisition/Index', [
            'data' => $purchaseRequisitions,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'division' => $division,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'divisions' => $divisions,
            ],
            'statistics' => $statistics,
            'auth' => [
                'user' => auth()->user()
            ],
        ]);
    }

    /**
     * Show the form for creating a new purchase requisition
     */
    public function create()
    {
        $categories = PurchaseRequisitionCategory::orderBy('name')->get();
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();
        $tickets = Ticket::whereHas('status', function($query) {
                        $query->whereNotIn('slug', ['closed', 'cancelled']);
                    })
                    ->with(['outlet', 'category', 'status'])
                    ->orderBy('created_at', 'desc')
                    ->limit(100)
                    ->get();
        $divisions = Divisi::active()->orderBy('nama_divisi')->get();

        return Inertia::render('PurchaseRequisition/Create', [
            'categories' => $categories,
            'outlets' => $outlets,
            'tickets' => $tickets,
            'divisions' => $divisions,
        ]);
    }

    /**
     * Store a newly created purchase requisition
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'division_id' => 'required|exists:tbl_data_divisi,id',
            'category_id' => 'nullable|exists:purchase_requisition_categories,id',
            'outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
            'ticket_id' => 'nullable|exists:tickets,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'string|in:IDR,USD',
            'priority' => 'string|in:LOW,MEDIUM,HIGH,URGENT',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            'approvers' => 'nullable|array',
            'approvers.*' => 'required|exists:users,id',
            'mode' => 'required|in:pr_ops,purchase_payment',
        ]);

        // Check budget limit before saving
        if ($validated['category_id']) {
            $budgetValidation = $this->validateBudgetLimit($validated['category_id'], $validated['outlet_id'] ?? null, $validated['amount']);
            if (!$budgetValidation['valid']) {
                return back()->withErrors([
                    'budget_exceeded' => $budgetValidation['message']
                ]);
            }
        }

        // Generate PR number
        $validated['pr_number'] = $this->generateRequisitionNumber();
        $validated['date'] = now()->toDateString();
        $validated['warehouse_id'] = 1; // Default warehouse
        $validated['requested_by'] = auth()->id();
        $validated['department'] = 'Operations';
        $validated['status'] = 'DRAFT';
        // Persist mode selection
        $validated['mode'] = $validated['mode'] ?? 'pr_ops';
        $validated['created_by'] = auth()->id();

        try {
            DB::beginTransaction();
            
            // Create purchase requisition
            $purchaseRequisition = PurchaseRequisition::create($validated);
            
            // Create items
            foreach ($validated['items'] as $itemData) {
                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $purchaseRequisition->id,
                    'item_name' => $itemData['item_name'],
                    'qty' => $itemData['qty'],
                    'unit' => $itemData['unit'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['subtotal'],
                ]);
            }

            // Create approval flows if approvers provided
            if (!empty($validated['approvers'])) {
                foreach ($validated['approvers'] as $index => $approverId) {
                    PurchaseRequisitionApprovalFlow::create([
                        'purchase_requisition_id' => $purchaseRequisition->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1, // Level 1 = terendah, level terakhir = tertinggi
                        'status' => 'PENDING',
                    ]);
                }
            }
            
            DB::commit();
            
            // Send notification to the lowest level approver
            $this->sendNotificationToNextApprover($purchaseRequisition);
            
            // Return JSON response for AJAX requests (for file upload)
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Purchase Requisition created successfully!',
                    'purchase_requisition' => $purchaseRequisition
                ]);
            }
            
            return redirect()->route('purchase-requisitions.show', $purchaseRequisition)
                           ->with('success', 'Purchase Requisition created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create purchase requisition: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified purchase requisition
     */
    public function show(PurchaseRequisition $purchaseRequisition)
    {
        $purchaseRequisition->load([
            'division',
            'outlet',
            'ticket',
            'category',
            'creator',
            'comments.user',
            'attachments.uploader',
            'history.user',
            'items',
            'approvalFlows.approver.jabatan'
        ]);

        // Get budget information for the category
        $budgetInfo = null;
        if ($purchaseRequisition->category_id) {
            $category = $purchaseRequisition->category;
            if ($category) {
                $currentMonth = date('m');
                $currentYear = date('Y');
                
                if ($category->isGlobalBudget()) {
                    // GLOBAL BUDGET: Calculate across all outlets
                    $usedAmount = PurchaseRequisition::where('category_id', $purchaseRequisition->category_id)
                        ->whereYear('created_at', $currentYear)
                        ->whereMonth('created_at', $currentMonth)
                        ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                        ->sum('amount');
                    
                    $budgetInfo = [
                        'budget_type' => 'GLOBAL',
                        'category_budget' => $category->budget_limit,
                        'category_used_amount' => $usedAmount,
                        'category_remaining_amount' => $category->budget_limit - $usedAmount,
                        'current_month' => $currentMonth,
                        'current_year' => $currentYear,
                    ];
                } else if ($category->isPerOutletBudget() && $purchaseRequisition->outlet_id) {
                    // PER_OUTLET BUDGET: Calculate per specific outlet
                    $outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $purchaseRequisition->category_id)
                        ->where('outlet_id', $purchaseRequisition->outlet_id)
                        ->with('outlet')
                        ->first();
                    
                    if ($outletBudget) {
                        $outletUsedAmount = PurchaseRequisition::where('category_id', $purchaseRequisition->category_id)
                            ->where('outlet_id', $purchaseRequisition->outlet_id)
                            ->whereYear('created_at', $currentYear)
                            ->whereMonth('created_at', $currentMonth)
                            ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                            ->sum('amount');
                        
                        $budgetInfo = [
                            'budget_type' => 'PER_OUTLET',
                            'category_budget' => $category->budget_limit, // Global budget for reference
                            'outlet_budget' => $outletBudget->allocated_budget,
                            'outlet_used_amount' => $outletUsedAmount,
                            'outlet_remaining_amount' => $outletBudget->allocated_budget - $outletUsedAmount,
                            'current_month' => $currentMonth,
                            'current_year' => $currentYear,
                            'outlet_info' => [
                                'id' => $outletBudget->outlet_id,
                                'name' => $outletBudget->outlet->nama_outlet ?? 'Unknown Outlet',
                            ],
                        ];
                    }
                }
            }
        }

        return Inertia::render('PurchaseRequisition/Show', [
            'purchaseRequisition' => $purchaseRequisition,
            'budgetInfo' => $budgetInfo,
            'currentUser' => auth()->user(),
        ]);
    }

    /**
     * Show the form for editing the specified purchase requisition
     */
    public function edit(PurchaseRequisition $purchaseRequisition)
    {
        if ($purchaseRequisition->status !== 'DRAFT') {
            return redirect()->route('purchase-requisitions.show', $purchaseRequisition)
                           ->with('error', 'Only draft purchase requisitions can be edited.');
        }

        $categories = PurchaseRequisitionCategory::orderBy('name')->get();
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();
        $tickets = Ticket::whereHas('status', function($query) {
                        $query->whereNotIn('slug', ['closed', 'cancelled']);
                    })
                    ->with(['outlet', 'category', 'status'])
                    ->orderBy('created_at', 'desc')
                    ->limit(100)
                    ->get();
        $divisions = Divisi::active()->orderBy('nama_divisi')->get();

        return Inertia::render('PurchaseRequisition/Edit', [
            'purchaseRequisition' => $purchaseRequisition->load('attachments.uploader'),
            'categories' => $categories,
            'outlets' => $outlets,
            'tickets' => $tickets,
            'divisions' => $divisions,
        ]);
    }

    /**
     * Update the specified purchase requisition
     */
    public function update(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        if ($purchaseRequisition->status !== 'DRAFT') {
            return back()->withErrors(['error' => 'Only draft purchase requisitions can be edited.']);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'division_id' => 'required|exists:tbl_data_divisi,id',
            'category_id' => 'nullable|exists:purchase_requisition_categories,id',
            'outlet_id' => 'nullable|exists:outlets,id',
            'ticket_id' => 'nullable|exists:tickets,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'string|in:IDR,USD',
            'priority' => 'string|in:LOW,MEDIUM,HIGH,URGENT',
        ]);

        // Check budget limit before updating (exclude current record from calculation)
        if ($validated['category_id']) {
            $budgetValidation = $this->validateBudgetLimit($validated['category_id'], $validated['outlet_id'] ?? null, $validated['amount'], $purchaseRequisition->id);
            if (!$budgetValidation['valid']) {
                return back()->withErrors([
                    'budget_exceeded' => $budgetValidation['message']
                ]);
            }
        }

        $validated['updated_by'] = auth()->id();

        try {
            $purchaseRequisition->update($validated);
            
            return redirect()->route('purchase-requisitions.show', $purchaseRequisition)
                           ->with('success', 'Purchase Requisition updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update purchase requisition: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified purchase requisition
     */
    public function destroy(PurchaseRequisition $purchaseRequisition)
    {
        // Allow delete for DRAFT and SUBMITTED status (not yet approved/processed)
        $deletableStatuses = ['DRAFT', 'SUBMITTED'];
        if (!in_array($purchaseRequisition->status, $deletableStatuses)) {
            return back()->withErrors(['error' => 'Only draft and submitted (not yet approved) purchase requisitions can be deleted.']);
        }

        if ($purchaseRequisition->created_by !== auth()->id()) {
            return back()->withErrors(['error' => 'You can only delete your own purchase requisitions.']);
        }

        try {
            // Delete related data first (due to foreign key constraints)
            $purchaseRequisition->items()->delete();
            $purchaseRequisition->approvalFlows()->delete();
            $purchaseRequisition->comments()->delete();
            $purchaseRequisition->attachments()->delete();
            $purchaseRequisition->history()->delete();

            // Delete the main record
            $purchaseRequisition->delete();
            
            return redirect()->route('purchase-requisitions.index')
                           ->with('success', 'Purchase Requisition deleted successfully!');
        } catch (\Exception $e) {
            \Log::error('Error deleting purchase requisition', [
                'id' => $purchaseRequisition->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['error' => 'Failed to delete purchase requisition: ' . $e->getMessage()]);
        }
    }

    /**
     * Submit purchase requisition for approval
     */
    public function submit(PurchaseRequisition $purchaseRequisition)
    {
        if ($purchaseRequisition->status !== 'DRAFT') {
            return back()->withErrors(['error' => 'Only draft purchase requisitions can be submitted.']);
        }

        try {
            $purchaseRequisition->update(['status' => 'SUBMITTED']);
            
            return redirect()->route('purchase-requisitions.show', $purchaseRequisition)
                           ->with('success', 'Purchase Requisition submitted for approval!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to submit purchase requisition: ' . $e->getMessage()]);
        }
    }

    /**
     * Approve purchase requisition
     */
    public function approve(PurchaseRequisition $purchaseRequisition)
    {
        if ($purchaseRequisition->status !== 'SUBMITTED') {
            return back()->withErrors(['error' => 'Only submitted purchase requisitions can be approved.']);
        }

        try {
            // Get current approver
            $currentApprover = auth()->user();
            
            // Update the approval flow for current approver
            $currentApprovalFlow = $purchaseRequisition->approvalFlows()
                ->where('approver_id', $currentApprover->id)
                ->where('status', 'PENDING')
                ->first();
            
            if ($currentApprovalFlow) {
                $currentApprovalFlow->update([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                ]);
            }
            
            // Check if there are more approvers pending
            $pendingApprovers = $purchaseRequisition->approvalFlows()
                ->where('status', 'PENDING')
                ->count();
            
            if ($pendingApprovers > 0) {
                // Still have pending approvers, keep status as SUBMITTED
                // Send notification to next approver
                $this->sendNotificationToNextApprover($purchaseRequisition);
                
                $message = 'Purchase Requisition approved! Notification sent to next approver.';
            } else {
                // All approvers have approved, update status to APPROVED
                $purchaseRequisition->update([
                    'status' => 'APPROVED',
                    'approved_ssd_by' => auth()->id(),
                    'approved_ssd_at' => now(),
                ]);
                
                // Send notification to creator that PR is fully approved
                $this->sendNotificationToCreator($purchaseRequisition, 'approved');
                
                $message = 'Purchase Requisition fully approved!';
            }
            
            return redirect()->route('purchase-requisitions.show', $purchaseRequisition)
                           ->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to approve purchase requisition: ' . $e->getMessage()]);
        }
    }

    /**
     * Reject purchase requisition
     */
    public function reject(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        if ($purchaseRequisition->status !== 'SUBMITTED') {
            return back()->withErrors(['error' => 'Only submitted purchase requisitions can be rejected.']);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            // Get current approver
            $currentApprover = auth()->user();
            
            // Update the approval flow for current approver
            $currentApprovalFlow = $purchaseRequisition->approvalFlows()
                ->where('approver_id', $currentApprover->id)
                ->where('status', 'PENDING')
                ->first();
            
            if ($currentApprovalFlow) {
                $currentApprovalFlow->update([
                    'status' => 'REJECTED',
                    'rejected_at' => now(),
                    'comments' => $validated['rejection_reason'],
                ]);
            }
            
            $purchaseRequisition->update([
                'status' => 'REJECTED',
                'notes' => $validated['rejection_reason'],
            ]);
            
            // Send notification to creator that PR was rejected
            $this->sendNotificationToCreator($purchaseRequisition, 'rejected');
            
            return redirect()->route('purchase-requisitions.show', $purchaseRequisition)
                           ->with('success', 'Purchase Requisition rejected.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to reject purchase requisition: ' . $e->getMessage()]);
        }
    }

    /**
     * Process purchase requisition
     */
    public function process(PurchaseRequisition $purchaseRequisition)
    {
        if ($purchaseRequisition->status !== 'APPROVED') {
            return back()->withErrors(['error' => 'Only approved purchase requisitions can be processed.']);
        }

        // For PR Ops and Payment modes, check if all items are in PO before allowing status change
        if (in_array($purchaseRequisition->mode, ['pr_ops', 'purchase_payment'])) {
            // Get all items for this PR
            $allPrItems = \App\Models\PurchaseRequisitionItem::where('purchase_requisition_id', $purchaseRequisition->id)->get();
            
            if ($allPrItems->isNotEmpty()) {
                // Get all PR item IDs that are already in PO
                $prItemIdsInPO = \Illuminate\Support\Facades\DB::table('purchase_order_ops_items')
                    ->whereNotNull('pr_ops_item_id')
                    ->whereIn('pr_ops_item_id', $allPrItems->pluck('id')->toArray())
                    ->pluck('pr_ops_item_id')
                    ->toArray();

                // Check if all items are in PO
                $allItemsInPO = $allPrItems->every(function ($item) use ($prItemIdsInPO) {
                    return in_array($item->id, $prItemIdsInPO);
                });

                if (!$allItemsInPO) {
                    return back()->withErrors([
                        'error' => 'Tidak dapat mengubah status ke PROCESSED. Belum semua item dari PR ini dibuat menjadi Purchase Order. Silakan buat PO untuk semua item terlebih dahulu.'
                    ]);
                }
            }
        }

        try {
            $purchaseRequisition->update(['status' => 'PROCESSED']);
            
            return redirect()->route('purchase-requisitions.show', $purchaseRequisition)
                           ->with('success', 'Purchase Requisition is being processed!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to process purchase requisition: ' . $e->getMessage()]);
        }
    }

    /**
     * Complete purchase requisition
     */
    public function complete(PurchaseRequisition $purchaseRequisition)
    {
        if ($purchaseRequisition->status !== 'PROCESSED') {
            return back()->withErrors(['error' => 'Only processed purchase requisitions can be completed.']);
        }

        try {
            $purchaseRequisition->update(['status' => 'COMPLETED']);
            
            return redirect()->route('purchase-requisitions.show', $purchaseRequisition)
                           ->with('success', 'Purchase Requisition completed!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to complete purchase requisition: ' . $e->getMessage()]);
        }
    }

    /**
     * Add comment to purchase requisition
     */
    public function addComment(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
            'is_internal' => 'boolean',
        ]);

        try {
            $purchaseRequisition->comments()->create([
                'user_id' => auth()->id(),
                'comment' => $validated['comment'],
                'is_internal' => $validated['is_internal'] ?? false,
            ]);
            
            return back()->with('success', 'Comment added successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to add comment: ' . $e->getMessage()]);
        }
    }


    /**
     * Get categories for API
     */
    public function getCategories()
    {
        $categories = PurchaseRequisitionCategory::orderBy('name')->get();
        return response()->json(['success' => true, 'categories' => $categories]);
    }

    /**
     * Get tickets for API
     */
    public function getTickets(Request $request)
    {
        $tickets = Ticket::whereHas('status', function($query) {
                        $query->whereNotIn('slug', ['closed', 'cancelled']);
                    })
                    ->with(['outlet', 'category', 'status'])
                    ->orderBy('created_at', 'desc')
                    ->limit(100)
                    ->get();
        
        return response()->json(['success' => true, 'tickets' => $tickets]);
    }

    /**
     * Get users for approval autocomplete
     */
    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');
        
        $users = User::where('users.status', 'A')
            ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where(function($query) use ($search) {
                $query->where('users.nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%")
                      ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            })
            ->select('users.id', 'users.nama_lengkap as name', 'users.email', 'tbl_data_jabatan.nama_jabatan as jabatan')
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();
        
        return response()->json(['success' => true, 'users' => $users]);
    }

    /**
     * Send notification to the next approver in line
     */
    private function sendNotificationToNextApprover($purchaseRequisition)
    {
        try {
            // Get the lowest level approver that is still pending
            $nextApprover = $purchaseRequisition->approvalFlows()
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();

            if (!$nextApprover) {
                return; // No pending approvers
            }

            // Get approver details
            $approver = $nextApprover->approver;
            if (!$approver) {
                return;
            }

            // Get creator details
            $creator = $purchaseRequisition->creator;
            $creatorName = $creator ? $creator->nama_lengkap : 'Unknown User';

            // Get division and category details
            $divisionName = $purchaseRequisition->division ? $purchaseRequisition->division->nama_divisi : 'Unknown Division';
            $categoryName = $purchaseRequisition->category ? $purchaseRequisition->category->name : 'Unknown Category';

            // Create notification message
            $message = "Purchase Requisition baru memerlukan persetujuan Anda:\n\n";
            $message .= "No: {$purchaseRequisition->pr_number}\n";
            $message .= "Judul: {$purchaseRequisition->title}\n";
            $message .= "Divisi: {$divisionName}\n";
            $message .= "Kategori: {$categoryName}\n";
            $message .= "Jumlah: Rp " . number_format($purchaseRequisition->amount, 0, ',', '.') . "\n";
            $message .= "Level Approval: {$nextApprover->approval_level}\n";
            $message .= "Diajukan oleh: {$creatorName}\n\n";
            $message .= "Silakan segera lakukan review dan approval.";

            // Insert notification
            DB::table('notifications')->insert([
                'user_id' => $approver->id,
                'task_id' => $purchaseRequisition->id,
                'type' => 'purchase_requisition_approval',
                'message' => $message,
                'url' => config('app.url') . '/purchase-requisitions/' . $purchaseRequisition->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);


        } catch (\Exception $e) {
            \Log::error('Failed to send notification to next approver', [
                'purchase_requisition_id' => $purchaseRequisition->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send notification to creator about PR status
     */
    private function sendNotificationToCreator($purchaseRequisition, $status)
    {
        try {
            $creator = $purchaseRequisition->creator;
            if (!$creator) {
                return;
            }

            // Get division and category details
            $divisionName = $purchaseRequisition->division ? $purchaseRequisition->division->nama_divisi : 'Unknown Division';
            $categoryName = $purchaseRequisition->category ? $purchaseRequisition->category->name : 'Unknown Category';

            $message = '';
            $type = '';

            switch ($status) {
                case 'approved':
                    $message = "Purchase Requisition Anda telah disetujui:\n\n";
                    $message .= "No: {$purchaseRequisition->pr_number}\n";
                    $message .= "Judul: {$purchaseRequisition->title}\n";
                    $message .= "Divisi: {$divisionName}\n";
                    $message .= "Kategori: {$categoryName}\n";
                    $message .= "Jumlah: Rp " . number_format($purchaseRequisition->amount, 0, ',', '.') . "\n\n";
                    $message .= "PR telah disetujui oleh semua approver dan siap untuk diproses.";
                    $type = 'purchase_requisition_approved';
                    break;
                
                case 'rejected':
                    $message = "Purchase Requisition Anda telah ditolak:\n\n";
                    $message .= "No: {$purchaseRequisition->pr_number}\n";
                    $message .= "Judul: {$purchaseRequisition->title}\n";
                    $message .= "Divisi: {$divisionName}\n";
                    $message .= "Kategori: {$categoryName}\n";
                    $message .= "Jumlah: Rp " . number_format($purchaseRequisition->amount, 0, ',', '.') . "\n\n";
                    $message .= "Alasan penolakan: " . ($purchaseRequisition->notes ?? 'Tidak ada alasan yang diberikan');
                    $type = 'purchase_requisition_rejected';
                    break;
            }

            // Insert notification
            DB::table('notifications')->insert([
                'user_id' => $creator->id,
                'task_id' => $purchaseRequisition->id,
                'type' => $type,
                'message' => $message,
                'url' => config('app.url') . '/purchase-requisitions/' . $purchaseRequisition->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);


        } catch (\Exception $e) {
            \Log::error('Failed to send notification to creator', [
                'purchase_requisition_id' => $purchaseRequisition->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get pending approvals for current user
     */
    public function getPendingApprovals()
    {
        try {
            $currentUser = auth()->user();
            
            // Get PRs where current user is the next approver in line
            $pendingApprovals = PurchaseRequisition::where('status', 'SUBMITTED')
                ->whereHas('approvalFlows', function($query) use ($currentUser) {
                    $query->where('approver_id', $currentUser->id)
                          ->where('status', 'PENDING');
                })
                ->with([
                    'division',
                    'category',
                    'outlet',
                    'creator',
                    'approvalFlows.approver.jabatan'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // Filter to only show PRs where current user is next in line
            $filteredApprovals = $pendingApprovals->filter(function($pr) use ($currentUser) {
                $pendingFlows = $pr->approvalFlows->where('status', 'PENDING');
                if ($pendingFlows->isEmpty()) return false;
                
                $nextApprover = $pendingFlows->sortBy('approval_level')->first();
                return $nextApprover->approver_id === $currentUser->id;
            });

            return response()->json([
                'success' => true,
                'purchase_requisitions' => $filteredApprovals->values()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting pending PR approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get pending approvals'
            ], 500);
        }
    }

    /**
     * Get approval details for modal
     */
    public function getApprovalDetails($id)
    {
        try {
            $purchaseRequisition = PurchaseRequisition::with([
                'division',
                'category',
                'outlet',
                'creator',
                'items',
                'attachments.uploader',
                'approvalFlows.approver.jabatan'
            ])->findOrFail($id);

            // Get budget information
            $budgetInfo = null;
            if ($purchaseRequisition->category_id) {
                $category = $purchaseRequisition->category;
                if ($category) {
                    $currentMonth = now()->month;
                    $currentYear = now()->year;
                    
                    if ($category->isGlobalBudget()) {
                        // GLOBAL BUDGET: Calculate across all outlets
                        $prQuery = PurchaseRequisition::where('category_id', $purchaseRequisition->category_id)
                            ->whereYear('created_at', $currentYear)
                            ->whereMonth('created_at', $currentMonth);
                        
                        // Calculate different amounts
                        $totalUsedAmount = (clone $prQuery)->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])->sum('amount');
                        $approvedAmount = (clone $prQuery)->whereIn('status', ['APPROVED', 'PROCESSED', 'COMPLETED'])->sum('amount');
                        $unapprovedAmount = (clone $prQuery)->where('status', 'SUBMITTED')->sum('amount');
                        
                        // Get PR IDs in this category for the month
                        $prIds = (clone $prQuery)->pluck('id')->toArray();
                        
                        // Calculate PO created amount (POs created from PRs in this category)
                        $poCreatedAmount = \App\Models\PurchaseOrderOps::where('source_type', 'purchase_requisition_ops')
                            ->whereIn('source_id', $prIds)
                            ->sum('grand_total');
                        
                        // Calculate paid and unpaid amounts
                        $paidAmount = \App\Models\Payment::whereIn('purchase_requisition_id', $prIds)
                            ->where('status', 'paid')
                            ->sum('amount');
                        
                        $unpaidAmount = \App\Models\Payment::whereIn('purchase_requisition_id', $prIds)
                            ->whereIn('status', ['approved', 'pending'])
                            ->sum('amount');
                        
                        $budgetInfo = [
                            'budget_type' => 'GLOBAL',
                            'category_budget' => $category->budget_limit,
                            'category_used_amount' => $totalUsedAmount,
                            'category_remaining_amount' => $category->budget_limit - $totalUsedAmount,
                            'approved_amount' => $approvedAmount,
                            'unapproved_amount' => $unapprovedAmount,
                            'po_created_amount' => $poCreatedAmount,
                            'paid_amount' => $paidAmount,
                            'unpaid_amount' => $unpaidAmount,
                            'real_remaining_budget' => $category->budget_limit - $approvedAmount - $unapprovedAmount,
                            'current_month' => $currentMonth,
                            'current_year' => $currentYear,
                        ];
                        
                        // Calculate outlet used amount if PR has outlet_id (regardless of budget type)
                        if ($purchaseRequisition->outlet_id) {
                            $outletUsedAmount = PurchaseRequisition::where('category_id', $purchaseRequisition->category_id)
                                ->where('outlet_id', $purchaseRequisition->outlet_id)
                                ->whereYear('created_at', $currentYear)
                                ->whereMonth('created_at', $currentMonth)
                                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                                ->sum('amount');
                            
                            $budgetInfo['outlet_used_amount'] = $outletUsedAmount;
                            $budgetInfo['outlet_info'] = [
                                'id' => $purchaseRequisition->outlet_id,
                                'name' => $purchaseRequisition->outlet->nama_outlet ?? 'Unknown Outlet',
                            ];
                        }
                    } else if ($category->isPerOutletBudget() && $purchaseRequisition->outlet_id) {
                        // PER_OUTLET BUDGET: Calculate per specific outlet
                        $outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $purchaseRequisition->category_id)
                            ->where('outlet_id', $purchaseRequisition->outlet_id)
                            ->with('outlet')
                            ->first();
                        
                        if ($outletBudget) {
                            $prQuery = PurchaseRequisition::where('category_id', $purchaseRequisition->category_id)
                                ->where('outlet_id', $purchaseRequisition->outlet_id)
                                ->whereYear('created_at', $currentYear)
                                ->whereMonth('created_at', $currentMonth);
                            
                            // Calculate different amounts
                            $outletUsedAmount = (clone $prQuery)->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])->sum('amount');
                            $approvedAmount = (clone $prQuery)->whereIn('status', ['APPROVED', 'PROCESSED', 'COMPLETED'])->sum('amount');
                            $unapprovedAmount = (clone $prQuery)->where('status', 'SUBMITTED')->sum('amount');
                            
                            // Get PR IDs in this category and outlet for the month
                            $prIds = (clone $prQuery)->pluck('id')->toArray();
                            
                            // Calculate PO created amount (POs created from PRs in this category and outlet)
                            $poCreatedAmount = \App\Models\PurchaseOrderOps::where('source_type', 'purchase_requisition_ops')
                                ->whereIn('source_id', $prIds)
                                ->sum('grand_total');
                            
                            // Calculate paid and unpaid amounts
                            $paidAmount = \App\Models\Payment::whereIn('purchase_requisition_id', $prIds)
                                ->where('status', 'paid')
                                ->sum('amount');
                            
                            $unpaidAmount = \App\Models\Payment::whereIn('purchase_requisition_id', $prIds)
                                ->whereIn('status', ['approved', 'pending'])
                                ->sum('amount');
                            
                            $budgetInfo = [
                                'budget_type' => 'PER_OUTLET',
                                'category_budget' => $category->budget_limit, // Global budget for reference
                                'outlet_budget' => $outletBudget->allocated_budget,
                                'outlet_used_amount' => $outletUsedAmount,
                                'outlet_remaining_amount' => $outletBudget->allocated_budget - $outletUsedAmount,
                                'approved_amount' => $approvedAmount,
                                'unapproved_amount' => $unapprovedAmount,
                                'po_created_amount' => $poCreatedAmount,
                                'paid_amount' => $paidAmount,
                                'unpaid_amount' => $unpaidAmount,
                                'real_remaining_budget' => $outletBudget->allocated_budget - $approvedAmount - $unapprovedAmount,
                                'current_month' => $currentMonth,
                                'current_year' => $currentYear,
                                'outlet_info' => [
                                    'id' => $outletBudget->outlet_id,
                                    'name' => $outletBudget->outlet->nama_outlet ?? 'Unknown Outlet',
                                ],
                            ];
                        }
                    }
                }
            }


            return response()->json([
                'success' => true,
                'purchase_requisition' => $purchaseRequisition,
                'budget_info' => $budgetInfo
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting PR approval details', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get approval details'
            ], 500);
        }
    }

    /**
     * Print preview for multiple purchase requisitions
     */
    public function printPreview(Request $request)
    {
        try {
            
            $ids = $request->get('ids', '');
            
            if (empty($ids)) {
                \Log::warning('No IDs provided in printPreview');
                return response()->json(['error' => 'No IDs provided'], 400);
            }

            $prIds = explode(',', $ids);
            
            // Validate that all IDs are numeric
            foreach ($prIds as $id) {
                if (!is_numeric($id)) {
                    \Log::warning('Invalid ID format', ['id' => $id]);
                    return response()->json(['error' => 'Invalid ID format: ' . $id], 400);
                }
            }
            
                $purchaseRequisitions = PurchaseRequisition::with([
                    'division',
                    'outlet',
                    'ticket',
                    'category',
                    'creator',
                    'items',
                    'attachments.uploader',
                    'approvalFlows.approver.jabatan'
                ])->whereIn('id', $prIds)->get();

        // Get budget information for each PR
        $budgetInfos = [];
        foreach ($purchaseRequisitions as $pr) {
            $budgetInfo = null;
            if ($pr->category_id) {
                $category = $pr->category;
                if ($category) {
                    $currentMonth = now()->month;
                    $currentYear = now()->year;
                    
                    if ($category->isGlobalBudget()) {
                        // GLOBAL BUDGET: Calculate across all outlets
                        $usedAmount = PurchaseRequisition::where('category_id', $pr->category_id)
                            ->whereYear('created_at', $currentYear)
                            ->whereMonth('created_at', $currentMonth)
                            ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                            ->sum('amount');
                        
                        $budgetInfo = [
                            'budget_type' => 'GLOBAL',
                            'category_budget' => $category->budget_limit,
                            'category_used_amount' => $usedAmount,
                            'category_remaining_amount' => $category->budget_limit - $usedAmount,
                            'current_month' => $currentMonth,
                            'current_year' => $currentYear,
                        ];
                    } else if ($category->isPerOutletBudget() && $pr->outlet_id) {
                        // PER_OUTLET BUDGET: Calculate per specific outlet
                        $outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $pr->category_id)
                            ->where('outlet_id', $pr->outlet_id)
                            ->with('outlet')
                            ->first();
                        
                        if ($outletBudget) {
                            $outletUsedAmount = PurchaseRequisition::where('category_id', $pr->category_id)
                                ->where('outlet_id', $pr->outlet_id)
                                ->whereYear('created_at', $currentYear)
                                ->whereMonth('created_at', $currentMonth)
                                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                                ->sum('amount');
                            
                            $budgetInfo = [
                                'budget_type' => 'PER_OUTLET',
                                'category_budget' => $category->budget_limit, // Global budget for reference
                                'outlet_budget' => $outletBudget->allocated_budget,
                                'outlet_used_amount' => $outletUsedAmount,
                                'outlet_remaining_amount' => $outletBudget->allocated_budget - $outletUsedAmount,
                                'current_month' => $currentMonth,
                                'current_year' => $currentYear,
                                'outlet_info' => [
                                    'id' => $outletBudget->outlet_id,
                                    'name' => $outletBudget->outlet->nama_outlet ?? 'Unknown Outlet',
                                ],
                            ];
                        }
                    }
                }
            }
            $budgetInfos[$pr->id] = $budgetInfo;
        }

            
            return view('purchase-requisitions.print-preview', [
                'purchaseRequisitions' => $purchaseRequisitions,
                'budgetInfos' => $budgetInfos,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in printPreview method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ids' => $request->get('ids', '')
            ]);
            
            return response()->json([
                'error' => 'Failed to generate print preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test method for debugging
     */
    public function testPrint(Request $request)
    {
        return response()->json([
            'message' => 'Test method works',
            'request_data' => $request->all(),
            'ids' => $request->get('ids', ''),
        ]);
    }

    /**
     * Get budget info for API
     */
    public function getBudgetInfo(Request $request)
    {
        $categoryId = $request->get('category_id');
        $outletId = $request->get('outlet_id');
        $currentAmount = $request->get('current_amount', 0); // Amount being input
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        if (!$categoryId) {
            return response()->json(['success' => false, 'message' => 'Category is required']);
        }

        // Get category budget
        $category = PurchaseRequisitionCategory::find($categoryId);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Category not found']);
        }

        $budgetInfo = [];

        if ($category->isGlobalBudget()) {
            // GLOBAL BUDGET: Calculate across all outlets
            $categoryBudget = $category->budget_limit;
            
            $categoryUsedAmount = PurchaseRequisition::where('category_id', $categoryId)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->sum('amount');

            $totalWithCurrent = $categoryUsedAmount + $currentAmount;
            $remainingAfterCurrent = $categoryBudget - $totalWithCurrent;

            $budgetInfo = [
                'budget_type' => 'GLOBAL',
                'category_budget' => $categoryBudget,
                'category_used_amount' => $categoryUsedAmount,
                'current_amount' => $currentAmount,
                'total_with_current' => $totalWithCurrent,
                'category_remaining_amount' => $categoryBudget - $categoryUsedAmount,
                'remaining_after_current' => $remainingAfterCurrent,
                'exceeds_budget' => $totalWithCurrent > $categoryBudget,
            ];

        } else if ($category->isPerOutletBudget()) {
            // PER_OUTLET BUDGET: Calculate per specific outlet
            if (!$outletId) {
                return response()->json(['success' => false, 'message' => 'Outlet ID is required for per-outlet budget']);
            }

            // Get outlet budget allocation
            $outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->first();

            if (!$outletBudget) {
                return response()->json(['success' => false, 'message' => 'Outlet budget not configured for this category']);
            }

            // Calculate used amount for this specific outlet
            $outletUsedAmount = PurchaseRequisition::where('category_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->sum('amount');

            $totalWithCurrent = $outletUsedAmount + $currentAmount;
            $remainingAfterCurrent = $outletBudget->allocated_budget - $totalWithCurrent;

            $budgetInfo = [
                'budget_type' => 'PER_OUTLET',
                'category_budget' => $category->budget_limit, // Global budget for reference
                'outlet_budget' => $outletBudget->allocated_budget,
                'outlet_used_amount' => $outletUsedAmount,
                'current_amount' => $currentAmount,
                'total_with_current' => $totalWithCurrent,
                'outlet_remaining_amount' => $outletBudget->allocated_budget - $outletUsedAmount,
                'remaining_after_current' => $remainingAfterCurrent,
                'exceeds_budget' => $totalWithCurrent > $outletBudget->allocated_budget,
                'outlet_info' => [
                    'id' => $outletBudget->outlet_id,
                    'name' => $outletBudget->outlet->nama_outlet ?? 'Unknown Outlet',
                ],
            ];
        }

        return response()->json([
            'success' => true,
            ...$budgetInfo
        ]);
    }

    /**
     * Upload attachment for purchase requisition
     */
    public function uploadAttachment(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . $originalName;
            $filePath = $file->storeAs('purchase_requisitions/attachments', $fileName, 'public');
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            $attachment = PurchaseRequisitionAttachment::create([
                'purchase_requisition_id' => $purchaseRequisition->id,
                'file_name' => $originalName,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'attachment' => $attachment->load('uploader'),
            ]);

        } catch (\Exception $e) {
            \Log::error('Upload attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete attachment
     */
    public function deleteAttachment(PurchaseRequisitionAttachment $attachment)
    {
        try {
            // Check if user has permission to delete (only uploader or admin)
            if ($attachment->uploaded_by !== auth()->id() && !auth()->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to delete this file',
                ], 403);
            }

            // Delete file from storage
            if (\Storage::disk('public')->exists($attachment->file_path)) {
                \Storage::disk('public')->delete($attachment->file_path);
            }

            // Delete record from database
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
            ]);

        } catch (\Exception $e) {
            \Log::error('Delete attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download attachment
     */
    public function downloadAttachment(PurchaseRequisitionAttachment $attachment)
    {
        try {
            if (!\Storage::disk('public')->exists($attachment->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            return \Storage::disk('public')->download($attachment->file_path, $attachment->file_name);

        } catch (\Exception $e) {
            \Log::error('Download attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to download file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View attachment (for images)
     */
    public function viewAttachment(PurchaseRequisitionAttachment $attachment)
    {
        try {
            if (!\Storage::disk('public')->exists($attachment->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            // Check if it's an image
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            $extension = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION));
            
            if (!in_array($extension, $imageExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is not an image',
                ], 400);
            }

            $file = \Storage::disk('public')->get($attachment->file_path);
            $mimeType = \Storage::disk('public')->mimeType($attachment->file_path);

            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $attachment->file_name . '"');

        } catch (\Exception $e) {
            \Log::error('View attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to view file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate unique requisition number
     */
    private function generateRequisitionNumber()
    {
        $year = now()->year;
        $month = now()->month;
        
        $lastPR = PurchaseRequisition::whereYear('created_at', $year)
                                   ->whereMonth('created_at', $month)
                                   ->orderBy('id', 'desc')
                                   ->first();
        
        $sequence = $lastPR ? (int) substr($lastPR->pr_number, -4) + 1 : 1;
        
        return "PR{$year}{$month}" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get PR tracking report data
     */
    public function getPRTrackingReport(Request $request)
    {
        try {
            $query = PurchaseRequisition::with([
                'division',
                'creator',
                'approvalFlows.approver',
                'purchaseOrders' => function($q) {
                    $q->select('id', 'number', 'status', 'created_at', 'source_id')
                      ->with(['approvalFlows.approver']);
                },
                'payments' => function($q) {
                    $q->select('id', 'payment_number', 'amount', 'status', 'payment_date', 'purchase_requisition_id');
                }
            ]);

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('pr_number', 'like', "%{$search}%")
                      ->orWhere('title', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('division')) {
                $query->where('division_id', $request->division);
            }

            if ($request->filled('dateFrom')) {
                $query->whereDate('created_at', '>=', $request->dateFrom);
            }

            if ($request->filled('dateTo')) {
                $query->whereDate('created_at', '<=', $request->dateTo);
            }

            // Get per_page from request, default to 15
            $perPage = $request->get('per_page', 15);
            
            // Paginate results
            $prs = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $prs->items(),
                'pagination' => [
                    'current_page' => $prs->currentPage(),
                    'last_page' => $prs->lastPage(),
                    'per_page' => $prs->perPage(),
                    'total' => $prs->total(),
                    'from' => $prs->firstItem(),
                    'to' => $prs->lastItem(),
                    'links' => $prs->linkCollection()->toArray()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting PR tracking report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get PR tracking report'
            ], 500);
        }
    }

    /**
     * Get divisions for filter
     */
    public function getDivisions()
    {
        try {
            $divisions = \App\Models\Divisi::select('id', 'nama_divisi')
                ->orderBy('nama_divisi')
                ->get();

            return response()->json($divisions);
        } catch (\Exception $e) {
            \Log::error('Error getting divisions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([], 500);
        }
    }

    /**
     * Validate budget limit for purchase requisition
     */
    private function validateBudgetLimit($categoryId, $outletId = null, $amount, $excludeId = null)
    {
        $category = PurchaseRequisitionCategory::find($categoryId);
        if (!$category) {
            return [
                'valid' => false,
                'message' => 'Category not found'
            ];
        }

        $currentMonth = now()->month;
        $currentYear = now()->year;

        if ($category->isGlobalBudget()) {
            // GLOBAL BUDGET: Calculate across all outlets
            $query = PurchaseRequisition::where('category_id', $categoryId)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED']);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $usedAmount = $query->sum('amount');
            $totalWithCurrent = $usedAmount + $amount;

            if ($totalWithCurrent > $category->budget_limit) {
                return [
                    'valid' => false,
                    'message' => "Total amount (Rp " . number_format($totalWithCurrent, 0, ',', '.') . ") exceeds category budget limit (Rp " . number_format($category->budget_limit, 0, ',', '.') . ") for this month."
                ];
            }

        } else if ($category->isPerOutletBudget()) {
            // PER_OUTLET BUDGET: Calculate per specific outlet
            if (!$outletId) {
                return [
                    'valid' => false,
                    'message' => 'Outlet ID is required for per-outlet budget'
                ];
            }

            // Get outlet budget allocation
            $outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->first();

            if (!$outletBudget) {
                return [
                    'valid' => false,
                    'message' => 'Outlet budget not configured for this category'
                ];
            }

            // Calculate used amount for this specific outlet
            $query = PurchaseRequisition::where('category_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED']);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $usedAmount = $query->sum('amount');
            $totalWithCurrent = $usedAmount + $amount;

            if ($totalWithCurrent > $outletBudget->allocated_budget) {
                return [
                    'valid' => false,
                    'message' => "Total amount (Rp " . number_format($totalWithCurrent, 0, ',', '.') . ") exceeds outlet budget limit (Rp " . number_format($outletBudget->allocated_budget, 0, ',', '.') . ") for this month."
                ];
            }
        }

        return [
            'valid' => true,
            'message' => 'Budget validation passed'
        ];
    }
}