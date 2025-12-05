<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderOpsAttachment extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_ops_attachments';

    protected $fillable = [
        'purchase_order_ops_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function purchaseOrderOps()
    {
        return $this->belongsTo(PurchaseOrderOps::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Accessor for formatted file size
    public function getFileSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    // Accessor for file extension
    public function getFileExtensionAttribute()
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    // Accessor for file icon (if needed in backend)
    public function getFileIconAttribute()
    {
        $extension = strtolower($this->file_extension);
        $iconMap = [
            'pdf' => 'fa-file-pdf',
            'doc' => 'fa-file-word',
            'docx' => 'fa-file-word',
            'xls' => 'fa-file-excel',
            'xlsx' => 'fa-file-excel',
            'ppt' => 'fa-file-powerpoint',
            'pptx' => 'fa-file-powerpoint',
            'jpg' => 'fa-file-image',
            'jpeg' => 'fa-file-image',
            'png' => 'fa-file-image',
            'gif' => 'fa-file-image',
            'txt' => 'fa-file-alt',
            'zip' => 'fa-file-archive',
            'rar' => 'fa-file-archive',
            'webp' => 'fa-file-image',
            'bmp' => 'fa-file-image',
        ];
        return $iconMap[$extension] ?? 'fa-file';
    }
}
