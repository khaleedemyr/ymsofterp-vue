<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebProfileBanner extends Model
{
    use HasFactory;

    protected $table = 'web_profile_banners';

    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'background_image',
        'content_image',
        'order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'background_image_url',
        'content_image_url'
    ];

    // Accessor untuk full URL
    public function getBackgroundImageUrlAttribute()
    {
        if (!$this->background_image) {
            return null;
        }
        
        // Pastikan path tidak double "storage/"
        $path = $this->background_image;
        if (strpos($path, 'storage/') === 0) {
            $path = substr($path, 8); // Remove "storage/" prefix if exists
        }
        
        // Generate absolute URL dengan format: https://domain.com/storage/path
        // Gunakan URL dari request yang masuk (auto-detect dari web_profile)
        // Fallback ke APP_URL jika tidak ada request (misal: queue, console)
        if (request() && request()->getSchemeAndHttpHost()) {
            $baseUrl = request()->getSchemeAndHttpHost();
        } else {
            // Fallback ke config jika tidak ada request context
            $baseUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');
        }
        
        // Encode path untuk handle spasi dan karakter khusus di filename
        $pathParts = explode('/', $path);
        $encodedParts = array_map(function($part) {
            // Encode hanya bagian filename (bagian terakhir), bukan folder
            return rawurlencode($part);
        }, $pathParts);
        $encodedPath = implode('/', $encodedParts);
        
        $url = $baseUrl . '/storage/' . $encodedPath;
        
        return $url;
    }

    public function getContentImageUrlAttribute()
    {
        if (!$this->content_image) {
            return null;
        }
        
        // Pastikan path tidak double "storage/"
        $path = $this->content_image;
        if (strpos($path, 'storage/') === 0) {
            $path = substr($path, 8); // Remove "storage/" prefix if exists
        }
        
        // Generate absolute URL dengan format: https://domain.com/storage/path
        // Gunakan URL dari request yang masuk (auto-detect dari web_profile)
        // Fallback ke APP_URL jika tidak ada request (misal: queue, console)
        if (request() && request()->getSchemeAndHttpHost()) {
            $baseUrl = request()->getSchemeAndHttpHost();
        } else {
            // Fallback ke config jika tidak ada request context
            $baseUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');
        }
        
        // Encode path untuk handle spasi dan karakter khusus di filename
        $pathParts = explode('/', $path);
        $encodedParts = array_map(function($part) {
            // Encode hanya bagian filename (bagian terakhir), bukan folder
            return rawurlencode($part);
        }, $pathParts);
        $encodedPath = implode('/', $encodedParts);
        
        $url = $baseUrl . '/storage/' . $encodedPath;
        
        return $url;
    }
}

