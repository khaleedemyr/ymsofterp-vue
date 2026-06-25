<?php

namespace App\Services;

use App\Models\UpsellingSalesAchievement;
use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

class UpsellingSalesAchievementService
{
    public static function calculateNetAverageCheck(float $itemPrice): float
    {
        $withService = (float) round($itemPrice + ($itemPrice * 0.05));
        $withPb1 = (float) round($withService + ($withService * 0.10));

        return $withPb1;
    }

    public function resolveAverageCheck(int $itemId, int $outletId): float
    {
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
        if (! $outlet) {
            return 0.0;
        }

        $regionId = $outlet->region_id ? (int) $outlet->region_id : null;
        $basePrice = FloorOrderItemPriceResolver::resolveMediumUnitPrice(
            $itemId,
            $regionId,
            (string) $outletId
        );

        return self::calculateNetAverageCheck($basePrice);
    }

    public function searchPosItems(int $outletId, string $term = ''): array
    {
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
        if (! $outlet) {
            return [];
        }

        $regionId = $outlet->region_id ?? null;
        $term = trim($term);

        $query = DB::table('items as i')
            ->join('categories as c', 'c.id', '=', 'i.category_id')
            ->join('category_outlet as co', function ($join) use ($outletId) {
                $join->on('co.category_id', '=', 'c.id')
                    ->where('co.outlet_id', $outletId);
            })
            ->leftJoin('sub_categories as sc', 'sc.id', '=', 'i.sub_category_id')
            ->leftJoin('item_availabilities as ia_outlet', function ($join) use ($outletId) {
                $join->on('ia_outlet.item_id', '=', 'i.id')
                    ->where('ia_outlet.availability_type', 'outlet')
                    ->where('ia_outlet.outlet_id', $outletId);
            })
            ->leftJoin('item_availabilities as ia_region', function ($join) use ($regionId) {
                $join->on('ia_region.item_id', '=', 'i.id')
                    ->where('ia_region.availability_type', 'region')
                    ->where('ia_region.region_id', $regionId);
            })
            ->leftJoin('item_availabilities as ia_all', function ($join) {
                $join->on('ia_all.item_id', '=', 'i.id')
                    ->where('ia_all.availability_type', 'all');
            })
            ->where('c.show_pos', '1')
            ->where('c.status', 'active')
            ->where('i.status', 'active')
            ->where(function ($q) {
                $q->whereNotNull('ia_outlet.id')
                    ->orWhereNotNull('ia_region.id')
                    ->orWhereNotNull('ia_all.id');
            })
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('i.name', 'like', "%{$term}%")
                        ->orWhere('i.sku', 'like', "%{$term}%")
                        ->orWhere('c.name', 'like', "%{$term}%")
                        ->orWhere('sc.name', 'like', "%{$term}%");
                });
            })
            ->select(
                'i.id',
                'i.name',
                'i.sku',
                'c.name as category_name',
                'sc.name as sub_category_name'
            )
            ->distinct()
            ->orderBy('i.name')
            ->limit(30);

        return $query->get()->map(function ($row) use ($outletId, $regionId) {
            $basePrice = FloorOrderItemPriceResolver::resolveMediumUnitPrice(
                (int) $row->id,
                $regionId ? (int) $regionId : null,
                (string) $outletId
            );
            $categoryLabel = (string) $row->category_name;
            if (! empty($row->sub_category_name)) {
                $categoryLabel .= ' - '.$row->sub_category_name;
            }

            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'sku' => (string) ($row->sku ?? ''),
                'category_label' => $categoryLabel,
                'base_price' => $basePrice,
                'average_check' => self::calculateNetAverageCheck($basePrice),
                'display_label' => $row->name.' ('.$categoryLabel.')',
            ];
        })->values()->all();
    }

    public function getActualSalesMap(int $outletId, int $month, int $year, array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
        if (! $outlet || empty($outlet->qr_code)) {
            return [];
        }

        $dateFrom = sprintf('%04d-%02d-01', $year, $month);
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        $rows = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.kode_outlet', $outlet->qr_code)
            ->whereDate('orders.created_at', '>=', $dateFrom)
            ->whereDate('orders.created_at', '<=', $dateTo)
            ->whereIn('order_items.item_id', $itemIds)
            ->select(
                'order_items.item_id',
                'order_items.qty',
                'order_items.price'
            )
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $qty = (float) $row->qty;
            $netPrice = self::calculateNetAverageCheck((float) $row->price);

            if (! isset($map[$itemId])) {
                $map[$itemId] = [
                    'cover' => 0.0,
                    'fb_revenue' => 0.0,
                ];
            }

            $map[$itemId]['cover'] += $qty;
            $map[$itemId]['fb_revenue'] += $qty * $netPrice;
        }

        foreach ($map as $itemId => $entry) {
            $cover = (float) $entry['cover'];
            $fbRevenue = (float) round($entry['fb_revenue']);
            $map[$itemId] = [
                'cover' => $cover,
                'average_check' => $cover > 0 ? (float) round($fbRevenue / $cover) : 0.0,
                'fb_revenue' => $fbRevenue,
            ];
        }

        return $map;
    }

    public function buildDetailRows(UpsellingSalesAchievement $achievement): array
    {
        $achievement->loadMissing(['items', 'outlet']);
        $itemIds = $achievement->items->pluck('item_id')->map(fn ($id) => (int) $id)->all();
        $actualMap = $this->getActualSalesMap(
            (int) $achievement->outlet_id,
            (int) $achievement->month,
            (int) $achievement->year,
            $itemIds
        );

        $rows = [];
        $totals = [
            'target_cover' => 0,
            'target_fb_revenue' => 0.0,
            'actual_cover' => 0.0,
            'actual_fb_revenue' => 0.0,
        ];

        foreach ($achievement->items as $index => $line) {
            $actual = $actualMap[(int) $line->item_id] ?? [
                'cover' => 0.0,
                'average_check' => 0.0,
                'fb_revenue' => 0.0,
            ];

            $targetRevenue = (float) $line->fb_revenue;
            $actualRevenue = (float) $actual['fb_revenue'];
            $achievementPct = $targetRevenue > 0
                ? round(($actualRevenue / $targetRevenue) * 100, 2)
                : 0.0;

            $rows[] = [
                'no' => $index + 1,
                'item_id' => (int) $line->item_id,
                'item_name' => $line->item_name,
                'category_label' => $line->category_label,
                'target' => [
                    'average_check' => (float) $line->average_check,
                    'cover' => (int) $line->cover,
                    'fb_revenue' => $targetRevenue,
                ],
                'actual' => [
                    'average_check' => (float) $actual['average_check'],
                    'cover' => (float) $actual['cover'],
                    'fb_revenue' => $actualRevenue,
                ],
                'achievement_percent' => $achievementPct,
            ];

            $totals['target_cover'] += (int) $line->cover;
            $totals['target_fb_revenue'] += $targetRevenue;
            $totals['actual_cover'] += (float) $actual['cover'];
            $totals['actual_fb_revenue'] += $actualRevenue;
        }

        $totals['achievement_percent'] = $totals['target_fb_revenue'] > 0
            ? round(($totals['actual_fb_revenue'] / $totals['target_fb_revenue']) * 100, 2)
            : 0.0;

        return [
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    public static function monthLabel(int $month): string
    {
        $labels = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $labels[$month] ?? (string) $month;
    }
}
