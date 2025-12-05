<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class LmsCurriculumMaterialFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_curriculum_material_files';

    protected $fillable = [
        'material_id',
        'file_path',
        'file_name',
        'file_size',
        'file_mime_type',
        'file_type',
        'order_number',
        'is_primary',
        'status',
        'created_by'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'order_number' => 'integer',
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'file_url',
        'file_size_formatted',
        'file_type_text'
    ];

    // Relationships
    public function material()
    {
        return $this->belongsTo(LmsCurriculumMaterial::class, 'material_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('file_type', $type);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_number');
    }

    // Accessors
    public function getFileUrlAttribute()
    {
        if ($this->file_path && Storage::disk('public')->exists($this->file_path)) {
            return Storage::disk('public')->url($this->file_path);
        }
        return null;
    }

    public function getFileSizeFormattedAttribute()
    {
        if (!$this->file_size || $this->file_size === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($this->file_size) / log($k));
        
        return round($this->file_size / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    public function getFileTypeTextAttribute()
    {
        $typeMap = [
            'pdf' => 'PDF Document',
            'image' => 'Image File',
            'video' => 'Video File',
            'document' => 'Document File',
            'link' => 'External Link'
        ];
        
        return $typeMap[$this->file_type] ?? 'Unknown Type';
    }

    // Methods
    public function deleteFile()
    {
        if ($this->file_path && Storage::disk('public')->exists($this->file_path)) {
            Storage::disk('public')->delete($this->file_path);
        }
    }

    public function updateFile($file, $newFileName = null)
    {
        // Delete old file
        $this->deleteFile();

        // Store new file
        $filePath = $file->store('lms/materials', 'public');
        
        $this->file_path = $filePath;
        $this->file_name = $newFileName ?: $file->getClientOriginalName();
        $this->file_size = $file->getSize();
        $this->file_mime_type = $file->getMimeType();
        $this->file_type = $this->getFileType($file->getMimeType());
        $this->save();
    }

    public function setAsPrimary()
    {
        // Remove primary from other files in the same material
        $this->material->files()->where('id', '!=', $this->id)->update(['is_primary' => false]);
        
        // Set this file as primary
        $this->update(['is_primary' => true]);
    }

    private function getFileType($mimeType)
    {
        $mimeToType = [
            'application/pdf' => 'pdf',
            'application/msword' => 'document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
            'application/vnd.ms-powerpoint' => 'document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'document',
            'application/vnd.ms-excel' => 'document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'document',
            'image/jpeg' => 'image',
            'image/jpg' => 'image',
            'image/png' => 'image',
            'image/gif' => 'image',
            'image/webp' => 'image',
            'video/mp4' => 'video',
            'video/avi' => 'video',
            'video/quicktime' => 'video',
            'video/webm' => 'video',
        ];
        
        return $mimeToType[$mimeType] ?? 'document';
    }
}
