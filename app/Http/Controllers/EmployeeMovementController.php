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
        $validated = $request->validate([
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
            'adjustment_effective_date' => 'nullable|date',
            'comments' => 'nullable|string',
            'kpi_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'psikotest_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'training_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'other_attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'hod_approver_id' => 'nullable|exists:users,id',
            'gm_approver_id' => 'nullable|exists:users,id',
            'gm_hr_approver_id' => 'nullable|exists:users,id',
            'bod_approver_id' => 'nullable|exists:users,id',
            'status' => 'required|in:draft,pending,approved,rejected',
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
        $validated = $request->validate([
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
            'adjustment_effective_date' => 'nullable|date',
            'comments' => 'nullable|string',
            'kpi_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'psikotest_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'training_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'other_attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'hod_approver_id' => 'nullable|exists:users,id',
            'gm_approver_id' => 'nullable|exists:users,id',
            'gm_hr_approver_id' => 'nullable|exists:users,id',
            'bod_approver_id' => 'nullable|exists:users,id',
            'status' => 'required|in:draft,pending,approved,rejected',
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
}
