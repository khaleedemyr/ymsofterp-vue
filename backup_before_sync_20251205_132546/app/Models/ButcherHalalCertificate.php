<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ButcherHalalCertificate extends Model
{
    protected $guarded = [];

    public function butcherProcess()
    {
        return $this->belongsTo(ButcherProcess::class);
    }

    // Accessor for file_url
    public function getFileUrlAttribute()
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }
} 