<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'created_by',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SharedDocument::class, 'folder_id');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(DocumentAccessScope::class, 'resource_id')
            ->where('resource_type', DocumentAccessScope::RESOURCE_FOLDER);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $innerQuery) use ($user) {
            $innerQuery->where('created_by', $user->id)
                ->orWhere('is_public', true)
                ->orWhereHas('scopes', function (Builder $scopeQuery) use ($user) {
                    $scopeQuery->where(function (Builder $scopeMatchQuery) use ($user) {
                        $scopeMatchQuery->where(function (Builder $userScopeQuery) use ($user) {
                            $userScopeQuery->where('scope_type', DocumentAccessScope::SCOPE_USER)
                                ->where('scope_id', $user->id);
                        });

                        if (!empty($user->id_jabatan)) {
                            $scopeMatchQuery->orWhere(function (Builder $jabatanScopeQuery) use ($user) {
                                $jabatanScopeQuery->where('scope_type', DocumentAccessScope::SCOPE_JABATAN)
                                    ->where('scope_id', $user->id_jabatan);
                            });
                        }

                        if (!empty($user->division_id)) {
                            $scopeMatchQuery->orWhere(function (Builder $divisionScopeQuery) use ($user) {
                                $divisionScopeQuery->where('scope_type', DocumentAccessScope::SCOPE_DIVISI)
                                    ->where('scope_id', $user->division_id);
                            });
                        }

                        if (!empty($user->id_outlet)) {
                            $scopeMatchQuery->orWhere(function (Builder $outletScopeQuery) use ($user) {
                                $outletScopeQuery->where('scope_type', DocumentAccessScope::SCOPE_OUTLET)
                                    ->where('scope_id', $user->id_outlet);
                            });
                        }
                    });
                });
        });
    }

    public function hasPermission(User $user, string $permission = 'view'): bool
    {
        if ($this->is_public || $this->created_by === $user->id) {
            return true;
        }

        $permissionLevels = [
            'view' => 1,
            'edit' => 2,
            'admin' => 3,
        ];

        $requiredLevel = $permissionLevels[$permission] ?? 1;

        $scopeCandidates = [
            [DocumentAccessScope::SCOPE_USER, $user->id],
            [DocumentAccessScope::SCOPE_JABATAN, $user->id_jabatan],
            [DocumentAccessScope::SCOPE_DIVISI, $user->division_id],
            [DocumentAccessScope::SCOPE_OUTLET, $user->id_outlet],
        ];

        $bestLevel = 0;
        foreach ($scopeCandidates as [$scopeType, $scopeId]) {
            if (empty($scopeId)) {
                continue;
            }

            $scope = $this->scopes()
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->first();

            if ($scope) {
                $bestLevel = max($bestLevel, $permissionLevels[$scope->permission] ?? 0);
            }
        }

        return $bestLevel >= $requiredLevel;
    }
}
