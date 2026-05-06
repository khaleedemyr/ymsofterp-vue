<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebProfileHomeServiceLanding extends Model
{
    protected $table = 'web_profile_home_service_landing';

    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'content_blocks',
        'collage_images',
        'gallery_card_image',
        'gallery_card_label',
        'gallery_card_url',
        'menu_card_image',
        'menu_card_label',
        'menu_card_url',
        'cta_label',
        'cta_url',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'collage_images' => 'array',
        ];
    }

    public static function singleton(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::create([
            'hero_title' => 'HOME SERVICE',
            'hero_subtitle' => null,
            'content_blocks' => [],
            'collage_images' => [],
        ]);
    }
}
