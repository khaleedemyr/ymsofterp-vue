<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyReportProgress extends Model
{
    use HasFactory;

    protected $table = 'daily_report_progress';
    
    protected $fillable = [
        'daily_report_id',
        'area_id',
        'progress_status',
        'form_data',
        'completed_at'
    ];

    protected $casts = [
        'form_data' => 'array',
        'completed_at' => 'datetime',
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

    // Scopes
    public function scopePending($query)
    {
        return $query->where('progress_status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('progress_status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('progress_status', 'completed');
    }

    public function scopeSkipped($query)
    {
        return $query->where('progress_status', 'skipped');
    }

    public function scopeByArea($query, $areaId)
    {
        return $query->where('area_id', $areaId);
    }

    // Helper methods
    public function isPending()
    {
        return $this->progress_status === 'pending';
    }

    public function isInProgress()
    {
        return $this->progress_status === 'in_progress';
    }

    public function isCompleted()
    {
        return $this->progress_status === 'completed';
    }

    public function isSkipped()
    {
        return $this->progress_status === 'skipped';
    }

    public function getStatusText()
    {
        return match($this->progress_status) {
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'skipped' => 'Skipped',
            default => 'Unknown'
        };
    }

    public function getStatusColor()
    {
        return match($this->progress_status) {
            'pending' => 'gray',
            'in_progress' => 'blue',
            'completed' => 'green',
            'skipped' => 'yellow',
            default => 'gray'
        };
    }

    public function getStatusIcon()
    {
        return match($this->progress_status) {
            'pending' => 'fa-clock',
            'in_progress' => 'fa-spinner',
            'completed' => 'fa-check-circle',
            'skipped' => 'fa-forward',
            default => 'fa-question'
        };
    }

    public function markAsCompleted($formData = null)
    {
        $this->update([
            'progress_status' => 'completed',
            'form_data' => $formData,
            'completed_at' => now()
        ]);
    }

    public function markAsSkipped()
    {
        $this->update([
            'progress_status' => 'skipped',
            'completed_at' => now()
        ]);
    }

    public function markAsInProgress()
    {
        $this->update([
            'progress_status' => 'in_progress'
        ]);
    }
}
