<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'erp_permission';
    
    protected $fillable = [
        'menu_id',
        'action',
        'code'
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'erp_role_permission', 'permission_id', 'role_id');
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }
} 