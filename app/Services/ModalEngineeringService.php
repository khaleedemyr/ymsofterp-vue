<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Perhitungan Modal x Engineering — selaras dengan laporan reports/modal-engineering.
 * Modal = stock cut + category cost (type usage). Engineering = total penjualan sebelum diskon/pajak/service.
 */
class ModalEngineeringService
{
    /**
     * @return array{
     *     stock_cut: float,
     *     category_cost_usage: float,
     *     total_modal: float,
     *     engineering: float,
     *     modal_x_engineering_pct: float|null
     * }
     */
    public function totalsForPeriod(int $outletId, string $rangeStart, string $rangeEnd, ?string $outletQr = null): array
    {
        if ($outletId <= 0) {
            return $this->emptyTotals();
        }

        $stockCut = (float) DB::table('stock_cut_details as scd')
            ->join('stock_cut_logs as scl', 'scd.stock_cut_log_id', '=', 'scl.id')
            ->where('scl.outlet_id', $outletId)
            ->where('scl.status', 'success')
            ->whereBetween('scl.tanggal', [$rangeStart, $rangeEnd])
            ->sum(DB::raw('COALESCE(scd.value_out, 0)'));

        $categoryCostUsage = (float) DB::table('outlet_internal_use_waste_headers as h')
            ->where('h.outlet_id', $outletId)
            ->where('h.type', 'usage')
            ->whereIn('h.status', ['APPROVED', 'PROCESSED'])
            ->whereBetween('h.date', [$rangeStart, $rangeEnd])
            ->sum(DB::raw('COALESCE(h.subtotal_mac, 0)'));

        $engineering = $this->engineeringTotal($outletId, $rangeStart, $rangeEnd, $outletQr);

        $stockCut = round($stockCut, 2);
        $categoryCostUsage = round($categoryCostUsage, 2);
        $totalModal = round($stockCut + $categoryCostUsage, 2);
        $engineering = round($engineering, 2);

        return [
            'stock_cut' => $stockCut,
            'category_cost_usage' => $categoryCostUsage,
            'total_modal' => $totalModal,
            'engineering' => $engineering,
            'modal_x_engineering_pct' => $engineering > 0
                ? round(($totalModal / $engineering) * 100, 2)
                : null,
        ];
    }

    /**
     * @return array<string, float|null>
     */
    private function emptyTotals(): array
    {
        return [
            'stock_cut' => 0.0,
            'category_cost_usage' => 0.0,
            'total_modal' => 0.0,
            'engineering' => 0.0,
            'modal_x_engineering_pct' => null,
        ];
    }

    private function engineeringTotal(int $outletId, string $rangeStart, string $rangeEnd, ?string $outletQr): float
    {
        $qr = trim((string) ($outletQr ?? ''));
        if ($qr === '') {
            $qr = trim((string) (DB::table('tbl_data_outlet')
                ->where('id_outlet', $outletId)
                ->value('qr_code') ?? ''));
        }

        if ($qr === '') {
            return 0.0;
        }

        return (float) DB::table('orders')
            ->where('kode_outlet', $qr)
            ->whereBetween(DB::raw('DATE(created_at)'), [$rangeStart, $rangeEnd])
            ->sum(DB::raw('COALESCE(total, 0)'));
    }
}
