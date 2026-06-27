<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebProfileOutletLanding extends Model
{
    protected $table = 'web_profile_outlet_landings';

    protected $fillable = [
        'outlet_id',
        'slug',
        'is_active',
        'outlet_subtitle',
        'headline',
        'intro_paragraph',
        'secondary_paragraph',
        'hero_image',
        'gallery_images',
        'logo_override',
        'address_override',
        'map_url',
        'book_now_label',
        'see_map_label',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'gallery_images' => 'array',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function isPublished(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (trim((string) $this->slug) === '') {
            return false;
        }

        if (trim((string) $this->hero_image) === '') {
            return false;
        }

        $hasText = trim((string) $this->headline) !== ''
            || trim((string) $this->intro_paragraph) !== '';

        return $hasText;
    }
}
