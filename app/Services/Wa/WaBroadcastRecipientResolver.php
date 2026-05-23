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
    /** Samakan collation saat join orders.member_id ↔ member_apps_members.member_id */
    private const MEMBER_ID_COLLATION = 'utf8mb4_unicode_ci';
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{phone_normalized: string, wa_id: string, member_apps_member_id: ?int, omni_contact_id: ?int, display_name: ?string, source: string}>
     */
    public function resolve(array $filters, int $previewLimit = 5000): Collection
    {
        $phones = [];
        $rows = collect();

        foreach ($this->iterateRecipientRows($filters) as $row) {
            $phone = (string) ($row['phone_normalized'] ?? '');
            if ($phone === '' || ! OmniPhoneNormalizer::isValidIndonesiaMobile($phone)) {
                continue;
            }

            if (($filters['dedupe'] ?? true) !== false && isset($phones[$phone])) {
                continue;
            }

            $phones[$phone] = true;
            $rows->push($row);

            if ($rows->count() >= $previewLimit) {
                break;
            }
        }

        $exclude = $filters['exclude_phones'] ?? [];
        if (is_array($exclude) && $exclude !== []) {
            $excludeSet = collect($exclude)
                ->map(fn ($p) => OmniPhoneNormalizer::normalize((string) $p))
                ->filter()
                ->flip();
            $rows = $rows->reject(fn ($r) => $excludeSet->has($r['phone_normalized']));
        }

        return $rows->values();
    }

    /**
     * Hitung + sample untuk UI — COUNT di SQL, sample dibatasi (tanpa scan seluruh tabel).
     *
     * @param  array<string, mixed>  $filters
     * @return array{count: int, sample: Collection<int, array<string, mixed>>}
     */
    public function preview(array $filters, int $sampleLimit = 20): array
    {
        $phones = [];
        $sample = collect();
        $total = 0;

        $sources = $filters['sources'] ?? ['member', 'omni_contact'];
        if (! is_array($sources)) {
            $sources = ['member', 'omni_contact'];
        }

        $dedupe = ($filters['dedupe'] ?? true) !== false;
        $memberFilters = $filters['member'] ?? [];
        $contactFilters = $filters['omni_contact'] ?? [];

        $memberQuery = null;
        $memberCount = 0;

        if (in_array('member', $sources, true)) {
            $memberQuery = $this->buildMemberQuery($memberFilters);
            $memberCount = (int) (clone $memberQuery)->count();
            $total += $memberCount;
            $this->mergePreviewRows(
                $this->fetchMemberRowsLimited($memberFilters, max(30, $sampleLimit * 3)),
                $phones,
                $sample,
                $sampleLimit
            );
        }

        if (in_array('omni_contact', $sources, true)) {
            $omniQuery = $this->buildOmniContactQuery($contactFilters, $memberQuery, $dedupe);
            $omniCount = (int) $omniQuery->count();
            $total += $omniCount;
            $this->mergePreviewRows(
                $this->fetchOmniRowsLimited($omniQuery, max(30, $sampleLimit * 3)),
                $phones,
                $sample,
                $sampleLimit
            );
        }

        $manualIds = $filters['manual_member_ids'] ?? [];
        if (is_array($manualIds) && $manualIds !== []) {
            $total += $this->countManualMemberExtras($manualIds, $memberQuery);
            $this->mergePreviewRows(
                $this->fromManualMembers($manualIds),
                $phones,
                $sample,
                $sampleLimit
            );
        }

        $manualPhones = $filters['manual_phones'] ?? [];
        if (is_array($manualPhones) && $manualPhones !== []) {
            $total += $this->mergePreviewRows(
                $this->fromManualPhones($manualPhones),
                $phones,
                $sample,
                $sampleLimit
            );
        }

        if (! empty($filters['exclude_phones']) && is_array($filters['exclude_phones'])) {
            foreach ($filters['exclude_phones'] as $phone) {
                $normalized = OmniPhoneNormalizer::normalize((string) $phone);
                if ($normalized !== '' && isset($phones[$normalized])) {
                    $total--;
                    unset($phones[$normalized]);
                }
            }
        }

        return [
            'count' => max(0, $total),
            'sample' => $sample->values(),
        ];
    }

    /**
     * Hitung nomor unik valid tanpa memuat seluruh dataset ke memori.
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters): int
    {
        if ($this->canUseFastMemberCount($filters)) {
            return $this->countMembersFast($filters['member'] ?? []);
        }

        $phones = [];

        foreach ($this->iterateRecipientRows($filters) as $row) {
            $phone = (string) ($row['phone_normalized'] ?? '');
            if ($phone === '' || ! OmniPhoneNormalizer::isValidIndonesiaMobile($phone)) {
                continue;
            }
            $phones[$phone] = true;
        }

        $this->applyExcludePhones($filters, $phones);

        return count($phones);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Generator<int, array<string, mixed>>
     */
    private function iterateRecipientRows(array $filters): \Generator
    {
        $sources = $filters['sources'] ?? ['member', 'omni_contact'];
        if (! is_array($sources)) {
            $sources = ['member', 'omni_contact'];
        }

        $manualIds = $filters['manual_member_ids'] ?? [];
        if (is_array($manualIds) && $manualIds !== []) {
            foreach ($this->fromManualMembers($manualIds) as $row) {
                if (is_array($row)) {
                    yield $row;
                }
            }
        }

        $manualPhones = $filters['manual_phones'] ?? [];
        if (is_array($manualPhones) && $manualPhones !== []) {
            foreach ($this->fromManualPhones($manualPhones) as $row) {
                if (is_array($row)) {
                    yield $row;
                }
            }
        }

        if (in_array('member', $sources, true)) {
            yield from $this->iterateMembers($filters['member'] ?? []);
        }

        if (in_array('omni_contact', $sources, true)) {
            yield from $this->iterateOmniContacts($filters['omni_contact'] ?? []);
        }
    }

    /**
     * @param  array<string, true>  $phones
     * @param  array<string, mixed>  $filters
     */
    private function applyExcludePhones(array $filters, array &$phones): void
    {
        $exclude = $filters['exclude_phones'] ?? [];
        if (! is_array($exclude) || $exclude === []) {
            return;
        }

        foreach ($exclude as $phone) {
            $normalized = OmniPhoneNormalizer::normalize((string) $phone);
            if ($normalized !== '') {
                unset($phones[$normalized]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function canUseFastMemberCount(array $filters): bool
    {
        $sources = $filters['sources'] ?? [];
        if (! is_array($sources) || $sources !== ['member']) {
            return false;
        }

        if (! empty($filters['manual_member_ids']) || ! empty($filters['manual_phones'])) {
            return false;
        }

        if (! empty($filters['exclude_phones'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $memberFilters
     */
    private function countMembersFast(array $memberFilters): int
    {
        return (int) $this->buildMemberQuery($memberFilters)->count();
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
     * @return \Generator<int, array<string, mixed>>
     */
    private function iterateMembers(array $memberFilters): \Generator
    {
        $query = $this->buildMemberQuery($memberFilters);

        foreach ($query->select(['id', 'nama_lengkap', 'mobile_phone'])->orderBy('id')->cursor() as $member) {
            $row = $this->rowFromMember($member, 'member');
            if ($row !== null) {
                yield $row;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $memberFilters
     */
    private function buildMemberQuery(array $memberFilters): Builder
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

        return $query;
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
     * @return \Generator<int, array<string, mixed>>
     */
    private function iterateOmniContacts(array $contactFilters): \Generator
    {
        $query = $this->buildOmniContactQuery($contactFilters);

        foreach ($query->select(['id', 'phone_normalized', 'display_name', 'member_apps_member_id'])->orderBy('id')->cursor() as $contact) {
            $normalized = OmniPhoneNormalizer::normalize((string) $contact->phone_normalized);
            if ($normalized === '') {
                continue;
            }

            yield [
                'phone_normalized' => $normalized,
                'wa_id' => $normalized,
                'member_apps_member_id' => $contact->member_apps_member_id ? (int) $contact->member_apps_member_id : null,
                'omni_contact_id' => (int) $contact->id,
                'display_name' => $contact->display_name,
                'source' => 'omni_contact',
            ];
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>|null>  $rows
     */
    private function mergePreviewRows(Collection $rows, array &$phones, Collection $sample, int $sampleLimit): int
    {
        $added = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $phone = (string) ($row['phone_normalized'] ?? '');
            if ($phone === '' || ! OmniPhoneNormalizer::isValidIndonesiaMobile($phone)) {
                continue;
            }

            if (isset($phones[$phone])) {
                continue;
            }

            $phones[$phone] = true;
            $added++;

            if ($sample->count() < $sampleLimit) {
                $sample->push($row);
            }
        }

        return $added;
    }

    /**
     * ID manual yang belum masuk hitungan query member (hindari double count).
     *
     * @param  list<int|string>  $manualIds
     */
    private function countManualMemberExtras(array $manualIds, ?Builder $memberQuery): int
    {
        $query = MemberAppsMember::query()
            ->whereIn('id', array_map('intval', $manualIds))
            ->where('is_active', 1)
            ->whereNotNull('mobile_phone')
            ->where('mobile_phone', '!=', '');

        if ($memberQuery !== null) {
            $query->whereNotIn('id', (clone $memberQuery)->select('id'));
        }

        return (int) $query->count();
    }

    /**
     * @param  array<string, mixed>  $memberFilters
     * @return Collection<int, array<string, mixed>|null>
     */
    private function fetchMemberRowsLimited(array $memberFilters, int $limit): Collection
    {
        return $this->buildMemberQuery($memberFilters)
            ->select(['id', 'nama_lengkap', 'mobile_phone'])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (MemberAppsMember $m) => $this->rowFromMember($m, 'member'))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchOmniRowsLimited(Builder $query, int $limit): Collection
    {
        return (clone $query)
            ->select(['id', 'phone_normalized', 'display_name', 'member_apps_member_id'])
            ->orderBy('id')
            ->limit($limit)
            ->get()
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
            ->values();
    }

    /**
     * @param  array<string, mixed>  $contactFilters
     */
    private function buildOmniContactQuery(
        array $contactFilters,
        ?Builder $memberQueryForDedupe = null,
        bool $dedupeWithMembers = true
    ): Builder {
        $query = OmniContact::query()
            ->whereNotNull('phone_normalized')
            ->where('phone_normalized', '!=', '');

        if (! empty($contactFilters['transaction_from']) || ! empty($contactFilters['transaction_to'])) {
            $memberIds = $this->buildMemberQuery($contactFilters)->select('id');
            $query->whereIn('member_apps_member_id', $memberIds);
        } else {
            $query->where(function (Builder $q) {
                $q->whereNull('member_apps_member_id')
                    ->orWhereIn('member_apps_member_id', function ($sub) {
                        $sub->select('id')
                            ->from('member_apps_members')
                            ->where('is_active', 1)
                            ->whereNotNull('mobile_phone')
                            ->where('mobile_phone', '!=', '');
                    });
            });
        }

        if ($dedupeWithMembers && $memberQueryForDedupe !== null) {
            $memberIds = (clone $memberQueryForDedupe)->select('id');
            $query->where(function (Builder $q) use ($memberIds) {
                $q->whereNull('member_apps_member_id')
                    ->orWhereNotIn('member_apps_member_id', $memberIds);
            });
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

        return $query;
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

        $collation = self::MEMBER_ID_COLLATION;
        $bindings = ['paid'];
        $dateSql = '';

        if ($from !== null) {
            $dateSql .= ' AND orders.created_at >= ?';
            $bindings[] = $from;
        }
        if ($to !== null) {
            $dateSql .= ' AND orders.created_at <= ?';
            $bindings[] = $to;
        }

        $query->whereRaw(
            "member_apps_members.member_id COLLATE {$collation} IN (
                SELECT DISTINCT orders.member_id COLLATE {$collation}
                FROM orders
                WHERE orders.status = ?
                {$dateSql}
            )",
            $bindings
        );
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
