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
        'show_in_menu_payment' => 'boolean',
        'menu_id' => 'array', // Changed to array for multiple selection
        'budget_limit' => 'decimal:2',
        'mode_payment' => 'array',
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
    
    // Helper: Get menus as array of IDs
    public function getMenuIdsAttribute()
    {
        if ($this->menu_id && is_array($this->menu_id)) {
            return $this->menu_id;
        }
        // Backward compatibility: if it's still a single value, convert to array
        if ($this->menu_id) {
            return [$this->menu_id];
        }
        return [];
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

