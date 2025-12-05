<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyReport extends Model
{
    use HasFactory;

    protected $table = 'daily_reports';
    
    protected $fillable = [
        'outlet_id',
        'inspection_time',
        'department_id',
        'user_id',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function department()
    {
        return $this->belongsTo(Departemen::class, 'department_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function reportAreas()
    {
        return $this->hasMany(DailyReportArea::class, 'daily_report_id', 'id');
    }

    public function progress()
    {
        return $this->hasMany(DailyReportProgress::class, 'daily_report_id', 'id');
    }

    public function briefing()
    {
        return $this->hasOne(DailyReportBriefing::class, 'daily_report_id', 'id');
    }

    public function productivity()
    {
        return $this->hasOne(DailyReportProductivity::class, 'daily_report_id', 'id');
    }

    public function visitTables()
    {
        return $this->hasMany(DailyReportVisitTable::class, 'daily_report_id', 'id');
    }

    public function summaries()
    {
        return $this->hasMany(DailyReportSummary::class, 'daily_report_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(DailyReportComment::class, 'daily_report_id', 'id');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByOutlet($query, $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeByInspectionTime($query, $inspectionTime)
    {
        return $query->where('inspection_time', $inspectionTime);
    }

    // Helper methods
    public function getProgressPercentage()
    {
        $totalAreas = $this->progress()->count();
        $completedAreas = $this->progress()->where('status', 'completed')->count();
        
        return $totalAreas > 0 ? round(($completedAreas / $totalAreas) * 100, 2) : 0;
    }

    public function getCompletedAreasCount()
    {
        return $this->progress()->where('status', 'completed')->count();
    }

    public function getTotalAreasCount()
    {
        return $this->progress()->count();
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isDraft()
    {
        return $this->status === 'draft';
    }

    /**
     * Calculate inspection rating based on G and NG areas (excluding NA)
     */
    public function getInspectionRating()
    {
        $totalInspectedAreas = $this->reportAreas()
            ->whereIn('status', ['G', 'NG'])
            ->count();
        
        $goodAreas = $this->reportAreas()
            ->where('status', 'G')
            ->count();
        
        if ($totalInspectedAreas === 0) {
            return 0;
        }
        
        return round(($goodAreas / $totalInspectedAreas) * 100, 2);
    }

    /**
     * Get inspection statistics
     */
    public function getInspectionStats()
    {
        $totalAreas = $this->reportAreas()->count();
        $goodAreas = $this->reportAreas()->where('status', 'G')->count();
        $notGoodAreas = $this->reportAreas()->where('status', 'NG')->count();
        $notAvailableAreas = $this->reportAreas()->where('status', 'NA')->count();
        $inspectedAreas = $goodAreas + $notGoodAreas; // G + NG
        
        $ratingPercentage = $inspectedAreas > 0 ? round(($goodAreas / $inspectedAreas) * 100, 2) : 0;
        $starRating = $this->calculateStarRating($ratingPercentage);
        
        return [
            'total_areas' => $totalAreas,
            'good_areas' => $goodAreas,
            'not_good_areas' => $notGoodAreas,
            'not_available_areas' => $notAvailableAreas,
            'inspected_areas' => $inspectedAreas,
            'rating' => $ratingPercentage,
            'star_rating' => $starRating
        ];
    }

    /**
     * Calculate star rating based on percentage
     * 0-20% = 1 star, 21-40% = 2 stars, 41-60% = 3 stars, 61-80% = 4 stars, 81-100% = 5 stars
     */
    public function calculateStarRating($percentage)
    {
        if ($percentage >= 81) return 5;
        if ($percentage >= 61) return 4;
        if ($percentage >= 41) return 3;
        if ($percentage >= 21) return 2;
        if ($percentage >= 1) return 1;
        return 0;
    }
}
