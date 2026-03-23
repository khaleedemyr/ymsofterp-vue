<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebProfileJustusAppsBlock extends Model
{
    protected $table = 'web_profile_justus_apps_blocks';

    protected $fillable = [
        'title',
        'body',
        'image_path',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        $path = $this->image_path;
        if (strpos($path, 'storage/') === 0) {
            $path = substr($path, 8);
        }

        if (request() && request()->getSchemeAndHttpHost()) {
            $baseUrl = request()->getSchemeAndHttpHost();
        } else {
            $baseUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');
        }

        $pathParts = explode('/', $path);
        $encodedParts = array_map(static function ($part) {
            return rawurlencode($part);
        }, $pathParts);
        $encodedPath = implode('/', $encodedParts);

        return $baseUrl.'/storage/'.$encodedPath;
    }
}

