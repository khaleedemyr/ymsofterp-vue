<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCutLog extends Model
{
    use HasFactory;

    protected $table = 'stock_cut_logs';

    protected $fillable = [
        'outlet_id',
        'tanggal',
        'type_filter',
        'total_items_cut',
        'total_modifiers_cut',
        'status',
        'error_message',
        'created_by'
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total_items_cut' => 'integer',
        'total_modifiers_cut' => 'integer',
    ];

    // Relationships
    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeByOutlet($query, $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }

    public function scopeByDate($query, $date)
    {
        return $query->where('tanggal', $date);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type_filter', $type);
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
