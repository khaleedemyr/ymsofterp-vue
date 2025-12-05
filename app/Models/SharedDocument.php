<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class SharedDocument extends Model
{
    use HasFactory;

    protected $table = 'shared_documents';
    protected $primaryKey = 'id';

    protected $fillable = [
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

    public function hasPermission(User $user, string $permission = 'view'): bool
    {
        // Debug logging
        \Log::info('hasPermission check', [
            'document_id' => $this->id,
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap,
            'requested_permission' => $permission,
            'is_public' => $this->is_public,
            'created_by' => $this->created_by,
            'user_is_creator' => ($this->created_by === $user->id),
        ]);

        if ($this->is_public) {
            \Log::info('Access granted: Document is public');
            return true;
        }

        if ($this->created_by === $user->id) {
            \Log::info('Access granted: User is creator');
            return true;
        }

        $userPermission = $this->permissions()->where('user_id', $user->id)->first();
        
        \Log::info('User permission found', [
            'user_permission' => $userPermission ? $userPermission->toArray() : null,
        ]);
        
        if (!$userPermission) {
            \Log::info('Access denied: No permission found');
            return false;
        }

        $permissionLevels = [
            'view' => 1,
            'edit' => 2,
            'admin' => 3,
        ];

        $requiredLevel = $permissionLevels[$permission] ?? 1;
        $userLevel = $permissionLevels[$userPermission->permission] ?? 0;

        $hasAccess = $userLevel >= $requiredLevel;
        
        \Log::info('Permission level check', [
            'required_level' => $requiredLevel,
            'user_level' => $userLevel,
            'has_access' => $hasAccess,
        ]);

        return $hasAccess;
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