<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentAccessScope extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_type',
        'resource_id',
        'scope_type',
        'scope_id',
        'permission',
    ];

    public const RESOURCE_DOCUMENT = 'document';
    public const RESOURCE_FOLDER = 'folder';

    public const SCOPE_USER = 'user';
    public const SCOPE_JABATAN = 'jabatan';
    public const SCOPE_DIVISI = 'divisi';
    public const SCOPE_OUTLET = 'outlet';
}
