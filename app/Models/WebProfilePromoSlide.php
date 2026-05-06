<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebProfilePromoSlide extends Model
{
    use HasFactory;

    protected $table = 'web_profile_promo_slides';

    protected $fillable = [
        'title',
        'image',
        'link_url',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
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
        $encodedParts = array_map(static fn ($part) => rawurlencode($part), $pathParts);
        $encodedPath = implode('/', $encodedParts);

        return $baseUrl.'/storage/'.$encodedPath;
    }
}
