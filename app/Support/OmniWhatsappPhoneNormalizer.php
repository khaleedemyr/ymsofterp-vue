<?php

namespace App\Support;

/**
 * Normalisasi WA ID / nomor pelanggan (Indonesia 62…, Malaysia 60…).
 */
final class OmniWhatsappPhoneNormalizer
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $withoutZero = substr($digits, 1);
            if ($withoutZero !== '' && str_starts_with($withoutZero, '1')) {
                return '60'.$withoutZero;
            }

            return '62'.$withoutZero;
        }

        if (str_starts_with($digits, '62') || str_starts_with($digits, '60')) {
            return $digits;
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        if (str_starts_with($digits, '1') && strlen($digits) >= 9 && strlen($digits) <= 10) {
            return '60'.$digits;
        }

        return $digits;
    }

    /**
     * @return list<string>
     */
    public static function lookupCandidates(string $from, ?string $normalized = null): array
    {
        $normalized ??= self::normalize($from);
        $digits = preg_replace('/\D/', '', $from) ?? '';

        $candidates = array_filter([
            $from,
            $normalized,
            $digits,
            $digits !== '' ? '0'.substr($digits, 1) : null,
            $digits !== '' ? '+'.$digits : null,
        ]);

        if ($normalized !== '' && str_starts_with($normalized, '60') && strlen($normalized) > 2) {
            $local = substr($normalized, 2);
            $candidates[] = $local;
            $candidates[] = '0'.$local;
            $candidates[] = '+'.$normalized;
        }

        if ($normalized !== '' && str_starts_with($normalized, '62') && strlen($normalized) > 2) {
            $local = substr($normalized, 2);
            $candidates[] = $local;
            $candidates[] = '0'.$local;
            $candidates[] = '+'.$normalized;
        }

        return array_values(array_unique(array_filter($candidates, fn ($v) => $v !== '' && $v !== null)));
    }
}
