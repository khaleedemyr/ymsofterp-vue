<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OutletRevenueTargetController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdminOutlet = (int) ($user->id_outlet ?? 0) === 1;

        $month = $request->input('month', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();

        $selectedOutletId = (int) ($request->input('outlet_id') ?: 0);
        if (!$isAdminOutlet) {
            $selectedOutletId = (int) ($user->id_outlet ?? 0);
        } elseif ($selectedOutletId <= 0) {
            $selectedOutletId = 1;
        }

        $outletsQuery = DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        if (!$isAdminOutlet) {
            $outletsQuery->where('id_outlet', $selectedOutletId);
        }

        $outlets = $outletsQuery->get();

        $monthlyTarget = null;
        $existingForecasts = [];

        if ($selectedOutletId > 0) {
            $header = DB::table('outlet_revenue_target_headers')
                ->where('outlet_id', $selectedOutletId)
                ->where('target_month', $monthDate)
                ->first();

            if ($header) {
                $monthlyTarget = $header->monthly_target;
                $existingForecasts = DB::table('outlet_revenue_target_details')
                    ->where('header_id', $header->id)
                    ->orderBy('forecast_date')
                    ->get(['forecast_date', 'forecast_revenue']);
            }
        }

        return Inertia::render('RevenueTargets/Index', [
            'outlets' => $outlets,
            'selectedOutletId' => $selectedOutletId,
            'selectedMonth' => $month,
            'monthlyTarget' => $monthlyTarget,
            'existingForecasts' => $existingForecasts,
            'canSelectOutlet' => $isAdminOutlet,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $isAdminOutlet = (int) ($user->id_outlet ?? 0) === 1;

        $validated = $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'monthly_target' => 'nullable|numeric|min:0',
            'forecasts' => 'nullable|array',
            'forecasts.*.forecast_date' => 'required|date',
            'forecasts.*.forecast_revenue' => 'nullable|numeric|min:0',
        ]);

        $outletId = (int) $validated['outlet_id'];
        if (!$isAdminOutlet) {
            $outletId = (int) ($user->id_outlet ?? 0);
        }

        $monthDate = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()->toDateString();
        $startOfMonth = Carbon::parse($monthDate)->startOfMonth();
        $endOfMonth = Carbon::parse($monthDate)->endOfMonth();

        DB::transaction(function () use ($validated, $outletId, $monthDate, $user, $startOfMonth, $endOfMonth) {
            $header = DB::table('outlet_revenue_target_headers')
                ->where('outlet_id', $outletId)
                ->where('target_month', $monthDate)
                ->first();

            if ($header) {
                DB::table('outlet_revenue_target_headers')
                    ->where('id', $header->id)
                    ->update([
                        'monthly_target' => $validated['monthly_target'] ?? 0,
                        'updated_by' => $user->id,
                        'updated_at' => now(),
                    ]);
                $headerId = $header->id;
            } else {
                $headerId = DB::table('outlet_revenue_target_headers')->insertGetId([
                    'outlet_id' => $outletId,
                    'target_month' => $monthDate,
                    'monthly_target' => $validated['monthly_target'] ?? 0,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('outlet_revenue_target_details')->where('header_id', $headerId)->delete();

            $rows = [];
            foreach (($validated['forecasts'] ?? []) as $item) {
                $forecastDate = Carbon::parse($item['forecast_date'])->startOfDay();
                if ($forecastDate->lt($startOfMonth) || $forecastDate->gt($endOfMonth)) {
                    continue;
                }

                $rawRevenue = $item['forecast_revenue'] ?? null;
                if ($rawRevenue === null || $rawRevenue === '') {
                    continue;
                }

                $rows[] = [
                    'header_id' => $headerId,
                    'forecast_date' => $forecastDate->toDateString(),
                    'forecast_revenue' => $rawRevenue,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($rows)) {
                DB::table('outlet_revenue_target_details')->insert($rows);
            }
        });

        return redirect()
            ->route('outlet-revenue-targets.index', [
                'outlet_id' => $outletId,
                'month' => $validated['month'],
            ])
            ->with('success', 'Monthly target & daily forecast berhasil disimpan.');
    }

    public function suggest(Request $request)
    {
        $user = auth()->user();
        $isAdminOutlet = (int) ($user->id_outlet ?? 0) === 1;

        $validated = $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $outletId = (int) $validated['outlet_id'];
        if (!$isAdminOutlet) {
            $outletId = (int) ($user->id_outlet ?? 0);
        }

        $monthStart = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $previousMonthStart = $monthStart->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $monthStart->copy()->subMonth()->endOfMonth();

        $twoMonthsAgoStart = $monthStart->copy()->subMonths(2)->startOfMonth();
        $twoMonthsAgoEnd = $monthStart->copy()->subMonths(2)->endOfMonth();

        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->select('id_outlet', 'qr_code', 'nama_outlet')
            ->first();

        if (!$outlet || empty($outlet->qr_code)) {
            return response()->json([
                'message' => 'Outlet tidak valid atau QR Code outlet belum tersedia.',
            ], 422);
        }

        $previousRevenueByDate = DB::table('orders')
            ->where('kode_outlet', $outlet->qr_code)
            ->whereBetween('created_at', [
                $previousMonthStart->toDateString() . ' 00:00:00',
                $previousMonthEnd->toDateString() . ' 23:59:59',
            ])
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('DATE(created_at) as dt, SUM(grand_total) as revenue')
            ->groupBy('dt')
            ->pluck('revenue', 'dt');

        $twoMonthsAgoTotal = (float) DB::table('orders')
            ->where('kode_outlet', $outlet->qr_code)
            ->whereBetween('created_at', [
                $twoMonthsAgoStart->toDateString() . ' 00:00:00',
                $twoMonthsAgoEnd->toDateString() . ' 23:59:59',
            ])
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->sum('grand_total');

        $holidays = DB::table('tbl_kalender_perusahaan')
            ->whereBetween('tgl_libur', [$previousMonthStart->toDateString(), $monthEnd->toDateString()])
            ->pluck('tgl_libur')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        $holidaySet = array_flip($holidays);
        $statsByDow = [];
        $statsByType = [
            'weekday' => [],
            'weekend' => [],
            'holiday' => [],
        ];
        $allValues = [];
        $previousMonthTotal = 0.0;

        $cursor = $previousMonthStart->copy();
        while ($cursor->lte($previousMonthEnd)) {
            $dateKey = $cursor->toDateString();
            $dow = (int) $cursor->dayOfWeek;
            $isHoliday = isset($holidaySet[$dateKey]);
            $isWeekend = in_array($dow, [0, 6], true);
            $dayType = $isHoliday ? 'holiday' : ($isWeekend ? 'weekend' : 'weekday');
            $revenue = (float) ($previousRevenueByDate[$dateKey] ?? 0);

            $statsByDow[$dow][] = $revenue;
            $statsByType[$dayType][] = $revenue;
            $allValues[] = $revenue;
            $previousMonthTotal += $revenue;

            $cursor->addDay();
        }

        $avgByDow = [];
        foreach ($statsByDow as $dow => $values) {
            $avgByDow[(int) $dow] = $this->average($values);
        }

        $avgWeekday = $this->average($statsByType['weekday']);
        $avgWeekend = $this->average($statsByType['weekend']);
        $avgHoliday = $this->average($statsByType['holiday']);
        $avgAll = $this->average($allValues);

        // Momentum internal dipakai sebagai proxy tren demand (naik/turun) antar bulan.
        $momentumFactor = 1.00;
        if ($twoMonthsAgoTotal > 0) {
            $rawMomentum = $previousMonthTotal / $twoMonthsAgoTotal;
            $momentumFactor = max(0.90, min(1.10, $rawMomentum));
        }

        $combinedFactor = max(0.85, min(1.20, $momentumFactor));
        $holidayRatio = $avgWeekday > 0 ? ($avgHoliday / $avgWeekday) : 1.15;
        $holidayBoost = max(1.05, min(1.40, $holidayRatio > 0 ? $holidayRatio : 1.15));

        $suggestions = [];
        $suggestedMonthlyTarget = 0.0;
        $cursor = $monthStart->copy();

        while ($cursor->lte($monthEnd)) {
            $dateKey = $cursor->toDateString();
            $dow = (int) $cursor->dayOfWeek;
            $isHoliday = isset($holidaySet[$dateKey]);
            $isWeekend = in_array($dow, [0, 6], true);
            $dayType = $isHoliday ? 'holiday' : ($isWeekend ? 'weekend' : 'weekday');

            $base = $avgByDow[$dow] ?? 0;
            if ($base <= 0) {
                $base = match ($dayType) {
                    'holiday' => $avgHoliday > 0 ? $avgHoliday : ($avgWeekend > 0 ? $avgWeekend : $avgAll),
                    'weekend' => $avgWeekend > 0 ? $avgWeekend : $avgAll,
                    default => $avgWeekday > 0 ? $avgWeekday : $avgAll,
                };
            }

            $suggested = $base * $combinedFactor;
            if ($dayType === 'holiday') {
                $suggested *= $holidayBoost;
            }

            $suggested = round(max(0, $suggested), 2);
            $suggestedMonthlyTarget += $suggested;

            $suggestions[] = [
                'forecast_date' => $dateKey,
                'day_name' => $cursor->locale('id')->isoFormat('dddd'),
                'day_type' => $dayType,
                'forecast_revenue' => $suggested,
            ];

            $cursor->addDay();
        }

        return response()->json([
            'outlet' => [
                'id' => (int) $outlet->id_outlet,
                'name' => $outlet->nama_outlet,
            ],
            'month' => $validated['month'],
            'monthly_target_suggested' => round($suggestedMonthlyTarget, 2),
            'forecasts' => $suggestions,
            'factors' => [
                'momentum_factor' => round($momentumFactor, 4),
                'combined_factor' => round($combinedFactor, 4),
                'holiday_boost' => round($holidayBoost, 4),
                'historical_reference_month' => $previousMonthStart->format('Y-m'),
            ],
            'note' => 'Ini adalah saran AI berbasis data bulan sebelumnya, pola kalender (weekday/weekend/libur), dan faktor ekonomi. User tetap bisa mengubah semua nilai sebelum simpan.',
        ]);
    }

    private function average(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }
}

