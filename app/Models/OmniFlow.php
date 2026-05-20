<?php

namespace App\Models;

use App\Support\OmniFlowDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmniFlow extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'trigger_type',
        'channel',
        'priority',
        'definition',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'definition' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(OmniFlowRun::class, 'flow_id');
    }

    public function usesGraph(): bool
    {
        return OmniFlowDefinition::isGraph($this->definition);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function steps(): array
    {
        return OmniFlowDefinition::steps($this->definition);
    }

    public function stepCount(): int
    {
        $def = is_array($this->definition) ? $this->definition : [];

        return OmniFlowDefinition::actionNodeCount($def);
    }
}
