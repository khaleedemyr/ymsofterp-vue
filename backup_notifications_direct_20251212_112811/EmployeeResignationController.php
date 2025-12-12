<?php

namespace App\Http\Controllers;

use App\Models\EmployeeResignation;
use App\Models\EmployeeResignationApprovalFlow;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Services\NotificationService;

class EmployeeResignationController extends Controller
{
    /**
     * Display a listing of employee resignations
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', 'all');
        $outlet = $request->get('outlet', 'all');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $perPage = $request->get('per_page', 15);

        $query = EmployeeResignation::with([
            'outlet',
            'employee',
            'creator',
            'approvalFlows.approver'
        ]);

        // Apply filters
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('resignation_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('employee', function($q) use ($search) {
                      $q->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                  })
                  ->orWhereHas('outlet', function($q) use ($search) {
                      $q->where('nama_outlet', 'like', "%{$search}%");
                  });
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($outlet !== 'all') {
            $query->where('outlet_id', $outlet);
        }

        if ($dateFrom) {
            $query->whereDate('resignation_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('resignation_date', '<=', $dateTo);
        }

        $resignations = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function($resignation) {
                // Load approval flows for each resignation
                $resignation->load('approvalFlows.approver');
                return $resignation;
            });

        // Get filter options
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();

        $statistics = [
            'total' => EmployeeResignation::count(),
            'draft' => EmployeeResignation::where('status', 'draft')->count(),
            'submitted' => EmployeeResignation::where('status', 'submitted')->count(),
            'approved' => EmployeeResignation::where('status', 'approved')->count(),
            'rejected' => EmployeeResignation::where('status', 'rejected')->count(),
        ];

        return Inertia::render('EmployeeResignation/Index', [
            'data' => $resignations,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'outlet' => $outlet,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'outlets' => $outlets,
            ],
            'statistics' => $statistics,
            'auth' => [
                'user' => auth()->user()
            ],
        ]);
    }

    /**
     * Show the form for creating a new employee resignation
     */
    public function create()
    {
        $user = Auth::user();
        $userOutletId = $user->id_outlet ?? null;
        
        // If user outlet is not 1, only show their outlet
        if ($userOutletId && $userOutletId != 1) {
            $outlets = Outlet::where('id_outlet', $userOutletId)
                ->where('status', 'A')
                ->orderBy('nama_outlet')
                ->get();
        } else {
            // User with outlet 1 can see all outlets
            $outlets = Outlet::active()->orderBy('nama_outlet')->get();
        }

        return Inertia::render('EmployeeResignation/Create', [
            'outlets' => $outlets,
            'userOutletId' => $userOutletId,
            'canSelectOutlet' => $userOutletId == 1,
        ]);
    }

    /**
     * Get employees by outlet
     */
    public function getEmployeesByOutlet(Request $request)
    {
        $outletId = $request->get('outlet_id');
        
        if (!$outletId) {
            return response()->json(['employees' => []]);
        }

        $employees = User::where('id_outlet', $outletId)
            ->where('status', 'A')
            ->select('id', 'nik', 'nama_lengkap', 'email')
            ->orderBy('nama_lengkap')
            ->get();

        return response()->json(['employees' => $employees]);
    }

    /**
     * Get approvers for search
     */
    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');
        
        if (strlen($search) < 2) {
            return response()->json(['approvers' => []]);
        }

