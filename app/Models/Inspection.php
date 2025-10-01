<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    use HasFactory;

    protected $table = 'inspections';
    
    protected $fillable = [
        'outlet_id',
        'departemen',
        'guidance_id',
        'inspection_mode',
        'inspection_date',
        'status',
        'total_findings',
        'total_points',
        'created_by'
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'guidance_total_points',
        'score'
    ];

    // Relationship dengan Outlet
    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    // Relationship dengan QaGuidance
    public function guidance()
    {
        return $this->belongsTo(QaGuidance::class, 'guidance_id');
    }

    // Relationship dengan InspectionDetail
    public function details()
    {
        return $this->hasMany(InspectionDetail::class, 'inspection_id');
    }

    // Relationship dengan User (created_by)
    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relationship dengan User (auditees)
    public function auditees()
    {
        return $this->belongsToMany(User::class, 'inspection_auditees', 'inspection_id', 'user_id');
    }

    // Relationship dengan CPA
    public function cpas()
    {
        return $this->hasMany(InspectionCPA::class, 'inspection_id');
    }

    // Scope untuk data draft
    public function scopeDraft($query)
    {
        return $query->where('status', 'Draft');
    }

    // Scope untuk data completed
    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completed');
    }

    // Scope untuk data berdasarkan outlet
    public function scopeByOutlet($query, $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }

    // Scope untuk data berdasarkan departemen
    public function scopeByDepartemen($query, $departemen)
    {
        return $query->where('departemen', $departemen);
    }

    // Method untuk update total findings dan points
    public function updateTotals()
    {
        $this->total_findings = $this->details()->count();
        $this->total_points = $this->details()->sum('point');
        $this->save();
    }

    // Method untuk menghitung total points guidance
    public function getGuidanceTotalPointsAttribute()
    {
        if (!$this->guidance) {
            return 0;
        }

        $guidanceTotalPoints = 0;
        
        if ($this->guidance->guidanceCategories) {
            foreach ($this->guidance->guidanceCategories as $guidanceCategory) {
                if ($guidanceCategory->parameters) {
                    foreach ($guidanceCategory->parameters as $parameter) {
                        if ($parameter->details) {
                            foreach ($parameter->details as $detail) {
                                $guidanceTotalPoints += $detail->point;
                            }
                        }
                    }
                }
            }
        }

        return $guidanceTotalPoints;
    }

    // Method untuk menghitung score (persentase)
    public function getScoreAttribute()
    {
        if (!$this->guidance) {
            return 0;
        }

        $guidanceTotalPoints = $this->guidance_total_points;
        
        if ($guidanceTotalPoints == 0) {
            return 0;
        }

        // Hitung persentase: (Total Guidance - Total Inspeksi) / Total Guidance × 100
        $inspectionPoints = $this->total_points;
        $remainingPoints = $guidanceTotalPoints - $inspectionPoints;
        $score = ($remainingPoints / $guidanceTotalPoints) * 100;
        
        return round($score, 1); // Round to 1 decimal place
    }
}
