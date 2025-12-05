<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'activity_type',
        'module',
        'description',
        'ip_address',
        'user_agent',
        'old_data',
        'new_data',
        'created_at',
    ];

    // Jika old_data dan new_data berupa array/objek, otomatis di-cast ke json
    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public $timestamps = false; // Jika hanya ada created_at, tanpa updated_at
    // Jika ada updated_at juga, ganti menjadi true

    /**
     * Get the user that performed the activity
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
