<?php

namespace App\Support;

final class OmniContactMaritalStatus
{
    public const BELUM_MENIKAH = 'belum_menikah';

    public const MENIKAH = 'menikah';

    public const CERAI = 'cerai';

    public const JANDA_DUDA = 'janda_duda';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::BELUM_MENIKAH,
            self::MENIKAH,
            self::CERAI,
            self::JANDA_DUDA,
        ];
    }

    public static function isValid(?string $value): bool
    {
        return $value === null || $value === '' || in_array($value, self::values(), true);
    }

    public static function label(?string $value): ?string
    {
        return match ($value) {
            self::BELUM_MENIKAH => 'Belum menikah',
            self::MENIKAH => 'Menikah',
            self::CERAI => 'Cerai',
            self::JANDA_DUDA => 'Janda / duda',
            default => null,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $v) => ['value' => $v, 'label' => self::label($v) ?? $v],
            self::values()
        );
    }
}
