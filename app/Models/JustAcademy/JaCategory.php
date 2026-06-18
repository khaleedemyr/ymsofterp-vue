<?php

namespace App\Models\JustAcademy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaCategory extends Model
{
    protected $table = 'ja_categories';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function programs(): HasMany
    {
        return $this->hasMany(JaProgram::class, 'category_id');
    }
}
