<?php

namespace App\Services\Omni;

use App\Models\SocialContentPost;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SocialPerformanceAnalyticsService
{
    /**
     * @return array{
     *   filters: array{date_from: string, date_to: string, platform: string},
     *   sync: array{last_synced_at: ?string, post_count: int},
     *   content: list<array<string, mixed>>,
     *   highlights: array<string, mixed>,
     *   series: array<string, mixed>,
     *   top: array<string, list<array<string, mixed>>>,
     *   totals: array<string, int>
     * }
     */
    public function build(?string $dateFrom, ?string $dateTo, ?string $platform): array
    {
        $to = $this->parseDate($dateTo) ?? Carbon::today();
        $from = $this->parseDate($dateFrom) ?? $to->copy()->subDays(29);
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $platform = in_array($platform, ['instagram', 'facebook'], true) ? $platform : 'all';

        $query = SocialContentPost::query()
            ->with('metrics')
            ->whereNotNull('posted_at')
            ->whereDate('posted_at', '>=', $from->toDateString())
            ->whereDate('posted_at', '<=', $to->toDateString());

        if ($platform !== 'all') {
            $query->where('platform', $platform);
        }

        /** @var Collection<int, SocialContentPost> $posts */
        $posts = $query->orderByDesc('posted_at')->get();

        $rows = $posts->map(fn (SocialContentPost $p) => $this->formatRow($p))->values();

        $highlights = $this->buildHighlights($rows);
        $series = $this->buildSeries($rows, $from, $to);
        $top = [
            'engagement' => $this->topBy($rows, 'engagement', 5),
            'reach' => $this->topBy($rows, 'reach', 5),
            'comments' => $this->topBy($rows, 'comments', 5),
            'impressions' => $this->topBy($rows, 'impressions', 5),
        ];

        $lastSynced = SocialContentPost::query()->max('last_synced_at');
        $igCount = SocialContentPost::query()->where('platform', 'instagram')->count();
        $fbCount = SocialContentPost::query()->where('platform', 'facebook')->count();

        return [
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'platform' => $platform,
            ],
            'sync' => [
                'last_synced_at' => $lastSynced ? Carbon::parse($lastSynced)->toIso8601String() : null,
                'post_count' => $rows->count(),
                'ig_total' => $igCount,
                'fb_total' => $fbCount,
            ],
            'content' => $rows->all(),
            'highlights' => $highlights,
            'series' => $series,
            'top' => $top,
            'totals' => [
                'likes' => (int) $rows->sum('likes'),
                'comments' => (int) $rows->sum('comments'),
                'shares' => (int) $rows->sum('shares'),
                'saved' => (int) $rows->sum('saved'),
                'impressions' => (int) $rows->sum('impressions'),
                'reach' => (int) $rows->sum('reach'),
                'engagement' => (int) $rows->sum('engagement'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRow(SocialContentPost $post): array
    {
        $m = $post->metrics;
        $likes = (int) ($m?->likes ?? 0);
        $comments = (int) ($m?->comments ?? 0);
        $shares = (int) ($m?->shares ?? 0);
        $saved = (int) ($m?->saved ?? 0);
        $impressions = (int) ($m?->impressions ?? 0);
        $reach = (int) ($m?->reach ?? 0);
        $clicks = (int) ($m?->clicks ?? 0);
        $engagement = $likes + $comments + $shares + $saved;

        $title = trim((string) ($post->caption ?? ''));
        if ($title === '') {
            $title = 'Post '.$post->external_id;
        } elseif (mb_strlen($title) > 80) {
            $title = mb_substr($title, 0, 77).'…';
        }

        return [
            'id' => $post->id,
            'platform' => $post->platform,
            'account_id' => $post->account_id,
            'account_label' => $post->account_label,
            'external_id' => $post->external_id,
            'title' => $title,
            'caption' => (string) ($post->caption ?? ''),
            'permalink' => $post->permalink,
            'thumbnail_url' => $post->thumbnail_url,
            'media_type' => $post->media_type,
            'posted_at' => $post->posted_at?->toIso8601String(),
            'posted_date' => $post->posted_at?->toDateString(),
            'likes' => $likes,
            'comments' => $comments,
            'shares' => $shares,
            'saved' => $saved,
            'impressions' => $impressions,
            'reach' => $reach,
            'clicks' => $clicks,
            'engagement' => $engagement,
            'last_synced_at' => $post->last_synced_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildHighlights(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [
                'best' => null,
                'worst' => null,
                'most_impressions' => null,
                'most_likes' => null,
                'most_comments' => null,
                'most_shares' => null,
                'most_saved' => null,
            ];
        }

        $byEngagement = $rows->sortByDesc('engagement')->values();
        $best = $byEngagement->first();
        $worst = $byEngagement->last();

        return [
            'best' => $this->highlightCard($best, 'engagement'),
            'worst' => $this->highlightCard($worst, 'engagement'),
            'most_impressions' => $this->highlightCard($rows->sortByDesc('impressions')->first(), 'impressions'),
            'most_likes' => $this->highlightCard($rows->sortByDesc('likes')->first(), 'likes'),
            'most_comments' => $this->highlightCard($rows->sortByDesc('comments')->first(), 'comments'),
            'most_shares' => $this->highlightCard($rows->sortByDesc('shares')->first(), 'shares'),
            'most_saved' => $this->highlightCard($rows->sortByDesc('saved')->first(), 'saved'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array{title: string, value: int, platform: ?string, permalink: ?string, thumbnail_url: ?string}|null
     */
    private function highlightCard(?array $row, string $metricKey): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'title' => (string) ($row['title'] ?? '-'),
            'value' => (int) ($row[$metricKey] ?? 0),
            'platform' => $row['platform'] ?? null,
            'permalink' => $row['permalink'] ?? null,
            'thumbnail_url' => $row['thumbnail_url'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{labels: list<string>, impressions: list<int>, likes: list<int>}
     */
    private function buildSeries(Collection $rows, Carbon $from, Carbon $to): array
    {
        $labels = [];
        $impressions = [];
        $likes = [];

        $byDate = $rows->groupBy('posted_date');

        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $dayRows = $byDate->get($key, collect());
            $labels[] = $key;
            $impressions[] = (int) $dayRows->sum('impressions');
            $likes[] = (int) $dayRows->sum('likes');
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'impressions' => $impressions,
            'likes' => $likes,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function topBy(Collection $rows, string $key, int $limit): array
    {
        return $rows->sortByDesc($key)->take($limit)->values()->all();
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
