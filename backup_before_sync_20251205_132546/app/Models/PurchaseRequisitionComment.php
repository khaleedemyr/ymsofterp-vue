<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitionComment extends Model
{
    use HasFactory;

    protected $table = 'purchase_requisition_comments';

    protected $fillable = [
        'purchase_requisition_id',
        'user_id',
        'comment',
        'is_internal',
        'attachment_path',
        'attachment_name',
        'attachment_size',
        'attachment_mime_type',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'attachment_size' => 'integer',
    ];

    // Accessors
    public function hasAttachment()
    {
        return !empty($this->attachment_path);
    }

    public function getAttachmentUrlAttribute()
    {
        if (!$this->hasAttachment()) {
            return null;
        }
        return asset('storage/' . $this->attachment_path);
    }

    public function getFormattedFileSizeAttribute()
    {
        if (!$this->attachment_size) {
            return null;
        }
        
        $bytes = $this->attachment_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getFileExtensionAttribute()
    {
        if (!$this->attachment_name) {
            return null;
        }
        return pathinfo($this->attachment_name, PATHINFO_EXTENSION);
    }

    public function isImage()
    {
        if (!$this->attachment_mime_type) {
            return false;
        }
        return strpos($this->attachment_mime_type, 'image/') === 0;
    }

    // Relationships
    public function purchaseRequisition()
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }
}