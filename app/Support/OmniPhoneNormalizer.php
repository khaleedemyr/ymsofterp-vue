<?php

namespace App\Support;

/**
 * Normalisasi nomor telepon untuk omnichannel & broadcast WA (format digit, prefix 62).
 */
class OmniPhoneNormalizer
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }

    /** @return list<string> */
    public static function matchCandidates(string $phone): array
    {
        $normalized = self::normalize($phone);
        if ($normalized === '') {
            return [];
        }

        return array_values(array_unique(array_filter([
            $normalized,
            '0'.substr($normalized, 2),
            '+'.$normalized,
        ])));
    }

    public static function isValidIndonesiaMobile(string $normalized): bool
    {
        if (! str_starts_with($normalized, '62')) {
            return false;
        }

        $len = strlen($normalized);

        return $len >= 10 && $len <= 15;
    }
}
