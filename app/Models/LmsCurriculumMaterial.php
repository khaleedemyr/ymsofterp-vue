<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class LmsCurriculumMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_curriculum_materials';

    protected $fillable = [
        'title',
        'description',
        'estimated_duration_minutes',
        'quiz_id',
        'questionnaire_id',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'estimated_duration_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'primary_file_url',
        'files_count',
        'primary_file_type'
    ];

    // Relationships
    public function sessionItems()
    {
        return $this->hasMany(LmsSessionItem::class, 'item_id');
    }

    public function files()
    {
        return $this->hasMany(LmsCurriculumMaterialFile::class, 'material_id')->ordered();
    }

    public function primaryFile()
    {
        return $this->hasOne(LmsCurriculumMaterialFile::class, 'material_id')->where('is_primary', true);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function quiz()
    {
        return $this->belongsTo(\App\Models\LmsQuiz::class, 'quiz_id');
    }

    public function questionnaire()
    {
        return $this->belongsTo(\App\Models\LmsQuestionnaire::class, 'questionnaire_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeHasFiles($query)
    {
        return $query->whereHas('files');
    }

    public function scopeByFileType($query, $type)
    {
        return $query->whereHas('files', function($q) use ($type) {
            $q->where('file_type', $type);
        });
    }

    // Accessors
    public function getPrimaryFileUrlAttribute()
    {
        if ($this->primaryFile) {
            return $this->primaryFile->file_url;
        }
        return null;
    }

    public function getFilesCountAttribute()
    {
        return $this->files()->count();
    }

    public function getPrimaryFileTypeAttribute()
    {
        if ($this->primaryFile) {
            return $this->primaryFile->file_type;
        }
        return null;
    }

    // Methods
    public function addFile($file, $orderNumber = null, $isPrimary = false)
    {
        // Store file to storage
        $filePath = $file->store('lms/materials', 'public');
        
        // Determine file type
        $fileType = $this->getFileType($file->getMimeType());
        
        // If this is primary, remove primary from other files
        if ($isPrimary) {
            $this->files()->update(['is_primary' => false]);
        }
        
        // Create file record
        $fileRecord = $this->files()->create([
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_mime_type' => $file->getMimeType(),
            'file_type' => $fileType,
            'order_number' => $orderNumber ?: ($this->files()->max('order_number') + 1),
            'is_primary' => $isPrimary,
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);
        
        return $fileRecord;
    }

    public function removeFile($fileId)
    {
        $file = $this->files()->find($fileId);
        if ($file) {
            $file->deleteFile();
            $file->delete();
            return true;
        }
        return false;
    }

    public function reorderFiles($fileOrder)
    {
        foreach ($fileOrder as $index => $fileId) {
            $this->files()->where('id', $fileId)->update(['order_number' => $index + 1]);
        }
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
