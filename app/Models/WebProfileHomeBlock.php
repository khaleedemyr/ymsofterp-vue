<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebProfileHomeBlock extends Model
{
    use HasFactory;

    protected $table = 'web_profile_home_blocks';

    protected $fillable = [
        'sort_order',
        'block_type',
        'title',
        'body',
        'video_path',
        'caption',
        'bg_variant',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'video_url',
    ];

    public function getVideoUrlAttribute(): ?string
    {
        if (! $this->video_path || $this->block_type !== 'video') {
            return null;
        }

        $path = $this->video_path;
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
