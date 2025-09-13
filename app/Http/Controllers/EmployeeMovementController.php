<?php

namespace App\Http\Controllers;

use App\Models\EmployeeMovement;
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
            'status' => 'required|in:draft,pending,approved,rejected,executed,error',
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

        $movement = EmployeeMovement::create($validated);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'create',
            'model' => 'EmployeeMovement',
            'model_id' => $movement->id,
            'description' => "Created employee movement for {$movement->employee_name}",
        ]);

        // Kirim notifikasi berjenjang ke approver pertama
        $this->sendApprovalNotifications($movement);

        return redirect()->route('employee-movements.index')
                        ->with('success', 'Employee movement created successfully.');
    }

    public function show(EmployeeMovement $employeeMovement)
    {
        $employeeMovement->load([
            'employee:id,nama_lengkap,nik,id_jabatan,id_outlet,division_id,tanggal_masuk',
            'hodApprover:id,nama_lengkap,nik',
            'gmApprover:id,nama_lengkap,nik',
            'gmHrApprover:id,nama_lengkap,nik',
            'bodApprover:id,nama_lengkap,nik'
        ]);
        
        return Inertia::render('EmployeeMovement/Show', [
            'movement' => $employeeMovement,
            'user' => Auth::user(),
        ]);
    }

    public function edit(EmployeeMovement $employeeMovement)
    {
        $employeeMovement->load(['employee:id,nama_lengkap,nik,id_jabatan,id_outlet,division_id,tanggal_masuk']);
        
        return Inertia::render('EmployeeMovement/Edit', [
            'movement' => $employeeMovement,
        ]);
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
            'status' => 'required|in:draft,pending,approved,rejected,executed,error',
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

        $employeeMovement->update($validated);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'model' => 'EmployeeMovement',
            'model_id' => $employeeMovement->id,
            'description' => "Updated employee movement for {$employeeMovement->employee_name}",
        ]);

        // Jika status berubah ke pending, kirim notifikasi berjenjang
        if ($validated['status'] === 'pending') {
            $this->sendApprovalNotifications($employeeMovement);
        }

        return redirect()->route('employee-movements.index')
                        ->with('success', 'Employee movement updated successfully.');
    }

    public function destroy(EmployeeMovement $employeeMovement)
    {
        $employeeName = $employeeMovement->employee_name;
        $employeeMovement->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'delete',
            'model' => 'EmployeeMovement',
            'model_id' => $employeeMovement->id,
            'description' => "Deleted employee movement for {$employeeName}",
        ]);

        return redirect()->route('employee-movements.index')
                        ->with('success', 'Employee movement deleted successfully.');
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

    public function getApprovers()
    {
        $approvers = User::where('status', 'A')
                        ->select('id', 'nama_lengkap', 'nik', 'id_jabatan')
                        ->with(['jabatan:id_jabatan,nama_jabatan'])
                        ->orderBy('nama_lengkap')
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
        $creator = Auth::user();
        
        // Tentukan approver pertama yang harus approve
        $nextApprover = $this->getNextApprover($movement);
        
        if ($nextApprover) {
            $approvalLevel = $this->getApprovalLevel($movement, $nextApprover['id']);
            
            DB::table('notifications')->insert([
                'user_id' => $nextApprover['id'],
                'type' => 'employee_movement_approval',
                'message' => "Personal Movement untuk {$movement->employee_name} membutuhkan persetujuan {$approvalLevel} Anda.\n\nJenis: " . ucwords(str_replace('_', ' ', $movement->employment_type)) . "\nDibuat oleh: {$creator->nama_lengkap}",
                'url' => config('app.url') . '/employee-movements/' . $movement->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
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
            'approval_level' => 'required|in:hod,gm,gm_hr,bod',
            'status' => 'required|in:approved,rejected',
            'notes' => 'nullable|string|max:500'
        ]);

        $movement = EmployeeMovement::findOrFail($id);
        $user = Auth::user();
        
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

        try {
            DB::beginTransaction();

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
                'description' => "{$request->approval_level} " . ucfirst($request->status) . " employee movement for {$movement->employee_name}",
            ]);

            // Kirim notifikasi ke approver berikutnya atau notifikasi selesai
            if ($request->status === 'approved') {
                $this->sendNextApprovalNotification($movement);
            } else {
                $this->sendRejectionNotification($movement, $user, $request->notes);
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
