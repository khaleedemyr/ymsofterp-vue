<?php

namespace App\Support;

/**
 * Koreksi ejaan deterministik untuk chat Indonesia (slang/typo umum).
 * Dipakai sebelum/sesudah AI agar hasil tidak mengubah makna (mis. "ape" → "apa", bukan "Silakan").
 */
final class OmniChatSpellfix
{
    /**
     * Pasangan pola → penggantian (word boundary, case-insensitive).
     *
     * @var array<string, string>
     */
    private const REPLACEMENTS = [
        '/\bape\b/iu' => 'apa',
        '/\bapae\b/iu' => 'apa',
        '/\bapaa\b/iu' => 'apa',
        '/\bgmn\b/iu' => 'gimana',
        '/\bgimna\b/iu' => 'gimana',
        '/\bknp\b/iu' => 'kenapa',
        '/\bknpa\b/iu' => 'kenapa',
        '/\bblm\b/iu' => 'belum',
        '/\budh\b/iu' => 'sudah',
        '/\budah\b/iu' => 'sudah',
        '/\btlg\b/iu' => 'tolong',
        '/\btolongnya\b/iu' => 'tolong',
        '/\bmksd\b/iu' => 'maksud',
        '/\bbgt\b/iu' => 'banget',
        '/\btrims\b/iu' => 'terima kasih',
        '/\bmakasih\b/iu' => 'terima kasih',
        '/\bmaafin\b/iu' => 'maaf',
        '/\bsy\b/iu' => 'saya',
        '/\bgk\b/iu' => 'nggak',
        '/\bga\b/iu' => 'nggak',
        '/\bdongh\b/iu' => 'dong',
        '/\bsiapah\b/iu' => 'siapa',
    ];

    public static function apply(string $text): string
    {
        $out = $text;
        foreach (self::REPLACEMENTS as $pattern => $replacement) {
            $out = preg_replace($pattern, $replacement, $out) ?? $out;
        }

        return self::preserveTrailingPunctuation($text, $out);
    }

    /**
     * Apakah hasil AI masih "satu pesan yang sama" (bukan balasan baru)?
     */
    public static function isAcceptableCorrection(string $original, string $corrected): bool
    {
        $o = self::normalize($original);
        $c = self::normalize($corrected);

        if ($o === '' || $c === '') {
            return false;
        }

        if ($o === $c) {
            return true;
        }

        $lenO = mb_strlen($o);
        $lenC = mb_strlen($c);

        if ($lenO <= 30) {
            $maxLen = max($lenO, $lenC);
            $distance = levenshtein(
                self::asciiFold($o),
                self::asciiFold($c)
            );
            if ($maxLen > 0 && $distance / $maxLen > 0.45) {
                return false;
            }
        } else {
            similar_text($o, $c, $pct);
            if ($pct < 35) {
                return false;
            }
        }

        $ratio = $lenC / max(1, $lenO);
        if ($ratio > 1.8 || $ratio < 0.45) {
            return false;
        }

        $oWords = preg_split('/\s+/u', $o, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $cWords = preg_split('/\s+/u', $c, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($oWords) <= 3 && count($cWords) > count($oWords) + 1) {
            return false;
        }

        return true;
    }

    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text;
    }

    private static function asciiFold(string $text): string
    {
        $folded = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return $folded !== false ? preg_replace('/[^a-z0-9 ?!]/', '', strtolower($folded)) ?? $text : $text;
    }

    /** Pertahankan tanda baca di akhir jika AI/rules menghapusnya. */
    private static function preserveTrailingPunctuation(string $original, string $fixed): string
    {
        if (preg_match('/([?!.…]+)\s*$/u', $original, $m)) {
            $trail = $m[1];
            if (! preg_match('/[?!.…]+\s*$/u', $fixed)) {
                return rtrim($fixed).$trail;
            }
        }

        return $fixed;
    }
}
