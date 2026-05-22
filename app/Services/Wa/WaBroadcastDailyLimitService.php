<?php

namespace App\Services\Wa;

use App\Models\WaBroadcastDailyUsage;
use Carbon\Carbon;

class WaBroadcastDailyLimitService
{
    public function cap(): int
    {
        return max(1, (int) config('omnichannel.wa_broadcast_daily_cap', 100000));
    }

    public function todayCount(?string $phoneNumberId = null): int
    {
        $phoneNumberId = $phoneNumberId ?: (string) config('services.meta.whatsapp_phone_number_id', '');

        $row = WaBroadcastDailyUsage::query()
            ->where('usage_date', Carbon::today()->toDateString())
            ->where('phone_number_id', $phoneNumberId)
            ->first();

        return (int) ($row?->sent_count ?? 0);
    }

    public function remainingToday(?string $phoneNumberId = null): int
    {
        return max(0, $this->cap() - $this->todayCount($phoneNumberId));
    }

    public function canSend(int $count = 1, ?string $phoneNumberId = null): bool
    {
        return $this->remainingToday($phoneNumberId) >= $count;
    }

    public function incrementSent(int $count = 1, ?string $phoneNumberId = null): void
    {
        $phoneNumberId = $phoneNumberId ?: (string) config('services.meta.whatsapp_phone_number_id', '');
        $date = Carbon::today()->toDateString();

        $row = WaBroadcastDailyUsage::query()->firstOrCreate(
            [
                'usage_date' => $date,
                'phone_number_id' => $phoneNumberId,
            ],
            ['sent_count' => 0]
        );

        $row->increment('sent_count', $count);
    }
}
