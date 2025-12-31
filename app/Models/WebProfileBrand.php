<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebProfileBrand extends Model
{
    use HasFactory;

    protected $table = 'web_profile_brands';

    protected $fillable = [
        'title',
        'slug',
        'link_menu',
        'menu_pdf',
        'thumbnail',
        'image',
        'content',
        'created_by',
        'updated_by'
    ];

    protected $appends = [
        'thumbnail_url',
        'image_url',
        'menu_pdf_url'
    ];

    // Accessor untuk full URL
    public function getThumbnailUrlAttribute()
    {
        if (!$this->thumbnail) {
            return null;
        }
        
        $path = $this->thumbnail;
        if (strpos($path, 'storage/') === 0) {
            $path = substr($path, 8);
        }
        
        if (request() && request()->getSchemeAndHttpHost()) {
            $baseUrl = request()->getSchemeAndHttpHost();
        } else {
            $baseUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');
        }
        
        $pathParts = explode('/', $path);
        $encodedParts = array_map(function($part) {
            return rawurlencode($part);
        }, $pathParts);
        $encodedPath = implode('/', $encodedParts);
        
        return $baseUrl . '/storage/' . $encodedPath;
    }

    public function getImageUrlAttribute()
    {
        if (!$this->image) {
            return null;
        }
        
        $path = $this->image;
        if (strpos($path, 'storage/') === 0) {
            $path = substr($path, 8);
        }
        
        if (request() && request()->getSchemeAndHttpHost()) {
            $baseUrl = request()->getSchemeAndHttpHost();
        } else {
            $baseUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');
        }
        
        $pathParts = explode('/', $path);
        $encodedParts = array_map(function($part) {
            return rawurlencode($part);
        }, $pathParts);
        $encodedPath = implode('/', $encodedParts);
        
        return $baseUrl . '/storage/' . $encodedPath;
    }

    public function getMenuPdfUrlAttribute()
    {
        if (!$this->menu_pdf) {
            return null;
        }
        
        $path = $this->menu_pdf;
        if (strpos($path, 'storage/') === 0) {
            $path = substr($path, 8);
        }
        
        if (request() && request()->getSchemeAndHttpHost()) {
            $baseUrl = request()->getSchemeAndHttpHost();
        } else {
            $baseUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');
        }
        
        $pathParts = explode('/', $path);
        $encodedParts = array_map(function($part) {
            return rawurlencode($part);
        }, $pathParts);
        $encodedPath = implode('/', $encodedParts);
        
        return $baseUrl . '/storage/' . $encodedPath;
    }
}

