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
            'items.outlet', // Load items with outlet for multi-outlet modes
            'items.category' // Load items with category for pr_ops and purchase_payment modes
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
                  // Search outlet: untuk pr_ops dan purchase_payment, cari di items.outlet
                  // Untuk mode lain, cari di header outlet
                  ->orWhere(function($subQ) use ($search) {
                      $subQ->whereIn('mode', ['pr_ops', 'purchase_payment'])
                          ->whereHas('items.outlet', function($itemQ) use ($search) {
                              $itemQ->where('nama_outlet', 'like', "%{$search}%");
                          });
                  })
                  ->orWhere(function($subQ) use ($search) {
                      $subQ->whereNotIn('mode', ['pr_ops', 'purchase_payment'])
                          ->whereHas('outlet', function($q) use ($search) {
                              $q->where('nama_outlet', 'like', "%{$search}%");
                          });
                  })
                  // Search category: untuk pr_ops dan purchase_payment, cari di items.category
                  // Untuk mode lain, cari di header category
                  ->orWhere(function($subQ) use ($search) {
                      $subQ->whereIn('mode', ['pr_ops', 'purchase_payment'])
                          ->whereHas('items.category', function($itemQ) use ($search) {
                              $itemQ->where('name', 'like', "%{$search}%")
                                    ->orWhere('subcategory', 'like', "%{$search}%");
                          });
                  })
                  ->orWhere(function($subQ) use ($search) {
                      $subQ->whereNotIn('mode', ['pr_ops', 'purchase_payment'])
                          ->whereHas('category', function($q) use ($search) {
                              $q->where('name', 'like', "%{$search}%")
                                ->orWhere('subcategory', 'like', "%{$search}%");
                          });
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

        // Filter category: untuk pr_ops dan purchase_payment, filter berdasarkan items.category_id
        // Untuk mode lain, filter berdasarkan category_id di header
        if ($category !== 'all') {
            $query->where(function($q) use ($category) {
                // Untuk mode pr_ops dan purchase_payment: filter berdasarkan items
                $q->where(function($subQ) use ($category) {
                    $subQ->whereIn('mode', ['pr_ops', 'purchase_payment'])
                        ->whereHas('items', function($itemQ) use ($category) {
                            $itemQ->where('category_id', $category);
                        });
                })
                // Untuk mode lain: filter berdasarkan category_id di header
                ->orWhere(function($subQ) use ($category) {
                    $subQ->whereNotIn('mode', ['pr_ops', 'purchase_payment'])
                        ->where('category_id', $category);
                });
            });
        }
        
        // Filter outlet: untuk pr_ops dan purchase_payment, filter berdasarkan items.outlet_id
        $outlet = $request->get('outlet', 'all');
        if ($outlet !== 'all') {
            $query->where(function($q) use ($outlet) {
                // Untuk mode pr_ops dan purchase_payment: filter berdasarkan items
                $q->where(function($subQ) use ($outlet) {
                    $subQ->whereIn('mode', ['pr_ops', 'purchase_payment'])
                        ->whereHas('items', function($itemQ) use ($outlet) {
                            $itemQ->where('outlet_id', $outlet);
                        });
                })
                // Untuk mode lain: filter berdasarkan outlet_id di header
                ->orWhere(function($subQ) use ($outlet) {
                    $subQ->whereNotIn('mode', ['pr_ops', 'purchase_payment'])
                        ->where('outlet_id', $outlet);
                });
            });
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
            
            // Count unread comments (comments created after user last viewed this PR)
            $userId = auth()->id();
            $unreadCount = 0;
            
            if ($userId) {
                // Get last view time from history (if user has viewed this PR)
                $lastView = DB::table('purchase_requisition_history')
                    ->where('purchase_requisition_id', $pr->id)
                    ->where('user_id', $userId)
                    ->where('action', 'viewed')
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                $lastViewTime = $lastView ? $lastView->created_at : null;
                
                // If user hasn't viewed, count all comments except their own
                // If user has viewed, count comments created after last view
                if ($lastViewTime) {
                    $unreadCount = DB::table('purchase_requisition_comments')
                        ->where('purchase_requisition_id', $pr->id)
                        ->where('user_id', '!=', $userId) // Exclude own comments
                        ->where('created_at', '>', $lastViewTime)
                        ->count();
                } else {
                    // User hasn't viewed this PR, count all comments except their own
                    $unreadCount = DB::table('purchase_requisition_comments')
                        ->where('purchase_requisition_id', $pr->id)
                        ->where('user_id', '!=', $userId) // Exclude own comments
                        ->count();
                }
            }
            
            $pr->unread_comments_count = $unreadCount;
            
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

        // Return JSON for API requests (mobile app)
        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return response()->json([
                'success' => true,
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
                    'outlets' => Outlet::active()->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
                ],
                'statistics' => $statistics,
            ]);
        }
        
        // Return Inertia for web requests
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
                'outlets' => Outlet::active()->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
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
    public function show($id)
    {
        // Use findOrFail to ensure model is loaded correctly
        $purchaseRequisition = PurchaseRequisition::with([
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

        // Get budget information for the category (hide for kasbon mode)
        // Use the same logic as getBudgetInfo method for consistency
        // For pr_ops mode, category might be at items level, so get from first item if PR level category is null
        $budgetInfo = null;
        if ($purchaseRequisition->mode !== 'kasbon') {
            $categoryId = $purchaseRequisition->category_id;
            $outletId = $purchaseRequisition->outlet_id;
            
            // For pr_ops mode, if category_id is null at PR level, get from first item
            if (!$categoryId && in_array($purchaseRequisition->mode, ['pr_ops', 'purchase_payment'])) {
                $firstItem = $purchaseRequisition->items()->whereNotNull('category_id')->first();
                if ($firstItem) {
                    $categoryId = $firstItem->category_id;
                    // Also get outlet_id from item if not set at PR level
                    if (!$outletId) {
                        $outletId = $firstItem->outlet_id;
                    }
                }
            }
            
            if ($categoryId) {
                $category = PurchaseRequisitionCategory::find($categoryId);
                if ($category) {
                    $currentMonth = date('m');
                    $currentYear = date('Y');
                    
                    // Create a request object to call getBudgetInfo method
                    $budgetRequest = new Request([
                        'category_id' => $categoryId,
                        'outlet_id' => $outletId,
                        'current_amount' => 0, // No current amount for show page
                        'year' => $currentYear,
                        'month' => $currentMonth,
                    ]);
                    
                    try {
                        $budgetResponse = $this->getBudgetInfo($budgetRequest);
                        $budgetData = json_decode($budgetResponse->getContent(), true);
                        if ($budgetData && $budgetData['success']) {
                            $budgetInfo = $budgetData;
                            $budgetInfo['current_month'] = $currentMonth;
                            $budgetInfo['current_year'] = $currentYear;
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Failed to get budget info in show method: ' . $e->getMessage());
                        $budgetInfo = null;
                    }
                }
            }
        }

        // Return JSON for API requests (mobile app)
        if (request()->expectsJson() || request()->is('api/*') || request()->wantsJson()) {
            // Build response array manually to ensure all fields are included
            $prData = [
                'id' => $purchaseRequisition->id,
                'pr_number' => $purchaseRequisition->pr_number,
                'date' => $purchaseRequisition->date ? $purchaseRequisition->date->format('Y-m-d') : null,
                'warehouse_id' => $purchaseRequisition->warehouse_id,
                'requested_by' => $purchaseRequisition->requested_by,
                'department' => $purchaseRequisition->department,
                'division_id' => $purchaseRequisition->division_id,
                'category_id' => $purchaseRequisition->category_id,
                'outlet_id' => $purchaseRequisition->outlet_id,
                'ticket_id' => $purchaseRequisition->ticket_id,
                'title' => $purchaseRequisition->title,
                'description' => $purchaseRequisition->description,
                'amount' => $purchaseRequisition->amount,
                'currency' => $purchaseRequisition->currency,
                'status' => $purchaseRequisition->status,
                'priority' => $purchaseRequisition->priority,
                'notes' => $purchaseRequisition->notes,
                'mode' => $purchaseRequisition->mode,
                'created_by' => $purchaseRequisition->created_by,
                'updated_by' => $purchaseRequisition->updated_by,
                'approved_ssd_by' => $purchaseRequisition->approved_ssd_by,
                'approved_ssd_at' => $purchaseRequisition->approved_ssd_at ? $purchaseRequisition->approved_ssd_at->format('Y-m-d H:i:s') : null,
                'approved_cc_by' => $purchaseRequisition->approved_cc_by,
                'approved_cc_at' => $purchaseRequisition->approved_cc_at ? $purchaseRequisition->approved_cc_at->format('Y-m-d H:i:s') : null,
                'is_held' => $purchaseRequisition->is_held,
                'held_at' => $purchaseRequisition->held_at ? $purchaseRequisition->held_at->format('Y-m-d H:i:s') : null,
                'held_by' => $purchaseRequisition->held_by,
                'hold_reason' => $purchaseRequisition->hold_reason,
                'created_at' => $purchaseRequisition->created_at ? $purchaseRequisition->created_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $purchaseRequisition->updated_at ? $purchaseRequisition->updated_at->format('Y-m-d H:i:s') : null,
            ];
            
            // Add relationships
            if ($purchaseRequisition->relationLoaded('division')) {
                $prData['division'] = $purchaseRequisition->division ? [
                    'id' => $purchaseRequisition->division->id,
                    'nama_divisi' => $purchaseRequisition->division->nama_divisi,
                ] : null;
            }
            
            if ($purchaseRequisition->relationLoaded('outlet')) {
                $prData['outlet'] = $purchaseRequisition->outlet ? [
                    'id_outlet' => $purchaseRequisition->outlet->id_outlet,
                    'nama_outlet' => $purchaseRequisition->outlet->nama_outlet,
                ] : null;
            }
            
            if ($purchaseRequisition->relationLoaded('ticket')) {
                $prData['ticket'] = $purchaseRequisition->ticket ? [
                    'id' => $purchaseRequisition->ticket->id,
                    'ticket_number' => $purchaseRequisition->ticket->ticket_number,
                ] : null;
            }
            
            if ($purchaseRequisition->relationLoaded('category')) {
                $prData['category'] = $purchaseRequisition->category ? [
                    'id' => $purchaseRequisition->category->id,
                    'name' => $purchaseRequisition->category->name,
                ] : null;
            }
            
            if ($purchaseRequisition->relationLoaded('creator')) {
                $prData['creator'] = $purchaseRequisition->creator ? [
                    'id' => $purchaseRequisition->creator->id,
                    'nama_lengkap' => $purchaseRequisition->creator->nama_lengkap,
                ] : null;
            }
            
            // Add items, comments, attachments, etc.
            if ($purchaseRequisition->relationLoaded('items')) {
                $prData['items'] = $purchaseRequisition->items->map(function($item) {
                    return array_merge($item->toArray(), [
                        'outlet' => $item->outlet ? [
                            'id_outlet' => $item->outlet->id_outlet,
                            'nama_outlet' => $item->outlet->nama_outlet,
                        ] : null,
                        'category' => $item->category ? [
                            'id' => $item->category->id,
                            'name' => $item->category->name,
                        ] : null,
                    ]);
                })->toArray();
            }
            
            if ($purchaseRequisition->relationLoaded('comments')) {
                $prData['comments'] = $purchaseRequisition->comments->map(function($comment) {
                    return array_merge($comment->toArray(), [
                        'user' => $comment->user ? [
                            'id' => $comment->user->id,
                            'nama_lengkap' => $comment->user->nama_lengkap,
                        ] : null,
                    ]);
                })->toArray();
            }
            
            if ($purchaseRequisition->relationLoaded('attachments')) {
                $prData['attachments'] = $purchaseRequisition->attachments->toArray();
            }
            
            if ($purchaseRequisition->relationLoaded('history')) {
                $prData['history'] = $purchaseRequisition->history->map(function($history) {
                    return array_merge($history->toArray(), [
                        'user' => $history->user ? [
                            'id' => $history->user->id,
                            'nama_lengkap' => $history->user->nama_lengkap,
                        ] : null,
                    ]);
                })->toArray();
            }
            
            if ($purchaseRequisition->relationLoaded('approvalFlows')) {
                $prData['approvalFlows'] = $purchaseRequisition->approvalFlows->map(function($flow) {
                    return array_merge($flow->toArray(), [
                        'approver' => $flow->approver ? [
                            'id' => $flow->approver->id,
                            'nama_lengkap' => $flow->approver->nama_lengkap,
                            'jabatan' => $flow->approver->jabatan ? [
                                'id' => $flow->approver->jabatan->id,
                                'nama_jabatan' => $flow->approver->jabatan->nama_jabatan,
                            ] : null,
                        ] : null,
                    ]);
                })->toArray();
            }
            
            return response()->json([
                'success' => true,
                'purchaseRequisition' => $prData,
                'modeSpecificData' => $modeSpecificData,
                'budgetInfo' => $budgetInfo,
            ]);
        }
        
        // Return Inertia for web requests
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
            $isSuperadmin = $currentApprover->id_role === '5af56935b011a' && $currentApprover->status === 'A';
            
            if ($isSuperadmin) {
                // Superadmin can approve any pending level - approve the next pending level
                $pendingFlows = $purchaseRequisition->approvalFlows()
                    ->where('status', 'PENDING')
                    ->orderBy('approval_level')
                    ->get();
                
                if ($pendingFlows->isEmpty()) {
                    return back()->withErrors(['error' => 'No pending approvals found.']);
                }
                
                // Approve the next pending level
                $nextFlow = $pendingFlows->first();
                $nextFlow->update([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'approver_id' => $currentApprover->id, // Update approver_id to superadmin
                ]);
            } else {
                // Regular users: Update the approval flow for current approver
                $currentApprovalFlow = $purchaseRequisition->approvalFlows()
                    ->where('approver_id', $currentApprover->id)
                    ->where('status', 'PENDING')
                    ->first();
                
                if (!$currentApprovalFlow) {
                    return back()->withErrors(['error' => 'You are not authorized to approve this purchase requisition.']);
                }
                
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
            $isSuperadmin = $currentApprover->id_role === '5af56935b011a' && $currentApprover->status === 'A';
            
            if ($isSuperadmin) {
                // Superadmin can reject any pending level - reject the next pending level
                $pendingFlows = $purchaseRequisition->approvalFlows()
                    ->where('status', 'PENDING')
                    ->orderBy('approval_level')
                    ->get();
                
                if ($pendingFlows->isEmpty()) {
                    return back()->withErrors(['error' => 'No pending approvals found.']);
                }
                
                // Reject the next pending level
                $nextFlow = $pendingFlows->first();
                $nextFlow->update([
                    'status' => 'REJECTED',
                    'rejected_at' => now(),
                    'comments' => $validated['rejection_reason'],
                    'approver_id' => $currentApprover->id, // Update approver_id to superadmin
                ]);
            } else {
                // Regular users: Update the approval flow for current approver
                $currentApprovalFlow = $purchaseRequisition->approvalFlows()
                    ->where('approver_id', $currentApprover->id)
                    ->where('status', 'PENDING')
                    ->first();
                
                if (!$currentApprovalFlow) {
                    return back()->withErrors(['error' => 'You are not authorized to reject this purchase requisition.']);
                }
                
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
            'attachment' => 'nullable|file|max:10240', // Max 10MB
        ]);

        try {
            $commentData = [
                'user_id' => auth()->id(),
                'comment' => $validated['comment'],
                'is_internal' => $validated['is_internal'] ?? false,
            ];

            // Handle attachment upload
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $originalName = $file->getClientOriginalName();
                $fileName = time() . '_' . $originalName;
                $filePath = $file->storeAs('purchase_requisitions/comments', $fileName, 'public');
                $fileSize = $file->getSize();
                $mimeType = $file->getMimeType();

                $commentData['attachment_path'] = $filePath;
                $commentData['attachment_name'] = $originalName;
                $commentData['attachment_size'] = $fileSize;
                $commentData['attachment_mime_type'] = $mimeType;
            }

            $comment = $purchaseRequisition->comments()->create($commentData);
            $comment->load('user');
            
            // Send notifications to creator and approvers (excluding comment author)
            $this->sendCommentNotifications($purchaseRequisition, $comment);
            
            // Return JSON for API requests
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Comment added successfully!',
                    'comment' => $comment
                ]);
            }
            
            return back()->with('success', 'Comment added successfully!');
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to add comment: ' . $e->getMessage()
                ], 500);
            }
            return back()->withErrors(['error' => 'Failed to add comment: ' . $e->getMessage()]);
        }
    }

    /**
     * Send notifications for new comment to creator and approvers
     */
    private function sendCommentNotifications(PurchaseRequisition $purchaseRequisition, $comment)
    {
        try {
            $commenter = auth()->user();
            $commenterId = auth()->id();
            
            // Get creator
            $creator = DB::table('users')->where('id', $purchaseRequisition->created_by)->first();
            
            // Get all approvers from approval flows
            $approvers = DB::table('purchase_requisition_approval_flows as praf')
                ->join('users', 'praf.approver_id', '=', 'users.id')
                ->where('praf.purchase_requisition_id', $purchaseRequisition->id)
                ->select('users.id', 'users.nama_lengkap')
                ->distinct()
                ->get();
            
            // Collect user IDs to notify (creator + approvers, excluding commenter)
            $notifyUserIds = collect();
            
            // Add creator if exists and not the commenter
            if ($creator && $creator->id != $commenterId) {
                $notifyUserIds->push($creator->id);
            }
            
            // Add approvers if not the commenter
            foreach ($approvers as $approver) {
                if ($approver->id != $commenterId) {
                    $notifyUserIds->push($approver->id);
                }
            }
            
            // Remove duplicates
            $notifyUserIds = $notifyUserIds->unique();
            
            if ($notifyUserIds->isEmpty()) {
                return;
            }
            
            // Prepare message
            $commentPreview = strlen($comment->comment) > 100 
                ? substr($comment->comment, 0, 100) . '...' 
                : $comment->comment;
            
            $message = "Ada komentar baru pada Purchase Requisition:\n\n";
            $message .= "PR Number: {$purchaseRequisition->pr_number}\n";
            $message .= "Title: {$purchaseRequisition->title}\n";
            $message .= "Komentar: {$commentPreview}\n";
            $message .= "Dari: {$commenter->nama_lengkap}";
            
            if ($comment->is_internal) {
                $message .= "\n\n[Komentar Internal]";
            }
            
            // Send notifications
            foreach ($notifyUserIds as $userId) {
                DB::table('notifications')->insert([
                    'user_id' => $userId,
                    'task_id' => $purchaseRequisition->id,
                    'type' => 'purchase_requisition_comment',
                    'message' => $message,
                    'url' => config('app.url') . '/purchase-requisitions/' . $purchaseRequisition->id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            \Log::info('Comment notifications sent', [
                'purchase_requisition_id' => $purchaseRequisition->id,
                'comment_id' => $comment->id,
                'commenter_id' => $commenterId,
                'notified_users_count' => $notifyUserIds->count()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to send comment notifications', [
                'purchase_requisition_id' => $purchaseRequisition->id,
                'comment_id' => $comment->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Don't throw exception, just log the error
        }
    }

    /**
     * Get comments for purchase requisition
     */
    public function getComments(PurchaseRequisition $purchaseRequisition)
    {
        $comments = $purchaseRequisition->comments()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        // Mark as read by creating history (only if not already exists for today)
        $userId = auth()->id();
        if ($userId) {
            $today = now()->startOfDay();
            $existingHistory = DB::table('purchase_requisition_history')
                ->where('purchase_requisition_id', $purchaseRequisition->id)
                ->where('user_id', $userId)
                ->where('action', 'viewed')
                ->where('created_at', '>=', $today)
                ->first();
            
            if (!$existingHistory) {
                DB::table('purchase_requisition_history')->insert([
                    'purchase_requisition_id' => $purchaseRequisition->id,
                    'user_id' => $userId,
                    'action' => 'viewed',
                    'description' => 'Viewed comments',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Update existing history timestamp
                DB::table('purchase_requisition_history')
                    ->where('id', $existingHistory->id)
                    ->update([
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
    }

    /**
     * Update comment
     */
    public function updateComment(Request $request, PurchaseRequisition $purchaseRequisition, $commentId)
    {
        $comment = \App\Models\PurchaseRequisitionComment::findOrFail($commentId);

        // Check if comment belongs to this PR
        if ($comment->purchase_requisition_id !== $purchaseRequisition->id) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found for this purchase requisition',
            ], 404);
        }

        // Check if user can edit this comment (only the author)
        if ($comment->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own comments',
            ], 403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
            'is_internal' => 'boolean',
        ]);

        try {
            $comment->update([
                'comment' => $validated['comment'],
                'is_internal' => $validated['is_internal'] ?? $comment->is_internal,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'data' => $comment->load('user'),
            ]);
        } catch (\Exception $e) {
            \Log::error('Update comment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete comment
     */
    public function deleteComment(PurchaseRequisition $purchaseRequisition, $commentId)
    {
        $comment = \App\Models\PurchaseRequisitionComment::findOrFail($commentId);

        // Check if comment belongs to this PR
        if ($comment->purchase_requisition_id !== $purchaseRequisition->id) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found for this purchase requisition',
            ], 404);
        }

        // Check if user can delete this comment (only the author)
        if ($comment->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own comments',
            ], 403);
        }

        try {
            // Delete attachment file if exists
            if ($comment->attachment_path) {
                $filePath = storage_path('app/public/' . $comment->attachment_path);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete comment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment: ' . $e->getMessage(),
            ], 500);
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
            if (!$currentUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: User not authenticated',
                    'purchase_requisitions' => []
                ], 401);
            }
            
            $isSuperadmin = $currentUser->id_role === '5af56935b011a' && $currentUser->status === 'A';
            
            if ($isSuperadmin) {
                // Superadmin can see all pending approvals
                $pendingApprovals = PurchaseRequisition::where('status', 'SUBMITTED')
                    ->whereHas('approvalFlows', function($query) {
                        $query->where('status', 'PENDING');
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
            } else {
                // Regular users: Get PRs where current user is the next approver in line
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
                $pendingApprovals = $pendingApprovals->filter(function($pr) use ($currentUser) {
                    $pendingFlows = $pr->approvalFlows->where('status', 'PENDING');
                    if ($pendingFlows->isEmpty()) return false;
                    
                    $nextApprover = $pendingFlows->sortBy('approval_level')->first();
                    return $nextApprover->approver_id === $currentUser->id;
                });
            }

            // Add approver_name and unread_comments_count to each PR
            $userId = auth()->id();
            $pendingApprovals = $pendingApprovals->map(function($pr) use ($userId) {
                $pendingFlows = $pr->approvalFlows->where('status', 'PENDING');
                if ($pendingFlows->isEmpty()) {
                    $pr->approver_name = null;
                } else {
                    $nextApprover = $pendingFlows->sortBy('approval_level')->first();
                    $pr->approver_name = $nextApprover->approver->nama_lengkap ?? null;
                }
                
                // Calculate unread comments count
                $pr->unread_comments_count = 0;
                if ($userId) {
                    $lastView = DB::table('purchase_requisition_history')
                        ->where('purchase_requisition_id', $pr->id)
                        ->where('user_id', $userId)
                        ->where('action', 'viewed')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    $lastViewTime = $lastView ? $lastView->created_at : null;
                    if ($lastViewTime) {
                        $pr->unread_comments_count = DB::table('purchase_requisition_comments')
                            ->where('purchase_requisition_id', $pr->id)
                            ->where('user_id', '!=', $userId)
                            ->where('created_at', '>', $lastViewTime)
                            ->count();
                    } else {
                        $pr->unread_comments_count = DB::table('purchase_requisition_comments')
                            ->where('purchase_requisition_id', $pr->id)
                            ->where('user_id', '!=', $userId)
                            ->count();
                    }
                }
                
                return $pr;
            });

            return response()->json([
                'success' => true,
                'purchase_requisitions' => $pendingApprovals->values()
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
     * Get Payment Tracker - List of approved payments by approver
     */
    public function getPaymentTracker(Request $request)
    {
        try {
            $currentUser = auth()->user();
            $isSuperadmin = $currentUser->id_role === '5af56935b011a' && $currentUser->status === 'A';
            
            $search = $request->get('search', '');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);
            
            // Build query for approved approval flows
            $query = DB::table('purchase_requisition_approval_flows as praf')
                ->join('purchase_requisitions as pr', 'praf.purchase_requisition_id', '=', 'pr.id')
                ->join('users as approver', 'praf.approver_id', '=', 'approver.id')
                ->leftJoin('tbl_data_divisi as division', 'pr.division_id', '=', 'division.id')
                ->leftJoin('tbl_data_outlet as outlet', 'pr.outlet_id', '=', 'outlet.id_outlet')
                ->leftJoin('purchase_requisition_categories as category', 'pr.category_id', '=', 'category.id')
                ->leftJoin('users as creator', 'pr.created_by', '=', 'creator.id')
                ->where('praf.status', 'APPROVED')
                ->whereNotNull('praf.approved_at')
                ->select([
                    'pr.id',
                    'pr.pr_number',
                    'pr.title',
                    'pr.description',
                    'pr.amount',
                    'pr.status',
                    'pr.mode',
                    'pr.date',
                    'pr.created_at',
                    'pr.created_by',
                    'praf.approved_at',
                    'praf.approval_level',
                    'praf.comments as approval_comments',
                    'approver.id as approver_id',
                    'approver.nama_lengkap as approver_name',
                    'approver.email as approver_email',
                    'approver.avatar as approver_avatar',
                    'division.nama_divisi as division_name',
                    'outlet.nama_outlet as outlet_name',
                    'category.name as category_name',
                    'creator.nama_lengkap as creator_name',
                    'creator.email as creator_email',
                    'creator.avatar as creator_avatar',
                ]);
            
            // Filter by approver - superadmin can see all, regular users only their own
            if (!$isSuperadmin) {
                $query->where('praf.approver_id', $currentUser->id);
            }
            
            // Filter by date range
            if ($fromDate) {
                $query->whereDate('praf.approved_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate('praf.approved_at', '<=', $toDate);
            }
            
            // Search in all columns
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('pr.pr_number', 'like', '%' . $search . '%')
                      ->orWhere('pr.title', 'like', '%' . $search . '%')
                      ->orWhere('pr.description', 'like', '%' . $search . '%')
                      ->orWhere('pr.amount', 'like', '%' . $search . '%')
                      ->orWhere('division.nama_divisi', 'like', '%' . $search . '%')
                      ->orWhere('outlet.nama_outlet', 'like', '%' . $search . '%')
                      ->orWhere('category.name', 'like', '%' . $search . '%')
                      ->orWhere('creator.nama_lengkap', 'like', '%' . $search . '%')
                      ->orWhere('creator.email', 'like', '%' . $search . '%')
                      ->orWhere('approver.nama_lengkap', 'like', '%' . $search . '%')
                      ->orWhere('approver.email', 'like', '%' . $search . '%')
                      ->orWhere('praf.comments', 'like', '%' . $search . '%');
                });
            }
            
            // Get total count before pagination
            $total = $query->count();
            
            // Apply pagination
            $results = $query->orderBy('praf.approved_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            // Format the results
            $formattedResults = $results->map(function($item) {
                return [
                    'id' => $item->id,
                    'pr_number' => $item->pr_number,
                    'title' => $item->title,
                    'description' => $item->description,
                    'amount' => $item->amount,
                    'status' => $item->status,
                    'mode' => $item->mode,
                    'date' => $item->date,
                    'created_at' => $item->created_at,
                    'approved_at' => $item->approved_at,
                    'approval_level' => $item->approval_level,
                    'approval_comments' => $item->approval_comments,
                    'approver' => [
                        'id' => $item->approver_id,
                        'name' => $item->approver_name,
                        'email' => $item->approver_email,
                        'avatar' => $item->approver_avatar,
                    ],
                    'division' => $item->division_name,
                    'outlet' => $item->outlet_name,
                    'category' => $item->category_name,
                    'creator' => [
                        'name' => $item->creator_name,
                        'email' => $item->creator_email,
                        'avatar' => $item->creator_avatar,
                    ],
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedResults,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => $total,
                    'last_page' => (int)ceil($total / $perPage),
                    'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                    'to' => min($page * $perPage, $total),
                ],
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error getting payment tracker', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get payment tracker data'
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
                'approvalFlows.approver.jabatan',
                'comments.user'
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
                
                // Ensure travel_agenda is always set, even if empty
                if (empty($modeSpecificData['travel_agenda'])) {
                    $modeSpecificData['travel_agenda'] = '';
                }
                
                // Ensure travel_notes is always set
                if (!isset($modeSpecificData['travel_notes'])) {
                    $modeSpecificData['travel_notes'] = '';
                }
                
                // Ensure travel_outlets is always an array
                if (!isset($modeSpecificData['travel_outlets'])) {
                    $modeSpecificData['travel_outlets'] = [];
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
                        // GLOBAL BUDGET: Calculate across all outlets
                        // Use the same logic as getBudgetInfo method for consistency
                        $categoryBudget = $category->budget_limit;
                        
                        // Get PR IDs in this category for the month (BUDGET IS MONTHLY - filter by month)
                        // Support both old structure (category at PR level) and new structure (category at items level)
                        $prIds = DB::table('purchase_requisitions as pr')
                            ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                            ->where(function($q) use ($category) {
                                $q->where('pr.category_id', $category->id)
                                  ->orWhere('pri.category_id', $category->id);
                            })
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                            ->where('pr.is_held', false) // Exclude held PRs
                            ->distinct()
                            ->pluck('pr.id')
                            ->toArray();
                        
                        // Get PO IDs linked to PRs in this category
                        // IMPORTANT: Untuk PR Ops, filter PO items berdasarkan category_id di purchase_requisition_items
                        // Untuk global budget, sum semua PO items dari semua outlets yang sesuai category
                        $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
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
                            ->where('poi.source_type', 'purchase_requisition_ops')
                            ->where(function($q) use ($category) {
                                // Old structure: category at PR level
                                $q->where('pr.category_id', $category->id)
                                  // New structure: category at items level (PENTING: filter by pri.category_id)
                                  ->orWhere('pri.category_id', $category->id);
                            })
                            ->distinct()
                            ->pluck('poi.purchase_order_ops_id')
                            ->toArray();
                        
                        // Get paid amount from non_food_payments (BUDGET IS MONTHLY - filter by payment_date)
                        // IMPORTANT: Only count NFP with status 'paid' (not 'approved')
                        // Untuk global budget, sum semua payment dari semua PO yang sesuai category (semua outlets)
                        $paidAmountFromPo = DB::table('non_food_payments as nfp')
                            ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                            ->where('nfp.status', 'paid') // Only 'paid' status, not 'approved'
                            ->where('nfp.status', '!=', 'cancelled')
                            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                            ->sum('nfp.amount');
                        
                        // Get Retail Non Food amounts (BUDGET IS MONTHLY - filter by transaction_date)
                        $retailNonFoodApproved = RetailNonFood::where('category_budget_id', $category->id)
                            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                            ->where('status', 'approved')
                            ->sum('total_amount');
                        
                        // Get unpaid PR data
                        // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO dan belum jadi NFP
                        // Support both old structure (category at PR level) and new structure (category at items level)
                        $prIdsForUnpaid = DB::table('purchase_requisitions as pr')
                            ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                            ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                $join->on('pr.id', '=', 'poi.source_id')
                                     ->where('poi.source_type', '=', 'purchase_requisition_ops');
                            })
                            ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                            ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
                            ->where(function($q) use ($category) {
                                $q->where('pr.category_id', $category->id)
                                  ->orWhere('pri.category_id', $category->id);
                            })
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->whereIn('pr.status', ['SUBMITTED', 'APPROVED']) // Only SUBMITTED and APPROVED
                            ->where('pr.is_held', false) // Exclude held PRs
                            ->whereNull('poo.id') // PR yang belum jadi PO (belum ada PO)
                            ->whereNull('nfp.id') // PR yang belum jadi NFP (baik langsung maupun melalui PO)
                            ->distinct()
                            ->pluck('pr.id')
                            ->toArray();
                        
                        $allPrs = PurchaseRequisition::whereIn('id', $prIdsForUnpaid)->get();
                        
                        // Get PO totals per PR - untuk cek apakah PR sudah jadi PO
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
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->where('pr.is_held', false)
                            ->where('poi.source_type', 'purchase_requisition_ops')
                            ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                            ->groupBy('pr.id')
                            ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                            ->pluck('po_total', 'pr_id')
                            ->toArray();
                        
                        // Calculate unpaid for each PR
                        // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO
                        // PR yang sudah difilter di query (belum jadi PO, status SUBMITTED/APPROVED)
                        // IMPORTANT: Untuk GLOBAL budget, sum semua items dari semua outlets (tidak filter outlet)
                        // Untuk PR Ops, hitung berdasarkan items.subtotal di category ini (sum semua outlets)
                        $prUnpaidAmount = 0;
                        foreach ($allPrs as $pr) {
                            // Untuk PR Ops: hitung berdasarkan items di category ini (sum semua outlets)
                            if (in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
                                // Hitung subtotal items di category ini (semua outlets)
                                $categoryItemsSubtotal = DB::table('purchase_requisition_items')
                                    ->where('purchase_requisition_id', $pr->id)
                                    ->where('category_id', $category->id)
                                    ->sum('subtotal');
                                $prUnpaidAmount += $categoryItemsSubtotal ?? 0;
                            } else {
                                // Untuk mode lain: gunakan PR amount
                                $prUnpaidAmount += $pr->amount;
                            }
                        }
                        
                        // Get unpaid PO data
                        // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
                        $allPOs = DB::table('purchase_order_ops as poo')
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
                            ->leftJoin('non_food_payments as nfp', 'poo.id', '=', 'nfp.purchase_order_ops_id')
                            ->where(function($q) use ($category) {
                                $q->where('pr.category_id', $category->id)
                                  ->orWhere('pri.category_id', $category->id);
                            })
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->where('pr.is_held', false)
                            ->where('poi.source_type', 'purchase_requisition_ops')
                            ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                            ->whereNull('nfp.id') // PO yang belum jadi NFP (belum ada NFP)
                            ->groupBy('poo.id')
                            ->select('poo.id as po_id', DB::raw('SUM(poi.total) as po_total'))
                            ->get();
                        
                        // Calculate unpaid for each PO
                        // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
                        // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                        $poUnpaidAmount = 0;
                        foreach ($allPOs as $po) {
                            // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                            $poUnpaidAmount += $po->po_total ?? 0;
                        }
                        
                        // Calculate PO created amount (all PO items created from PRs in this category)
                        // IMPORTANT: Untuk PR Ops, hitung berdasarkan PO items yang sesuai category (sum semua outlets)
                        // This includes both paid and unpaid PO items
                        $poCreatedAmount = DB::table('purchase_order_ops_items as poi')
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
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->where('pr.is_held', false)
                            ->where('poi.source_type', 'purchase_requisition_ops')
                            ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                            ->sum('poi.total');
                        
                        // Calculate unpaid NFP
                        // NEW LOGIC: NFP unpaid = NFP dengan status pending dan approved
                        // Mencakup NFP yang langsung dari PR (tanpa PO) dan NFP yang melalui PO
                        // Case 1: NFP langsung dari PR
                        $nfpUnpaidFromPr = DB::table('non_food_payments as nfp')
                            ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                            ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                            ->where(function($q) use ($category) {
                                $q->where('pr.category_id', $category->id)
                                  ->orWhere('pri.category_id', $category->id);
                            })
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->where('pr.is_held', false)
                            ->whereNull('nfp.purchase_order_ops_id') // NFP langsung dari PR (tanpa PO)
                            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                            ->whereIn('nfp.status', ['pending', 'approved'])
                            ->where('nfp.status', '!=', 'cancelled')
                            ->sum('nfp.amount');
                        
                        // Case 2: NFP melalui PO
                        $nfpUnpaidFromPo = DB::table('non_food_payments as nfp')
                            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                            ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                                     ->where('poi.source_type', '=', 'purchase_requisition_ops');
                            })
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
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->where('pr.is_held', false)
                            ->whereNotNull('nfp.purchase_order_ops_id') // NFP melalui PO
                            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                            ->whereIn('nfp.status', ['pending', 'approved'])
                            ->where('nfp.status', '!=', 'cancelled')
                            ->sum('nfp.amount');
                        
                        $nfpUnpaidAmount = ($nfpUnpaidFromPr ?? 0) + ($nfpUnpaidFromPo ?? 0);
                        
                        // Get NFP breakdown by status (submitted, approved, paid)
                        // NFP Submitted
                        $nfpSubmittedFromPr = DB::table('non_food_payments as nfp')
                            ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                            ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                            ->where(function($q) use ($category) {
                                $q->where('pr.category_id', $category->id)
                                  ->orWhere('pri.category_id', $category->id);
                            })
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->where('pr.is_held', false)
                            ->whereNull('nfp.purchase_order_ops_id')
                            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                            ->where('nfp.status', 'submitted')
                            ->where('nfp.status', '!=', 'cancelled')
                            ->sum('nfp.amount');
                        
                        $nfpSubmittedFromPo = DB::table('non_food_payments as nfp')
                            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                            ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                                     ->where('poi.source_type', '=', 'purchase_requisition_ops');
                            })
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
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->where('pr.is_held', false)
                            ->whereNotNull('nfp.purchase_order_ops_id')
                            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                            ->where('nfp.status', 'submitted')
                            ->where('nfp.status', '!=', 'cancelled')
                            ->sum('nfp.amount');
                        
                        $nfpSubmittedAmount = ($nfpSubmittedFromPr ?? 0) + ($nfpSubmittedFromPo ?? 0);
                        
                        // NFP Approved (status = 'approved', belum paid)
                        $nfpApprovedFromPr = DB::table('non_food_payments as nfp')
                            ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                            ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                            ->where(function($q) use ($category) {
                                $q->where('pr.category_id', $category->id)
                                  ->orWhere('pri.category_id', $category->id);
                            })
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->where('pr.is_held', false)
                            ->whereNull('nfp.purchase_order_ops_id')
                            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                            ->where('nfp.status', 'approved')
                            ->where('nfp.status', '!=', 'cancelled')
                            ->sum('nfp.amount');
                        
                        $nfpApprovedFromPo = DB::table('non_food_payments as nfp')
                            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                            ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                                     ->where('poi.source_type', '=', 'purchase_requisition_ops');
                            })
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
                            ->whereYear('pr.created_at', $currentYear)
                            ->whereMonth('pr.created_at', $currentMonth)
                            ->where('pr.is_held', false)
                            ->whereNotNull('nfp.purchase_order_ops_id')
                            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                            ->where('nfp.status', 'approved')
                            ->where('nfp.status', '!=', 'cancelled')
                            ->sum('nfp.amount');
                        
                        $nfpApprovedAmount = ($nfpApprovedFromPr ?? 0) + ($nfpApprovedFromPo ?? 0);
                        
                        // NFP Paid (already calculated in paidAmountFromPo)
                        $nfpPaidAmount = $paidAmountFromPo;
                        
                        // Total unpaid = PR unpaid + PO unpaid + NFP unpaid
                        $unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount;
                        
                        // Total used = Paid (from non_food_payments 'paid' + RNF 'approved') + Unpaid (PR + PO + NFP 'approved')
                        $paidAmount = $paidAmountFromPo + $retailNonFoodApproved;
                        $categoryUsedAmount = $paidAmount + $unpaidAmount;
                        
                        // Calculate approved and unapproved amounts for global budget
                        // IMPORTANT: Untuk PR Ops, hitung berdasarkan items di category ini (sum semua outlets)
                        $approvedAmount = 0;
                        $unapprovedAmount = 0;
                        $prsForStatus = PurchaseRequisition::whereIn('id', $prIds)->get();
                        foreach ($prsForStatus as $pr) {
                            // Untuk PR Ops: hitung berdasarkan items di category ini (sum semua outlets)
                            if (in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
                                $categoryItemsSubtotal = DB::table('purchase_requisition_items')
                                    ->where('purchase_requisition_id', $pr->id)
                                    ->where('category_id', $category->id)
                                    ->sum('subtotal');
                                $prAmount = $categoryItemsSubtotal ?? 0;
                            } else {
                                $prAmount = $pr->amount;
                            }
                            
                            if (in_array($pr->status, ['APPROVED', 'PROCESSED', 'COMPLETED'])) {
                                $approvedAmount += $prAmount;
                            }
                            if ($pr->status === 'SUBMITTED') {
                                $unapprovedAmount += $prAmount;
                            }
                        }
                        
                        // Calculate real remaining budget (budget - used - current amount)
                        // Current amount = 0 for approval modal (no input)
                        $currentAmount = 0;
                        $totalWithCurrent = $categoryUsedAmount + $currentAmount;
                        $realRemainingBudget = $categoryBudget - $totalWithCurrent;
                        
                        $budgetInfo = [
                            'budget_type' => 'GLOBAL',
                            'current_year' => $currentYear,
                            'current_month' => $currentMonth,
                            'category_budget' => $categoryBudget,
                            'category_used_amount' => $categoryUsedAmount,
                            'category_remaining_amount' => $categoryBudget - $categoryUsedAmount,
                            'paid_amount' => $paidAmount,
                            'unpaid_amount' => $unpaidAmount,
                            'approved_amount' => $approvedAmount,
                            'unapproved_amount' => $unapprovedAmount,
                            'po_created_amount' => $poCreatedAmount,
                            'real_remaining_budget' => $realRemainingBudget,
                            'remaining_after_current' => $realRemainingBudget,
                            'retail_non_food_approved' => $retailNonFoodApproved,
                            'breakdown' => [
                                'pr_unpaid' => $prUnpaidAmount, // PR Submitted & Approved yang belum dibuat PO
                                'po_unpaid' => $poUnpaidAmount, // PO Submitted & Approved yang belum dibuat NFP
                                'nfp_submitted' => $nfpSubmittedAmount, // NFP Submitted
                                'nfp_approved' => $nfpApprovedAmount, // NFP Approved (unpaid)
                                'nfp_paid' => $nfpPaidAmount, // NFP Paid
                                'retail_non_food' => $retailNonFoodApproved, // Retail Non Food Approved
                            ],
                        ];
                        
                        // Calculate outlet used amount if PR has outlet_id (regardless of budget type)
                        // Used amount = Paid (from non_food_payments) + Unpaid PR + Retail Non Food
                        // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
                        if ($outletIdForBudget) {
                            // Get PR IDs for this outlet
                            $outletPrIds = DB::table('purchase_requisitions as pr')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                                ->where('pr.is_held', false) // Exclude held PRs
                                ->distinct()
                                ->pluck('pr.id')
                                ->toArray();
                            
                            // Get PO IDs linked to PRs in this outlet
                            // IMPORTANT: Untuk PR Ops, filter PO items berdasarkan outlet_id di purchase_requisition_items
                            $outletPoIds = DB::table('purchase_order_ops_items as poi')
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
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level (PENTING: filter by pri.outlet_id)
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->distinct()
                                ->pluck('poi.purchase_order_ops_id')
                                ->toArray();
                            
                            // Get paid amount from non_food_payments for this outlet (BUDGET IS MONTHLY - filter by payment_date)
                            // IMPORTANT: Untuk PR Ops, hanya hitung payment untuk items di outlet ini
                            // Karena satu PO bisa punya items dari multiple outlets, kita perlu filter berdasarkan PO items yang sesuai outlet
                            $outletPaidAmount = 0;
                            if (!empty($outletPoIds)) {
                                // Untuk setiap PO, hitung hanya payment untuk items di outlet ini
                                foreach ($outletPoIds as $poId) {
                                    // Get PO items yang berasal dari PR items di outlet ini
                                    $outletPoItemIds = DB::table('purchase_order_ops_items as poi')
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
                                        ->where('poi.purchase_order_ops_id', $poId)
                                        ->where('poi.source_type', 'purchase_requisition_ops')
                                        ->where(function($q) use ($category, $outletIdForBudget) {
                                            $q->where(function($q2) use ($category, $outletIdForBudget) {
                                                $q2->where('pr.category_id', $category->id)
                                                   ->where('pr.outlet_id', $outletIdForBudget);
                                            })
                                            ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                                $q2->where('pri.category_id', $category->id)
                                                   ->where('pri.outlet_id', $outletIdForBudget);
                                            });
                                        })
                                        ->pluck('poi.total')
                                        ->toArray();
                                    
                                    // Get payment untuk PO ini
                                    $poPayment = DB::table('non_food_payments')
                                        ->where('purchase_order_ops_id', $poId)
                                        ->where('status', 'paid')
                                        ->where('status', '!=', 'cancelled')
                                        ->whereBetween('payment_date', [$dateFrom, $dateTo])
                                        ->first();
                                    
                                    if ($poPayment) {
                                        // Hitung proporsi: total PO items di outlet ini / total PO items
                                        $poTotalItems = DB::table('purchase_order_ops_items')
                                            ->where('purchase_order_ops_id', $poId)
                                            ->sum('total');
                                        
                                        if ($poTotalItems > 0) {
                                            $outletPoItemsTotal = array_sum($outletPoItemIds);
                                            $proportion = $outletPoItemsTotal / $poTotalItems;
                                            $outletPaidAmount += $poPayment->amount * $proportion;
                                        }
                                    }
                                }
                            }
                            
                            // Get unpaid PR data for this outlet
                            // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO dan belum jadi NFP
                            // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
                            $outletPrIdsForUnpaid = DB::table('purchase_requisitions as pr')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                    $join->on('pr.id', '=', 'poi.source_id')
                                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                                })
                                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED']) // Only SUBMITTED and APPROVED
                                ->where('pr.is_held', false) // Exclude held PRs
                                ->whereNull('poo.id') // PR yang belum jadi PO (belum ada PO)
                                ->whereNull('nfp.id') // PR yang belum jadi NFP (baik langsung maupun melalui PO)
                                ->distinct()
                                ->pluck('pr.id')
                                ->toArray();
                            
                            $outletAllPrs = PurchaseRequisition::whereIn('id', $outletPrIdsForUnpaid)->get();
                            
                            // Get PO totals per PR - untuk cek apakah PR sudah jadi PO
                            $outletPoTotalsByPr = DB::table('purchase_order_ops_items as poi')
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
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                                ->groupBy('pr.id')
                                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                                ->pluck('po_total', 'pr_id')
                                ->toArray();
                            
                            // Calculate unpaid for each PR
                            // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO
                            // PR yang sudah difilter di query (belum jadi PO, status SUBMITTED/APPROVED)
                            // IMPORTANT: Untuk PR Ops (mode pr_ops/purchase_payment), hitung berdasarkan items di outlet tersebut
                            // Untuk mode lain, gunakan PR amount
                            $outletPrUnpaidAmount = 0;
                            foreach ($outletAllPrs as $pr) {
                                // Untuk PR Ops: hitung berdasarkan items di outlet tersebut
                                if (in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
                                    // Hitung subtotal items di outlet ini
                                    $outletItemsSubtotal = DB::table('purchase_requisition_items')
                                        ->where('purchase_requisition_id', $pr->id)
                                        ->where('outlet_id', $outletIdForBudget)
                                        ->where('category_id', $category->id)
                                        ->sum('subtotal');
                                    $outletPrUnpaidAmount += $outletItemsSubtotal ?? 0;
                                } else {
                                    // Untuk mode lain: gunakan PR amount
                                    $outletPrUnpaidAmount += $pr->amount;
                                }
                            }
                            
                            // Get unpaid PO data for this outlet
                            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
                            // IMPORTANT: Untuk PR Ops, filter PO items berdasarkan outlet_id di purchase_requisition_items
                            $outletAllPOs = DB::table('purchase_order_ops as poo')
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
                                ->leftJoin('non_food_payments as nfp', 'poo.id', '=', 'nfp.purchase_order_ops_id')
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level (PENTING: filter by pri.outlet_id)
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                                ->whereNull('nfp.id') // PO yang belum jadi NFP (belum ada NFP)
                                ->groupBy('poo.id')
                                ->select('poo.id as po_id', DB::raw('SUM(poi.total) as po_total'))
                                ->get();
                            
                            // Calculate unpaid for each PO
                            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
                            // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                            // IMPORTANT: Query sudah filter by outlet_id di pri, jadi po_total sudah benar per outlet
                            $outletPoUnpaidAmount = 0;
                            foreach ($outletAllPOs as $po) {
                                // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                                // po_total sudah benar karena sudah di-filter by outlet_id di pri
                                $outletPoUnpaidAmount += $po->po_total ?? 0;
                            }
                            
                            // Calculate unpaid NFP
                            // NEW LOGIC: NFP unpaid = NFP dengan status pending dan approved
                            // Mencakup NFP yang langsung dari PR (tanpa PO) dan NFP yang melalui PO
                            // Case 1: NFP langsung dari PR untuk outlet ini
                            $outletNfpUnpaidFromPr = DB::table('non_food_payments as nfp')
                                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->whereNull('nfp.purchase_order_ops_id') // NFP langsung dari PR (tanpa PO)
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->whereIn('nfp.status', ['pending', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            // Case 2: NFP melalui PO untuk outlet ini
                            $outletNfpUnpaidFromPo = DB::table('non_food_payments as nfp')
                                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                                })
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
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->whereNotNull('nfp.purchase_order_ops_id') // NFP melalui PO
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->whereIn('nfp.status', ['pending', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            $outletNfpUnpaidAmount = ($outletNfpUnpaidFromPr ?? 0) + ($outletNfpUnpaidFromPo ?? 0);
                            
                            // Total unpaid = PR unpaid + PO unpaid + NFP unpaid
                            $outletUnpaidAmount = $outletPrUnpaidAmount + $outletPoUnpaidAmount + $outletNfpUnpaidAmount;
                            
                            // Add Retail Non Food for this outlet
                            // Approved = paid
                            $outletRetailNonFoodApproved = RetailNonFood::where('category_budget_id', $category->id)
                                ->where('outlet_id', $outletIdForBudget)
                                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                                ->where('status', 'approved')
                                ->sum('total_amount');
                            
                            // Total used = Paid (from non_food_payments 'paid' + RNF 'approved') + Unpaid (PR + PO + NFP 'approved')
                            $outletUsedAmount = $outletPaidAmount + $outletRetailNonFoodApproved + $outletUnpaidAmount;
                            
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
                            // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
                            // Get PR IDs in this category and outlet for the month
                            $prIds = DB::table('purchase_requisitions as pr')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false) // Exclude held PRs
                                ->distinct()
                                ->pluck('pr.id')
                                ->toArray();
                            
                            // Calculate different amounts (using PR IDs from both structures)
                            // IMPORTANT: Untuk PR Ops, hitung berdasarkan items di outlet tersebut, bukan total PR amount
                            $outletPrUsedAmount = 0;
                            $approvedAmount = 0;
                            $unapprovedAmount = 0;
                            
                            $prs = PurchaseRequisition::whereIn('id', $prIds)->get();
                            foreach ($prs as $pr) {
                                // Untuk PR Ops: hitung berdasarkan items di outlet tersebut
                                if (in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
                                    $outletItemsSubtotal = DB::table('purchase_requisition_items')
                                        ->where('purchase_requisition_id', $pr->id)
                                        ->where('outlet_id', $outletIdForBudget)
                                        ->where('category_id', $category->id)
                                        ->sum('subtotal');
                                    $prAmount = $outletItemsSubtotal ?? 0;
                                } else {
                                    // Untuk mode lain: gunakan PR amount
                                    $prAmount = $pr->amount;
                                }
                                
                                if (in_array($pr->status, ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])) {
                                    $outletPrUsedAmount += $prAmount;
                                }
                                if (in_array($pr->status, ['APPROVED', 'PROCESSED', 'COMPLETED'])) {
                                    $approvedAmount += $prAmount;
                                }
                                if ($pr->status === 'SUBMITTED') {
                                    $unapprovedAmount += $prAmount;
                                }
                            }
                            
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
                            
                            // Calculate PO created amount (all PO items created from PRs in this category and outlet)
                            // IMPORTANT: Untuk PR Ops, hitung berdasarkan PO items yang sesuai category dan outlet
                            $poCreatedAmount = DB::table('purchase_order_ops_items as poi')
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
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                                ->sum('poi.total');
                            
                            // Calculate paid and unpaid amounts from non_food_payments (same logic as Opex Report)
                            // Get PO IDs that are linked to PRs in this category and outlet
                            $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poi.source_id', $prIds)
                                ->distinct()
                                ->pluck('poi.purchase_order_ops_id')
                                ->toArray();
                            
                            // Get paid amount from non_food_payments for this outlet (BUDGET IS MONTHLY - filter by payment_date)
                            // IMPORTANT: Only count NFP with status 'paid' (not 'approved')
                            $paidAmountFromPo = DB::table('non_food_payments as nfp')
                                ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                                ->where('nfp.status', 'paid') // Only 'paid' status, not 'approved'
                                ->where('nfp.status', '!=', 'cancelled')
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->sum('nfp.amount');
                            
                            // Get unpaid PR data for this outlet
                            // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO dan belum jadi NFP
                            // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
                            $prIdsForUnpaid = DB::table('purchase_requisitions as pr')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                    $join->on('pr.id', '=', 'poi.source_id')
                                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                                })
                                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED']) // Only SUBMITTED and APPROVED
                                ->where('pr.is_held', false) // Exclude held PRs
                                ->whereNull('poo.id') // PR yang belum jadi PO (belum ada PO)
                                ->whereNull('nfp.id') // PR yang belum jadi NFP (baik langsung maupun melalui PO)
                                ->distinct()
                                ->pluck('pr.id')
                                ->toArray();
                            
                            $allPrs = PurchaseRequisition::whereIn('id', $prIdsForUnpaid)->get();
                            
                            // Get PO totals per PR - untuk cek apakah PR sudah jadi PO
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
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                                ->groupBy('pr.id')
                                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                                ->pluck('po_total', 'pr_id')
                                ->toArray();
                            
                        // Calculate unpaid for each PR
                        // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO
                        // PR yang sudah difilter di query (belum jadi PO, status SUBMITTED/APPROVED)
                        // IMPORTANT: Untuk GLOBAL budget, sum semua items dari semua outlets (tidak filter outlet)
                        // Untuk PR Ops, hitung berdasarkan items.subtotal di category ini (sum semua outlets)
                        $prUnpaidAmount = 0;
                        foreach ($allPrs as $pr) {
                            // Untuk PR Ops: hitung berdasarkan items di category ini (sum semua outlets)
                            if (in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
                                // Hitung subtotal items di category ini (semua outlets)
                                $categoryItemsSubtotal = DB::table('purchase_requisition_items')
                                    ->where('purchase_requisition_id', $pr->id)
                                    ->where('category_id', $category->id)
                                    ->sum('subtotal');
                                $prUnpaidAmount += $categoryItemsSubtotal ?? 0;
                            } else {
                                // Untuk mode lain: gunakan PR amount
                                $prUnpaidAmount += $pr->amount;
                            }
                        }
                            
                            // Get unpaid PO data for this outlet
                            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
                            $allPOs = DB::table('purchase_order_ops as poo')
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
                                ->leftJoin('non_food_payments as nfp', 'poo.id', '=', 'nfp.purchase_order_ops_id')
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                                ->whereNull('nfp.id') // PO yang belum jadi NFP (belum ada NFP)
                                ->groupBy('poo.id')
                                ->select('poo.id as po_id', DB::raw('SUM(poi.total) as po_total'))
                                ->get();
                            
                            // Calculate unpaid for each PO
                            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
                            // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                            $poUnpaidAmount = 0;
                            foreach ($allPOs as $po) {
                                // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                                $poUnpaidAmount += $po->po_total ?? 0;
                            }
                            
                            // Calculate unpaid NFP
                            // NEW LOGIC: NFP unpaid = NFP dengan status pending dan approved
                            // Mencakup NFP yang langsung dari PR (tanpa PO) dan NFP yang melalui PO
                            // Case 1: NFP langsung dari PR untuk outlet ini
                            $nfpUnpaidFromPr = DB::table('non_food_payments as nfp')
                                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->whereNull('nfp.purchase_order_ops_id') // NFP langsung dari PR (tanpa PO)
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->whereIn('nfp.status', ['pending', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            // Case 2: NFP melalui PO untuk outlet ini
                            $nfpUnpaidFromPo = DB::table('non_food_payments as nfp')
                                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                                })
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
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->whereNotNull('nfp.purchase_order_ops_id') // NFP melalui PO
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->whereIn('nfp.status', ['pending', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            $nfpUnpaidAmount = ($nfpUnpaidFromPr ?? 0) + ($nfpUnpaidFromPo ?? 0);
                            
                            // Get NFP breakdown by status (submitted, approved, paid) for this outlet
                            // NFP Submitted
                            $nfpSubmittedFromPr = DB::table('non_food_payments as nfp')
                                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->whereNull('nfp.purchase_order_ops_id')
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->where('nfp.status', 'submitted')
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            $nfpSubmittedFromPo = DB::table('non_food_payments as nfp')
                                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                                })
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
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->whereNotNull('nfp.purchase_order_ops_id')
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->where('nfp.status', 'submitted')
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            $nfpSubmittedAmount = ($nfpSubmittedFromPr ?? 0) + ($nfpSubmittedFromPo ?? 0);
                            
                            // NFP Approved (status = 'approved', belum paid)
                            $nfpApprovedFromPr = DB::table('non_food_payments as nfp')
                                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->whereNull('nfp.purchase_order_ops_id')
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->where('nfp.status', 'approved')
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            $nfpApprovedFromPo = DB::table('non_food_payments as nfp')
                                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                                })
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
                                ->where(function($q) use ($category, $outletIdForBudget) {
                                    $q->where(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletIdForBudget);
                                    })
                                    ->orWhere(function($q2) use ($category, $outletIdForBudget) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletIdForBudget);
                                    });
                                })
                                ->whereYear('pr.created_at', $currentYear)
                                ->whereMonth('pr.created_at', $currentMonth)
                                ->where('pr.is_held', false)
                                ->whereNotNull('nfp.purchase_order_ops_id')
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->where('nfp.status', 'approved')
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            $nfpApprovedAmount = ($nfpApprovedFromPr ?? 0) + ($nfpApprovedFromPo ?? 0);
                            
                            // NFP Paid (already calculated in paidAmountFromPo)
                            $nfpPaidAmount = $paidAmountFromPo;
                            
                            // Total unpaid = PR unpaid + PO unpaid + NFP unpaid
                            $unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount;
                            
                            // Total used = Paid (from non_food_payments 'paid' + RNF 'approved') + Unpaid (PR + PO + NFP 'approved')
                            $paidAmount = $paidAmountFromPo + $outletRetailNonFoodApproved;
                            $outletUsedAmount = $paidAmount + $unpaidAmount;
                            $categoryUsedAmount = $paidAmount + $unpaidAmount;
                            
                            // Calculate real remaining budget (budget - used - current amount)
                            // Current amount = 0 for approval modal (no input)
                            $currentAmount = 0;
                            $totalWithCurrent = $outletUsedAmount + $currentAmount;
                            $realRemainingBudget = $outletBudget->allocated_budget - $totalWithCurrent;
                            
                            $budgetInfo = [
                                'budget_type' => 'PER_OUTLET',
                                'current_year' => $currentYear,
                                'current_month' => $currentMonth,
                                'category_budget' => $category->budget_limit, // Global budget for reference
                                'outlet_budget' => $outletBudget->allocated_budget,
                                'outlet_used_amount' => $outletUsedAmount,
                                'outlet_remaining_amount' => $outletBudget->allocated_budget - $outletUsedAmount,
                                'paid_amount' => $paidAmount,
                                'unpaid_amount' => $unpaidAmount,
                                'approved_amount' => $totalApprovedAmount,
                                'unapproved_amount' => $totalUnapprovedAmount,
                                'po_created_amount' => $poCreatedAmount,
                                'real_remaining_budget' => $realRemainingBudget,
                                'remaining_after_current' => $realRemainingBudget,
                                'retail_non_food_approved' => $outletRetailNonFoodApproved,
                                'outlet_info' => [
                                    'id' => $outletBudget->outlet_id,
                                    'name' => $outletBudget->outlet->nama_outlet ?? 'Unknown Outlet',
                                ],
                                'breakdown' => [
                                    'pr_unpaid' => $prUnpaidAmount, // PR Submitted & Approved yang belum dibuat PO
                                    'po_unpaid' => $poUnpaidAmount, // PO Submitted & Approved yang belum dibuat NFP
                                    'nfp_submitted' => $nfpSubmittedAmount, // NFP Submitted
                                    'nfp_approved' => $nfpApprovedAmount, // NFP Approved (unpaid)
                                    'nfp_paid' => $nfpPaidAmount, // NFP Paid
                                    'retail_non_food' => $outletRetailNonFoodApproved, // Retail Non Food Approved
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
        // Use the same logic as getBudgetInfo method for consistency
        $budgetInfos = [];
        foreach ($purchaseRequisitions as $pr) {
            $budgetInfo = null;
            if ($pr->mode !== 'kasbon' && $pr->category_id) {
                $currentMonth = date('m');
                $currentYear = date('Y');
                
                // Create a request object to call getBudgetInfo method
                $budgetRequest = new Request([
                    'category_id' => $pr->category_id,
                    'outlet_id' => $pr->outlet_id,
                    'current_amount' => 0, // No current amount for print
                    'year' => $currentYear,
                    'month' => $currentMonth,
                ]);
                
                try {
                    $budgetResponse = $this->getBudgetInfo($budgetRequest);
                    $budgetData = json_decode($budgetResponse->getContent(), true);
                    if ($budgetData && $budgetData['success']) {
                        $budgetInfo = $budgetData;
                        $budgetInfo['current_month'] = $currentMonth;
                        $budgetInfo['current_year'] = $currentYear;
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to get budget info for PR ' . $pr->id . ' in print preview: ' . $e->getMessage());
                    $budgetInfo = null;
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
            // Support both old structure (category at PR level) and new structure (category at items level)
            $prIds = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('pr.is_held', false) // Exclude held PRs
                ->distinct()
                ->pluck('pr.id')
                ->toArray();
            
            // Get PO IDs linked to PRs in this category
            // IMPORTANT: Untuk PR Ops, filter PO items berdasarkan category_id di purchase_requisition_items
            // Untuk global budget, sum semua PO items dari semua outlets yang sesuai category
            $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
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
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where(function($q) use ($categoryId) {
                    // Old structure: category at PR level
                    $q->where('pr.category_id', $categoryId)
                      // New structure: category at items level (PENTING: filter by pri.category_id)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->distinct()
                ->pluck('poi.purchase_order_ops_id')
                ->toArray();
            
            // Get paid amount from non_food_payments (BUDGET IS MONTHLY - filter by payment_date)
            // IMPORTANT: Only count NFP with status 'paid' (not 'approved')
            // Untuk global budget, sum semua payment dari semua PO yang sesuai category (semua outlets)
            $paidAmountFromPo = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                ->where('nfp.status', 'paid') // Only 'paid' status, not 'approved'
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
            
            // Get unpaid PR data
            // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO dan belum jadi NFP
            // Support both old structure (category at PR level) and new structure (category at items level)
            $prIdsForUnpaid = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('pr.id', '=', 'poi.source_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED']) // Only SUBMITTED and APPROVED
                ->where('pr.is_held', false) // Exclude held PRs
                ->whereNull('poo.id') // PR yang belum jadi PO (belum ada PO)
                ->whereNull('nfp.id') // PR yang belum jadi NFP (baik langsung maupun melalui PO)
                ->distinct()
                ->pluck('pr.id')
                ->toArray();
            
            $allPrs = PurchaseRequisition::whereIn('id', $prIdsForUnpaid)->get();
            
            // Get PO totals per PR - untuk cek apakah PR sudah jadi PO
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
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                ->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                ->pluck('po_total', 'pr_id')
                ->toArray();
            
            // Get total paid per PR (BUDGET IS MONTHLY - filter by PR created_at month and payment_date)
            // Exclude held PRs from unpaid calculation
            // Support both old structure (category at PR level) and new structure (category at items level)
            // IMPORTANT: Only count NFP with status 'paid' (not 'approved')
            // IMPORTANT: Only count payment for PO that still exists and is approved (not deleted)
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
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false) // Exclude held PRs
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'paid') // Only 'paid' status, not 'approved'
                ->where('nfp.status', '!=', 'cancelled')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where('poo.status', 'approved') // Only count payment for PO that still exists and is approved (not deleted)
                ->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                ->pluck('total_paid', 'pr_id')
                ->toArray();
            
            // Get unpaid PO data
            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
            $allPOs = DB::table('purchase_order_ops as poo')
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
                ->leftJoin('non_food_payments as nfp', 'poo.id', '=', 'nfp.purchase_order_ops_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                ->whereNull('nfp.id') // PO yang belum jadi NFP (belum ada NFP)
                ->groupBy('poo.id')
                ->select('poo.id as po_id', DB::raw('SUM(poi.total) as po_total'))
                ->get();

            // Calculate unpaid for each PR
            // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO
            // PR yang sudah difilter di query (belum jadi PO, status SUBMITTED/APPROVED)
            // IMPORTANT: Untuk GLOBAL budget, sum semua items dari semua outlets (tidak filter outlet)
            // Untuk PR Ops, hitung berdasarkan items.subtotal di category ini (sum semua outlets)
            $prUnpaidAmount = 0;
            foreach ($allPrs as $pr) {
                // Untuk PR Ops: hitung berdasarkan items di category ini (sum semua outlets)
                if (in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
                    // Hitung subtotal items di category ini (semua outlets) - TIDAK filter outlet untuk global budget
                    $categoryItemsSubtotal = DB::table('purchase_requisition_items')
                        ->where('purchase_requisition_id', $pr->id)
                        ->where('category_id', $categoryId)
                        ->sum('subtotal');
                    $prUnpaidAmount += $categoryItemsSubtotal ?? 0;
                } else {
                    // Untuk mode lain: gunakan PR amount
                    $prUnpaidAmount += $pr->amount;
                }
            }

            // Calculate unpaid for each PO
            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
            // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
            // IMPORTANT: Query sudah filter by outlet_id di pri, jadi po_total sudah benar per outlet
            $poUnpaidAmount = 0;
            foreach ($allPOs as $po) {
                // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                // po_total sudah benar karena sudah di-filter by outlet_id di pri
                $poUnpaidAmount += $po->po_total ?? 0;
            }

            // Calculate unpaid NFP
            // NEW LOGIC: NFP unpaid = NFP dengan status pending dan approved
            // Mencakup NFP yang langsung dari PR (tanpa PO) dan NFP yang melalui PO
            // Case 1: NFP langsung dari PR (nfp.purchase_requisition_id IS NOT NULL, nfp.purchase_order_ops_id IS NULL)
            $nfpUnpaidFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id') // NFP langsung dari PR (tanpa PO)
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->whereIn('nfp.status', ['pending', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            // Case 2: NFP melalui PO (nfp.purchase_order_ops_id IS NOT NULL)
            $nfpUnpaidFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
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
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id') // NFP melalui PO
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->whereIn('nfp.status', ['pending', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpUnpaidAmount = ($nfpUnpaidFromPr ?? 0) + ($nfpUnpaidFromPo ?? 0);
            
            // Get NFP breakdown by status (submitted, approved, paid)
            // NFP Submitted
            $nfpSubmittedFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'submitted')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpSubmittedFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
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
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'submitted')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpSubmittedAmount = ($nfpSubmittedFromPr ?? 0) + ($nfpSubmittedFromPo ?? 0);
            
            // NFP Approved (status = 'approved', belum paid)
            $nfpApprovedFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'approved')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpApprovedFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
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
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'approved')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpApprovedAmount = ($nfpApprovedFromPr ?? 0) + ($nfpApprovedFromPo ?? 0);
            
            // NFP Paid (already calculated in paidAmountFromPo)
            $nfpPaidAmount = $paidAmountFromPo;
            
            // Total unpaid = PR unpaid + PO unpaid + NFP unpaid
            $unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount;
            
            // Total used = Paid (from non_food_payments 'paid' + RNF 'approved') + Unpaid (PR + PO + NFP 'approved')
            $paidAmount = $paidAmountFromPo + $retailNonFoodApproved;
            $categoryUsedAmount = $paidAmount + $unpaidAmount;

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
                'breakdown' => [
                    'pr_unpaid' => $prUnpaidAmount, // PR Submitted & Approved yang belum dibuat PO
                    'po_unpaid' => $poUnpaidAmount, // PO Submitted & Approved yang belum dibuat NFP
                    'nfp_submitted' => $nfpSubmittedAmount, // NFP Submitted
                    'nfp_approved' => $nfpApprovedAmount, // NFP Approved (unpaid)
                    'nfp_paid' => $nfpPaidAmount, // NFP Paid
                    'retail_non_food' => $retailNonFoodApproved, // Retail Non Food Approved
                ],
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
            // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
            $prIds = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('pr.is_held', false) // Exclude held PRs
                ->distinct()
                ->pluck('pr.id')
                ->toArray();
            
            // Get PO IDs linked to PRs in this outlet
            // IMPORTANT: Untuk PR Ops, filter PO items berdasarkan outlet_id di purchase_requisition_items
            $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
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
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level (PENTING: filter by pri.outlet_id)
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->distinct()
                ->pluck('poi.purchase_order_ops_id')
                ->toArray();
            
            // Get paid amount from non_food_payments for this outlet (BUDGET IS MONTHLY - filter by payment_date)
            // IMPORTANT: Untuk PR Ops, hanya hitung payment untuk items di outlet ini
            // Karena satu PO bisa punya items dari multiple outlets, kita perlu filter berdasarkan PO items yang sesuai outlet
            $paidAmountFromPo = 0;
            if (!empty($poIdsInCategory)) {
                // Untuk setiap PO, hitung hanya payment untuk items di outlet ini
                foreach ($poIdsInCategory as $poId) {
                    // Get PO items yang berasal dari PR items di outlet ini
                    $outletPoItemIds = DB::table('purchase_order_ops_items as poi')
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
                        ->where('poi.purchase_order_ops_id', $poId)
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->where(function($q) use ($categoryId, $outletId) {
                            $q->where(function($q2) use ($categoryId, $outletId) {
                                $q2->where('pr.category_id', $categoryId)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($categoryId, $outletId) {
                                $q2->where('pri.category_id', $categoryId)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->pluck('poi.total')
                        ->toArray();
                    
                    // Get payment untuk PO ini
                    $poPayment = DB::table('non_food_payments')
                        ->where('purchase_order_ops_id', $poId)
                        ->where('status', 'paid')
                        ->where('status', '!=', 'cancelled')
                        ->whereBetween('payment_date', [$dateFrom, $dateTo])
                        ->first();
                    
                    if ($poPayment) {
                        // Hitung proporsi: total PO items di outlet ini / total PO items
                        $poTotalItems = DB::table('purchase_order_ops_items')
                            ->where('purchase_order_ops_id', $poId)
                            ->sum('total');
                        
                        if ($poTotalItems > 0) {
                            $outletPoItemsTotal = array_sum($outletPoItemIds);
                            $proportion = $outletPoItemsTotal / $poTotalItems;
                            $paidAmountFromPo += $poPayment->amount * $proportion;
                        }
                    }
                }
            }
            
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
            
            // Get unpaid PR data for this outlet
            // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO dan belum jadi NFP
            // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
            $prIdsForUnpaid = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('pr.id', '=', 'poi.source_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED']) // Only SUBMITTED and APPROVED
                ->where('pr.is_held', false) // Exclude held PRs
                ->whereNull('poo.id') // PR yang belum jadi PO (belum ada PO)
                ->whereNull('nfp.id') // PR yang belum jadi NFP (baik langsung maupun melalui PO)
                ->distinct()
                ->pluck('pr.id')
                ->toArray();
            
            $allPrs = PurchaseRequisition::whereIn('id', $prIdsForUnpaid)->get();
            
            // Get PO totals per PR for this outlet (BUDGET IS MONTHLY - filter by PR created_at month)
            // Exclude held PRs from unpaid calculation
            // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
            // IMPORTANT: Don't filter by PO date - we want to include all POs for PRs in the date range
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
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false) // Exclude held PRs
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                ->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                ->pluck('po_total', 'pr_id')
                ->toArray();
            
            // Get total paid per PR for this outlet (BUDGET IS MONTHLY - filter by PR created_at month and payment_date)
            // Exclude held PRs from unpaid calculation
            // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
            // IMPORTANT: Only count NFP with status 'paid' (not 'approved')
            // IMPORTANT: Only count payment for PO that still exists and is approved (not deleted)
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
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false) // Exclude held PRs
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'paid') // Only 'paid' status, not 'approved'
                ->where('nfp.status', '!=', 'cancelled')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where('poo.status', 'approved') // Only count payment for PO that still exists and is approved (not deleted)
                ->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                ->pluck('total_paid', 'pr_id')
                ->toArray();
            
            // Get unpaid PO data for this outlet
            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
            $allPOs = DB::table('purchase_order_ops as poo')
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
                ->leftJoin('non_food_payments as nfp', 'poo.id', '=', 'nfp.purchase_order_ops_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                ->whereNull('nfp.id') // PO yang belum jadi NFP (belum ada NFP)
                ->groupBy('poo.id')
                ->select('poo.id as po_id', DB::raw('SUM(poi.total) as po_total'))
                ->get();

            // Calculate unpaid for each PR
            // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO
            // PR yang sudah difilter di query (belum jadi PO, status SUBMITTED/APPROVED)
            // IMPORTANT: Untuk PR Ops, hitung berdasarkan items di outlet tersebut
            $prUnpaidAmount = 0;
            foreach ($allPrs as $pr) {
                // Untuk PR Ops: hitung berdasarkan items di outlet tersebut
                if (in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
                    // Hitung subtotal items di outlet ini
                    $outletItemsSubtotal = DB::table('purchase_requisition_items')
                        ->where('purchase_requisition_id', $pr->id)
                        ->where('outlet_id', $outletId)
                        ->where('category_id', $categoryId)
                        ->sum('subtotal');
                    $prUnpaidAmount += $outletItemsSubtotal ?? 0;
                } else {
                    // Untuk mode lain: gunakan PR amount
                    $prUnpaidAmount += $pr->amount;
                }
            }

            // Calculate unpaid for each PO
            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
            // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
            // IMPORTANT: Query sudah filter by outlet_id di pri, jadi po_total sudah benar per outlet
            $poUnpaidAmount = 0;
            foreach ($allPOs as $po) {
                // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                // po_total sudah benar karena sudah di-filter by outlet_id di pri
                $poUnpaidAmount += $po->po_total ?? 0;
            }

            // Calculate unpaid NFP
            // NEW LOGIC: NFP unpaid = NFP dengan status pending dan approved
            // Mencakup NFP yang langsung dari PR (tanpa PO) dan NFP yang melalui PO
            // Case 1: NFP langsung dari PR untuk outlet ini
            $nfpUnpaidFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id') // NFP langsung dari PR (tanpa PO)
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->whereIn('nfp.status', ['pending', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            // Case 2: NFP melalui PO untuk outlet ini
            $nfpUnpaidFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
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
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id') // NFP melalui PO
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->whereIn('nfp.status', ['pending', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpUnpaidAmount = ($nfpUnpaidFromPr ?? 0) + ($nfpUnpaidFromPo ?? 0);
            
            // Get NFP breakdown by status (submitted, approved, paid) for this outlet
            // NFP Submitted
            $nfpSubmittedFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'submitted')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpSubmittedFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
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
                ->where(function($q) use ($categoryId, $outletId) {
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'submitted')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpSubmittedAmount = ($nfpSubmittedFromPr ?? 0) + ($nfpSubmittedFromPo ?? 0);
            
            // NFP Approved (status = 'approved', belum paid)
            $nfpApprovedFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'approved')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpApprovedFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
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
                ->where(function($q) use ($categoryId, $outletId) {
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'approved')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpApprovedAmount = ($nfpApprovedFromPr ?? 0) + ($nfpApprovedFromPo ?? 0);
            
            // NFP Paid (already calculated in paidAmountFromPo)
            $nfpPaidAmount = $paidAmountFromPo;
            
            // Total unpaid = PR unpaid + PO unpaid + NFP unpaid
            $unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount;
            
            // Total used = Paid (from non_food_payments 'paid' + RNF 'approved') + Unpaid (PR + PO + NFP 'approved')
            $paidAmount = $paidAmountFromPo + $outletRetailNonFoodApproved;
            $outletUsedAmount = $paidAmount + $unpaidAmount;

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
                'breakdown' => [
                    'pr_unpaid' => $prUnpaidAmount, // PR Submitted & Approved yang belum dibuat PO
                    'po_unpaid' => $poUnpaidAmount, // PO Submitted & Approved yang belum dibuat NFP
                    'nfp_submitted' => $nfpSubmittedAmount, // NFP Submitted
                    'nfp_approved' => $nfpApprovedAmount, // NFP Approved (unpaid)
                    'nfp_paid' => $nfpPaidAmount, // NFP Paid
                    'retail_non_food' => $outletRetailNonFoodApproved, // Retail Non Food Approved
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
     * View attachment via API (supports Bearer token)
     * Supports both images and other file types (PDF, etc.)
     */
    public function viewAttachmentApi($attachmentId)
    {
        try {
            \Log::info('View attachment API called with ID: ' . $attachmentId);
            
            $attachment = \App\Models\PurchaseRequisitionAttachment::findOrFail($attachmentId);
            \Log::info('Attachment found: ' . $attachment->file_name . ', path: ' . $attachment->file_path);
            
            if (!\Storage::disk('public')->exists($attachment->file_path)) {
                \Log::error('File not found in storage: ' . $attachment->file_path);
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            $file = \Storage::disk('public')->get($attachment->file_path);
            $mimeType = \Storage::disk('public')->mimeType($attachment->file_path);
            
            \Log::info('Serving file: ' . $attachment->file_name . ', mime: ' . $mimeType);

            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $attachment->file_name . '"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET')
                ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Attachment not found with ID: ' . $attachmentId);
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('View attachment API error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
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