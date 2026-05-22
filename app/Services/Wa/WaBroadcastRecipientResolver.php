<?php

namespace App\Services\Wa;

use App\Models\MemberAppsMember;
use App\Models\OmniContact;
use App\Support\OmniPhoneNormalizer;
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
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters): int
    {
        return $this->resolve($filters, PHP_INT_MAX)->count();
    }

    /**
     * @param  list<int|string>  $memberIds
     */
    private function fromManualMembers(array $memberIds): Collection
    {
        return MemberAppsMember::query()
            ->whereIn('id', array_map('intval', $memberIds))
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
        $query = MemberAppsMember::query()
            ->whereNotNull('mobile_phone')
            ->where('mobile_phone', '!=', '');

        if (($memberFilters['is_active'] ?? true) !== false) {
            $query->where('is_active', 1);
        }

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
            ->filter();
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
            ->where('phone_normalized', '!=', '');

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
            ->filter();
    }
}
