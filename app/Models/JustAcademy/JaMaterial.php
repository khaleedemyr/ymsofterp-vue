<?php

namespace App\Models\JustAcademy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaMaterial extends Model
{
    protected $table = 'ja_materials';

    protected $fillable = [
        'title',
        'type',
        'file_path',
        'url',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function programItems(): HasMany
    {
        return $this->hasMany(JaProgramItem::class, 'material_id');
    }
}
