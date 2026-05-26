<?php

namespace App\Models;

use App\Support\OmniFlowDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmniMessageTemplate extends Model
{
    protected $fillable = [
        'title',
        'shortcut',
        'body',
        'message_mode',
        'config',
        'is_active',
        'sort_order',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'config' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedConfig(): array
    {
        $config = is_array($this->config) ? $this->config : [];

        return OmniFlowDefinition::normalizeSendMessageConfig(array_merge($config, [
            'body' => $this->body,
            'message_mode' => $this->message_mode ?? 'text',
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toInboxPayload(): array
    {
        $normalized = $this->normalizedConfig();
        $mode = (string) ($normalized['message_mode'] ?? 'text');
        $config = $normalized;
        unset($config['body'], $config['message_mode']);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'shortcut' => $this->shortcut,
            'body' => $this->body,
            'message_mode' => $mode,
            'config' => $config,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listActiveForInbox(): array
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (self $t) => $t->toInboxPayload())
            ->values()
            ->all();
    }
}
