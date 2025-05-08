<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_number',
        'title',
        'description',
        'status',
        'priority_name',
        'label_name',
        'due_date',
        'created_by_name',
        'division_id',
        'id_outlet'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function members()
    {
        return $this->hasMany(\App\Models\MaintenanceMember::class, 'task_id');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
} 