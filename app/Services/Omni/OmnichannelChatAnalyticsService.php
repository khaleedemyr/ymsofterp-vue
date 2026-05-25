<?php

namespace App\Services\Omni;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Models\User;
use App\Support\OmnichannelAuthorization;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OmnichannelChatAnalyticsService
{
    /**
     * @return array{
     *   summary: array<string, mixed>,
     *   series: array<string, mixed>,
     *   filters: array<string, mixed>
     * }
     */
    public function build(User $user, ?string $dateFrom, ?string $dateTo, ?string $channel): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $end = $dateTo
            ? Carbon::parse($dateTo, $tz)->endOfDay()
            : now($tz)->endOfDay();
        $start = $dateFrom
            ? Carbon::parse($dateFrom, $tz)->startOfDay()
            : $end->copy()->subDays(29)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $channelFilter = $this->normalizeChannel($channel);
        $days = $this->buildDaySeries($start, $end);

        $messageStats = $this->aggregateMessagesByDay($user, $start, $end, $channelFilter);
        $newChatsByDay = $this->aggregateNewChatsByDay($user, $start, $end, $channelFilter);
        $frtByDay = $this->aggregateFirstResponseByDay($user, $start, $end, $channelFilter);

        $inboundSeries = [];
        $outboundSeries = [];
        $newChatSeries = [];
        $frtMinutesSeries = [];
        $frtSampleSeries = [];

        $totalInbound = 0;
        $totalOutbound = 0;
        $totalNewChats = 0;
        $frtWeightedSeconds = 0;
        $frtWeightedCount = 0;

        foreach ($days as $day) {
            $msg = $messageStats[$day] ?? ['inbound' => 0, 'outbound' => 0];
            $inbound = (int) $msg['inbound'];
            $outbound = (int) $msg['outbound'];
            $newChats = (int) ($newChatsByDay[$day] ?? 0);
            $frt = $frtByDay[$day] ?? ['avg_seconds' => null, 'samples' => 0];

            $inboundSeries[] = $inbound;
            $outboundSeries[] = $outbound;
            $newChatSeries[] = $newChats;
            $totalInbound += $inbound;
            $totalOutbound += $outbound;
            $totalNewChats += $newChats;

            if ($frt['avg_seconds'] !== null && $frt['samples'] > 0) {
                $avgSec = (float) $frt['avg_seconds'];
                $frtMinutesSeries[] = round($avgSec / 60, 1);
                $frtSampleSeries[] = (int) $frt['samples'];
                $frtWeightedSeconds += $avgSec * (int) $frt['samples'];
                $frtWeightedCount += (int) $frt['samples'];
            } else {
                $frtMinutesSeries[] = null;
                $frtSampleSeries[] = 0;
            }
        }

        $avgFrtSeconds = $frtWeightedCount > 0
            ? (int) round($frtWeightedSeconds / $frtWeightedCount)
            : null;

        $byChannel = $this->aggregateByChannel($user, $start, $end);

        return [
            'filters' => [
                'date_from' => $start->toDateString(),
                'date_to' => $end->toDateString(),
                'channel' => $channelFilter,
            ],
            'summary' => [
                'new_chats' => $totalNewChats,
                'inbound_messages' => $totalInbound,
                'outbound_messages' => $totalOutbound,
                'avg_first_response_seconds' => $avgFrtSeconds,
                'avg_first_response_label' => $this->formatDuration($avgFrtSeconds),
                'conversations_with_reply' => $frtWeightedCount,
                'by_channel' => $byChannel,
            ],
            'series' => [
                'labels' => $days,
                'new_chats' => $newChatSeries,
                'inbound_messages' => $inboundSeries,
                'outbound_messages' => $outboundSeries,
                'avg_first_response_minutes' => $frtMinutesSeries,
                'first_response_samples' => $frtSampleSeries,
            ],
        ];
    }

    private function normalizeChannel(?string $channel): ?string
    {
        if ($channel === null || $channel === '' || $channel === 'all') {
            return null;
        }

        $allowed = ['whatsapp', 'instagram', 'messenger', 'facebook'];
        if (! in_array($channel, $allowed, true)) {
            return null;
        }

        if ($channel === 'facebook') {
            return 'messenger';
        }

        return $channel;
    }

    /**
     * @return list<string>
     */
    private function buildDaySeries(Carbon $start, Carbon $end): array
    {
        $days = [];
        foreach (CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->startOfDay()) as $day) {
            $days[] = $day->format('Y-m-d');
        }

        return $days;
    }

    private function scopedConversations(User $user, ?string $channel): Builder
    {
        $query = OmniConversation::query()->select('id');
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        OmnichannelAuthorization::applyInboxVisibility($query, $user, 'all', $canSeeAll);

        if ($channel === 'messenger') {
            $query->whereIn('channel', ['messenger', 'facebook']);
        } elseif ($channel !== null) {
            $query->where('channel', $channel);
        }

        return $query;
    }

    /**
     * @return array<string, array{inbound: int, outbound: int}>
     */
    private function aggregateMessagesByDay(User $user, Carbon $start, Carbon $end, ?string $channel): array
    {
        $dateExpr = 'DATE(COALESCE(omni_messages.sent_at, omni_messages.created_at))';

        $rows = OmniMessage::query()
            ->selectRaw("{$dateExpr} as day")
            ->selectRaw("SUM(CASE WHEN omni_messages.direction = 'inbound' THEN 1 ELSE 0 END) as inbound_cnt")
            ->selectRaw("SUM(CASE WHEN omni_messages.direction = 'outbound' THEN 1 ELSE 0 END) as outbound_cnt")
            ->whereIn('conversation_id', $this->scopedConversations($user, $channel))
            ->whereIn('direction', ['inbound', 'outbound'])
            ->whereRaw('COALESCE(sent_at, created_at) BETWEEN ? AND ?', [$start, $end])
            ->groupByRaw($dateExpr)
            ->orderBy('day')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->day] = [
                'inbound' => (int) $row->inbound_cnt,
                'outbound' => (int) $row->outbound_cnt,
            ];
        }

        return $map;
    }

    /**
     * Chat baru = percakapan yang pesan masuk pertama pelanggan jatuh pada hari tersebut.
     *
     * @return array<string, int>
     */
    private function aggregateNewChatsByDay(User $user, Carbon $start, Carbon $end, ?string $channel): array
    {
        $firstInbound = OmniMessage::query()
            ->select('conversation_id')
            ->selectRaw('MIN(COALESCE(sent_at, created_at)) as first_inbound_at')
            ->where('direction', 'inbound')
            ->whereIn('conversation_id', $this->scopedConversations($user, $channel))
            ->groupBy('conversation_id');

        $rows = DB::query()
            ->fromSub($firstInbound, 'fi')
            ->selectRaw('DATE(fi.first_inbound_at) as day')
            ->selectRaw('COUNT(*) as cnt')
            ->whereBetween('fi.first_inbound_at', [$start, $end])
            ->groupByRaw('DATE(fi.first_inbound_at)')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->day] = (int) $row->cnt;
        }

        return $map;
    }

    /**
     * Waktu balas pertama: dari pesan masuk pertama pelanggan → balasan outbound pertama.
     *
     * @return array<string, array{avg_seconds: ?float, samples: int}>
     */
    private function aggregateFirstResponseByDay(User $user, Carbon $start, Carbon $end, ?string $channel): array
    {
        $scoped = $this->scopedConversations($user, $channel);

        $firstInbound = OmniMessage::query()
            ->select('conversation_id')
            ->selectRaw('MIN(COALESCE(sent_at, created_at)) as first_inbound_at')
            ->where('direction', 'inbound')
            ->whereIn('conversation_id', $scoped)
            ->groupBy('conversation_id');

        $firstOutbound = OmniMessage::query()
            ->select('conversation_id')
            ->selectRaw('MIN(COALESCE(sent_at, created_at)) as first_outbound_at')
            ->where('direction', 'outbound')
            ->whereIn('conversation_id', $scoped)
            ->groupBy('conversation_id');

        $rows = DB::query()
            ->fromSub($firstInbound, 'fi')
            ->joinSub($firstOutbound, 'fo', 'fo.conversation_id', '=', 'fi.conversation_id')
            ->whereColumn('fo.first_outbound_at', '>=', 'fi.first_inbound_at')
            ->whereBetween('fi.first_inbound_at', [$start, $end])
            ->selectRaw('DATE(fi.first_inbound_at) as day')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, fi.first_inbound_at, fo.first_outbound_at)) as avg_seconds')
            ->selectRaw('COUNT(*) as samples')
            ->groupByRaw('DATE(fi.first_inbound_at)')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->day] = [
                'avg_seconds' => $row->avg_seconds !== null ? (float) $row->avg_seconds : null,
                'samples' => (int) $row->samples,
            ];
        }

        return $map;
    }

    /**
     * @return list<array{channel: string, label: string, new_chats: int, inbound: int, outbound: int}>
     */
    private function aggregateByChannel(User $user, Carbon $start, Carbon $end): array
    {
        $channels = ['whatsapp', 'instagram', 'messenger'];
        $result = [];

        foreach ($channels as $ch) {
            $msg = $this->aggregateMessagesByDay($user, $start, $end, $ch);
            $chats = $this->aggregateNewChatsByDay($user, $start, $end, $ch);
            $inbound = array_sum(array_column($msg, 'inbound'));
            $outbound = array_sum(array_column($msg, 'outbound'));
            $newChats = array_sum($chats);

            if ($newChats === 0 && $inbound === 0 && $outbound === 0) {
                continue;
            }

            $result[] = [
                'channel' => $ch,
                'label' => $this->channelLabel($ch),
                'new_chats' => $newChats,
                'inbound' => $inbound,
                'outbound' => $outbound,
            ];
        }

        return $result;
    }

    private function channelLabel(string $channel): string
    {
        return match ($channel) {
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'messenger' => 'Messenger',
            default => ucfirst($channel),
        };
    }

    private function formatDuration(?int $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds.' detik';
        }

        if ($seconds < 3600) {
            $m = (int) floor($seconds / 60);
            $s = $seconds % 60;

            return $s > 0 ? "{$m} menit {$s} dtk" : "{$m} menit";
        }

        $h = (int) floor($seconds / 3600);
        $m = (int) floor(($seconds % 3600) / 60);

        return $m > 0 ? "{$h} jam {$m} menit" : "{$h} jam";
    }
}
