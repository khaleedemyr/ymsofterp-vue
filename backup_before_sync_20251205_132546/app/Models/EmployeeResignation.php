<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeResignation extends Model
{
    use HasFactory;

    protected $table = 'employee_resignations';

    protected $fillable = [
        'resignation_number',
        'outlet_id',
        'employee_id',
        'resignation_date',
        'resignation_type', // 'prosedural' or 'non_prosedural'
        'notes',
        'status', // 'draft', 'submitted', 'approved', 'rejected'
        'created_by',
    ];

    protected $casts = [
        'resignation_date' => 'date',
    ];

    // Relationships
    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function approvalFlows()
    {
        return $this->hasMany(EmployeeResignationApprovalFlow::class, 'employee_resignation_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}

