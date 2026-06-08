<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class InventoryCardSerialGrouper
{
    /**
     * @var array<int, string>
     */
    private const GROUPABLE_REFERENCE_TYPES = [
        'serial_receive',
        'serial_receive_rollback',
        'outlet_transfer',
        'internal_warehouse_transfer',
        'outlet_food_return',
        'delivery_order',
        'internal_use_waste',
        'warehouse_transfer',
        'warehouse_sale',
    ];

    /**
     * @param  iterable<int, object|array<string, mixed>>  $cards
     * @return array<int, array<string, mixed>>
     */
    public static function group(iterable $cards): array
    {
        $normalized = [];
        foreach ($cards as $card) {
            $normalized[] = self::normalizeCard($card);
        }

        if ($normalized === []) {
            return [];
        }

        $bucketed = [];
        foreach ($normalized as $index => $card) {
            $key = self::groupKey($card);
            if ($key === null) {
                continue;
            }
            $bucketed[$key][] = ['index' => $index, 'card' => $card];
        }

        $mergeKeys = [];
        foreach ($bucketed as $key => $members) {
            if (count($members) >= 2) {
                $mergeKeys[$key] = true;
            }
        }

        $mergedAtIndex = [];
        $skipIndexes = [];

        foreach ($bucketed as $key => $members) {
            if (! isset($mergeKeys[$key])) {
                continue;
            }

            $cardsOnly = array_map(fn (array $member) => $member['card'], $members);
            $mergedAtIndex[$members[0]['index']] = self::buildGroupedCard($cardsOnly);

            foreach ($members as $member) {
                if ($member['index'] !== $members[0]['index']) {
                    $skipIndexes[$member['index']] = true;
                }
            }
        }

        $result = [];
        foreach ($normalized as $index => $card) {
            if (isset($skipIndexes[$index])) {
                continue;
            }

            if (isset($mergedAtIndex[$index])) {
                $result[] = $mergedAtIndex[$index];
                continue;
            }

            $enriched = self::enrichDeliveryOrderFromMovements($card);
            $result[] = $enriched ?? self::wrapSingle($card);
        }

        return $result;
    }

    /**
     * @param  object|array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private static function normalizeCard(object|array $card): array
    {
        return is_array($card) ? $card : (array) $card;
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private static function groupKey(array $card): ?string
    {
        $referenceType = trim((string) ($card['reference_type'] ?? ''));
        $referenceId = (int) ($card['reference_id'] ?? 0);
        $itemId = (int) ($card['item_id'] ?? 0);
        $date = substr((string) ($card['date'] ?? ''), 0, 10);

        if ($referenceType === '' || $referenceId <= 0 || $itemId <= 0 || $date === '') {
            return null;
        }

        if (! in_array($referenceType, self::GROUPABLE_REFERENCE_TYPES, true)
            && self::extractSerialNumber((string) ($card['description'] ?? '')) === null) {
            return null;
        }

        return $referenceType.'|'.$referenceId.'|'.$date.'|'.$itemId;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<string, mixed>
     */
    private static function buildGroupedCard(array $cards): array
    {
        usort($cards, function (array $a, array $b) {
            $idA = (int) ($a['id'] ?? 0);
            $idB = (int) ($b['id'] ?? 0);

            return $idA <=> $idB;
        });

        $first = $cards[0];
        $last = $cards[count($cards) - 1];
        $serialLines = [];

        foreach ($cards as $card) {
            $serialLines[] = self::buildSerialLine($card);
        }

        $grouped = $first;
        $grouped['is_grouped'] = true;
        $grouped['group_key'] = 'grp-'.($first['id'] ?? uniqid());
        $grouped['serial_count'] = count($serialLines);
        $grouped['serial_lines'] = $serialLines;
        $grouped['in_qty_small'] = self::sumField($cards, 'in_qty_small');
        $grouped['in_qty_medium'] = self::sumField($cards, 'in_qty_medium');
        $grouped['in_qty_large'] = self::sumField($cards, 'in_qty_large');
        $grouped['out_qty_small'] = self::sumField($cards, 'out_qty_small');
        $grouped['out_qty_medium'] = self::sumField($cards, 'out_qty_medium');
        $grouped['out_qty_large'] = self::sumField($cards, 'out_qty_large');
        $grouped['value_in'] = self::sumField($cards, 'value_in');
        $grouped['value_out'] = self::sumField($cards, 'value_out');
        $grouped['saldo_qty_small'] = $last['saldo_qty_small'] ?? 0;
        $grouped['saldo_qty_medium'] = $last['saldo_qty_medium'] ?? 0;
        $grouped['saldo_qty_large'] = $last['saldo_qty_large'] ?? 0;
        $grouped['saldo_value'] = $last['saldo_value'] ?? 0;
        $grouped['description'] = self::buildGroupedDescription($first, count($serialLines));

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>|null
     */
    private static function enrichDeliveryOrderFromMovements(array $card): ?array
    {
        if (($card['reference_type'] ?? '') !== 'delivery_order') {
            return null;
        }

        $doId = (int) ($card['reference_id'] ?? 0);
        $itemId = (int) ($card['item_id'] ?? 0);
        if ($doId <= 0 || $itemId <= 0) {
            return null;
        }

        $movements = DB::table('inventory_serial_movements')
            ->where('delivery_order_id', $doId)
            ->where('item_id', $itemId)
            ->where('movement_type', 'out')
            ->orderBy('id')
            ->get(['id', 'serial_number', 'qty', 'unit_id', 'moved_at']);

        if ($movements->isEmpty()) {
            return null;
        }

        $serialLines = [];
        foreach ($movements as $movement) {
            $qty = (float) ($movement->qty ?? 0);
            $serialLines[] = [
                'id' => (int) $movement->id,
                'serial_number' => (string) $movement->serial_number,
                'date' => $card['date'] ?? null,
                'in_qty_small' => 0,
                'in_qty_medium' => 0,
                'in_qty_large' => 0,
                'out_qty_small' => $qty,
                'out_qty_medium' => 0,
                'out_qty_large' => 0,
                'small_unit_name' => $card['small_unit_name'] ?? '',
                'medium_unit_name' => $card['medium_unit_name'] ?? '',
                'large_unit_name' => $card['large_unit_name'] ?? '',
                'description' => 'Serial: '.(string) $movement->serial_number,
            ];
        }

        $enriched = $card;
        $enriched['is_grouped'] = true;
        $enriched['group_key'] = 'do-'.$doId.'-'.$itemId.'-'.($card['id'] ?? 0);
        $enriched['serial_count'] = count($serialLines);
        $enriched['serial_lines'] = $serialLines;
        $enriched['description'] = self::buildGroupedDescription($card, count($serialLines));

        return $enriched;
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private static function wrapSingle(array $card): array
    {
        $card['is_grouped'] = false;
        $card['serial_lines'] = [];
        $card['serial_count'] = 0;

        return $card;
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private static function buildSerialLine(array $card): array
    {
        $serialNumber = self::extractSerialNumber((string) ($card['description'] ?? '')) ?? '-';

        return [
            'id' => $card['id'] ?? null,
            'serial_number' => $serialNumber,
            'date' => $card['date'] ?? null,
            'in_qty_small' => $card['in_qty_small'] ?? 0,
            'in_qty_medium' => $card['in_qty_medium'] ?? 0,
            'in_qty_large' => $card['in_qty_large'] ?? 0,
            'out_qty_small' => $card['out_qty_small'] ?? 0,
            'out_qty_medium' => $card['out_qty_medium'] ?? 0,
            'out_qty_large' => $card['out_qty_large'] ?? 0,
            'small_unit_name' => $card['small_unit_name'] ?? '',
            'medium_unit_name' => $card['medium_unit_name'] ?? '',
            'large_unit_name' => $card['large_unit_name'] ?? '',
            'description' => $card['description'] ?? '',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     */
    private static function sumField(array $cards, string $field): float
    {
        $total = 0.0;
        foreach ($cards as $card) {
            $total += (float) ($card[$field] ?? 0);
        }

        return round($total, 4);
    }

    private static function buildGroupedDescription(array $card, int $serialCount): string
    {
        $base = trim((string) ($card['description'] ?? ''));
        $base = preg_replace('/:\s*[A-Za-z0-9\-_.]+$/', '', $base) ?? $base;
        $base = preg_replace('/\([^)]*Serial[^)]*\)/i', '', $base) ?? $base;
        $base = trim($base, " \t\n\r\0\x0B-");

        if ($base === '') {
            $referenceType = (string) ($card['reference_type'] ?? 'transaksi');
            $base = ucwords(str_replace('_', ' ', $referenceType));
        }

        return $base.' ('.$serialCount.' nomor seri)';
    }

    private static function extractSerialNumber(string $description): ?string
    {
        $description = trim($description);
        if ($description === '') {
            return null;
        }

        $patterns = [
            '/Serial\s+Receive:\s*([^\s,;]+)/i',
            '/Rollback\s+Serial\s+Receive:\s*([^\s,;]+)/i',
            '/Serial:\s*([^\s,;)]+)/i',
            '/\(([^)]+)\)\s*$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                $serial = trim((string) ($matches[1] ?? ''));
                if ($serial !== '' && stripos($serial, 'serial') === false) {
                    return $serial;
                }
            }
        }

        return null;
    }
}
