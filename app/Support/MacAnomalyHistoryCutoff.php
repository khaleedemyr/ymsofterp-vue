<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Riwayat MAC sebelum tanggal ini diabaikan (data lama / pra-perbaikan sistem).
 */
final class MacAnomalyHistoryCutoff
{
    public const DATE = '2026-09-01';

    public static function effectiveDateFrom(?string $dateFrom): string
    {
        $from = trim((string) $dateFrom);

        if ($from === '' || $from < self::DATE) {
            return self::DATE;
        }

        return $from;
    }

    public static function applyToQuery(Builder $query, string $dateColumn): Builder
    {
        return $query->where($dateColumn, '>=', self::DATE);
    }
}
