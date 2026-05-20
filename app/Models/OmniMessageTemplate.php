<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmniMessageTemplate extends Model
{
    protected $fillable = [
        'title',
        'shortcut',
        'body',
        'is_active',
        'sort_order',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return list<array{id: int, title: string, shortcut: string|null, body: string}>
     */
    public static function listActiveForInbox(): array
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'title', 'shortcut', 'body'])
            ->map(fn (self $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'shortcut' => $t->shortcut,
                'body' => $t->body,
            ])
            ->values()
            ->all();
    }
}
