<?php

namespace App\Http\Controllers;

use App\Models\EmployeeMovement;
use App\Models\EmployeeMovementApprovalFlow;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class EmployeeMovementController extends Controller
{
    /**
     * Pre-process request data to handle multiselect objects
     */
    private function preprocessRequestData(Request $request)
    {
        $requestData = $request->all();
        
        // Convert multiselect objects to string IDs
        if (isset($requestData['employee_id']) && is_array($requestData['employee_id'])) {
            $requestData['employee_id'] = $requestData['employee_id']['id'] ?? $requestData['employee_id'][0] ?? null;
        }
        if (isset($requestData['hod_approver_id']) && is_array($requestData['hod_approver_id'])) {
            $requestData['hod_approver_id'] = $requestData['hod_approver_id']['id'] ?? $requestData['hod_approver_id'][0] ?? null;
        }
        if (isset($requestData['gm_approver_id']) && is_array($requestData['gm_approver_id'])) {
            $requestData['gm_approver_id'] = $requestData['gm_approver_id']['id'] ?? $requestData['gm_approver_id'][0] ?? null;
        }
        if (isset($requestData['gm_hr_approver_id']) && is_array($requestData['gm_hr_approver_id'])) {
            $requestData['gm_hr_approver_id'] = $requestData['gm_hr_approver_id']['id'] ?? $requestData['gm_hr_approver_id'][0] ?? null;
        }
        if (isset($requestData['bod_approver_id']) && is_array($requestData['bod_approver_id'])) {
            $requestData['bod_approver_id'] = $requestData['bod_approver_id']['id'] ?? $requestData['bod_approver_id'][0] ?? null;
        }
        
        // Process approvers array (new approval flow system)
        // Convert array of objects to array of IDs, or ensure it's already an array of IDs
        if (isset($requestData['approvers'])) {
            if (is_array($requestData['approvers'])) {
                $approvers = [];
                foreach ($requestData['approvers'] as $approver) {
                    // Skip null or empty values
                    if ($approver === null || $approver === '') {
                        continue;
                    }
                    if (is_array($approver) && isset($approver['id'])) {
                        $approvers[] = $approver['id'];
                    } elseif (is_string($approver) || is_numeric($approver)) {
                        $approvers[] = $approver;
                    }
                }
                $requestData['approvers'] = $approvers;
            } elseif (is_string($requestData['approvers'])) {
                // If it's a JSON string, decode it
                $decoded = json_decode($requestData['approvers'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Filter out null values
                    $requestData['approvers'] = array_filter($decoded, function($val) {
                        return $val !== null && $val !== '';
                    });
                } else {
                    // If not JSON, treat as single value
                    $requestData['approvers'] = [$requestData['approvers']];
                }
            }
        }
        
        // Create new request with processed data and copy files
        $processedRequest = Request::create(
            $request->url(),
            $request->method(),
            $requestData,
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all()
        );
        
        return $processedRequest;
    }
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'all');
        $employeeId = $request->input('employee_id');

        $query = EmployeeMovement::query()
            ->with(['employee:id,nama_lengkap,nik,id_jabatan,id_outlet,division_id,tanggal_masuk'])
            ->leftJoin('users', 'employee_movements.employee_id', '=', 'users.id')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->select(
                'employee_movements.*',
                'users.nama_lengkap',
                'users.nik',
                'tbl_data_jabatan.nama_jabatan',
                'tbl_data_outlet.nama_outlet',
                'tbl_data_divisi.nama_divisi'
            );

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('users.nama_lengkap', 'like', "%$search%")
                  ->orWhere('users.nik', 'like', "%$search%")
                  ->orWhere('employee_movements.employee_name', 'like', "%$search%");
            });
        }

        if ($status !== 'all') {
            $query->where('employee_movements.status', $status);
        }

        if ($employeeId) {
            $query->where('employee_movements.employee_id', $employeeId);
        }

        $movements = $query->orderBy('employee_movements.created_at', 'desc')
                          ->paginate(10)
                          ->withQueryString();

        // Get employees for filter dropdown
        $employees = User::where('status', 'A')
                        ->select('id', 'nama_lengkap', 'nik')
                        ->orderBy('nama_lengkap')
                        ->get();

        return Inertia::render('EmployeeMovement/Index', [
            'movements' => $movements,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'employee_id' => $employeeId,
            ],
            'employees' => $employees,
        ]);
    }

    public function create()
    {
        return Inertia::render('EmployeeMovement/Create');
    }

    public function store(Request $request)
    {
        $processedRequest = $this->preprocessRequestData($request);

        $validated = $processedRequest->validate([
            'employee_id' => 'required|exists:users,id',
            'employee_name' => 'required|string|max:255',
            'employee_position' => 'nullable|string|max:255',
            'employee_division' => 'nullable|string|max:255',
            'employee_unit_property' => 'nullable|string|max:255',
            'employee_join_date' => 'nullable|date',
            'employment_type' => 'nullable|in:extend_contract_without_adjustment,extend_contract_with_adjustment,promotion,demotion,mutation,termination',
            'employment_effective_date' => 'required|date',
            'kpi_required' => 'boolean',
            'kpi_date' => 'nullable|date',
            'psikotest_required' => 'boolean',
            'psikotest_score' => 'nullable|string|max:50',
            'psikotest_date' => 'nullable|date',
            'training_attendance_required' => 'boolean',
            'training_attendance_date' => 'nullable|date',
            'position_change' => 'boolean',
            'position_from' => 'nullable|string|max:255',
            'position_to' => 'nullable|string|max:255',
            'level_change' => 'boolean',
            'level_from' => 'nullable|string|max:255',
            'level_to' => 'nullable|string|max:255',
            'salary_change' => 'boolean',
            'salary_from' => 'nullable|numeric|min:0',
            'salary_to' => 'nullable|numeric|min:0',
            'department_change' => 'boolean',
            'department_from' => 'nullable|string|max:255',
            'department_to' => 'nullable|string|max:255',
            'division_change' => 'boolean',
            'division_from' => 'nullable|string|max:255',
            'division_to' => 'nullable|string|max:255',
            'unit_property_change' => 'boolean',
            'unit_property_from' => 'nullable|string|max:255',
            'unit_property_to' => 'nullable|string|max:255',
            'comments' => 'nullable|string',
            'kpi_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'psikotest_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'training_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'other_attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'hod_approver_id' => 'nullable|exists:users,id',
            'gm_approver_id' => 'nullable|exists:users,id',
            'gm_hr_approver_id' => 'nullable|exists:users,id',
            'bod_approver_id' => 'nullable|exists:users,id',
            'approvers' => 'nullable|array',
            'approvers.*' => 'nullable|exists:users,id',
            'status' => 'nullable|in:draft,pending,approved,rejected,executed,error',
        ]);

        // Handle file uploads
        if ($request->hasFile('kpi_attachment')) {
            $validated['kpi_attachment'] = $request->file('kpi_attachment')->store('employee-movements/kpi', 'public');
        }
        
        if ($request->hasFile('psikotest_attachment')) {
            $validated['psikotest_attachment'] = $request->file('psikotest_attachment')->store('employee-movements/psikotest', 'public');
        }
        
        if ($request->hasFile('training_attachment')) {
            $validated['training_attachment'] = $request->file('training_attachment')->store('employee-movements/training', 'public');
        }
        
        if ($request->hasFile('other_attachments')) {
            $otherAttachments = [];
            foreach ($request->file('other_attachments') as $file) {
                $otherAttachments[] = $file->store('employee-movements/other', 'public');
            }
            $validated['other_attachments'] = json_encode($otherAttachments);
        }

        DB::beginTransaction();
        try {
            // Set status to 'pending' automatically if not provided or if it's 'draft'
            if (!isset($validated['status']) || $validated['status'] === 'draft') {
                $validated['status'] = 'pending';
            }
            
            // Extract approvers before creating movement (so it's not saved to employee_movements table)
            $approvers = $validated['approvers'] ?? null;
            
            // Debug: Log approvers data BEFORE unset
            \Log::info('Employee Movement Store - Approvers Data', [
                'approvers_before_unset' => $approvers,
                'is_array' => is_array($approvers),
                'count' => is_array($approvers) ? count($approvers) : 0,
                'raw_request_approvers' => $request->input('approvers'),
                'validated_approvers' => $validated['approvers'] ?? 'not set',
            ]);
            
            unset($validated['approvers']); // Remove from validated to prevent saving to employee_movements table
            
            $movement = EmployeeMovement::create($validated);

            // Create approval flows if approvers provided (new system)
            if (!empty($approvers) && is_array($approvers)) {
                // Filter out null, empty, or invalid values
                $validApprovers = array_filter($approvers, function($id) {
                    return !empty($id) && ($id !== null) && ($id !== '');
                });
                
                if (count($validApprovers) > 0) {
                    // Re-index array to ensure sequential approval levels
                    $validApprovers = array_values($validApprovers);
                    
                    foreach ($validApprovers as $index => $approverId) {
                        EmployeeMovementApprovalFlow::create([
                            'employee_movement_id' => $movement->id,
                            'approver_id' => $approverId,
                            'approval_level' => $index + 1, // Level 1 = terendah, level terakhir = tertinggi
                            'status' => 'PENDING',
                        ]);
                    }
                }
            } elseif (!empty($validated['hod_approver_id']) || !empty($validated['gm_approver_id']) || 
                      !empty($validated['gm_hr_approver_id']) || !empty($validated['bod_approver_id'])) {
                // Legacy: Create approval flows from old approver fields for backward compatibility
                $approvers = [];
                if (!empty($validated['hod_approver_id'])) {
                    $approvers[] = $validated['hod_approver_id'];
                }
                if (!empty($validated['gm_approver_id'])) {
                    $approvers[] = $validated['gm_approver_id'];
                }
                if (!empty($validated['gm_hr_approver_id'])) {
                    $approvers[] = $validated['gm_hr_approver_id'];
                }
                if (!empty($validated['bod_approver_id'])) {
                    $approvers[] = $validated['bod_approver_id'];
                }
                
                foreach ($approvers as $index => $approverId) {
                    EmployeeMovementApprovalFlow::create([
                        'employee_movement_id' => $movement->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1,
                        'status' => 'PENDING',
                    ]);
                }
            }

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'create',
                'model' => 'EmployeeMovement',
                'model_id' => $movement->id,
                'description' => "Created employee movement for {$movement->employee_name}",
            ]);

            DB::commit();

            // Kirim notifikasi berjenjang ke approver pertama
            $this->sendApprovalNotifications($movement);

            return redirect()->route('employee-movements.index')
                            ->with('success', 'Employee movement created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create employee movement: ' . $e->getMessage()]);
        }
    }

    public function show(EmployeeMovement $employeeMovement)
    {
        $employeeMovement->load([
            'employee:id,nama_lengkap,nik,id_jabatan,id_outlet,division_id,tanggal_masuk',
            'hodApprover:id,nama_lengkap,nik',
            'gmApprover:id,nama_lengkap,nik',
            'gmHrApprover:id,nama_lengkap,nik',
            'bodApprover:id,nama_lengkap,nik',
            'approvalFlows.approver.jabatan'
        ]);
        
        // Convert IDs to names for display
        $movementData = $employeeMovement->toArray();
        
        // Convert unit_property_from (outlet ID) to outlet name
        if ($movementData['unit_property_from']) {
            $outletFrom = DB::table('tbl_data_outlet')
                ->where('id_outlet', $movementData['unit_property_from'])
                ->value('nama_outlet');
            $movementData['unit_property_from'] = $outletFrom ?: $movementData['unit_property_from'];
        }
        
        // Convert unit_property_to (outlet ID) to outlet name
        if ($movementData['unit_property_to']) {
            $outletTo = DB::table('tbl_data_outlet')
                ->where('id_outlet', $movementData['unit_property_to'])
                ->value('nama_outlet');
            $movementData['unit_property_to'] = $outletTo ?: $movementData['unit_property_to'];
        }
        
        // Convert division_from (division ID) to division name
        if ($movementData['division_from']) {
            $divisionFrom = DB::table('tbl_data_divisi')
                ->where('id', $movementData['division_from'])
                ->value('nama_divisi');
            $movementData['division_from'] = $divisionFrom ?: $movementData['division_from'];
        }
        
        // Convert division_to (division ID) to division name
        if ($movementData['division_to']) {
            $divisionTo = DB::table('tbl_data_divisi')
                ->where('id', $movementData['division_to'])
                ->value('nama_divisi');
            $movementData['division_to'] = $divisionTo ?: $movementData['division_to'];
        }
        
        // Convert position_from (jabatan ID) to position name
        if ($movementData['position_from']) {
            $positionFrom = DB::table('tbl_data_jabatan')
                ->where('id_jabatan', $movementData['position_from'])
                ->value('nama_jabatan');
            $movementData['position_from'] = $positionFrom ?: $movementData['position_from'];
        }
        
        // Convert position_to (jabatan ID) to position name
        if ($movementData['position_to']) {
            $positionTo = DB::table('tbl_data_jabatan')
                ->where('id_jabatan', $movementData['position_to'])
                ->value('nama_jabatan');
            $movementData['position_to'] = $positionTo ?: $movementData['position_to'];
        }
        
        // Convert level_from (level ID) to level name
        if ($movementData['level_from']) {
            $levelFrom = DB::table('tbl_data_level')
                ->where('id', $movementData['level_from'])
                ->value('nama_level');
            $movementData['level_from'] = $levelFrom ?: $movementData['level_from'];
        }
        
        // Convert level_to (level ID) to level name
        if ($movementData['level_to']) {
            $levelTo = DB::table('tbl_data_level')
                ->where('id', $movementData['level_to'])
                ->value('nama_level');
            $movementData['level_to'] = $levelTo ?: $movementData['level_to'];
        }
        
        return Inertia::render('EmployeeMovement/Show', [
            'movement' => $movementData,
            'user' => Auth::user(),
        ]);
    }

    public function edit(EmployeeMovement $employeeMovement)
    {
        $employeeMovement->load([
            'employee:id,nama_lengkap,nik,id_jabatan,id_outlet,division_id,tanggal_masuk',
            'approvalFlows.approver.jabatan'
        ]);
        
        return Inertia::render('EmployeeMovement/Edit', [
            'movement' => $employeeMovement,
        ]);
    }

    /**
     * Return pending personal movement approvals for the current approver.
     */
    /**
     * Get pending approvals for API (returns JSON)
     * Alias for pendingApprovals method
     */
    public function getPendingApprovals(Request $request)
    {
        return $this->pendingApprovals($request);
    }

    public function pendingApprovals(Request $request)
    {
        $user = Auth::user();

        // First, get movements with pending approval flows (new system)
        $movementsWithApprovalFlows = EmployeeMovement::whereHas('approvalFlows', function($q) use ($user) {
            $q->where('approver_id', $user->id)
              ->where('status', 'PENDING');
        })
        ->where('status', 'pending')
        ->with(['approvalFlows' => function($q) {
            $q->orderBy('approval_level');
        }, 'approvalFlows.approver.jabatan'])
        ->get()
        ->filter(function($movement) use ($user) {
            // Check if all previous flows are approved
            $pendingFlow = $movement->approvalFlows->firstWhere('approver_id', $user->id);
            if (!$pendingFlow || $pendingFlow->status !== 'PENDING') {
                return false;
            }
            
            $previousFlows = $movement->approvalFlows->where('approval_level', '<', $pendingFlow->approval_level);
            return $previousFlows->every(function($flow) {
                return $flow->status === 'APPROVED';
            });
        });

        // Then, get movements with old approval system (backward compatibility)
        $query = EmployeeMovement::query()
            ->select([
                'id',
                'employee_id',
                'employee_name',
                'employment_type',
                'status',
                'hod_approver_id', 'hod_approval',
                'gm_approver_id', 'gm_approval',
                'gm_hr_approver_id', 'gm_hr_approval',
                'bod_approver_id', 'bod_approval',
                'created_at', 'updated_at'
            ])
            ->whereDoesntHave('approvalFlows') // Only get movements without approval flows
            ->where(function ($q) use ($user) {
                // HOD pending
                $q->orWhere(function ($q2) use ($user) {
                    $q2->where('hod_approver_id', $user->id)
                        ->where(function ($s) {
                            $s->whereNull('hod_approval')
                              ->orWhere('hod_approval', '')
                              ->orWhereRaw("LOWER(hod_approval) = 'pending'");
                        })
                        ->whereRaw("LOWER(status) = 'pending'")
                        ->whereRaw("LOWER(COALESCE(gm_approval, '')) <> 'rejected'")
                        ->whereRaw("LOWER(COALESCE(gm_hr_approval, '')) <> 'rejected'")
                        ->whereRaw("LOWER(COALESCE(bod_approval, '')) <> 'rejected'");
                });
                // GM pending (after HOD approved)
                $q->orWhere(function ($q2) use ($user) {
                    $q2->where('gm_approver_id', $user->id)
                        ->where(function ($s) {
                            $s->whereNull('gm_approval')
                              ->orWhere('gm_approval', '')
                              ->orWhereRaw("LOWER(gm_approval) = 'pending'");
                        })
                        ->whereRaw("LOWER(COALESCE(hod_approval, '')) = 'approved'")
                        ->whereRaw("LOWER(status) = 'pending'")
                        ->whereRaw("LOWER(COALESCE(hod_approval, '')) <> 'rejected'")
                        ->whereRaw("LOWER(COALESCE(gm_hr_approval, '')) <> 'rejected'")
                        ->whereRaw("LOWER(COALESCE(bod_approval, '')) <> 'rejected'");
                });
                // GM HR pending (after GM approved)
                $q->orWhere(function ($q2) use ($user) {
                    $q2->where('gm_hr_approver_id', $user->id)
                        ->where(function ($s) {
                            $s->whereNull('gm_hr_approval')
                              ->orWhere('gm_hr_approval', '')
                              ->orWhereRaw("LOWER(gm_hr_approval) = 'pending'");
                        })
                        ->whereRaw("LOWER(COALESCE(gm_approval, '')) = 'approved'")
                        ->whereRaw("LOWER(status) = 'pending'")
                        ->whereRaw("LOWER(COALESCE(hod_approval, '')) <> 'rejected'")
                        ->whereRaw("LOWER(COALESCE(gm_approval, '')) <> 'rejected'")
                        ->whereRaw("LOWER(COALESCE(bod_approval, '')) <> 'rejected'");
                });
                // BOD pending (after GM HR approved)
                $q->orWhere(function ($q2) use ($user) {
                    $q2->where('bod_approver_id', $user->id)
                        ->where(function ($s) {
                            $s->whereNull('bod_approval')
                              ->orWhere('bod_approval', '')
                              ->orWhereRaw("LOWER(bod_approval) = 'pending'");
                        })
                        ->whereRaw("LOWER(COALESCE(gm_hr_approval, '')) = 'approved'")
                        ->whereRaw("LOWER(status) = 'pending'")
                        ->whereRaw("LOWER(COALESCE(hod_approval, '')) <> 'rejected'")
                        ->whereRaw("LOWER(COALESCE(gm_approval, '')) <> 'rejected'")
                        ->whereRaw("LOWER(COALESCE(gm_hr_approval, '')) <> 'rejected'");
                });
            })
            ->orderByDesc('created_at');

        $limit = (int) $request->input('limit', 100);
        $movementsOld = $query->limit($limit)->get();

        // Merge and limit results
        $allMovements = $movementsWithApprovalFlows->merge($movementsOld)->take($limit);

        return response()->json([
            'success' => true,
            'data' => $allMovements->values(),
        ]);
    }

    /**
     * Debug endpoint: return essential fields for a specific movement as JSON
     */
    public function debugMovement($id)
    {
        $movement = EmployeeMovement::with([
            'employee:id,nama_lengkap,nik,id_jabatan,id_outlet,division_id',
            'employee.jabatan:id_jabatan,nama_jabatan',
            'employee.outlet:id_outlet,nama_outlet',
            'employee.divisi:id,nama_divisi',
            'hodApprover:id,nama_lengkap',
            'gmApprover:id,nama_lengkap',
            'gmHrApprover:id,nama_lengkap',
            'bodApprover:id,nama_lengkap',
            'approvalFlows.approver.jabatan'
        ])
            ->where('employee_movements.id', $id)
            ->first();

        if (!$movement) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Get current employee data from users table
        $employee = $movement->employee;
        if ($employee) {
            // Add employee current data to movement object for frontend
            $movement->employee_jabatan = $employee->jabatan ? $employee->jabatan->nama_jabatan : null;
            $movement->employee_outlet = $employee->outlet ? $employee->outlet->nama_outlet : null;
            $movement->employee_divisi = $employee->divisi ? $employee->divisi->nama_divisi : null;
            $movement->employee_nama_lengkap = $employee->nama_lengkap;
        }

        // Convert IDs to names for display (same as show method)
        $movementData = $movement->toArray();
        
        // Convert unit_property_from (outlet ID) to outlet name
        if ($movementData['unit_property_from']) {
            $outletFrom = DB::table('tbl_data_outlet')
                ->where('id_outlet', $movementData['unit_property_from'])
                ->value('nama_outlet');
            $movementData['unit_property_from'] = $outletFrom ?: $movementData['unit_property_from'];
        }
        
        // Convert unit_property_to (outlet ID) to outlet name
        if ($movementData['unit_property_to']) {
            $outletTo = DB::table('tbl_data_outlet')
                ->where('id_outlet', $movementData['unit_property_to'])
                ->value('nama_outlet');
            $movementData['unit_property_to'] = $outletTo ?: $movementData['unit_property_to'];
        }
        
        // Convert division_from (division ID) to division name
        if ($movementData['division_from']) {
            $divisionFrom = DB::table('tbl_data_divisi')
                ->where('id', $movementData['division_from'])
                ->value('nama_divisi');
            $movementData['division_from'] = $divisionFrom ?: $movementData['division_from'];
        }
        
        // Convert division_to (division ID) to division name
        if ($movementData['division_to']) {
            $divisionTo = DB::table('tbl_data_divisi')
                ->where('id', $movementData['division_to'])
                ->value('nama_divisi');
            $movementData['division_to'] = $divisionTo ?: $movementData['division_to'];
        }
        
        // Convert position_from (jabatan ID) to position name
        if ($movementData['position_from']) {
            $positionFrom = DB::table('tbl_data_jabatan')
                ->where('id_jabatan', $movementData['position_from'])
                ->value('nama_jabatan');
            $movementData['position_from'] = $positionFrom ?: $movementData['position_from'];
        }
        
        // Convert position_to (jabatan ID) to position name
        if ($movementData['position_to']) {
            $positionTo = DB::table('tbl_data_jabatan')
                ->where('id_jabatan', $movementData['position_to'])
                ->value('nama_jabatan');
            $movementData['position_to'] = $positionTo ?: $movementData['position_to'];
        }
        
        // Convert level_from (level ID) to level name
        if ($movementData['level_from']) {
            $levelFrom = DB::table('tbl_data_level')
                ->where('id', $movementData['level_from'])
                ->value('nama_level');
            $movementData['level_from'] = $levelFrom ?: $movementData['level_from'];
        }
        
        // Convert level_to (level ID) to level name
        if ($movementData['level_to']) {
            $levelTo = DB::table('tbl_data_level')
                ->where('id', $movementData['level_to'])
                ->value('nama_level');
            $movementData['level_to'] = $levelTo ?: $movementData['level_to'];
        }

        return response()->json(['success' => true, 'data' => $movementData]);
    }

    public function update(Request $request, EmployeeMovement $employeeMovement)
    {
        $processedRequest = $this->preprocessRequestData($request);

        $validated = $processedRequest->validate([
            'employee_id' => 'required|exists:users,id',
            'employee_name' => 'required|string|max:255',
            'employee_position' => 'nullable|string|max:255',
            'employee_division' => 'nullable|string|max:255',
            'employee_unit_property' => 'nullable|string|max:255',
            'employee_join_date' => 'nullable|date',
            'employment_type' => 'nullable|in:extend_contract_without_adjustment,extend_contract_with_adjustment,promotion,demotion,mutation,termination',
            'employment_effective_date' => 'nullable|date',
            'kpi_required' => 'boolean',
            'kpi_date' => 'nullable|date',
            'psikotest_required' => 'boolean',
            'psikotest_score' => 'nullable|string|max:50',
            'psikotest_date' => 'nullable|date',
            'training_attendance_required' => 'boolean',
            'training_attendance_date' => 'nullable|date',
            'position_change' => 'boolean',
            'position_from' => 'nullable|string|max:255',
            'position_to' => 'nullable|string|max:255',
            'level_change' => 'boolean',
            'level_from' => 'nullable|string|max:255',
            'level_to' => 'nullable|string|max:255',
            'salary_change' => 'boolean',
            'salary_from' => 'nullable|numeric|min:0',
            'salary_to' => 'nullable|numeric|min:0',
            'department_change' => 'boolean',
            'department_from' => 'nullable|string|max:255',
            'department_to' => 'nullable|string|max:255',
            'division_change' => 'boolean',
            'division_from' => 'nullable|string|max:255',
            'division_to' => 'nullable|string|max:255',
            'unit_property_change' => 'boolean',
            'unit_property_from' => 'nullable|string|max:255',
            'unit_property_to' => 'nullable|string|max:255',
            'comments' => 'nullable|string',
            'kpi_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'psikotest_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'training_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'other_attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'hod_approver_id' => 'nullable|exists:users,id',
            'gm_approver_id' => 'nullable|exists:users,id',
            'gm_hr_approver_id' => 'nullable|exists:users,id',
            'bod_approver_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:draft,pending,approved,rejected,executed,error',
        ]);

        // Handle file uploads
        if ($request->hasFile('kpi_attachment')) {
            // Delete old file if exists
            if ($employeeMovement->kpi_attachment) {
                \Storage::disk('public')->delete($employeeMovement->kpi_attachment);
            }
            $validated['kpi_attachment'] = $request->file('kpi_attachment')->store('employee-movements/kpi', 'public');
        }
        
        if ($request->hasFile('psikotest_attachment')) {
            // Delete old file if exists
            if ($employeeMovement->psikotest_attachment) {
                \Storage::disk('public')->delete($employeeMovement->psikotest_attachment);
            }
            $validated['psikotest_attachment'] = $request->file('psikotest_attachment')->store('employee-movements/psikotest', 'public');
        }
        
        if ($request->hasFile('training_attachment')) {
            // Delete old file if exists
            if ($employeeMovement->training_attachment) {
                \Storage::disk('public')->delete($employeeMovement->training_attachment);
            }
            $validated['training_attachment'] = $request->file('training_attachment')->store('employee-movements/training', 'public');
        }
        
        if ($request->hasFile('other_attachments')) {
            // Delete old files if exist
            if ($employeeMovement->other_attachments) {
                $oldAttachments = json_decode($employeeMovement->other_attachments, true);
                foreach ($oldAttachments as $oldFile) {
                    \Storage::disk('public')->delete($oldFile);
                }
            }
            
            $otherAttachments = [];
            foreach ($request->file('other_attachments') as $file) {
                $otherAttachments[] = $file->store('employee-movements/other', 'public');
            }
            $validated['other_attachments'] = json_encode($otherAttachments);
        }

        DB::beginTransaction();
        try {
            $employeeMovement->update($validated);

            // Update approval flows if approvers provided (new system)
            // Only update if status is draft (can still edit approval flows)
            if ($validated['status'] === 'draft' && !empty($validated['approvers']) && is_array($validated['approvers'])) {
                // Delete existing approval flows
                $employeeMovement->approvalFlows()->delete();
                
                // Create new approval flows
                foreach ($validated['approvers'] as $index => $approverId) {
                    EmployeeMovementApprovalFlow::create([
                        'employee_movement_id' => $employeeMovement->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1,
                        'status' => 'PENDING',
                    ]);
                }
            }

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'update',
                'model' => 'EmployeeMovement',
                'model_id' => $employeeMovement->id,
                'description' => "Updated employee movement for {$employeeMovement->employee_name}",
            ]);

            DB::commit();

            // Jika status berubah ke pending, kirim notifikasi berjenjang
            if ($validated['status'] === 'pending') {
                $this->sendApprovalNotifications($employeeMovement);
            }

            return redirect()->route('employee-movements.index')
                            ->with('success', 'Employee movement updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update employee movement: ' . $e->getMessage()]);
        }
    }

    public function destroy(EmployeeMovement $employeeMovement)
    {
        $employeeName = $employeeMovement->employee_name;
        $movementId = $employeeMovement->id;
        
        try {
            // Delete related data first (due to foreign key constraints)
            // Delete approval flows
            $employeeMovement->approvalFlows()->delete();
            
            // Delete file attachments if they exist
            if ($employeeMovement->kpi_attachment) {
                \Storage::disk('public')->delete($employeeMovement->kpi_attachment);
            }
            if ($employeeMovement->psikotest_attachment) {
                \Storage::disk('public')->delete($employeeMovement->psikotest_attachment);
            }
            if ($employeeMovement->training_attachment) {
                \Storage::disk('public')->delete($employeeMovement->training_attachment);
            }
            if ($employeeMovement->other_attachments) {
                $otherAttachments = json_decode($employeeMovement->other_attachments, true);
                if (is_array($otherAttachments)) {
                    foreach ($otherAttachments as $attachment) {
                        if (isset($attachment['file_path'])) {
                            \Storage::disk('public')->delete($attachment['file_path']);
                        }
                    }
                }
            }
            
            // Delete the main record
            $employeeMovement->delete();

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete',
                'model' => 'EmployeeMovement',
                'model_id' => $movementId,
                'description' => "Deleted employee movement for {$employeeName}",
            ]);

            return redirect()->route('employee-movements.index')
                            ->with('success', 'Employee movement deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to delete employee movement: ' . $e->getMessage());
            return redirect()->route('employee-movements.index')
                            ->withErrors(['error' => 'Failed to delete employee movement: ' . $e->getMessage()]);
        }
    }

    public function searchEmployee(Request $request)
    {
        $search = $request->input('search');
        
        $employees = User::where('status', 'A')
                        ->where(function($query) use ($search) {
                            $query->where('nama_lengkap', 'like', "%$search%")
                                  ->orWhere('nik', 'like', "%$search%")
                                  ->orWhere('email', 'like', "%$search%");
                        })
                        ->with(['jabatan:id_jabatan,nama_jabatan,id_level', 'divisi:id,nama_divisi', 'outlet:id_outlet,nama_outlet'])
                        ->select('id', 'nama_lengkap', 'nik', 'email', 'id_jabatan', 'id_outlet', 'division_id', 'tanggal_masuk')
                        ->limit(10)
                        ->get();

        return response()->json($employees);
    }

    public function getEmployeeDetails($id)
    {
        $employee = User::where('id', $id)
                       ->with(['jabatan:id_jabatan,nama_jabatan,id_level', 'divisi:id,nama_divisi', 'outlet:id_outlet,nama_outlet'])
                       ->select('id', 'nama_lengkap', 'nik', 'email', 'id_jabatan', 'id_outlet', 'division_id', 'tanggal_masuk')
                       ->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Get current salary from Payroll_master
        $payrollData = DB::table('payroll_master')
            ->where('user_id', $employee->id)
            ->first();

        $currentSalary = 0;
        if ($payrollData) {
            $currentSalary = $payrollData->gaji + $payrollData->tunjangan;
        }

        // Get current level from jabatan
        $currentLevel = null;
        if ($employee->jabatan && $employee->jabatan->id_level) {
            $currentLevel = DB::table('tbl_data_level')
                ->where('id', $employee->jabatan->id_level)
                ->value('nama_level');
        }
        
        // Debug logging
        \Log::info('Employee Details Debug', [
            'employee_id' => $employee->id,
            'jabatan' => $employee->jabatan,
            'jabatan_id_level' => $employee->jabatan ? $employee->jabatan->id_level : null,
            'current_level' => $currentLevel,
        ]);

        return response()->json([
            'id' => $employee->id,
            'name' => $employee->nama_lengkap,
            'nik' => $employee->nik,
            'position' => $employee->jabatan ? $employee->jabatan->nama_jabatan : null,
            'division' => $employee->divisi ? $employee->divisi->nama_divisi : null,
            'unit_property' => $employee->outlet ? $employee->outlet->nama_outlet : null,
            'join_date' => $employee->tanggal_masuk,
            'current_salary' => $currentSalary,
            'current_level' => $currentLevel,
        ]);
    }

    public function getDropdownData()
    {
        // Get positions (jabatan)
        $positions = DB::table('tbl_data_jabatan')
            ->where('status', 'A')
            ->select('id_jabatan as id', 'nama_jabatan as name')
            ->orderBy('nama_jabatan')
            ->get();

        // Get levels
        $levels = DB::table('tbl_data_level')
            ->where('status', 'A')
            ->select('id', 'nama_level as name')
            ->orderBy('nama_level')
            ->get();

        // Get divisions
        $divisions = DB::table('tbl_data_divisi')
            ->where('status', 'A')
            ->select('id', 'nama_divisi as name')
            ->orderBy('nama_divisi')
            ->get();

        // Get outlets
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return response()->json([
            'success' => true,
            'positions' => $positions,
            'levels' => $levels,
            'divisions' => $divisions,
            'outlets' => $outlets,
        ]);
    }

    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');
        
        $query = User::where('users.status', 'A')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan');
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('users.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('users.email', 'like', "%{$search}%")
                  ->orWhere('users.nik', 'like', "%{$search}%")
                  ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            });
        }
        
        $approvers = $query->select('users.id', 'users.nama_lengkap', 'users.nik', 'users.email', 'users.id_jabatan')
            ->with(['jabatan:id_jabatan,nama_jabatan'])
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'approvers' => $approvers,
        ]);
    }

    /**
     * Kirim notifikasi berjenjang ke approver
     */
    private function sendApprovalNotifications($movement)
    {
        try {
            // Reload movement with approval flows
            $movement->load('approvalFlows.approver');
            
            // Check if using new approval flow system
            if ($movement->approvalFlows && $movement->approvalFlows->count() > 0) {
                // Use new approval flow system - send notification to first approver
                $this->sendNotificationToNextApprover($movement);
            } else {
                // Legacy: Use old approval system
                $creator = Auth::user();
                
                // Tentukan approver pertama yang harus approve
                $nextApprover = $this->getNextApprover($movement);
                
                if ($nextApprover) {
                    $approvalLevel = $this->getApprovalLevel($movement, $nextApprover['id']);
                    
                    DB::table('notifications')->insert([
                        'user_id' => $nextApprover['id'],
                        'task_id' => $movement->id,
                        'type' => 'employee_movement_approval',
                        'message' => "Personal Movement untuk {$movement->employee_name} membutuhkan persetujuan {$approvalLevel} Anda.\n\nJenis: " . ucwords(str_replace('_', ' ', $movement->employment_type)) . "\nDibuat oleh: {$creator->nama_lengkap}",
                        'url' => config('app.url') . '/employee-movements/' . $movement->id,
                        'is_read' => 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send approval notifications', [
                'employee_movement_id' => $movement->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Dapatkan approver berikutnya yang harus approve
     */
    private function getNextApprover($movement)
    {
        // Urutan approval: HOD -> GM -> GM HR -> BOD
        if ($movement->hod_approver_id && !$movement->hod_approval) {
            $approver = User::find($movement->hod_approver_id);
            return $approver ? ['id' => $approver->id, 'name' => $approver->nama_lengkap, 'level' => 'HOD'] : null;
        }
        
        if ($movement->gm_approver_id && !$movement->gm_approval && $movement->hod_approval === 'approved') {
            $approver = User::find($movement->gm_approver_id);
            return $approver ? ['id' => $approver->id, 'name' => $approver->nama_lengkap, 'level' => 'GM'] : null;
        }
        
        if ($movement->gm_hr_approver_id && !$movement->gm_hr_approval && $movement->gm_approval === 'approved') {
            $approver = User::find($movement->gm_hr_approver_id);
            return $approver ? ['id' => $approver->id, 'name' => $approver->nama_lengkap, 'level' => 'GM HR'] : null;
        }
        
        if ($movement->bod_approver_id && !$movement->bod_approval && $movement->gm_hr_approval === 'approved') {
            $approver = User::find($movement->bod_approver_id);
            return $approver ? ['id' => $approver->id, 'name' => $approver->nama_lengkap, 'level' => 'BOD'] : null;
        }
        
        return null;
    }

    /**
     * Dapatkan level approval berdasarkan approver ID
     */
    private function getApprovalLevel($movement, $approverId)
    {
        if ($movement->hod_approver_id == $approverId) return 'HOD';
        if ($movement->gm_approver_id == $approverId) return 'GM';
        if ($movement->gm_hr_approver_id == $approverId) return 'GM HR';
        if ($movement->bod_approver_id == $approverId) return 'BOD';
        return 'Unknown';
    }

    /**
     * Approve employee movement
     */
    public function approve(Request $request, $id)
    {
        $request->validate([
            'approval_flow_id' => 'nullable|exists:employee_movement_approval_flows,id', // New system
            'approval_level' => 'nullable|in:hod,gm,gm_hr,bod', // Legacy system
            'status' => 'required|in:approved,rejected',
            'notes' => 'nullable|string|max:500'
        ]);

        $movement = EmployeeMovement::with('approvalFlows')->findOrFail($id);
        $user = Auth::user();
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';

        try {
            DB::beginTransaction();

            // New approval flow system
            if ($request->has('approval_flow_id')) {
                $approvalFlow = EmployeeMovementApprovalFlow::where('id', $request->approval_flow_id)
                    ->where('employee_movement_id', $movement->id)
                    ->first();

                if (!$approvalFlow) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Approval flow not found'
                    ], 404);
                }

                // Validate that user is the approver (unless superadmin)
                if (!$isSuperadmin && $approvalFlow->approver_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak berhak melakukan approval pada flow ini'
                    ], 403);
                }

                // Validate that previous flows are approved
                if (!$isSuperadmin) {
                    $previousFlows = $movement->approvalFlows()
                        ->where('approval_level', '<', $approvalFlow->approval_level)
                        ->get();
                    
                    $allPreviousApproved = $previousFlows->every(function($flow) {
                        return $flow->status === 'APPROVED';
                    });

                    if (!$allPreviousApproved) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Approval sebelumnya belum selesai'
                        ], 400);
                    }
                }

                // Update approval flow
                if ($request->status === 'approved') {
                    $approvalFlow->approve($request->notes);
                } else {
                    $approvalFlow->reject($request->notes);
                    // If rejected, update movement status and notify creator
                    $movement->update(['status' => 'rejected']);
                    $this->sendNotificationToCreator($movement, 'rejected', $request->notes);
                    
                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => 'Approval berhasil diproses'
                    ]);
                }

                // Check if there are more pending approvers
                $pendingFlows = $movement->approvalFlows()
                    ->where('status', 'PENDING')
                    ->count();

                if ($pendingFlows > 0) {
                    // Still have pending approvers, keep status as pending
                    $movement->update(['status' => 'pending']);
                    // Send notification to next approver
                    $this->sendNotificationToNextApprover($movement);
                } else {
                    // All approvers have approved, update status to approved
                    $movement->update(['status' => 'approved']);
                    
                    // Send notification to creator that movement is fully approved
                    $this->sendNotificationToCreator($movement, 'approved');
                    
                    // Cek apakah effective date sudah tiba, jika ya eksekusi langsung
                    $effectiveDate = $movement->employment_effective_date;
                    if ($effectiveDate && now()->toDateString() >= $effectiveDate) {
                        $this->executeEmployeeMovement($movement);
                    }
                }

                // Log activity
                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'approve',
                    'model' => 'EmployeeMovement',
                    'model_id' => $movement->id,
                    'description' => "Approval flow " . ucfirst($request->status) . " employee movement for {$movement->employee_name}",
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Approval berhasil diproses'
                ]);
            }

            // Legacy approval system (backward compatibility)
            if (!$request->has('approval_level')) {
                return response()->json([
                    'success' => false,
                    'message' => 'approval_level or approval_flow_id is required'
                ], 400);
            }

            // Validasi bahwa user adalah approver yang benar
            if (!$this->isValidApprover($movement, $user, $request->approval_level)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak melakukan approval pada level ini'
                ], 403);
            }

            // Validasi bahwa approval sebelumnya sudah selesai
            if (!$this->isPreviousApprovalCompleted($movement, $request->approval_level)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Approval sebelumnya belum selesai'
                ], 400);
            }

            // Update approval status
            $approvalField = $request->approval_level . '_approval';
            $approvalDateField = $request->approval_level . '_approval_date';
            
            $movement->update([
                $approvalField => $request->status,
                $approvalDateField => now(),
                'status' => $request->status === 'rejected' ? 'rejected' : 'pending'
            ]);

            // Jika semua approval selesai dan approved, set status jadi approved
            if ($request->status === 'approved' && $this->isAllApprovalsCompleted($movement)) {
                $movement->update(['status' => 'approved']);
                
                // Send notification to creator that movement is fully approved
                $this->sendNotificationToCreator($movement, 'approved');
                
                // Cek apakah effective date sudah tiba, jika ya eksekusi langsung
                $effectiveDate = $movement->employment_effective_date;
                if ($effectiveDate && now()->toDateString() >= $effectiveDate) {
                    $this->executeEmployeeMovement($movement);
                }
            } elseif ($request->status === 'rejected') {
                // Send notification to creator that movement was rejected
                $this->sendNotificationToCreator($movement, 'rejected', $request->notes);
            }

            // Log activity
            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'approve',
                'model' => 'EmployeeMovement',
                'model_id' => $movement->id,
                'description' => "{$request->approval_level} " . ucfirst($request->status) . " employee movement for {$movement->employee_name}",
            ]);

            // Kirim notifikasi ke approver berikutnya (legacy system)
            if ($request->status === 'approved') {
                $this->sendNextApprovalNotification($movement);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approval berhasil diproses'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validasi apakah user adalah approver yang benar
     */
    private function isValidApprover($movement, $user, $approvalLevel)
    {
        switch ($approvalLevel) {
            case 'hod':
                return $movement->hod_approver_id == $user->id;
            case 'gm':
                return $movement->gm_approver_id == $user->id;
            case 'gm_hr':
                return $movement->gm_hr_approver_id == $user->id;
            case 'bod':
                return $movement->bod_approver_id == $user->id;
            default:
                return false;
        }
    }

    /**
     * Validasi apakah approval sebelumnya sudah selesai
     */
    private function isPreviousApprovalCompleted($movement, $currentLevel)
    {
        switch ($currentLevel) {
            case 'hod':
                return true; // HOD adalah yang pertama
            case 'gm':
                return $movement->hod_approval === 'approved';
            case 'gm_hr':
                return $movement->gm_approval === 'approved';
            case 'bod':
                return $movement->gm_hr_approval === 'approved';
            default:
                return false;
        }
    }

    /**
     * Cek apakah semua approval sudah selesai
     */
    private function isAllApprovalsCompleted($movement)
    {
        $requiredApprovals = [];
        
        if ($movement->hod_approver_id) $requiredApprovals[] = $movement->hod_approval === 'approved';
        if ($movement->gm_approver_id) $requiredApprovals[] = $movement->gm_approval === 'approved';
        if ($movement->gm_hr_approver_id) $requiredApprovals[] = $movement->gm_hr_approval === 'approved';
        if ($movement->bod_approver_id) $requiredApprovals[] = $movement->bod_approval === 'approved';
        
        return !empty($requiredApprovals) && !in_array(false, $requiredApprovals);
    }

    /**
     * Kirim notifikasi ke approver berikutnya
     */
    private function sendNextApprovalNotification($movement)
    {
        $nextApprover = $this->getNextApprover($movement);
        
        if ($nextApprover) {
            DB::table('notifications')->insert([
                'user_id' => $nextApprover['id'],
                'type' => 'employee_movement_approval',
                'message' => "Personal Movement untuk {$movement->employee_name} telah disetujui dan membutuhkan persetujuan {$nextApprover['level']} Anda.\n\nJenis: " . ucwords(str_replace('_', ' ', $movement->employment_type)),
                'url' => config('app.url') . '/employee-movements/' . $movement->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            // Semua approval selesai, kirim notifikasi ke creator
            $creator = User::find($movement->employee_id);
            if ($creator) {
                DB::table('notifications')->insert([
                    'user_id' => $creator->id,
                    'type' => 'employee_movement_completed',
                    'message' => "Personal Movement untuk {$movement->employee_name} telah disetujui oleh semua pihak.",
                    'url' => config('app.url') . '/employee-movements/' . $movement->id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }

    /**
     * Kirim notifikasi penolakan
     */
    private function sendRejectionNotification($movement, $rejector, $notes)
    {
        $creator = User::find($movement->employee_id);
        if ($creator) {
            $message = "Personal Movement untuk {$movement->employee_name} telah ditolak oleh {$rejector->nama_lengkap}";
            if ($notes) {
                $message .= "\n\nAlasan: {$notes}";
            }
            
            DB::table('notifications')->insert([
                'user_id' => $creator->id,
                'type' => 'employee_movement_rejected',
                'message' => $message,
                'url' => config('app.url') . '/employee-movements/' . $movement->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Update salary for employee movement (HR only)
     */
    public function updateSalary(Request $request, $id)
    {
        $user = Auth::user();
        
        // Validasi bahwa user adalah HR (division_id = 6)
        if ($user->division_id !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya HR yang dapat mengedit salary'
            ], 403);
        }

        $request->validate([
            'gaji_pokok_to' => 'nullable|numeric|min:0',
            'tunjangan_to' => 'nullable|numeric|min:0',
        ]);

        $movement = EmployeeMovement::findOrFail($id);
        
        // Validasi bahwa employment type mengizinkan salary change
        if ($movement->employment_type === 'extend_contract_without_adjustment' || 
            $movement->employment_type === 'termination') {
            return response()->json([
                'success' => false,
                'message' => 'Salary tidak dapat diubah untuk employment type ini'
            ], 400);
        }

        try {
            // Hitung total salary
            $gajiPokok = $request->gaji_pokok_to ? (int)str_replace(['.', ','], '', $request->gaji_pokok_to) : 0;
            $tunjangan = $request->tunjangan_to ? (int)str_replace(['.', ','], '', $request->tunjangan_to) : 0;
            $totalSalary = $gajiPokok + $tunjangan;

            $movement->update([
                'gaji_pokok_to' => $gajiPokok,
                'tunjangan_to' => $tunjangan,
                'salary_to' => $totalSalary,
            ]);

            // Log activity
            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'update_salary',
                'model' => 'EmployeeMovement',
                'model_id' => $movement->id,
                'description' => "Updated salary for employee movement {$movement->employee_name}",
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Salary berhasil diperbarui'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eksekusi perubahan employee setelah semua approval selesai
     */
    private function executeEmployeeMovement($movement)
    {
        try {
            DB::beginTransaction();

            $employee = User::find($movement->employee_id);
            if (!$employee) {
                throw new \Exception('Employee not found');
            }

            $effectiveDate = $movement->employment_effective_date;
            $now = now();

            // 1. Jika position diubah, ubah id_jabatan di users
            if ($movement->position_change && $movement->position_to) {
                $employee->update(['id_jabatan' => $movement->position_to]);
                
                // Log activity
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'position_change',
                    'model' => 'User',
                    'model_id' => $employee->id,
                    'description' => "Position changed from {$movement->position_from} to {$movement->position_to} for {$employee->nama_lengkap}",
                ]);
            }

            // 2. Jika level diubah, ubah id_level di tbl_data_jabatan
            if ($movement->level_change && $movement->level_to) {
                // Ambil jabatan yang baru
                $newJabatan = DB::table('tbl_data_jabatan')
                    ->where('id_jabatan', $movement->position_to)
                    ->first();
                
                if ($newJabatan) {
                    // Update id_level di tbl_data_jabatan
                    DB::table('tbl_data_jabatan')
                        ->where('id_jabatan', $movement->position_to)
                        ->update(['id_level' => $movement->level_to]);
                    
                    // Log activity
                    ActivityLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'level_change',
                        'model' => 'Jabatan',
                        'model_id' => $movement->position_to,
                        'description' => "Level changed from {$movement->level_from} to {$movement->level_to} for position {$newJabatan->nama_jabatan}",
                    ]);
                }
            }

            // 3. Jika salary diubah, ubah gaji dan tunjangan di payroll_master
            if ($movement->salary_change && $movement->salary_to) {
                // Cek apakah sudah ada data di payroll_master
                $payrollData = DB::table('payroll_master')
                    ->where('user_id', $employee->id)
                    ->first();

                if ($payrollData) {
                    // Update existing payroll data
                    DB::table('payroll_master')
                        ->where('user_id', $employee->id)
                        ->update([
                            'gaji' => $movement->gaji_pokok_to,
                            'tunjangan' => $movement->tunjangan_to,
                            'updated_at' => $now
                        ]);
                } else {
                    // Create new payroll data
                    DB::table('payroll_master')->insert([
                        'user_id' => $employee->id,
                        'outlet_id' => $employee->id_outlet,
                        'division_id' => $employee->division_id,
                        'gaji' => $movement->gaji_pokok_to,
                        'tunjangan' => $movement->tunjangan_to,
                        'ot' => 0,
                        'um' => 0,
                        'ph' => 0,
                        'sc' => 0,
                        'bpjs_jkn' => 0,
                        'bpjs_tk' => 0,
                        'lb' => 0,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                }
                
                // Log activity
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'salary_change',
                    'model' => 'PayrollMaster',
                    'model_id' => $employee->id,
                    'description' => "Salary changed from {$movement->salary_from} to {$movement->salary_to} for {$employee->nama_lengkap}",
                ]);
            }

            // 4. Jika division diubah, ubah division_id di users
            if ($movement->division_change && $movement->division_to) {
                $employee->update(['division_id' => $movement->division_to]);
                
                // Log activity
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'division_change',
                    'model' => 'User',
                    'model_id' => $employee->id,
                    'description' => "Division changed from {$movement->division_from} to {$movement->division_to} for {$employee->nama_lengkap}",
                ]);
            }

            // 5. Jika unit/property diubah, ubah id_outlet di users
            if ($movement->unit_property_change && $movement->unit_property_to) {
                $employee->update(['id_outlet' => $movement->unit_property_to]);
                
                // Log activity
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'outlet_change',
                    'model' => 'User',
                    'model_id' => $employee->id,
                    'description' => "Outlet changed from {$movement->unit_property_from} to {$movement->unit_property_to} for {$employee->nama_lengkap}",
                ]);
            }

            // 6. Jika employment type adalah Termination, set status='N'
            if ($movement->employment_type === 'termination') {
                $employee->update(['status' => 'N']);
                
                // Log activity
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'termination',
                    'model' => 'User',
                    'model_id' => $employee->id,
                    'description' => "Employee {$employee->nama_lengkap} terminated",
                ]);
            }

            // Update movement status menjadi executed
            $movement->update(['status' => 'executed']);

            DB::commit();

            // Kirim notifikasi ke creator bahwa perubahan telah dieksekusi
            $this->sendExecutionNotification($movement);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error executing employee movement', [
                'movement_id' => $movement->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Update movement status menjadi error
            $movement->update(['status' => 'error']);
            
            throw $e;
        }
    }

    /**
     * Kirim notifikasi bahwa perubahan telah dieksekusi
     */
    private function sendExecutionNotification($movement)
    {
        $creator = User::find($movement->employee_id);
        if ($creator) {
            $changes = [];
            
            if ($movement->position_change) $changes[] = 'Position';
            if ($movement->level_change) $changes[] = 'Level';
            if ($movement->salary_change) $changes[] = 'Salary';
            if ($movement->division_change) $changes[] = 'Division';
            if ($movement->unit_property_change) $changes[] = 'Unit/Property';
            if ($movement->employment_type === 'termination') $changes[] = 'Termination';
            
            $changesText = !empty($changes) ? implode(', ', $changes) : 'No changes';
            
            DB::table('notifications')->insert([
                'user_id' => $creator->id,
                'type' => 'employee_movement_executed',
                'message' => "Personal Movement untuk {$movement->employee_name} telah dieksekusi. Perubahan yang diterapkan: {$changesText}",
                'url' => config('app.url') . '/employee-movements/' . $movement->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Send notification to next approver in the approval flow
     */
    private function sendNotificationToNextApprover($movement)
    {
        try {
            // Get the lowest level approver that is still pending
            $nextApprover = $movement->approvalFlows()
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

            // Get creator details (employee who created the movement)
            $creator = User::find($movement->employee_id);
            $creatorName = $creator ? $creator->nama_lengkap : 'Unknown User';

            // Create notification message
            $message = "Employee Movement baru memerlukan persetujuan Anda:\n\n";
            $message .= "Karyawan: {$movement->employee_name}\n";
            $message .= "Jenis: " . str_replace('_', ' ', $movement->employment_type) . "\n";
            $message .= "Level Approval: {$nextApprover->approval_level}\n";
            $message .= "Diajukan oleh: {$creatorName}\n\n";
            $message .= "Silakan segera lakukan review dan approval.";

            // Insert notification
            DB::table('notifications')->insert([
                'user_id' => $approver->id,
                'task_id' => $movement->id,
                'type' => 'employee_movement_approval',
                'message' => $message,
                'url' => config('app.url') . '/employee-movements/' . $movement->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send notification to next approver', [
                'employee_movement_id' => $movement->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send notification to creator about movement status
     */
    private function sendNotificationToCreator($movement, $status, $rejectionNotes = null)
    {
        try {
            // Get creator (employee who created the movement)
            $creator = User::find($movement->employee_id);
            if (!$creator) {
                return;
            }

            $message = '';
            $type = '';

            switch ($status) {
                case 'approved':
                    $message = "Employee Movement Anda telah disetujui:\n\n";
                    $message .= "Karyawan: {$movement->employee_name}\n";
                    $message .= "Jenis: " . str_replace('_', ' ', $movement->employment_type) . "\n";
                    if ($movement->employment_effective_date) {
                        $message .= "Tanggal Efektif: " . date('d M Y', strtotime($movement->employment_effective_date)) . "\n";
                    }
                    $message .= "\nEmployee Movement telah disetujui oleh semua approver dan siap untuk dieksekusi.";
                    $type = 'employee_movement_approved';
                    break;
                
                case 'rejected':
                    $message = "Employee Movement Anda telah ditolak:\n\n";
                    $message .= "Karyawan: {$movement->employee_name}\n";
                    $message .= "Jenis: " . str_replace('_', ' ', $movement->employment_type) . "\n";
                    $message .= "\nAlasan penolakan: " . ($rejectionNotes ?? 'Tidak ada alasan yang diberikan');
                    $type = 'employee_movement_rejected';
                    break;
            }

            // Insert notification
            DB::table('notifications')->insert([
                'user_id' => $creator->id,
                'task_id' => $movement->id,
                'type' => $type,
                'message' => $message,
                'url' => config('app.url') . '/employee-movements/' . $movement->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send notification to creator', [
                'employee_movement_id' => $movement->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Manual execution of approved employee movement (for admin/HR)
     */
    public function executeMovement($id)
    {
        $user = Auth::user();
        
        // Validasi bahwa user adalah HR (division_id = 6) atau superadmin
        if ($user->division_id !== 6 && $user->id_role !== '5af56935b011a') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya HR atau superadmin yang dapat mengeksekusi movement'
            ], 403);
        }

        $movement = EmployeeMovement::findOrFail($id);
        
        if ($movement->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Movement harus dalam status approved untuk dieksekusi'
            ], 400);
        }

        try {
            $this->executeEmployeeMovement($movement);
            
            return response()->json([
                'success' => true,
                'message' => 'Employee movement berhasil dieksekusi'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}
