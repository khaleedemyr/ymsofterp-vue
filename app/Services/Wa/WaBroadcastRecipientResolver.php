<?php

namespace App\Services\Wa;

use App\Models\MemberAppsMember;
use App\Models\OmniContact;
use App\Support\OmniPhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolve penerima broadcast dari member + omni_contacts berdasarkan filter JSON.
 */
class WaBroadcastRecipientResolver
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{phone_normalized: string, wa_id: string, member_apps_member_id: ?int, omni_contact_id: ?int, display_name: ?string, source: string}>
     */
    public function resolve(array $filters, int $previewLimit = 5000): Collection
    {
        $sources = $filters['sources'] ?? ['member', 'omni_contact'];
        if (! is_array($sources)) {
            $sources = ['member', 'omni_contact'];
        }

        $rows = collect();
        $manualIds = $filters['manual_member_ids'] ?? [];
        if (is_array($manualIds) && $manualIds !== []) {
            $rows = $rows->merge($this->fromManualMembers($manualIds));
        }

        $manualPhones = $filters['manual_phones'] ?? [];
        if (is_array($manualPhones) && $manualPhones !== []) {
            $rows = $rows->merge($this->fromManualPhones($manualPhones));
        }

        if (in_array('member', $sources, true)) {
            $rows = $rows->merge($this->fromMembers($filters['member'] ?? []));
        }

        if (in_array('omni_contact', $sources, true)) {
            $rows = $rows->merge($this->fromOmniContacts($filters['omni_contact'] ?? []));
        }

        $dedupe = ($filters['dedupe'] ?? true) !== false;
        if ($dedupe) {
            $rows = $rows->unique('phone_normalized');
        }

        $exclude = $filters['exclude_phones'] ?? [];
        if (is_array($exclude) && $exclude !== []) {
            $excludeSet = collect($exclude)
                ->map(fn ($p) => OmniPhoneNormalizer::normalize((string) $p))
                ->filter()
                ->flip();
            $rows = $rows->reject(fn ($r) => $excludeSet->has($r['phone_normalized']));
        }

        return $rows
            ->filter(fn ($r) => OmniPhoneNormalizer::isValidIndonesiaMobile($r['phone_normalized']))
            ->take($previewLimit)
            ->values();
    }

    /**
     * Hitung nomor unik valid tanpa memuat seluruh dataset ke memori.
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters): int
    {
        $phones = [];

        $sources = $filters['sources'] ?? ['member', 'omni_contact'];
        if (! is_array($sources)) {
            $sources = ['member', 'omni_contact'];
        }

        $manualIds = $filters['manual_member_ids'] ?? [];
        if (is_array($manualIds) && $manualIds !== []) {
            $this->collectPhones($this->fromManualMembers($manualIds), $phones);
        }

        $manualPhones = $filters['manual_phones'] ?? [];
        if (is_array($manualPhones) && $manualPhones !== []) {
            $this->collectPhones($this->fromManualPhones($manualPhones), $phones);
        }

        if (in_array('member', $sources, true)) {
            $this->collectPhones($this->fromMembers($filters['member'] ?? []), $phones);
        }

        if (in_array('omni_contact', $sources, true)) {
            $this->collectPhones($this->fromOmniContacts($filters['omni_contact'] ?? []), $phones);
        }

        $exclude = $filters['exclude_phones'] ?? [];
        if (is_array($exclude) && $exclude !== []) {
            foreach ($exclude as $phone) {
                $normalized = OmniPhoneNormalizer::normalize((string) $phone);
                if ($normalized !== '') {
                    unset($phones[$normalized]);
                }
            }
        }

        return count($phones);
    }

    /**
     * @param  Collection<int, array<string, mixed>|null>  $rows
     * @param  array<string, true>  $phones
     */
    private function collectPhones(Collection $rows, array &$phones): void
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $phone = (string) ($row['phone_normalized'] ?? '');
            if ($phone === '' || ! OmniPhoneNormalizer::isValidIndonesiaMobile($phone)) {
                continue;
            }
            $phones[$phone] = true;
        }
    }

    /**
     * @param  list<int|string>  $memberIds
     */
    private function fromManualMembers(array $memberIds): Collection
    {
        return MemberAppsMember::query()
            ->whereIn('id', array_map('intval', $memberIds))
            ->where('is_active', 1)
            ->whereNotNull('mobile_phone')
            ->where('mobile_phone', '!=', '')
            ->get(['id', 'nama_lengkap', 'mobile_phone'])
            ->map(fn (MemberAppsMember $m) => $this->rowFromMember($m, 'manual'))
            ->filter();
    }

    /**
     * @param  list<string>  $phones
     */
    private function fromManualPhones(array $phones): Collection
    {
        return collect($phones)->map(function ($phone) {
            $normalized = OmniPhoneNormalizer::normalize((string) $phone);
            if ($normalized === '') {
                return null;
            }

            return [
                'phone_normalized' => $normalized,
                'wa_id' => $normalized,
                'member_apps_member_id' => null,
                'omni_contact_id' => null,
                'display_name' => null,
                'source' => 'manual',
            ];
        })->filter();
    }

    /**
     * @param  array<string, mixed>  $memberFilters
     */
    private function fromMembers(array $memberFilters): Collection
    {
        $query = MemberAppsMember::query();
        $this->applyStaticMemberFilters($query);
        $this->applyMemberTransactionDateFilter($query, $memberFilters);

        if (! empty($memberFilters['member_levels']) && is_array($memberFilters['member_levels'])) {
            $query->whereIn('member_level', $memberFilters['member_levels']);
        }

        if (isset($memberFilters['is_exclusive_member']) && $memberFilters['is_exclusive_member'] !== null && $memberFilters['is_exclusive_member'] !== '') {
            $query->where('is_exclusive_member', filter_var($memberFilters['is_exclusive_member'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }

        if (! empty($memberFilters['min_total_spending'])) {
            $query->where('total_spending', '>=', (float) $memberFilters['min_total_spending']);
        }

        if (! empty($memberFilters['allow_notification_only'])) {
            $query->where('allow_notification', 1);
        }

        if (! empty($memberFilters['mobile_verified_only'])) {
            $query->whereNotNull('mobile_verified_at');
        }

        if (! empty($memberFilters['search']) && is_string($memberFilters['search'])) {
            $term = '%'.trim($memberFilters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('nama_lengkap', 'like', $term)
                    ->orWhere('member_id', 'like', $term)
                    ->orWhere('mobile_phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return $query
            ->select(['id', 'nama_lengkap', 'mobile_phone'])
            ->orderBy('id')
            ->cursor()
            ->map(fn (MemberAppsMember $m) => $this->rowFromMember($m, 'member'))
            ->filter()
            ->collect();
    }

    private function rowFromMember(MemberAppsMember $member, string $source): ?array
    {
        $normalized = OmniPhoneNormalizer::normalize((string) $member->mobile_phone);
        if ($normalized === '') {
            return null;
        }

        return [
            'phone_normalized' => $normalized,
            'wa_id' => $normalized,
            'member_apps_member_id' => (int) $member->id,
            'omni_contact_id' => null,
            'display_name' => (string) $member->nama_lengkap,
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $contactFilters
     */
    private function fromOmniContacts(array $contactFilters): Collection
    {
        $query = OmniContact::query()
            ->whereNotNull('phone_normalized')
            ->where('phone_normalized', '!=', '')
            ->where(function (Builder $q) {
                $q->whereNull('member_apps_member_id')
                    ->orWhereHas('member', function (Builder $memberQuery) {
                        $this->applyStaticMemberFilters($memberQuery);
                    });
            });

        if (! empty($contactFilters['transaction_from']) || ! empty($contactFilters['transaction_to'])) {
            $this->applyMemberTransactionDateFilterOnOmniContact($query, $contactFilters);
        }

        if (isset($contactFilters['has_member_link'])) {
            if (filter_var($contactFilters['has_member_link'], FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('member_apps_member_id');
            } else {
                $query->whereNull('member_apps_member_id');
            }
        }

        if (! empty($contactFilters['search']) && is_string($contactFilters['search'])) {
            $term = '%'.trim($contactFilters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('display_name', 'like', $term)
                    ->orWhere('phone_normalized', 'like', $term);
            });
        }

        return $query
            ->select(['id', 'phone_normalized', 'display_name', 'member_apps_member_id'])
            ->orderBy('id')
            ->cursor()
            ->map(function (OmniContact $c) {
                $normalized = OmniPhoneNormalizer::normalize((string) $c->phone_normalized);
                if ($normalized === '') {
                    return null;
                }

                return [
                    'phone_normalized' => $normalized,
                    'wa_id' => $normalized,
                    'member_apps_member_id' => $c->member_apps_member_id ? (int) $c->member_apps_member_id : null,
                    'omni_contact_id' => (int) $c->id,
                    'display_name' => $c->display_name,
                    'source' => 'omni_contact',
                ];
            })
            ->filter()
            ->collect();
    }

    /**
     * Filter statis: nomor HP terisi + member aktif.
     */
    private function applyStaticMemberFilters(Builder $query): void
    {
        $query
            ->where('is_active', 1)
            ->whereNotNull('mobile_phone')
            ->where('mobile_phone', '!=', '');
    }

    /**
     * Member dengan transaksi paid di tabel orders pada rentang tanggal.
     *
     * @param  array<string, mixed>  $memberFilters
     */
    private function applyMemberTransactionDateFilter(Builder $query, array $memberFilters): void
    {
        [$from, $to] = $this->parseTransactionDateRange($memberFilters);
        if ($from === null && $to === null) {
            return;
        }

        $query->whereExists(function ($sub) use ($from, $to) {
            $sub->from('orders')
                ->whereColumn('orders.member_id', 'member_apps_members.id')
                ->where('orders.status', 'paid');

            if ($from !== null) {
                $sub->where('orders.created_at', '>=', $from);
            }
            if ($to !== null) {
                $sub->where('orders.created_at', '<=', $to);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $contactFilters
     */
    private function applyMemberTransactionDateFilterOnOmniContact(Builder $query, array $contactFilters): void
    {
        [$from, $to] = $this->parseTransactionDateRange($contactFilters);
        if ($from === null && $to === null) {
            return;
        }

        $query->whereHas('member', function (Builder $memberQuery) use ($from, $to) {
            $this->applyStaticMemberFilters($memberQuery);
            $memberQuery->whereExists(function ($sub) use ($from, $to) {
                $sub->from('orders')
                    ->whereColumn('orders.member_id', 'member_apps_members.id')
                    ->where('orders.status', 'paid');

                if ($from !== null) {
                    $sub->where('orders.created_at', '>=', $from);
                }
                if ($to !== null) {
                    $sub->where('orders.created_at', '<=', $to);
                }
            });
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function parseTransactionDateRange(array $filters): array
    {
        $fromRaw = $filters['transaction_from'] ?? null;
        $toRaw = $filters['transaction_to'] ?? null;

        if (($fromRaw === null || $fromRaw === '') && ($toRaw === null || $toRaw === '')) {
            return [null, null];
        }

        $from = ($fromRaw !== null && $fromRaw !== '')
            ? Carbon::parse((string) $fromRaw)->startOfDay()
            : null;
        $to = ($toRaw !== null && $toRaw !== '')
            ? Carbon::parse((string) $toRaw)->endOfDay()
            : null;

        if ($from === null && $to !== null) {
            $from = $to->copy()->startOfDay();
        }
        if ($from !== null && $to === null) {
            $to = $from->copy()->endOfDay();
        }

        return [$from, $to];
    }
}
