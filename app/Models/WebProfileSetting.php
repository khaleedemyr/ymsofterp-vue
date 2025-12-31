<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebProfileSetting extends Model
{
    use HasFactory;

    protected $table = 'web_profile_settings';

    protected $fillable = [
        'key',
        'value',
        'type'
    ];

    public static function getValue($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function setValue($key, $value, $type = 'text')
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}

