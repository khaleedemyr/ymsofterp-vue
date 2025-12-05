<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    protected $table = 'chart_of_accounts';
    protected $guarded = [];
    
    protected $casts = [
        'is_active' => 'boolean',
        'parent_id' => 'integer',
    ];
    
    // Relationship: Parent CoA
    public function parent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }
    
    // Relationship: Child CoAs
    public function children()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id')->orderBy('code');
    }
    
    // Helper: Get full path (code dengan parent)
    public function getFullCodeAttribute()
    {
        if ($this->parent) {
            return $this->parent->code . '.' . $this->code;
        }
        return $this->code;
    }
}

