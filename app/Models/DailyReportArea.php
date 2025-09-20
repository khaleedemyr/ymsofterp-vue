<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyReportArea extends Model
{
    use HasFactory;

    protected $table = 'daily_report_areas';
    
    protected $fillable = [
        'daily_report_id',
        'area_id',
        'status',
        'finding_problem',
        'dept_concern_id',
        'documentation'
    ];

    protected $casts = [
        'documentation' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function dailyReport()
    {
        return $this->belongsTo(DailyReport::class, 'daily_report_id', 'id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id', 'id');
    }

    public function deptConcern()
    {
        return $this->belongsTo(Divisi::class, 'dept_concern_id', 'id');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeGood($query)
    {
        return $query->where('status', 'G');
    }

    public function scopeNotGood($query)
    {
        return $query->where('status', 'NG');
    }

    public function scopeNotAvailable($query)
    {
        return $query->where('status', 'NA');
    }

    public function scopeWithDocumentation($query)
    {
        return $query->whereNotNull('documentation');
    }

    // Helper methods
    public function hasDocumentation()
    {
        return !empty($this->documentation) && count($this->documentation) > 0;
    }

    public function getDocumentationCount()
    {
        return $this->hasDocumentation() ? count($this->documentation) : 0;
    }

    public function getStatusText()
    {
        return match($this->status) {
            'G' => 'Good',
            'NG' => 'Not Good',
            'NA' => 'Not Available',
            default => 'Unknown'
        };
    }

    public function getStatusColor()
    {
        return match($this->status) {
            'G' => 'green',
            'NG' => 'red',
            'NA' => 'yellow',
            default => 'gray'
        };
    }

    public function isGood()
    {
        return $this->status === 'G';
    }

    public function isNotGood()
    {
        return $this->status === 'NG';
    }

    public function isNotAvailable()
    {
        return $this->status === 'NA';
    }
}