        $approvers = User::where('status', 'A')
            ->where(function($query) use ($search) {
                $query->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('nik', 'like', "%{$search}%");
            })
            ->with(['jabatan', 'outlet'])
            ->limit(20)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'nik' => $user->nik,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email' => $user->email,
                    'jabatan' => $user->jabatan ? [
                        'id' => $user->jabatan->id_jabatan,
                        'nama_jabatan' => $user->jabatan->nama_jabatan,
                    ] : null,
                    'outlet' => $user->outlet ? [
                        'id' => $user->outlet->id_outlet,
                        'nama_outlet' => $user->outlet->nama_outlet,
                    ] : null,
                ];
            });

        return response()->json(['approvers' => $approvers]);
    }

    /**
     * Store a newly created employee resignation
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'employee_id' => 'required|exists:users,id',
            'resignation_date' => 'required|date',
            'resignation_type' => 'required|in:prosedural,non_prosedural',
            'notes' => 'nullable|string|max:1000',
            'approvers' => 'nullable|array',
            'approvers.*' => 'required|exists:users,id',
        ]);

        // Generate resignation number
        $validated['resignation_number'] = $this->generateResignationNumber();
        $validated['status'] = 'draft';
        $validated['created_by'] = Auth::id();

        try {
            DB::beginTransaction();
            
            // Create employee resignation
            $resignation = EmployeeResignation::create($validated);

            // Create approval flows if approvers provided
            if (!empty($validated['approvers'])) {
                foreach ($validated['approvers'] as $index => $approverId) {
                    EmployeeResignationApprovalFlow::create([
                        'employee_resignation_id' => $resignation->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1,
                        'status' => 'PENDING',
                    ]);
                }
                
                // Update status to submitted if approvers exist
                $resignation->update(['status' => 'submitted']);
            }
            
            DB::commit();
            
            // Send notification to the lowest level approver
            if (!empty($validated['approvers'])) {
                $this->sendNotificationToNextApprover($resignation);
            }
            
            return redirect()->route('employee-resignations.show', $resignation)
                           ->with('success', 'Employee resignation created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create employee resignation: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified employee resignation
     */
    public function show(EmployeeResignation $employeeResignation)
    {
        $employeeResignation->load([
            'outlet',
            'employee.jabatan',
            'employee.outlet',
            'creator',
            'approvalFlows.approver.jabatan'
        ]);

        // For API requests (from approval card)
        if (request()->expectsJson() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'resignation' => [
                    'id' => $employeeResignation->id,
                    'resignation_number' => $employeeResignation->resignation_number,
                    'outlet' => $employeeResignation->outlet ? [
                        'id' => $employeeResignation->outlet->id_outlet,
                        'nama_outlet' => $employeeResignation->outlet->nama_outlet,
                    ] : null,
                    'employee' => $employeeResignation->employee ? [
                        'id' => $employeeResignation->employee->id,
                        'nama_lengkap' => $employeeResignation->employee->nama_lengkap,
                        'nik' => $employeeResignation->employee->nik,
                        'email' => $employeeResignation->employee->email,
                    ] : null,
                    'resignation_date' => $employeeResignation->resignation_date,
                    'resignation_type' => $employeeResignation->resignation_type,
                    'notes' => $employeeResignation->notes,
                    'status' => $employeeResignation->status,
                    'creator' => $employeeResignation->creator ? [
                        'id' => $employeeResignation->creator->id,
                        'nama_lengkap' => $employeeResignation->creator->nama_lengkap,
                        'email' => $employeeResignation->creator->email,
                    ] : null,
                    'created_at' => $employeeResignation->created_at,
                    'approval_flows' => $employeeResignation->approvalFlows->map(function($flow) {
                        return [
                            'id' => $flow->id,
                            'approval_level' => $flow->approval_level,
                            'status' => $flow->status,
                            'approved_at' => $flow->approved_at,
                            'rejected_at' => $flow->rejected_at,
                            'comments' => $flow->comments,
                            'approver' => $flow->approver ? [
                                'id' => $flow->approver->id,
                                'nama_lengkap' => $flow->approver->nama_lengkap,
                                'email' => $flow->approver->email,
                            ] : null,
                        ];
                    }),
                    'current_approval_flow_id' => $employeeResignation->approvalFlows()
                        ->where('approver_id', Auth::id())
                        ->where('status', 'PENDING')
                        ->value('id'),
                ]
            ]);
        }

        return Inertia::render('EmployeeResignation/Show', [
            'resignation' => $employeeResignation,
        ]);
    }

    /**
     * Generate resignation number
     */
    private function generateResignationNumber()
    {
        $year = date('Y');
        $month = date('m');
        
        // Get last resignation number for this month
        $lastResignation = EmployeeResignation::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastResignation && preg_match('/RES-(\d{4})(\d{2})-(\d+)/', $lastResignation->resignation_number, $matches)) {
            $sequence = intval($matches[3]) + 1;
        } else {
            $sequence = 1;
        }
        
        return 'RES-' . $year . $month . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Send notification to next approver
     */
    private function sendNotificationToNextApprover($resignation)
    {
        try {
            // Get the lowest level approver that is still pending
            $nextApprover = $resignation->approvalFlows()
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
            $creator = $resignation->creator;
            $creatorName = $creator ? $creator->nama_lengkap : 'Unknown User';

            // Get employee details
            $employee = $resignation->employee;
            $employeeName = $employee ? $employee->nama_lengkap : 'Unknown Employee';

            // Get outlet details
            $outletName = $resignation->outlet ? $resignation->outlet->nama_outlet : 'Unknown Outlet';

            // Create notification message
            $message = "Pengajuan Resign Karyawan baru memerlukan persetujuan Anda:\n\n";
            $message .= "No: {$resignation->resignation_number}\n";
            $message .= "Nama Karyawan: {$employeeName}\n";
            $message .= "Outlet: {$outletName}\n";
            $message .= "Tanggal Resign: " . $resignation->resignation_date->format('d F Y') . "\n";
            $message .= "Tipe: " . ($resignation->resignation_type === 'prosedural' ? 'Prosedural' : 'Non Prosedural') . "\n";
            $message .= "Level Approval: {$nextApprover->approval_level}\n";
            $message .= "Diajukan oleh: {$creatorName}\n\n";
            $message .= "Silakan segera lakukan review dan approval.";

            // Insert notification
            DB::table('notifications')->insert([
                'user_id' => $approver->id,
                'task_id' => $resignation->id,
                'type' => 'employee_resignation_approval',
                'message' => $message,
                'url' => config('app.url') . '/employee-resignations/' . $resignation->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send notification to next approver', [
                'employee_resignation_id' => $resignation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get pending approvals for current user
     */
    public function pendingApprovals(Request $request)
    {
        $user = Auth::user();
        $limit = $request->get('limit', 100);

        // Get resignation IDs where current user is an approver with pending status
        $pendingResignationIds = EmployeeResignationApprovalFlow::where('approver_id', $user->id)
            ->where('status', 'PENDING')
            ->pluck('employee_resignation_id')
            ->unique();

        $isSuperadmin = $user && $user->id_role === '5af56935b011a';
        
        // Superadmin can see all pending resignations
        if ($isSuperadmin) {
            $pendingResignationIds = EmployeeResignationApprovalFlow::where('status', 'PENDING')
                ->pluck('employee_resignation_id')
                ->unique();
        }
        
        $resignations = EmployeeResignation::with([
            'outlet',
            'employee',
            'creator',
            'approvalFlows' => function($query) use ($user, $isSuperadmin) {
                if ($isSuperadmin) {
                    $query->where('status', 'PENDING')
                          ->orderBy('approval_level');
                } else {
                    $query->where('approver_id', $user->id)
                          ->where('status', 'PENDING')
                          ->orderBy('approval_level');
                }
            },
            'approvalFlows.approver'
        ])
        ->whereIn('id', $pendingResignationIds)
        ->where('status', 'submitted')
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get()
        ->map(function($resignation) use ($isSuperadmin, $user) {
            // Get next pending approval flow
            $pendingFlows = $resignation->approvalFlows->where('status', 'PENDING')->sortBy('approval_level');
            $currentFlow = $pendingFlows->first();
            
            // Get approver name
            $approverName = null;
            if ($currentFlow && $currentFlow->approver) {
                $approverName = $currentFlow->approver->nama_lengkap;
            } elseif (!$isSuperadmin) {
                // For regular users, they are the approver
                $approverName = $user->nama_lengkap;
            }
            
            return [
                'id' => $resignation->id,
                'resignation_number' => $resignation->resignation_number,
                'employee' => $resignation->employee ? [
                    'id' => $resignation->employee->id,
                    'nama_lengkap' => $resignation->employee->nama_lengkap,
                    'nik' => $resignation->employee->nik,
                ] : null,
                'outlet' => $resignation->outlet ? [
                    'id' => $resignation->outlet->id_outlet,
                    'nama_outlet' => $resignation->outlet->nama_outlet,
                ] : null,
                'resignation_date' => $resignation->resignation_date,
                'resignation_type' => $resignation->resignation_type,
                'status' => $resignation->status,
                'notes' => $resignation->notes,
                'creator' => $resignation->creator ? [
                    'id' => $resignation->creator->id,
                    'nama_lengkap' => $resignation->creator->nama_lengkap,
                ] : null,
                'created_at' => $resignation->created_at,
                'approval_level' => $currentFlow ? $currentFlow->approval_level : null,
                'approver_name' => $approverName,
            ];
        });

        return response()->json([
            'success' => true,
            'resignations' => $resignations
        ]);
    }

    /**
     * Approve employee resignation
     */
    public function approve(Request $request, $employeeResignation)
    {
        // Support both route model binding (EmployeeResignation model) and $id (integer/string)
        $employeeResignation = $employeeResignation instanceof EmployeeResignation 
            ? $employeeResignation 
            : EmployeeResignation::findOrFail($employeeResignation);
        
        if ($employeeResignation->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Only submitted employee resignations can be approved.'
            ], 400);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
            'comment' => 'nullable|string|max:500', // Alias for note
            'comments' => 'nullable|string|max:500', // Alias for note
        ]);

        // Support both 'note', 'comment', and 'comments' parameters
        $note = $request->input('note') ?? $request->input('comment') ?? $request->input('comments');

        try {
            DB::beginTransaction();

            $currentApprover = Auth::user();
            
            // Get current approval flow for this approver
            // If approval_flow_id is provided, use it; otherwise find by approver_id
            if ($request->has('approval_flow_id')) {
                $currentApprovalFlow = $employeeResignation->approvalFlows()
                    ->where('id', $request->approval_flow_id)
                    ->where('status', 'PENDING')
                    ->first();
            } else {
                $currentApprovalFlow = $employeeResignation->approvalFlows()
                    ->where('approver_id', $currentApprover->id)
                    ->where('status', 'PENDING')
                    ->first();
            }

            if (!$currentApprovalFlow) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to approve this employee resignation.'
                ], 403);
            }

            // Approve current level
            $currentApprovalFlow->update([
                'status' => 'APPROVED',
                'approved_at' => now(),
                'comments' => $note,
            ]);

            // Check if there are more approvers pending
            $pendingApprovers = $employeeResignation->approvalFlows()
                ->where('status', 'PENDING')
                ->count();

            if ($pendingApprovers > 0) {
                // Still have pending approvers, keep status as submitted
                // Send notification to next approver
                $this->sendNotificationToNextApprover($employeeResignation);
                
                $message = 'Employee Resignation approved! Notification sent to next approver.';
            } else {
                // All approvers have approved, update status to approved
                $employeeResignation->update([
                    'status' => 'approved',
                ]);

                // Update employee status to 'N' (Non Active)
                if ($employeeResignation->employee_id) {
                    User::where('id', $employeeResignation->employee_id)
                        ->update(['status' => 'N']);
                }

                // Send notification to creator
                $this->sendNotificationToCreator($employeeResignation, 'approved');
                
                $message = 'Employee Resignation fully approved! Employee status updated to Non Active.';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve employee resignation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject employee resignation
     */
    public function reject(Request $request, $employeeResignation)
    {
        // Support both route model binding (EmployeeResignation model) and $id (integer/string)
        $employeeResignation = $employeeResignation instanceof EmployeeResignation 
            ? $employeeResignation 
            : EmployeeResignation::findOrFail($employeeResignation);
        
        if ($employeeResignation->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Only submitted employee resignations can be rejected.'
            ], 400);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
            'reason' => 'nullable|string|max:500', // Alias for note
            'comment' => 'nullable|string|max:500', // Alias for note
        ]);

        // Support 'note', 'reason', and 'comment' parameters
        $rejectionReason = $request->input('note') ?? $request->input('reason') ?? $request->input('comment');
        
        if (!$rejectionReason) {
            return response()->json([
                'success' => false,
                'message' => 'Alasan penolakan harus diisi'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $currentApprover = Auth::user();
            
            // Get current approval flow for this approver
            // If approval_flow_id is provided, use it; otherwise find by approver_id
            if ($request->has('approval_flow_id')) {
                $currentApprovalFlow = $employeeResignation->approvalFlows()
                    ->where('id', $request->approval_flow_id)
                    ->where('status', 'PENDING')
                    ->first();
            } else {
                $currentApprovalFlow = $employeeResignation->approvalFlows()
                    ->where('approver_id', $currentApprover->id)
                    ->where('status', 'PENDING')
                    ->first();
            }

            if (!$currentApprovalFlow) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to reject this employee resignation.'
                ], 403);
            }

            // Reject current level
            $currentApprovalFlow->update([
                'status' => 'REJECTED',
                'rejected_at' => now(),
                'comments' => $rejectionReason,
            ]);

            // Update resignation status to rejected
            $employeeResignation->update([
                'status' => 'rejected',
                'notes' => $rejectionReason,
            ]);

            // Send notification to creator
            $this->sendNotificationToCreator($employeeResignation, 'rejected');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee Resignation rejected.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject employee resignation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification to creator about resignation status
     */
    private function sendNotificationToCreator($resignation, $status)
    {
        try {
            $creator = $resignation->creator;
            if (!$creator) {
                return;
            }

            $employee = $resignation->employee;
            $employeeName = $employee ? $employee->nama_lengkap : 'Unknown Employee';

            $outletName = $resignation->outlet ? $resignation->outlet->nama_outlet : 'Unknown Outlet';

            $message = '';
            $type = '';

            switch ($status) {
                case 'approved':
                    $message = "Employee Resignation Anda telah disetujui:\n\n";
                    $message .= "No: {$resignation->resignation_number}\n";
                    $message .= "Nama Karyawan: {$employeeName}\n";
                    $message .= "Outlet: {$outletName}\n";
                    $message .= "Tanggal Resign: " . $resignation->resignation_date->format('d F Y') . "\n";
                    $message .= "Tipe: " . ($resignation->resignation_type === 'prosedural' ? 'Prosedural' : 'Non Prosedural') . "\n\n";
                    $message .= "Resignation telah disetujui oleh semua approver dan status karyawan telah diupdate menjadi Non Active.";
                    $type = 'employee_resignation_approved';
                    break;
                
                case 'rejected':
                    $message = "Employee Resignation Anda telah ditolak:\n\n";
                    $message .= "No: {$resignation->resignation_number}\n";
                    $message .= "Nama Karyawan: {$employeeName}\n";
                    $message .= "Outlet: {$outletName}\n";
                    $message .= "Tanggal Resign: " . $resignation->resignation_date->format('d F Y') . "\n";
                    $message .= "Tipe: " . ($resignation->resignation_type === 'prosedural' ? 'Prosedural' : 'Non Prosedural') . "\n\n";
                    $message .= "Alasan penolakan: " . ($resignation->notes ?? 'Tidak ada alasan yang diberikan');
                    $type = 'employee_resignation_rejected';
                    break;
            }

            if ($message) {
                DB::table('notifications')->insert([
                    'user_id' => $creator->id,
                    'task_id' => $resignation->id,
                    'type' => $type,
                    'message' => $message,
                    'url' => config('app.url') . '/employee-resignations/' . $resignation->id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to send notification to creator', [
                'employee_resignation_id' => $resignation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

