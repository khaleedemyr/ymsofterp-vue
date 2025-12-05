<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeMovement extends Model
{
    use HasFactory;

    protected $table = 'employee_movements';

                   protected $fillable = [
                   'employee_id',
                   'employee_name',
                   'employee_position',
                   'employee_division',
                   'employee_unit_property',
                   'employee_join_date',
                   'employment_type',
                   'employment_effective_date',
        'kpi_required',
        'kpi_date',
        'psikotest_required',
        'psikotest_score',
        'psikotest_date',
        'training_attendance_required',
        'training_attendance_date',
        'position_change',
        'position_from',
        'position_to',
        'level_change',
        'level_from',
        'level_to',
        'salary_change',
        'salary_from',
        'salary_to',
        'gaji_pokok_to',
        'tunjangan_to',
        'department_change',
        'department_from',
        'department_to',
        'division_change',
        'division_from',
        'division_to',
        'unit_property_change',
                 'unit_property_from',
         'unit_property_to',
         'comments',
         'kpi_attachment',
         'psikotest_attachment',
         'training_attachment',
         'other_attachments',
         'hod_approval',
        'hod_approval_date',
        'hod_approver_id',
        'gm_approval',
        'gm_approval_date',
        'gm_approver_id',
        'gm_hr_approval',
        'gm_hr_approval_date',
        'gm_hr_approver_id',
        'bod_approval',
        'bod_approval_date',
        'bod_approver_id',
        'status',
    ];

                   protected $casts = [
                   'employee_join_date' => 'date',
                   'employment_effective_date' => 'date',
                   'kpi_date' => 'date',
                   'psikotest_date' => 'date',
                   'training_attendance_date' => 'date',
                   'hod_approval_date' => 'datetime',
                   'gm_approval_date' => 'datetime',
                   'gm_hr_approval_date' => 'datetime',
                   'bod_approval_date' => 'datetime',
                   'kpi_required' => 'boolean',
                   'psikotest_required' => 'boolean',
                   'training_attendance_required' => 'boolean',
                   'position_change' => 'boolean',
                   'level_change' => 'boolean',
                   'salary_change' => 'boolean',
                   'department_change' => 'boolean',
                   'division_change' => 'boolean',
                   'unit_property_change' => 'boolean',
                   'salary_from' => 'decimal:2',
                   'salary_to' => 'decimal:2',
               ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id', 'id');
    }

    public function hodApprover()
    {
        return $this->belongsTo(User::class, 'hod_approver_id', 'id');
    }

    public function gmApprover()
    {
        return $this->belongsTo(User::class, 'gm_approver_id', 'id');
    }

    public function gmHrApprover()
    {
        return $this->belongsTo(User::class, 'gm_hr_approver_id', 'id');
    }

    public function bodApprover()
    {
        return $this->belongsTo(User::class, 'bod_approver_id', 'id');
    }

    public function approvalFlows()
    {
        return $this->hasMany(EmployeeMovementApprovalFlow::class)->orderedByLevel();
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeExecuted($query)
    {
        return $query->where('status', 'executed');
    }

    public function scopeError($query)
    {
        return $query->where('status', 'error');
    }
}
