<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SharedDocument extends Model
{
    use HasFactory;

    protected $table = 'shared_documents';
    protected $primaryKey = 'id';

    protected $fillable = [
        'folder_id',
        'title',
        'filename',
        'file_path',
        'file_type',
        'file_size',
        'description',
        'created_by',
        'document_key',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($document) {
            if (empty($document->document_key)) {
                $document->document_key = Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(DocumentPermission::class, 'document_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_permissions', 'document_id', 'user_id')
                    ->withPivot('permission')
                    ->withTimestamps();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(DocumentAccessScope::class, 'resource_id')
            ->where('resource_type', DocumentAccessScope::RESOURCE_DOCUMENT);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $innerQuery) use ($user) {
            $innerQuery->where('created_by', $user->id)
                ->orWhere('is_public', true)
                ->orWhereHas('permissions', function (Builder $legacyPermissionQuery) use ($user) {
                    $legacyPermissionQuery->where('user_id', $user->id);
                })
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
        $bestLevel = 0;

        $legacyPermission = $this->permissions()->where('user_id', $user->id)->first();
        if ($legacyPermission) {
            $bestLevel = max($bestLevel, $permissionLevels[$legacyPermission->permission] ?? 0);
        }

        $scopeCandidates = [
            [DocumentAccessScope::SCOPE_USER, $user->id],
            [DocumentAccessScope::SCOPE_JABATAN, $user->id_jabatan],
            [DocumentAccessScope::SCOPE_DIVISI, $user->division_id],
            [DocumentAccessScope::SCOPE_OUTLET, $user->id_outlet],
        ];

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

    public function getFileUrl(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function getOnlyOfficeConfig(): array
    {
        return [
            'document' => [
                'fileType' => $this->file_type,
                'key' => $this->document_key,
                'title' => $this->title,
                'url' => $this->getFileUrl(),
            ],
            'documentType' => $this->getDocumentType(),
            'editorConfig' => [
                'mode' => 'edit',
                'lang' => 'id',
                'callbackUrl' => route('documents.callback'),
            ],
        ];
    }

    private function getDocumentType(): string
    {
        $types = [
            'xlsx' => 'spreadsheet',
            'xls' => 'spreadsheet',
            'docx' => 'text',
            'doc' => 'text',
            'pptx' => 'presentation',
            'ppt' => 'presentation',
        ];

        return $types[$this->file_type] ?? 'text';
    }
} 