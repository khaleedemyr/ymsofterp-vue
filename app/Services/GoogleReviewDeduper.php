<?php

namespace App\Services;

/**
 * Menghilangkan review ganda (scraper / dataset yang mengulang baris yang sama).
 */
class GoogleReviewDeduper
{
    /**
     * @param  array<int, array<string, mixed>>  $reviews
     * @return array{reviews: array<int, array<string, mixed>>, removed: int}
     */
    public static function dedupe(array $reviews): array
    {
        $seen = [];
        $out = [];
        $removed = 0;

        foreach ($reviews as $r) {
            $r = is_array($r) ? $r : (array) $r;
            $fp = self::fingerprint($r);
            if (isset($seen[$fp])) {
                $removed++;
                continue;
            }
            $seen[$fp] = true;
            $out[] = $r;
        }

        return ['reviews' => array_values($out), 'removed' => $removed];
    }

    /**
     * @param  array<string, mixed>  $r
     */
    public static function fingerprint(array $r): string
    {
        $rid = trim((string) ($r['review_id'] ?? ''));
        if ($rid !== '') {
            return 'id:'.md5($rid);
        }

        $author = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($r['author'] ?? ''))));
        $date = trim((string) ($r['date'] ?? ''));
        $time = (int) ($r['time'] ?? 0);
        $text = mb_strtolower(preg_replace('/\s+/u', ' ', trim(mb_substr((string) ($r['text'] ?? ''), 0, 500))));

        return 'fp:'.hash('sha256', $author."\0".$date."\0".$time."\0".$text);
    }
}
