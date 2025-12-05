<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReportComment extends Model
{
    use HasFactory;

    protected $table = 'daily_report_comments';

    protected $fillable = [
        'daily_report_id',
        'user_id',
        'parent_id',
        'comment',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function dailyReport()
    {
        return $this->belongsTo(DailyReport::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(DailyReportComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(DailyReportComment::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    // Scopes
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByReport($query, $reportId)
    {
        return $query->where('daily_report_id', $reportId);
    }

    // Helper methods
    public function isReply()
    {
        return !is_null($this->parent_id);
    }

    public function getTimeAgo()
    {
        return $this->created_at->diffForHumans();
    }
}
