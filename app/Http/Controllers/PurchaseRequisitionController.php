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
use App\Models\RetailNonFood;
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
        $category = $request->get('category', 'all');
        $isHeld = $request->get('is_held', 'all');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $perPage = $request->get('per_page', 15);

        // Set default date range to current month if no date filter is provided
        if (empty($dateFrom) && empty($dateTo)) {
            $dateFrom = date('Y-m-01'); // First day of current month
            $dateTo = date('Y-m-t'); // Last day of current month
        }

        // Check if user has role with id '5af56935b011a' (can see all payments)
        // Check from users table id_role column
        $user = auth()->user();
        $canSeeAllPayments = false;
        
        if ($user && $user->id_role === '5af56935b011a') {
            $canSeeAllPayments = true;
        }

        $query = PurchaseRequisition::with([
            'division',
            'outlet',
            'ticket',
            'category',
            'creator',
            'heldBy', // Load user who held the PR
            'approvalFlows.approver', // Load approval flows with approver
            'items.outlet' // Load items with outlet for multi-outlet modes
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
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('amount', 'like', "%{$search}%")
                  ->orWhereHas('division', function($q) use ($search) {
                      $q->where('nama_divisi', 'like', "%{$search}%");
                  })
                  ->orWhereHas('outlet', function($q) use ($search) {
                      $q->where('nama_outlet', 'like', "%{$search}%");
                  })
                  ->orWhereHas('category', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('subcategory', 'like', "%{$search}%");
                  })
                  ->orWhereHas('creator', function($q) use ($search) {
                      $q->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('ticket', function($q) use ($search) {
                      $q->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                  });
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($division !== 'all') {
            $query->where('division_id', $division);
        }

        if ($category !== 'all') {
            $query->where('category_id', $category);
        }

        if ($isHeld !== 'all') {
            if ($isHeld === 'held') {
                $query->where('is_held', true);
            } elseif ($isHeld === 'not_held') {
                $query->where('is_held', false);
            }
        }

        // Date range filter
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }

        $purchaseRequisitions = $query->orderBy('created_at', 'desc')
                                    ->paginate($perPage)
                                    ->withQueryString();

        // Transform purchase requisitions to include outlet information for multi-outlet modes
        // and PO/Payment status information
        $purchaseRequisitions->getCollection()->transform(function($pr) {
            // For travel_application mode, try to get outlet names from notes
            if ($pr->mode === 'travel_application' && $pr->notes) {
                try {
                    $notesData = json_decode($pr->notes, true);
                    if (is_array($notesData) && isset($notesData['outlet_ids']) && is_array($notesData['outlet_ids'])) {
                        // Load outlet names
                        $outletIds = $notesData['outlet_ids'];
                        $outlets = Outlet::whereIn('id_outlet', $outletIds)->get(['id_outlet', 'nama_outlet']);
                        $pr->travel_outlets = $outlets->pluck('nama_outlet')->toArray();
                    }
                } catch (\Exception $e) {
                    // If parsing fails, leave it empty
                    $pr->travel_outlets = [];
                }
            }
            
            // Check if PR has been converted to PO
            $poIds = DB::table('purchase_order_ops_items')
                ->where('source_type', 'purchase_requisition_ops')
                ->where('source_id', $pr->id)
                ->distinct()
                ->pluck('purchase_order_ops_id')
                ->toArray();
            
            $poCount = count($poIds);
            
            $pr->has_po = $poCount > 0;
            $pr->po_count = $poCount;
            
            // Get PO details if exists
            if ($poCount > 0 && !empty($poIds)) {
                $poDetails = DB::table('purchase_order_ops_items as poi')
                    ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
                    ->leftJoin('users as creator', 'po.created_by', '=', 'creator.id')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->where('poi.source_id', $pr->id)
                    ->select(
                        'po.number',
                        'po.created_at',
                        'creator.nama_lengkap as creator_name',
                        'creator.email as creator_email'
                    )
                    ->distinct()
                    ->get()
                    ->map(function($po) {
                        return [
                            'number' => $po->number,
                            'created_at' => $po->created_at,
                            'creator_name' => $po->creator_name,
                            'creator_email' => $po->creator_email,
                        ];
                    })
                    ->toArray();
                $pr->po_details = $poDetails;
                $pr->po_numbers = array_column($poDetails, 'number');
            } else {
                $pr->po_details = [];
                $pr->po_numbers = [];
            }
            
            // Check if any PO from this PR has been paid
            $hasPayment = false;
            $paymentCount = 0;
            $totalPaidAmount = 0;
            
            if ($poCount > 0 && !empty($poIds)) {
                $payments = DB::table('non_food_payments as nfp')
                    ->leftJoin('users as creator', 'nfp.created_by', '=', 'creator.id')
                    ->whereIn('nfp.purchase_order_ops_id', $poIds)
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->select(
                        'nfp.payment_number',
                        'nfp.created_at',
                        'creator.nama_lengkap as creator_name',
                        'creator.email as creator_email'
                    )
                    ->get();
                
                $hasPayment = $payments->count() > 0;
                $paymentCount = $payments->count();
                $totalPaidAmount = DB::table('non_food_payments')
                    ->whereIn('purchase_order_ops_id', $poIds)
                    ->whereIn('status', ['paid', 'approved'])
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
                
                if ($hasPayment) {
                    $paymentDetails = $payments->map(function($payment) {
                        return [
                            'payment_number' => $payment->payment_number,
                            'created_at' => $payment->created_at,
                            'creator_name' => $payment->creator_name,
                            'creator_email' => $payment->creator_email,
                        ];
                    })->toArray();
                    $pr->payment_details = $paymentDetails;
                    $paymentNumbers = $payments->pluck('payment_number')->filter()->toArray();
                    $pr->payment_numbers = array_unique($paymentNumbers);
                } else {
                    $pr->payment_details = [];
                    $pr->payment_numbers = [];
                }
            } else {
                // Check for direct payment (without PO)
                $directPayments = DB::table('non_food_payments as nfp')
                    ->leftJoin('users as creator', 'nfp.created_by', '=', 'creator.id')
                    ->where('nfp.purchase_requisition_id', $pr->id)
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->select(
                        'nfp.payment_number',
                        'nfp.created_at',
                        'creator.nama_lengkap as creator_name',
                        'creator.email as creator_email'
                    )
                    ->get();
                
                if ($directPayments->count() > 0) {
                    $hasPayment = true;
                    $paymentCount = $directPayments->count();
                    $totalPaidAmount = DB::table('non_food_payments')
                        ->where('purchase_requisition_id', $pr->id)
                        ->whereIn('status', ['paid', 'approved'])
                        ->where('status', '!=', 'cancelled')
                        ->sum('amount');
                    
                    $paymentDetails = $directPayments->map(function($payment) {
                        return [
                            'payment_number' => $payment->payment_number,
                            'created_at' => $payment->created_at,
                            'creator_name' => $payment->creator_name,
                            'creator_email' => $payment->creator_email,
                        ];
                    })->toArray();
                    $pr->payment_details = $paymentDetails;
                    $paymentNumbers = $directPayments->pluck('payment_number')->filter()->toArray();
                    $pr->payment_numbers = array_unique($paymentNumbers);
                } else {
                    $pr->payment_details = [];
                    $pr->payment_numbers = [];
                }
            }
            
            $pr->has_payment = $hasPayment;
            $pr->payment_count = $paymentCount;
            $pr->total_paid_amount = $totalPaidAmount;
            
            return $pr;
        });

        // Get filter options
        $divisions = Divisi::whereHas('purchaseRequisitions')->active()->orderBy('nama_divisi')->get();
        $categories = PurchaseRequisitionCategory::whereHas('purchaseRequisitions')->orderBy('name')->get();
        
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
                'category' => $category,
                'is_held' => $isHeld,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'divisions' => $divisions,
                'categories' => $categories,
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
            'items.*.outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet', // For pr_ops mode
            'items.*.category_id' => 'nullable|exists:purchase_requisition_categories,id', // For pr_ops mode
            'items.*.item_type' => 'nullable|in:transport,allowance,others', // For travel_application mode
            'items.*.allowance_recipient_name' => 'nullable|string|max:255', // For allowance type
            'items.*.allowance_account_number' => 'nullable|string|max:100', // For allowance type
            'items.*.others_notes' => 'nullable|string', // For others type
            'travel_outlet_ids' => 'nullable|array', // For travel_application mode
            'travel_outlet_ids.*' => 'nullable|exists:tbl_data_outlet,id_outlet',
            'travel_agenda' => 'nullable|string', // For travel_application mode
            'travel_notes' => 'nullable|string', // For travel_application mode
            'approvers' => 'nullable|array',
            'approvers.*' => 'required|exists:users,id',
            'mode' => 'required|in:pr_ops,purchase_payment,travel_application,kasbon',
        ]);

        // Check budget limit before saving
        // For pr_ops and purchase_payment mode, check budget per outlet/category from items
        if ($validated['mode'] === 'pr_ops' || $validated['mode'] === 'purchase_payment') {
            // Group items by outlet_id and category_id
            $budgetChecks = [];
            foreach ($validated['items'] as $item) {
                if (!empty($item['category_id'])) {
                    $key = $item['outlet_id'] . '_' . $item['category_id'];
                    if (!isset($budgetChecks[$key])) {
                        $budgetChecks[$key] = [
                            'outlet_id' => $item['outlet_id'],
                            'category_id' => $item['category_id'],
                            'amount' => 0
                        ];
                    }
                    $budgetChecks[$key]['amount'] += $item['subtotal'];
                }
            }
            
            // Validate each outlet/category combination
            foreach ($budgetChecks as $check) {
                $budgetValidation = $this->validateBudgetLimit($check['category_id'], $check['outlet_id'], $check['amount']);
                if (!$budgetValidation['valid']) {
                    return back()->withErrors([
                        'budget_exceeded' => $budgetValidation['message']
                    ]);
                }
            }
        } else if ($validated['category_id']) {
            // For other modes (kasbon, travel_application), use main category_id and outlet_id
            $budgetValidation = $this->validateBudgetLimit($validated['category_id'], $validated['outlet_id'] ?? null, $validated['amount']);
            if (!$budgetValidation['valid']) {
                return back()->withErrors([
                    'budget_exceeded' => $budgetValidation['message']
                ]);
            }
        }

        // Validate kasbon period - DISABLED: Bebas input kapan saja
        // Validasi periode tanggal 20 bulan berjalan - 10 bulan selanjutnya telah dinonaktifkan
        // if ($validated['mode'] === 'kasbon' && $validated['outlet_id']) {
        //     $user = auth()->user();
        //     if ($user && $user->id_outlet != 1) {
        //         // Calculate kasbon period: tanggal 20 bulan berjalan hingga tanggal 10 bulan selanjutnya
        //         $now = now();
        //         $currentYear = $now->year;
        //         $currentMonth = $now->month;
        //         
        //         // Start date: tanggal 20 bulan berjalan
        //         $startDate = \Carbon\Carbon::create($currentYear, $currentMonth, 20);
        //         
        //         // End date: tanggal 10 bulan selanjutnya
        //         $endDate = \Carbon\Carbon::create($currentYear, $currentMonth + 1, 10);
        //         
        //         // If current date is before tanggal 20, use previous month's period
        //         if ($now->day < 20) {
        //             $startDate = \Carbon\Carbon::create($currentYear, $currentMonth - 1, 20);
        //             $endDate = \Carbon\Carbon::create($currentYear, $currentMonth, 10);
        //         }
        //         
        //         // Check if there's already a kasbon PR for this outlet in the period
        //         $existingKasbon = PurchaseRequisition::where('mode', 'kasbon')
        //             ->where('outlet_id', $validated['outlet_id'])
        //             ->whereDate('created_at', '>=', $startDate->toDateString())
        //             ->whereDate('created_at', '<=', $endDate->toDateString())
        //             ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
        //             ->first();
        //         
        //         if ($existingKasbon) {
        //             $creator = $existingKasbon->creator;
        //             $creatorName = $creator ? $creator->nama_lengkap : 'Unknown';
        //             $periodText = $startDate->format('d F Y') . ' - ' . $endDate->format('d F Y');
        //             
        //             return back()->withErrors([
        //                 'kasbon_exists' => "Sudah ada pengajuan kasbon untuk outlet ini di periode {$periodText}. Dibuat oleh: {$creatorName}. Per periode hanya diijinkan 1 user saja per outlet."
        //             ]);
        //         }
        //     }
        // }

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
                    'outlet_id' => $itemData['outlet_id'] ?? null, // For pr_ops mode
                    'category_id' => $itemData['category_id'] ?? null, // For pr_ops mode
                    'item_type' => $itemData['item_type'] ?? null, // For travel_application mode
                    'item_name' => $itemData['item_name'],
                    'qty' => $itemData['qty'],
                    'unit' => $itemData['unit'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['subtotal'],
                    'allowance_recipient_name' => $itemData['allowance_recipient_name'] ?? null, // For allowance type
                    'allowance_account_number' => $itemData['allowance_account_number'] ?? null, // For allowance type
                    'others_notes' => $itemData['others_notes'] ?? null, // For others type
                ]);
            }

            // For travel_application mode, store travel outlet IDs in notes or create a separate table
            // For now, we'll store it in notes field as JSON or comma-separated
            if ($validated['mode'] === 'travel_application' && !empty($validated['travel_outlet_ids'])) {
                $travelData = [
                    'outlet_ids' => $validated['travel_outlet_ids'],
                    'agenda' => $validated['travel_agenda'] ?? '',
                    'notes' => $validated['travel_notes'] ?? '',
                ];
                // Store travel data in notes field as JSON
                $purchaseRequisition->update([
                    'notes' => json_encode($travelData)
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
            'attachments.outlet',
            'history.user',
            'items.outlet',
            'items.category',
            'approvalFlows.approver.jabatan'
        ]);

        // Parse mode-specific data
        $modeSpecificData = [];
        if ($purchaseRequisition->mode === 'travel_application') {
            // Try to parse from notes field (new structure)
            if ($purchaseRequisition->notes) {
                $notesData = json_decode($purchaseRequisition->notes, true);
                if (is_array($notesData)) {
                    $modeSpecificData = [
                        'travel_outlet_ids' => $notesData['outlet_ids'] ?? [],
                        'travel_agenda' => $notesData['agenda'] ?? '',
                        'travel_notes' => $notesData['notes'] ?? '',
                    ];
                    // Load outlet names
                    if (!empty($modeSpecificData['travel_outlet_ids'])) {
                        $outlets = \App\Models\Outlet::whereIn('id_outlet', $modeSpecificData['travel_outlet_ids'])->get();
                        $modeSpecificData['travel_outlets'] = $outlets->map(function($outlet) {
                            return [
                                'id' => $outlet->id_outlet,
                                'name' => $outlet->nama_outlet,
                            ];
                        })->toArray();
                    }
                }
            }
            // Fallback: if notes is not JSON, use description as agenda (for old data)
            if (empty($modeSpecificData['travel_agenda']) && $purchaseRequisition->description) {
                $modeSpecificData['travel_agenda'] = $purchaseRequisition->description;
            }
        } elseif ($purchaseRequisition->mode === 'kasbon') {
            // For kasbon, extract amount and reason from items
            $kasbonItem = $purchaseRequisition->items->first();
            if ($kasbonItem) {
                $modeSpecificData = [
                    'kasbon_amount' => $kasbonItem->subtotal ?? 0,
                    'kasbon_reason' => $kasbonItem->item_name ?? '',
                ];
            }
        }
        // For legacy data (mode is null), modeSpecificData will be empty array, which is fine

        // Get budget information for the category (hide for kasbon mode)
        $budgetInfo = null;
        if ($purchaseRequisition->mode !== 'kasbon' && $purchaseRequisition->category_id) {
            $category = $purchaseRequisition->category;
            if ($category) {
                $currentMonth = date('m');
                $currentYear = date('Y');
                
                if ($category->isGlobalBudget()) {
                    // GLOBAL BUDGET: Calculate across all outlets
                    $prUsedAmount = PurchaseRequisition::where('category_id', $purchaseRequisition->category_id)
                        ->whereYear('created_at', $currentYear)
                        ->whereMonth('created_at', $currentMonth)
                        ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                        ->sum('amount');
                    
                    // Add Retail Non Food
                    $retailNonFoodUsed = RetailNonFood::where('category_budget_id', $purchaseRequisition->category_id)
                        ->whereYear('transaction_date', $currentYear)
                        ->whereMonth('transaction_date', $currentMonth)
                        ->whereIn('status', ['approved', 'pending'])
                        ->sum('total_amount');
                    
                    $usedAmount = $prUsedAmount + $retailNonFoodUsed;
                    
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
                        $outletPrUsedAmount = PurchaseRequisition::where('category_id', $purchaseRequisition->category_id)
                            ->where('outlet_id', $purchaseRequisition->outlet_id)
                            ->whereYear('created_at', $currentYear)
                            ->whereMonth('created_at', $currentMonth)
                            ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                            ->sum('amount');
                        
                        // Add Retail Non Food for this outlet
                        $outletRetailNonFoodUsed = RetailNonFood::where('category_budget_id', $purchaseRequisition->category_id)
                            ->where('outlet_id', $purchaseRequisition->outlet_id)
                            ->whereYear('transaction_date', $currentYear)
                            ->whereMonth('transaction_date', $currentMonth)
                            ->whereIn('status', ['approved', 'pending'])
                            ->sum('total_amount');
                        
                        $outletUsedAmount = $outletPrUsedAmount + $outletRetailNonFoodUsed;
                        
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
            'modeSpecificData' => $modeSpecificData,
            'currentUser' => auth()->user(),
        ]);
    }

    /**
     * Show the form for editing the specified purchase requisition
     */
    public function edit(PurchaseRequisition $purchaseRequisition)
    {
        if (!in_array($purchaseRequisition->status, ['DRAFT', 'SUBMITTED'])) {
            return redirect()->route('purchase-requisitions.show', $purchaseRequisition)
                           ->with('error', 'Only draft and submitted purchase requisitions can be edited.');
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

        // Load purchase requisition with all necessary relationships
        $purchaseRequisition->load([
            'attachments.uploader',
            'attachments.outlet',
            'items.outlet',
            'items.category',
            'approvalFlows' => function($query) {
                $query->with(['approver' => function($q) {
                    $q->select('id', 'nik', 'nama_lengkap', 'email', 'id_jabatan')
                      ->with(['jabatan' => function($j) {
                          $j->select('id_jabatan', 'nama_jabatan');
                      }]);
                }]);
            }
        ]);

        // Parse mode-specific data (similar to show method)
        $modeSpecificData = [];
        if ($purchaseRequisition->mode === 'travel_application') {
            if ($purchaseRequisition->notes) {
                $notesData = json_decode($purchaseRequisition->notes, true);
                if (is_array($notesData)) {
                    $modeSpecificData = [
                        'travel_outlet_ids' => $notesData['outlet_ids'] ?? [],
                        'travel_agenda' => $notesData['agenda'] ?? '',
                        'travel_notes' => $notesData['notes'] ?? '',
                    ];
                }
            }
            if (empty($modeSpecificData['travel_agenda']) && $purchaseRequisition->description) {
                $modeSpecificData['travel_agenda'] = $purchaseRequisition->description;
            }
        } elseif ($purchaseRequisition->mode === 'kasbon') {
            $kasbonItem = $purchaseRequisition->items->first();
            if ($kasbonItem) {
                $modeSpecificData = [
                    'kasbon_amount' => $kasbonItem->subtotal ?? 0,
                    'kasbon_reason' => $kasbonItem->item_name ?? '',
                ];
            }
        }

        return Inertia::render('PurchaseRequisition/Edit', [
            'purchaseRequisition' => $purchaseRequisition,
            'categories' => $categories,
            'outlets' => $outlets,
            'tickets' => $tickets,
            'divisions' => $divisions,
            'modeSpecificData' => $modeSpecificData,
        ]);
    }

    /**
     * Update the specified purchase requisition
     */
    public function update(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        if (!in_array($purchaseRequisition->status, ['DRAFT', 'SUBMITTED'])) {
            return back()->withErrors(['error' => 'Only draft and submitted purchase requisitions can be edited.']);
        }

        // Use similar validation as store method
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'division_id' => 'required|exists:tbl_data_divisi,id',
            'category_id' => 'nullable|exists:purchase_requisition_categories,id',
            'outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
            'ticket_id' => 'nullable|exists:tickets,id',
            'currency' => 'string|in:IDR,USD',
            'priority' => 'string|in:LOW,MEDIUM,HIGH,URGENT',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            'items.*.outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
            'items.*.category_id' => 'nullable|exists:purchase_requisition_categories,id',
            'items.*.item_type' => 'nullable|in:transport,allowance,others',
            'items.*.allowance_recipient_name' => 'nullable|string|max:255',
            'items.*.allowance_account_number' => 'nullable|string|max:100',
            'items.*.others_notes' => 'nullable|string',
            'travel_outlet_ids' => 'nullable|array',
            'travel_outlet_ids.*' => 'nullable|exists:tbl_data_outlet,id_outlet',
            'travel_agenda' => 'nullable|string',
            'travel_notes' => 'nullable|string',
            'kasbon_amount' => 'nullable|numeric|min:0',
            'kasbon_reason' => 'nullable|string',
            'approvers' => 'nullable|array',
            'approvers.*' => 'required|exists:users,id',
            'mode' => 'required|in:pr_ops,purchase_payment,travel_application,kasbon',
        ]);

        // Calculate total amount based on mode
        $totalAmount = 0;
        if ($validated['mode'] === 'kasbon') {
            $totalAmount = $validated['kasbon_amount'] ?? 0;
        } else {
            $totalAmount = array_sum(array_column($validated['items'], 'subtotal'));
        }
        $validated['amount'] = $totalAmount;

        // Check budget limit before updating (exclude current record from calculation)
        // For pr_ops and purchase_payment mode, check budget per outlet/category from items
        if ($validated['mode'] === 'pr_ops' || $validated['mode'] === 'purchase_payment') {
            // Group items by outlet_id and category_id
            $budgetChecks = [];
            foreach ($validated['items'] as $item) {
                if (!empty($item['category_id'])) {
                    $key = $item['outlet_id'] . '_' . $item['category_id'];
                    if (!isset($budgetChecks[$key])) {
                        $budgetChecks[$key] = [
                            'outlet_id' => $item['outlet_id'],
                            'category_id' => $item['category_id'],
                            'amount' => 0
                        ];
                    }
                    $budgetChecks[$key]['amount'] += $item['subtotal'];
                }
            }
            
            // Validate each outlet/category combination (exclude current PR from calculation)
            foreach ($budgetChecks as $check) {
                $budgetValidation = $this->validateBudgetLimit($check['category_id'], $check['outlet_id'], $check['amount'], $purchaseRequisition->id);
                if (!$budgetValidation['valid']) {
                    return back()->withErrors([
                        'budget_exceeded' => $budgetValidation['message']
                    ]);
                }
            }
        } else if ($validated['category_id']) {
            // For other modes (kasbon, travel_application), use main category_id and outlet_id
            $budgetValidation = $this->validateBudgetLimit($validated['category_id'], $validated['outlet_id'] ?? null, $validated['amount'], $purchaseRequisition->id);
            if (!$budgetValidation['valid']) {
                return back()->withErrors([
                    'budget_exceeded' => $budgetValidation['message']
                ]);
            }
        }

        // Validate kasbon period - DISABLED: Bebas input kapan saja
        // Validasi periode tanggal 20 bulan berjalan - 10 bulan selanjutnya telah dinonaktifkan
        // if ($validated['mode'] === 'kasbon' && $validated['outlet_id']) {
        //     $user = auth()->user();
        //     if ($user && $user->id_outlet != 1) {
        //         $now = now();
        //         $currentYear = $now->year;
        //         $currentMonth = $now->month;
        //         
        //         $startDate = \Carbon\Carbon::create($currentYear, $currentMonth, 20);
        //         $endDate = \Carbon\Carbon::create($currentYear, $currentMonth + 1, 10);
        //         
        //         if ($now->day < 20) {
        //             $startDate = \Carbon\Carbon::create($currentYear, $currentMonth - 1, 20);
        //             $endDate = \Carbon\Carbon::create($currentYear, $currentMonth, 10);
        //         }
        //         
        //         // Check if there's already a kasbon PR for this outlet in the period (exclude current PR)
        //         $existingKasbon = PurchaseRequisition::where('mode', 'kasbon')
        //             ->where('outlet_id', $validated['outlet_id'])
        //             ->where('id', '!=', $purchaseRequisition->id)
        //             ->whereDate('created_at', '>=', $startDate->toDateString())
        //             ->whereDate('created_at', '<=', $endDate->toDateString())
        //             ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
        //             ->first();
        //         
        //         if ($existingKasbon) {
        //             $creator = $existingKasbon->creator;
        //             $creatorName = $creator ? $creator->nama_lengkap : 'Unknown';
        //             $periodText = $startDate->format('d F Y') . ' - ' . $endDate->format('d F Y');
        //             
        //             return back()->withErrors([
        //                 'kasbon_exists' => "Sudah ada pengajuan kasbon untuk outlet ini di periode {$periodText}. Dibuat oleh: {$creatorName}. Per periode hanya diijinkan 1 user saja per outlet."
        //             ]);
        //         }
        //     }
        // }

        $validated['updated_by'] = auth()->id();
        $validated['mode'] = $validated['mode'] ?? $purchaseRequisition->mode ?? 'pr_ops';

        try {
            DB::beginTransaction();
            
            // Update purchase requisition
            $purchaseRequisition->update($validated);
            
            // Delete existing items
            $purchaseRequisition->items()->delete();
            
            // Create new items based on mode
            if ($validated['mode'] === 'kasbon') {
                // For kasbon: create single item from kasbon_amount and kasbon_reason
                $itemName = 'Kasbon: ' . ($validated['kasbon_reason'] ?? '');
                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $purchaseRequisition->id,
                    'outlet_id' => $validated['outlet_id'] ?? null,
                    'category_id' => $validated['category_id'] ?? null,
                    'item_name' => $itemName,
                    'qty' => 1,
                    'unit' => 'pcs',
                    'unit_price' => $validated['kasbon_amount'] ?? 0,
                    'subtotal' => $validated['kasbon_amount'] ?? 0,
                ]);
            } else {
                // For other modes: create items from items array
                foreach ($validated['items'] as $itemData) {
                    PurchaseRequisitionItem::create([
                        'purchase_requisition_id' => $purchaseRequisition->id,
                        'outlet_id' => $itemData['outlet_id'] ?? null,
                        'category_id' => $itemData['category_id'] ?? null,
                        'item_type' => $itemData['item_type'] ?? null,
                        'item_name' => $itemData['item_name'],
                        'qty' => $itemData['qty'],
                        'unit' => $itemData['unit'],
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemData['subtotal'],
                        'allowance_recipient_name' => $itemData['allowance_recipient_name'] ?? null,
                        'allowance_account_number' => $itemData['allowance_account_number'] ?? null,
                        'others_notes' => $itemData['others_notes'] ?? null,
                    ]);
                }
            }

            // For travel_application mode, store travel outlet IDs in notes
            if ($validated['mode'] === 'travel_application' && !empty($validated['travel_outlet_ids'])) {
                $travelData = [
                    'outlet_ids' => $validated['travel_outlet_ids'],
                    'agenda' => $validated['travel_agenda'] ?? '',
                    'notes' => $validated['travel_notes'] ?? '',
                ];
                $purchaseRequisition->update([
                    'notes' => json_encode($travelData)
                ]);
            } elseif ($validated['mode'] !== 'travel_application') {
                // Clear notes if not travel_application mode
                $purchaseRequisition->update(['notes' => null]);
            }

            // Delete existing approval flows
            $purchaseRequisition->approvalFlows()->delete();
            
            // Create new approval flows if approvers provided
            if (!empty($validated['approvers'])) {
                foreach ($validated['approvers'] as $index => $approverId) {
                    PurchaseRequisitionApprovalFlow::create([
                        'purchase_requisition_id' => $purchaseRequisition->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1,
                        'status' => 'PENDING',
                    ]);
                }
            }
            
            DB::commit();
            
            return redirect()->route('purchase-requisitions.show', $purchaseRequisition)
                           ->with('success', 'Purchase Requisition updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update purchase requisition: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified purchase requisition
     */
    public function destroy(PurchaseRequisition $purchaseRequisition)
    {
        $user = auth()->user();
        $hasSpecialRole = $user && $user->id_role === '5af56935b011a';
        
        // Allow delete for DRAFT and SUBMITTED status (not yet approved/processed)
        // Also allow delete for APPROVED status if user has special role
        $deletableStatuses = ['DRAFT', 'SUBMITTED'];
        $isApprovedStatus = $purchaseRequisition->status === 'APPROVED';
        
        if (!in_array($purchaseRequisition->status, $deletableStatuses) && !($isApprovedStatus && $hasSpecialRole)) {
            return back()->withErrors(['error' => 'Only draft and submitted (not yet approved) purchase requisitions can be deleted. Approved PRs can only be deleted by users with special role.']);
        }

        // For APPROVED status, only allow if user has special role
        if ($isApprovedStatus && !$hasSpecialRole) {
            return back()->withErrors(['error' => 'Only users with special role can delete approved purchase requisitions.']);
        }

        // For DRAFT and SUBMITTED, check if user is the creator
        // If user has special role (id_role='5af56935b011a'), allow delete all data without checking creator
        if (in_array($purchaseRequisition->status, $deletableStatuses) && !$hasSpecialRole && $purchaseRequisition->created_by !== auth()->id()) {
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

        // For PR Ops mode, check if all items are in PO before allowing status change
        if ($purchaseRequisition->mode === 'pr_ops') {
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
                'items.outlet',
                'items.category',
                'attachments.uploader',
                'attachments.outlet',
                'approvalFlows.approver.jabatan'
            ])->findOrFail($id);

            // Parse mode-specific data
            $modeSpecificData = [];
            if ($purchaseRequisition->mode === 'travel_application') {
                // Try to parse from notes field (new structure)
                if ($purchaseRequisition->notes) {
                    $notesData = json_decode($purchaseRequisition->notes, true);
                    if (is_array($notesData)) {
                        $modeSpecificData = [
                            'travel_outlet_ids' => $notesData['outlet_ids'] ?? [],
                            'travel_agenda' => $notesData['agenda'] ?? '',
                            'travel_notes' => $notesData['notes'] ?? '',
                        ];
                        // Load outlet names
                        if (!empty($modeSpecificData['travel_outlet_ids'])) {
                            $outlets = \App\Models\Outlet::whereIn('id_outlet', $modeSpecificData['travel_outlet_ids'])->get();
                            $modeSpecificData['travel_outlets'] = $outlets->map(function($outlet) {
                                return [
                                    'id' => $outlet->id_outlet,
                                    'name' => $outlet->nama_outlet,
                                ];
                            })->toArray();
                        }
                    }
                }
                // Fallback: if notes is not JSON, use description as agenda (for old data)
                if (empty($modeSpecificData['travel_agenda']) && $purchaseRequisition->description) {
                    $modeSpecificData['travel_agenda'] = $purchaseRequisition->description;
                }
            } elseif ($purchaseRequisition->mode === 'kasbon') {
                // For kasbon, extract amount and reason from items
                $kasbonItem = $purchaseRequisition->items->first();
                if ($kasbonItem) {
                    $modeSpecificData = [
                        'kasbon_amount' => $kasbonItem->subtotal ?? 0,
                        'kasbon_reason' => $kasbonItem->item_name ?? '',
                    ];
                }
            }
            // For legacy data (mode is null), modeSpecificData will be empty array, which is fine

            // Get budget information (hide for kasbon mode)
            $budgetInfo = null;
            
            // Get category for budget calculation
            // Support both old structure (category_id at PR level) and new structure (category_id at item level)
            $categoryForBudget = null;
            $outletIdForBudget = null;
            
            if ($purchaseRequisition->mode !== 'kasbon') {
                // For modes that use new structure (pr_ops, purchase_payment, travel_application)
                // Try to get category from items first, fallback to PR level for backward compatibility
                if (in_array($purchaseRequisition->mode, ['pr_ops', 'purchase_payment', 'travel_application'])) {
                    // Try to get category from items (new structure)
                    $firstItemWithCategory = $purchaseRequisition->items->first(function($item) {
                        return $item->category_id !== null;
                    });
                    
                    if ($firstItemWithCategory && $firstItemWithCategory->category_id) {
                        // New structure: category_id at item level
                        $categoryForBudget = PurchaseRequisitionCategory::find($firstItemWithCategory->category_id);
                        $outletIdForBudget = $firstItemWithCategory->outlet_id;
                    } else if ($purchaseRequisition->category_id) {
                        // Fallback to old structure: category_id at PR level (for backward compatibility)
                        $categoryForBudget = $purchaseRequisition->category;
                        $outletIdForBudget = $purchaseRequisition->outlet_id;
                    }
                } else {
                    // For legacy modes or modes without specific structure, use category from PR (old structure)
                    if ($purchaseRequisition->category_id) {
                        $categoryForBudget = $purchaseRequisition->category;
                        $outletIdForBudget = $purchaseRequisition->outlet_id;
                    }
                }
            }
            
            // Calculate budget info (for all modes except kasbon)
            if ($categoryForBudget) {
                $category = $categoryForBudget;
                $purchaseRequisition->category_id = $category->id; // Set for reference
                $purchaseRequisition->outlet_id = $outletIdForBudget; // Set for reference
                if ($category) {
                    $currentMonth = now()->month;
                    $currentYear = now()->year;
                    $dateFrom = date('Y-m-01', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
                    $dateTo = date('Y-m-t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
                    
                    if ($category->isGlobalBudget()) {
                        // GLOBAL BUDGET: Calculate across all outlets (BUDGET IS MONTHLY - filter by month)
                        $prQuery = PurchaseRequisition::where('category_id', $category->id)
                            ->whereYear('created_at', $currentYear)
                            ->whereMonth('created_at', $currentMonth);
                        
                        // Calculate different amounts
                        $totalUsedAmount = (clone $prQuery)->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])->sum('amount');
                        $approvedAmount = (clone $prQuery)->whereIn('status', ['APPROVED', 'PROCESSED', 'COMPLETED'])->sum('amount');
                        $unapprovedAmount = (clone $prQuery)->where('status', 'SUBMITTED')->sum('amount');
                        
                        // Calculate Retail Non Food amounts (only for GLOBAL budget)
                        $retailNonFoodApproved = RetailNonFood::where('category_budget_id', $category->id)
                            ->whereYear('transaction_date', $currentYear)
                            ->whereMonth('transaction_date', $currentMonth)
                            ->where('status', 'approved')
                            ->sum('total_amount');
                        
                        $retailNonFoodPending = RetailNonFood::where('category_budget_id', $category->id)
                            ->whereYear('transaction_date', $currentYear)
                            ->whereMonth('transaction_date', $currentMonth)
                            ->where('status', 'pending')
                            ->sum('total_amount');
                        
                        // Combine PR and Retail Non Food amounts
                        $totalApprovedAmount = $approvedAmount + $retailNonFoodApproved;
                        $totalUnapprovedAmount = $unapprovedAmount + $retailNonFoodPending;
                        
                        // Get PR IDs in this category for the month
                        $prIds = (clone $prQuery)->pluck('id')->toArray();
                        
                        // Calculate PO created amount (POs created from PRs in this category)
                        $poCreatedAmount = \App\Models\PurchaseOrderOps::where('source_type', 'purchase_requisition_ops')
                            ->whereIn('source_id', $prIds)
                            ->sum('grand_total');
                        
                        // Calculate paid and unpaid amounts from non_food_payments (same logic as Opex Report)
                        // Get PO IDs that are linked to PRs in this category
                        $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                            ->where('poi.source_type', 'purchase_requisition_ops')
                            ->whereIn('poi.source_id', $prIds)
                            ->distinct()
                            ->pluck('poi.purchase_order_ops_id')
                            ->toArray();
                        
                        // Get paid payments directly from non_food_payments (to avoid double counting)
                        // 1 payment = 1 transaksi, tidak dihitung berkali-kali
                        // Filter by payment date in current month (BUDGET IS MONTHLY)
                        $paidAmountFromPo = DB::table('non_food_payments as nfp')
                            ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                            ->whereIn('nfp.status', ['paid', 'approved'])
                            ->where('nfp.status', '!=', 'cancelled')
                            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                            ->sum('nfp.amount');
                        
                        // Add Retail Non Food approved amount (directly paid, same as Opex Report)
                        $paidAmountFromRnf = $retailNonFoodApproved;
                        
                        $paidAmount = $paidAmountFromPo + $paidAmountFromRnf;
                        
                        // Calculate unpaid: PO total - paid amount per PR
                        // Get all PRs in this category for the month (exclude held PRs)
                        $allPrs = (clone $prQuery)->where('is_held', false)->get();
                        
                        // Get PO totals per PR (BUDGET IS MONTHLY - filter by PR created_at month)
                        // Exclude held PRs from unpaid calculation
                        $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                            ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                            ->where('pr.category_id', $category->id)
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->where('pr.is_held', false) // Exclude held PRs
                            ->where('poi.source_type', 'purchase_requisition_ops')
                            ->where('poo.status', 'approved')
                            ->whereBetween('poo.date', [$dateFrom, $dateTo])
                            ->groupBy('pr.id')
                            ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                            ->pluck('po_total', 'pr_id')
                            ->toArray();
                        
                        // Get total paid per PR (BUDGET IS MONTHLY - filter by PR created_at month and payment_date)
                        // Exclude held PRs from unpaid calculation
                        $paidTotalsByPr = DB::table('non_food_payments as nfp')
                            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                            ->where('pr.category_id', $category->id)
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->where('pr.is_held', false) // Exclude held PRs
                            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                            ->whereIn('nfp.status', ['paid', 'approved'])
                            ->where('nfp.status', '!=', 'cancelled')
                            ->where('poi.source_type', 'purchase_requisition_ops')
                            ->groupBy('pr.id')
                            ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                            ->pluck('total_paid', 'pr_id')
                            ->toArray();
                        
                        // Calculate unpaid for each PR
                        $unpaidAmount = 0;
                        foreach ($allPrs as $pr) {
                            $prId = $pr->id;
                            $poTotal = $poTotalsByPr[$prId] ?? 0;
                            $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                            
                            // If PR hasn't been converted to PO, use PR amount
                            // If PR has been converted to PO, use PO total
                            $totalAmount = $poTotal > 0 ? $poTotal : $pr->amount;
                            $unpaidAmount += max(0, $totalAmount - $totalPaid);
                        }
                        
                        // Calculate category used amount = Paid (from non_food_payments) + Unpaid PR + Retail Non Food
                        // Same logic as outlet_used_amount
                        $categoryUsedAmount = $paidAmount + $unpaidAmount + $retailNonFoodPending;
                        
                        $budgetInfo = [
                            'budget_type' => 'GLOBAL',
                            'category_budget' => $category->budget_limit,
                            'category_used_amount' => $categoryUsedAmount,
                            'category_remaining_amount' => $category->budget_limit - $categoryUsedAmount,
                            'approved_amount' => $totalApprovedAmount,
                            'unapproved_amount' => $totalUnapprovedAmount,
                            'retail_non_food_approved' => $retailNonFoodApproved,
                            'retail_non_food_pending' => $retailNonFoodPending,
                            'po_created_amount' => $poCreatedAmount,
                            'paid_amount' => $paidAmount,
                            'unpaid_amount' => $unpaidAmount,
                            'real_remaining_budget' => $category->budget_limit - $totalApprovedAmount - $totalUnapprovedAmount,
                            'current_month' => $currentMonth,
                            'current_year' => $currentYear,
                        ];
                        
                        // Calculate outlet used amount if PR has outlet_id (regardless of budget type)
                        // Used amount = Paid (from non_food_payments) + Unpaid PR + Retail Non Food
                        if ($outletIdForBudget) {
                            // Get PR IDs for this outlet
                            $outletPrIds = PurchaseRequisition::where('category_id', $category->id)
                                ->where('outlet_id', $outletIdForBudget)
                                ->whereYear('created_at', $currentYear)
                                ->whereMonth('created_at', $currentMonth)
                                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                                ->pluck('id')
                                ->toArray();
                            
                            // Get PO IDs linked to PRs in this outlet
                            $outletPoIds = DB::table('purchase_order_ops_items as poi')
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poi.source_id', $outletPrIds)
                                ->distinct()
                                ->pluck('poi.purchase_order_ops_id')
                                ->toArray();
                            
                            // Get paid amount from non_food_payments for this outlet
                            $outletPaidAmount = DB::table('non_food_payments as nfp')
                                ->whereIn('nfp.purchase_order_ops_id', $outletPoIds)
                                ->whereIn('nfp.status', ['paid', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            // Get all PRs for this outlet (exclude held PRs)
                            $outletAllPrs = PurchaseRequisition::where('category_id', $category->id)
                                ->where('outlet_id', $outletIdForBudget)
                                ->whereYear('created_at', $currentYear)
                                ->whereMonth('created_at', $currentMonth)
                                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                                ->where('is_held', false) // Exclude held PRs
                                ->get();
                            
                            // Get PO totals per PR for this outlet (BUDGET IS MONTHLY - filter by PR created_at month)
                            // Exclude held PRs from unpaid calculation
                            $outletPoTotalsByPr = DB::table('purchase_order_ops_items as poi')
                                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                                ->where('pr.category_id', $category->id)
                                ->where('pr.outlet_id', $outletIdForBudget)
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false) // Exclude held PRs
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->where('poo.status', 'approved')
                                ->whereBetween('poo.date', [$dateFrom, $dateTo])
                                ->groupBy('pr.id')
                                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                                ->pluck('po_total', 'pr_id')
                                ->toArray();
                            
                            // Get total paid per PR for this outlet (BUDGET IS MONTHLY - filter by PR created_at month and payment_date)
                            // Exclude held PRs from unpaid calculation
                            $outletPaidTotalsByPr = DB::table('non_food_payments as nfp')
                                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                                ->where('pr.category_id', $category->id)
                                ->where('pr.outlet_id', $outletIdForBudget)
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false) // Exclude held PRs
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->whereIn('nfp.status', ['paid', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->groupBy('pr.id')
                                ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                                ->pluck('total_paid', 'pr_id')
                                ->toArray();
                            
                            // Calculate unpaid PR amount for this outlet
                            $outletUnpaidPrAmount = 0;
                            foreach ($outletAllPrs as $pr) {
                                $prId = $pr->id;
                                $poTotal = $outletPoTotalsByPr[$prId] ?? 0;
                                $totalPaid = $outletPaidTotalsByPr[$prId] ?? 0;
                                
                                // If PR hasn't been converted to PO, use PR amount
                                // If PR has been converted to PO, use PO total
                                $totalAmount = $poTotal > 0 ? $poTotal : $pr->amount;
                                $outletUnpaidPrAmount += max(0, $totalAmount - $totalPaid);
                            }
                            
                            // Add Retail Non Food for this outlet (approved + pending)
                            $outletRetailNonFoodUsed = RetailNonFood::where('category_budget_id', $category->id)
                                ->where('outlet_id', $outletIdForBudget)
                                ->whereYear('transaction_date', $currentYear)
                                ->whereMonth('transaction_date', $currentMonth)
                                ->whereIn('status', ['approved', 'pending'])
                                ->sum('total_amount');
                            
                            // Total used = Paid (from non_food_payments) + Unpaid PR + Retail Non Food
                            $outletUsedAmount = $outletPaidAmount + $outletUnpaidPrAmount + $outletRetailNonFoodUsed;
                            
                            $budgetInfo['outlet_used_amount'] = $outletUsedAmount;
                            // Get outlet name
                            $outlet = \App\Models\Outlet::find($outletIdForBudget);
                            $budgetInfo['outlet_info'] = [
                                'id' => $outletIdForBudget,
                                'name' => $outlet->nama_outlet ?? 'Unknown Outlet',
                            ];
                        }
                    } else if ($category->isPerOutletBudget() && $outletIdForBudget) {
                        // PER_OUTLET BUDGET: Calculate per specific outlet
                        $outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $category->id)
                            ->where('outlet_id', $outletIdForBudget)
                            ->with('outlet')
                            ->first();
                        
                        if ($outletBudget) {
                            $prQuery = PurchaseRequisition::where('category_id', $category->id)
                                ->where('outlet_id', $outletIdForBudget)
                                ->whereYear('created_at', $currentYear)
                                ->whereMonth('created_at', $currentMonth);
                            
                            // Calculate different amounts
                            $outletPrUsedAmount = (clone $prQuery)->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])->sum('amount');
                            $approvedAmount = (clone $prQuery)->whereIn('status', ['APPROVED', 'PROCESSED', 'COMPLETED'])->sum('amount');
                            $unapprovedAmount = (clone $prQuery)->where('status', 'SUBMITTED')->sum('amount');
                            
                            // Calculate Retail Non Food amounts for this outlet
                            $outletRetailNonFoodApproved = RetailNonFood::where('category_budget_id', $category->id)
                                ->where('outlet_id', $outletIdForBudget)
                                ->whereYear('transaction_date', $currentYear)
                                ->whereMonth('transaction_date', $currentMonth)
                                ->where('status', 'approved')
                                ->sum('total_amount');
                            
                            $outletRetailNonFoodPending = RetailNonFood::where('category_budget_id', $category->id)
                                ->where('outlet_id', $outletIdForBudget)
                                ->whereYear('transaction_date', $currentYear)
                                ->whereMonth('transaction_date', $currentMonth)
                                ->where('status', 'pending')
                                ->sum('total_amount');
                            
                            // Combine PR and Retail Non Food amounts
                            $outletUsedAmount = $outletPrUsedAmount + $outletRetailNonFoodApproved + $outletRetailNonFoodPending;
                            $totalApprovedAmount = $approvedAmount + $outletRetailNonFoodApproved;
                            $totalUnapprovedAmount = $unapprovedAmount + $outletRetailNonFoodPending;
                            
                            // Get PR IDs in this category and outlet for the month
                            $prIds = (clone $prQuery)->pluck('id')->toArray();
                            
                            // Calculate PO created amount (POs created from PRs in this category and outlet)
                            $poCreatedAmount = \App\Models\PurchaseOrderOps::where('source_type', 'purchase_requisition_ops')
                                ->whereIn('source_id', $prIds)
                                ->sum('grand_total');
                            
                            // Calculate paid and unpaid amounts from non_food_payments (same logic as Opex Report)
                            // Get PO IDs that are linked to PRs in this category and outlet
                            $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poi.source_id', $prIds)
                                ->distinct()
                                ->pluck('poi.purchase_order_ops_id')
                                ->toArray();
                            
                            // Get paid payments directly from non_food_payments (to avoid double counting)
                            // 1 payment = 1 transaksi, tidak dihitung berkali-kali
                            // Filter by payment date in current month (BUDGET IS MONTHLY)
                            $paidAmountFromPo = DB::table('non_food_payments as nfp')
                                ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                                ->whereIn('nfp.status', ['paid', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->sum('nfp.amount');
                            
                            // Add Retail Non Food approved amount (directly paid, same as Opex Report)
                            $paidAmountFromRnf = $outletRetailNonFoodApproved;
                            
                            $paidAmount = $paidAmountFromPo + $paidAmountFromRnf;
                            
                            // Calculate unpaid: PO total - paid amount per PR
                            // Get all PRs in this category and outlet for the month (exclude held PRs)
                            $allPrs = (clone $prQuery)->where('is_held', false)->get();
                            
                            // Get PO totals per PR (BUDGET IS MONTHLY - filter by PR created_at month)
                            // Exclude held PRs from unpaid calculation
                            $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                                ->where('pr.category_id', $category->id)
                                ->where('pr.outlet_id', $outletIdForBudget)
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false) // Exclude held PRs
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->where('poo.status', 'approved')
                                ->whereBetween('poo.date', [$dateFrom, $dateTo])
                                ->groupBy('pr.id')
                                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                                ->pluck('po_total', 'pr_id')
                                ->toArray();
                            
                            // Get total paid per PR (BUDGET IS MONTHLY - filter by PR created_at month and payment_date)
                            // Exclude held PRs from unpaid calculation
                            $paidTotalsByPr = DB::table('non_food_payments as nfp')
                                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                                ->where('pr.category_id', $category->id)
                                ->where('pr.outlet_id', $outletIdForBudget)
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false) // Exclude held PRs
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->whereIn('nfp.status', ['paid', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->groupBy('pr.id')
                                ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                                ->pluck('total_paid', 'pr_id')
                                ->toArray();
                            
                            // Calculate unpaid for each PR
                            $unpaidAmount = 0;
                            foreach ($allPrs as $pr) {
                                $prId = $pr->id;
                                $poTotal = $poTotalsByPr[$prId] ?? 0;
                                $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                                
                                // If PR hasn't been converted to PO, use PR amount
                                // If PR has been converted to PO, use PO total
                                $totalAmount = $poTotal > 0 ? $poTotal : $pr->amount;
                                $unpaidAmount += max(0, $totalAmount - $totalPaid);
                            }
                            
                            // Calculate category used amount = Paid (from non_food_payments) + Unpaid PR + Retail Non Food
                            // Same logic as outlet_used_amount
                            $categoryUsedAmount = $paidAmount + $unpaidAmount + $outletRetailNonFoodPending;
                            
                            $budgetInfo = [
                                'budget_type' => 'PER_OUTLET',
                                'category_budget' => $category->budget_limit, // Global budget for reference
                                'outlet_budget' => $outletBudget->allocated_budget,
                                'outlet_used_amount' => $outletUsedAmount,
                                'outlet_remaining_amount' => $outletBudget->allocated_budget - $outletUsedAmount,
                                'category_used_amount' => $categoryUsedAmount, // Add category_used_amount for consistency
                                'approved_amount' => $totalApprovedAmount,
                                'unapproved_amount' => $totalUnapprovedAmount,
                                'retail_non_food_approved' => $outletRetailNonFoodApproved,
                                'retail_non_food_pending' => $outletRetailNonFoodPending,
                                'po_created_amount' => $poCreatedAmount,
                                'paid_amount' => $paidAmount,
                                'unpaid_amount' => $unpaidAmount,
                                'real_remaining_budget' => $outletBudget->allocated_budget - $totalApprovedAmount - $totalUnapprovedAmount,
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
                'budget_info' => $budgetInfo, // Can be null if kasbon mode or no category
                'mode_specific_data' => $modeSpecificData
            ], 200, [], JSON_UNESCAPED_UNICODE);

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

        // Calculate date range for the month (BUDGET IS MONTHLY)
        $dateFrom = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
        $dateTo = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

        $budgetInfo = [];

        if ($category->isGlobalBudget()) {
            // GLOBAL BUDGET: Calculate across all outlets
            // Used amount = Paid (from non_food_payments) + Unpaid PR + Retail Non Food (same logic as approval)
            $categoryBudget = $category->budget_limit;
            
            // Get PR IDs in this category for the month (BUDGET IS MONTHLY - filter by month)
            $prIds = PurchaseRequisition::where('category_id', $categoryId)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->pluck('id')
                ->toArray();
            
            // Get PO IDs linked to PRs in this category
            $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poi.source_id', $prIds)
                ->distinct()
                ->pluck('poi.purchase_order_ops_id')
                ->toArray();
            
            // Get paid amount from non_food_payments (BUDGET IS MONTHLY - filter by payment_date)
            $paidAmountFromPo = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->sum('nfp.amount');
            
            // Get Retail Non Food amounts (BUDGET IS MONTHLY - filter by transaction_date)
            $retailNonFoodApproved = RetailNonFood::where('category_budget_id', $categoryId)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                ->where('status', 'approved')
                ->sum('total_amount');
            
            $retailNonFoodPending = RetailNonFood::where('category_budget_id', $categoryId)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                ->where('status', 'pending')
                ->sum('total_amount');
            
            // Get all PRs for unpaid calculation (exclude held PRs)
            $allPrs = PurchaseRequisition::where('category_id', $categoryId)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('is_held', false) // Exclude held PRs
                ->get();
            
            // Get PO totals per PR (BUDGET IS MONTHLY - filter by PR created_at month)
            // Exclude held PRs from unpaid calculation
            $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->where('pr.category_id', $categoryId)
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false) // Exclude held PRs
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where('poo.status', 'approved')
                ->whereBetween('poo.date', [$dateFrom, $dateTo])
                ->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                ->pluck('po_total', 'pr_id')
                ->toArray();
            
            // Get total paid per PR (BUDGET IS MONTHLY - filter by PR created_at month and payment_date)
            // Exclude held PRs from unpaid calculation
            $paidTotalsByPr = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->where('pr.category_id', $categoryId)
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false) // Exclude held PRs
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                ->pluck('total_paid', 'pr_id')
                ->toArray();
            
            // Calculate unpaid for each PR
            $unpaidAmount = 0;
            foreach ($allPrs as $pr) {
                $prId = $pr->id;
                $poTotal = $poTotalsByPr[$prId] ?? 0;
                $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                
                $totalAmount = $poTotal > 0 ? $poTotal : $pr->amount;
                $unpaidAmount += max(0, $totalAmount - $totalPaid);
            }
            
            // Total used = Paid (from non_food_payments + RNF approved) + Unpaid PR + RNF pending
            $paidAmount = $paidAmountFromPo + $retailNonFoodApproved;
            $categoryUsedAmount = $paidAmount + $unpaidAmount + $retailNonFoodPending;

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
            // Used amount = Paid (from non_food_payments) + Unpaid PR + Retail Non Food (same logic as approval)
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

            // Get PR IDs for this outlet
            $prIds = PurchaseRequisition::where('category_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->pluck('id')
                ->toArray();
            
            // Get PO IDs linked to PRs in this outlet
            $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poi.source_id', $prIds)
                ->distinct()
                ->pluck('poi.purchase_order_ops_id')
                ->toArray();
            
            // Get paid amount from non_food_payments for this outlet (BUDGET IS MONTHLY - filter by payment_date)
            $paidAmountFromPo = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->sum('nfp.amount');
            
            // Get Retail Non Food for this outlet (BUDGET IS MONTHLY - filter by transaction_date)
            $outletRetailNonFoodApproved = RetailNonFood::where('category_budget_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                ->where('status', 'approved')
                ->sum('total_amount');
            
            $outletRetailNonFoodPending = RetailNonFood::where('category_budget_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                ->where('status', 'pending')
                ->sum('total_amount');
            
            // Get all PRs for this outlet (exclude held PRs)
            $allPrs = PurchaseRequisition::where('category_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('is_held', false) // Exclude held PRs
                ->get();
            
            // Get PO totals per PR for this outlet (BUDGET IS MONTHLY - filter by PR created_at month)
            // Exclude held PRs from unpaid calculation
            $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->where('pr.category_id', $categoryId)
                ->where('pr.outlet_id', $outletId)
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false) // Exclude held PRs
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where('poo.status', 'approved')
                ->whereBetween('poo.date', [$dateFrom, $dateTo])
                ->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                ->pluck('po_total', 'pr_id')
                ->toArray();
            
            // Get total paid per PR for this outlet (BUDGET IS MONTHLY - filter by PR created_at month and payment_date)
            // Exclude held PRs from unpaid calculation
            $paidTotalsByPr = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->where('pr.category_id', $categoryId)
                ->where('pr.outlet_id', $outletId)
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false) // Exclude held PRs
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                ->pluck('total_paid', 'pr_id')
                ->toArray();
            
            // Calculate unpaid for each PR
            $unpaidAmount = 0;
            foreach ($allPrs as $pr) {
                $prId = $pr->id;
                $poTotal = $poTotalsByPr[$prId] ?? 0;
                $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                
                $totalAmount = $poTotal > 0 ? $poTotal : $pr->amount;
                $unpaidAmount += max(0, $totalAmount - $totalPaid);
            }
            
            // Total used = Paid (from non_food_payments + RNF approved) + Unpaid PR + RNF pending
            $paidAmount = $paidAmountFromPo + $outletRetailNonFoodApproved;
            $outletUsedAmount = $paidAmount + $unpaidAmount + $outletRetailNonFoodPending;

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
            'outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet', // For pr_ops mode
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
                'outlet_id' => $request->outlet_id ?? null, // For pr_ops mode
                'file_name' => $originalName,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'attachment' => $attachment->load(['uploader', 'outlet']),
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
            // Check if user has role with id '5af56935b011a' (can see all payments)
            // Check from users table id_role column
            $user = auth()->user();
            $canSeeAllPayments = false;
            
            if ($user && $user->id_role === '5af56935b011a') {
                $canSeeAllPayments = true;
            }

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

            // Filter by created_by if user doesn't have special role
            if (!$canSeeAllPayments && $user) {
                $query->where('created_by', $user->id);
            }

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
            // Used amount = Paid (from non_food_payments) + Unpaid PR + Retail Non Food (same logic as approval)
            $prQuery = PurchaseRequisition::where('category_id', $categoryId)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED']);

            if ($excludeId) {
                $prQuery->where('id', '!=', $excludeId);
            }

            $prIds = (clone $prQuery)->pluck('id')->toArray();
            
            // Get PO IDs linked to PRs in this category
            $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poi.source_id', $prIds)
                ->distinct()
                ->pluck('poi.purchase_order_ops_id')
                ->toArray();
            
            // Get paid amount from non_food_payments
            $paidAmountFromPo = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->whereYear('nfp.payment_date', $currentYear)
                ->whereMonth('nfp.payment_date', $currentMonth)
                ->sum('nfp.amount');
            
            // Get Retail Non Food amounts
            $retailNonFoodApproved = RetailNonFood::where('category_budget_id', $categoryId)
                ->whereYear('transaction_date', $currentYear)
                ->whereMonth('transaction_date', $currentMonth)
                ->where('status', 'approved')
                ->sum('total_amount');
            
            $retailNonFoodPending = RetailNonFood::where('category_budget_id', $categoryId)
                ->whereYear('transaction_date', $currentYear)
                ->whereMonth('transaction_date', $currentMonth)
                ->where('status', 'pending')
                ->sum('total_amount');
            
            // Get all PRs for unpaid calculation (exclude held PRs)
            $allPrs = (clone $prQuery)->where('is_held', false)->get();
            
            // Get PO totals per PR
            // Exclude held PRs from unpaid calculation
            $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->where('pr.category_id', $categoryId)
                ->where('pr.is_held', false) // Exclude held PRs
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where('poo.status', 'approved')
                ->whereYear('poo.date', $currentYear)
                ->whereMonth('poo.date', $currentMonth);
            
            if ($excludeId) {
                $poTotalsByPr->where('pr.id', '!=', $excludeId);
            }
            
            $poTotalsByPr = $poTotalsByPr->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                ->pluck('po_total', 'pr_id')
                ->toArray();
            
            // Get total paid per PR
            // Exclude held PRs from unpaid calculation
            $paidTotalsByPr = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->where('pr.category_id', $categoryId)
                ->where('pr.is_held', false) // Exclude held PRs
                ->whereYear('nfp.payment_date', $currentYear)
                ->whereMonth('nfp.payment_date', $currentMonth)
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->where('poi.source_type', 'purchase_requisition_ops');
            
            if ($excludeId) {
                $paidTotalsByPr->where('pr.id', '!=', $excludeId);
            }
            
            $paidTotalsByPr = $paidTotalsByPr->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                ->pluck('total_paid', 'pr_id')
                ->toArray();
            
            // Calculate unpaid for each PR
            $unpaidAmount = 0;
            foreach ($allPrs as $pr) {
                $prId = $pr->id;
                $poTotal = $poTotalsByPr[$prId] ?? 0;
                $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                
                $totalAmount = $poTotal > 0 ? $poTotal : $pr->amount;
                $unpaidAmount += max(0, $totalAmount - $totalPaid);
            }
            
            // Total used = Paid (from non_food_payments + RNF approved) + Unpaid PR + RNF pending
            $paidAmount = $paidAmountFromPo + $retailNonFoodApproved;
            $usedAmount = $paidAmount + $unpaidAmount + $retailNonFoodPending;
            
            $totalWithCurrent = $usedAmount + $amount;

            if ($totalWithCurrent > $category->budget_limit) {
                return [
                    'valid' => false,
                    'message' => "Total amount (Rp " . number_format($totalWithCurrent, 0, ',', '.') . ") exceeds category budget limit (Rp " . number_format($category->budget_limit, 0, ',', '.') . ") for this month."
                ];
            }

        } else if ($category->isPerOutletBudget()) {
            // PER_OUTLET BUDGET: Calculate per specific outlet
            // Used amount = Paid (from non_food_payments) + Unpaid PR + Retail Non Food (same logic as approval)
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

            // Get PR IDs for this outlet
            $prQuery = PurchaseRequisition::where('category_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED']);

            if ($excludeId) {
                $prQuery->where('id', '!=', $excludeId);
            }

            $prIds = (clone $prQuery)->pluck('id')->toArray();
            
            // Get PO IDs linked to PRs in this outlet
            $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poi.source_id', $prIds)
                ->distinct()
                ->pluck('poi.purchase_order_ops_id')
                ->toArray();
            
            // Get paid amount from non_food_payments for this outlet
            $paidAmountFromPo = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->whereYear('nfp.payment_date', $currentYear)
                ->whereMonth('nfp.payment_date', $currentMonth)
                ->sum('nfp.amount');
            
            // Get Retail Non Food for this outlet
            $outletRetailNonFoodApproved = RetailNonFood::where('category_budget_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->whereYear('transaction_date', $currentYear)
                ->whereMonth('transaction_date', $currentMonth)
                ->where('status', 'approved')
                ->sum('total_amount');
            
            $outletRetailNonFoodPending = RetailNonFood::where('category_budget_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->whereYear('transaction_date', $currentYear)
                ->whereMonth('transaction_date', $currentMonth)
                ->where('status', 'pending')
                ->sum('total_amount');
            
            // Get all PRs for this outlet (exclude held PRs)
            $allPrs = (clone $prQuery)->where('is_held', false)->get();
            
            // Get PO totals per PR for this outlet
            // Exclude held PRs from unpaid calculation
            $poTotalsByPrQuery = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->where('pr.category_id', $categoryId)
                ->where('pr.outlet_id', $outletId)
                ->where('pr.is_held', false) // Exclude held PRs
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where('poo.status', 'approved')
                ->whereYear('poo.date', $currentYear)
                ->whereMonth('poo.date', $currentMonth);
            
            if ($excludeId) {
                $poTotalsByPrQuery->where('pr.id', '!=', $excludeId);
            }
            
            $poTotalsByPr = $poTotalsByPrQuery->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                ->pluck('po_total', 'pr_id')
                ->toArray();
            
            // Get total paid per PR for this outlet
            // Exclude held PRs from unpaid calculation
            $paidTotalsByPrQuery = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->where('pr.category_id', $categoryId)
                ->where('pr.outlet_id', $outletId)
                ->where('pr.is_held', false) // Exclude held PRs
                ->whereYear('nfp.payment_date', $currentYear)
                ->whereMonth('nfp.payment_date', $currentMonth)
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->where('poi.source_type', 'purchase_requisition_ops');
            
            if ($excludeId) {
                $paidTotalsByPrQuery->where('pr.id', '!=', $excludeId);
            }
            
            $paidTotalsByPr = $paidTotalsByPrQuery->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                ->pluck('total_paid', 'pr_id')
                ->toArray();
            
            // Calculate unpaid for each PR
            $unpaidAmount = 0;
            foreach ($allPrs as $pr) {
                $prId = $pr->id;
                $poTotal = $poTotalsByPr[$prId] ?? 0;
                $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                
                $totalAmount = $poTotal > 0 ? $poTotal : $pr->amount;
                $unpaidAmount += max(0, $totalAmount - $totalPaid);
            }
            
            // Total used = Paid (from non_food_payments + RNF approved) + Unpaid PR + RNF pending
            $paidAmount = $paidAmountFromPo + $outletRetailNonFoodApproved;
            $usedAmount = $paidAmount + $unpaidAmount + $outletRetailNonFoodPending;
            
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

    /**
     * Check if kasbon exists for outlet in given period
     * DISABLED: Validasi duplikasi kasbon untuk outlet yang sama telah dinonaktifkan
     */
    public function checkKasbonPeriod(Request $request)
    {
        // Always return false - validasi duplikasi kasbon telah dinonaktifkan, bebas input kapan saja
        return response()->json([
            'exists' => false,
            'message' => 'No existing kasbon found for this outlet in the period'
        ]);
        
        // Original validation code (disabled):
        // $outletId = $request->get('outlet_id');
        // $startDate = $request->get('start_date');
        // $endDate = $request->get('end_date');
        // $excludeId = $request->get('exclude_id'); // For edit mode, exclude current PR
        //
        // if (!$outletId || !$startDate || !$endDate) {
        //     return response()->json([
        //         'exists' => false,
        //         'message' => 'Missing required parameters'
        //     ]);
        // }
        //
        // // Check if there's already a kasbon PR for this outlet in the period
        // $query = PurchaseRequisition::where('mode', 'kasbon')
        //     ->where('outlet_id', $outletId)
        //     ->whereDate('created_at', '>=', $startDate)
        //     ->whereDate('created_at', '<=', $endDate)
        //     ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED']);
        //
        // // Exclude current PR if editing
        // if ($excludeId) {
        //     $query->where('id', '!=', $excludeId);
        // }
        //
        // $existingKasbon = $query->first();
        //
        // if ($existingKasbon) {
        //     $creator = $existingKasbon->creator;
        //     $creatorName = $creator ? $creator->nama_lengkap : 'Unknown';
        //     
        //     return response()->json([
        //         'exists' => true,
        //         'message' => "Sudah ada pengajuan kasbon untuk outlet ini di periode yang sama. Dibuat oleh: {$creatorName}",
        //         'existing_pr' => [
        //             'id' => $existingKasbon->id,
        //             'pr_number' => $existingKasbon->pr_number,
        //             'created_by_name' => $creatorName,
        //             'created_at' => $existingKasbon->created_at->format('d/m/Y H:i')
        //         ]
        //     ]);
        // }
        //
        // return response()->json([
        //     'exists' => false,
        //     'message' => 'No existing kasbon found for this outlet in the period'
        // ]);
    }

    /**
     * Hold a purchase requisition
     */
    public function hold(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        $request->validate([
            'hold_reason' => 'required|string|max:1000'
        ], [
            'hold_reason.required' => 'Alasan hold harus diisi.'
        ]);

        if ($purchaseRequisition->is_held) {
            return back()->withErrors(['error' => 'Purchase Requisition is already on hold.']);
        }

        try {
            $purchaseRequisition->update([
                'is_held' => true,
                'held_at' => now(),
                'held_by' => auth()->id(),
                'hold_reason' => $request->hold_reason
            ]);

            // Log history
            \App\Models\PurchaseRequisitionHistory::create([
                'purchase_requisition_id' => $purchaseRequisition->id,
                'user_id' => auth()->id(),
                'action' => 'HOLD',
                'old_status' => $purchaseRequisition->status,
                'new_status' => $purchaseRequisition->status,
                'description' => 'PR di-hold' . ($request->hold_reason ? ': ' . $request->hold_reason : '')
            ]);

            return back()->with('success', 'Purchase Requisition berhasil di-hold.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal meng-hold Purchase Requisition: ' . $e->getMessage()]);
        }
    }

    /**
     * Release a purchase requisition from hold
     */
    public function release(PurchaseRequisition $purchaseRequisition)
    {
        if (!$purchaseRequisition->is_held) {
            return back()->withErrors(['error' => 'Purchase Requisition is not on hold.']);
        }

        try {
            $purchaseRequisition->update([
                'is_held' => false,
                'held_at' => null,
                'held_by' => null,
                'hold_reason' => null
            ]);

            // Log history
            \App\Models\PurchaseRequisitionHistory::create([
                'purchase_requisition_id' => $purchaseRequisition->id,
                'user_id' => auth()->id(),
                'action' => 'RELEASE',
                'old_status' => $purchaseRequisition->status,
                'new_status' => $purchaseRequisition->status,
                'description' => 'PR di-release dari hold'
            ]);

            return back()->with('success', 'Purchase Requisition berhasil di-release dari hold.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal me-release Purchase Requisition: ' . $e->getMessage()]);
        }
    }
}