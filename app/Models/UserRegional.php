<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRegional extends Model
{
    use HasFactory;

    public const AREAS = ['Bar', 'Kitchen', 'Service'];

    protected $table = 'user_regional';

    protected $fillable = [
        'user_id',
        'area',
        'areas',
        'target_outlet_visits',
        'outlet_visit_targets',
        'supervisor_position_id',
    ];

    protected $casts = [
        'areas' => 'array',
        'target_outlet_visits' => 'integer',
        'outlet_visit_targets' => 'array',
        'supervisor_position_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForArea($query, string $area)
    {
        return $query->where('area', $area);
    }

    /**
     * @return list<string>
     */
    public function resolveAreas(): array
    {
        return self::normalizeAreasList($this->areas, $this->area);
    }

    /**
     * @return list<string>
     */
    public static function areasForUserId(int $userId): array
    {
        $assignment = static::query()->where('user_id', $userId)->first();

        return $assignment ? $assignment->resolveAreas() : [];
    }

    /**
     * @param  mixed  $areas
     * @return list<string>
     */
    public static function normalizeAreasList(mixed $areas, ?string $fallbackArea = null): array
    {
        if (is_string($areas)) {
            $decoded = json_decode($areas, true);
            $areas = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($areas)) {
            $areas = [];
        }

        $normalized = array_values(array_unique(array_filter(
            array_map('strval', $areas),
            fn (string $area) => in_array($area, self::AREAS, true),
        )));

        if ($normalized !== []) {
            return $normalized;
        }

        if ($fallbackArea !== null && in_array($fallbackArea, self::AREAS, true)) {
            return [$fallbackArea];
        }

        return [];
    }
}
