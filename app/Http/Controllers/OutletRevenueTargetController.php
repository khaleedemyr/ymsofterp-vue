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
        $payload = $this->buildRevenueTargetsIndexPayload($request);

        return Inertia::render('RevenueTargets/Index', [
            'outlets' => $payload['outlets'],
            'selectedOutletId' => $payload['selectedOutletId'],
            'selectedMonth' => $payload['selectedMonth'],
            'monthlyTarget' => $payload['monthlyTarget'],
            'existingForecasts' => $payload['existingForecasts'],
            'canSelectOutlet' => $payload['canSelectOutlet'],
        ]);
    }

    /**
     * GET /api/approval-app/outlet-revenue-targets — payload sama seperti halaman web (mobile app).
     */
    public function apiIndex(Request $request)
    {
        $payload = $this->buildRevenueTargetsIndexPayload($request);

        return response()->json([
            'success' => true,
            'outlets' => $payload['outlets'],
            'selectedOutletId' => $payload['selectedOutletId'],
            'selectedMonth' => $payload['selectedMonth'],
            'monthlyTarget' => $payload['monthlyTarget'],
            'existingForecasts' => $payload['existingForecasts'],
            'canSelectOutlet' => $payload['canSelectOutlet'],
        ]);
    }

    /**
     * @return array{outlets:\Illuminate\Support\Collection, selectedOutletId:int, selectedMonth:string, monthlyTarget:mixed, existingForecasts:\Illuminate\Support\Collection|array, canSelectOutlet:bool}
     */
    protected function buildRevenueTargetsIndexPayload(Request $request): array
    {
        $user = auth()->user();
        /** Hanya user dengan id_outlet = 1 (HO) yang boleh memilih outlet; lainnya dikunci ke outlet user. */
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

        return [
            'outlets' => $outlets,
            'selectedOutletId' => $selectedOutletId,
            'selectedMonth' => $month,
            'monthlyTarget' => $monthlyTarget,
            'existingForecasts' => $existingForecasts,
            'canSelectOutlet' => $isAdminOutlet,
        ];
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

    /**
     * POST /api/approval-app/outlet-revenue-targets — simpan target (response JSON untuk mobile).
     */
    public function apiStore(Request $request)
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

        return response()->json([
            'success' => true,
            'message' => 'Monthly target & daily forecast berhasil disimpan.',
            'outlet_id' => $outletId,
            'month' => $validated['month'],
        ]);
    }

    public function suggest(Request $request)
    {
        $user = auth()->user();
        $isAdminOutlet = (int) ($user->id_outlet ?? 0) === 1;

        $validated = $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'monthly_target' => 'required|numeric|min:0.01',
        ]);

        $outletId = (int) $validated['outlet_id'];
        $inputMonthlyTarget = (float) $validated['monthly_target'];
        if (!$isAdminOutlet) {
            $outletId = (int) ($user->id_outlet ?? 0);
        }

        $monthStart = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $previousMonthStart = $monthStart->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $monthStart->copy()->subMonth()->endOfMonth();

        $twoMonthsAgoStart = $monthStart->copy()->subMonths(2)->startOfMonth();
        $threeMonthsAgoStart = $monthStart->copy()->subMonths(3)->startOfMonth();

        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->select('id_outlet', 'qr_code', 'nama_outlet')
            ->first();

        if (!$outlet || empty($outlet->qr_code)) {
            return response()->json([
                'message' => 'Outlet tidak valid atau QR Code outlet belum tersedia.',
            ], 422);
        }

        $last3RevenueByDate = DB::table('orders')
            ->where('kode_outlet', $outlet->qr_code)
            ->whereBetween('created_at', [
                $threeMonthsAgoStart->toDateString() . ' 00:00:00',
                $previousMonthEnd->toDateString() . ' 23:59:59',
            ])
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('DATE(created_at) as dt, SUM(grand_total) as revenue')
            ->groupBy('dt')
            ->pluck('revenue', 'dt');

        $monthlyTotalsRaw = DB::table('orders')
            ->where('kode_outlet', $outlet->qr_code)
            ->whereBetween('created_at', [
                $threeMonthsAgoStart->toDateString() . ' 00:00:00',
                $previousMonthEnd->toDateString() . ' 23:59:59',
            ])
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(grand_total) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $referenceMonths = [
            $threeMonthsAgoStart->format('Y-m'),
            $twoMonthsAgoStart->format('Y-m'),
            $previousMonthStart->format('Y-m'),
        ];

        $monthlyTotals = [];
        foreach ($referenceMonths as $refMonth) {
            $monthlyTotals[$refMonth] = (float) ($monthlyTotalsRaw[$refMonth] ?? 0);
        }

        $holidays = DB::table('tbl_kalender_perusahaan')
            ->whereBetween('tgl_libur', [$threeMonthsAgoStart->toDateString(), $monthEnd->toDateString()])
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
        $previousMonthTotal = (float) ($monthlyTotals[$previousMonthStart->format('Y-m')] ?? 0);
        $twoMonthsAgoTotal = (float) ($monthlyTotals[$twoMonthsAgoStart->format('Y-m')] ?? 0);
        $last3AverageMonthly = $this->average(array_values($monthlyTotals));

        $cursor = $threeMonthsAgoStart->copy();
        while ($cursor->lte($previousMonthEnd)) {
            $dateKey = $cursor->toDateString();
            $dow = (int) $cursor->dayOfWeek;
            $isHoliday = isset($holidaySet[$dateKey]);
            $isWeekend = in_array($dow, [0, 6], true);
            $dayType = $isHoliday ? 'holiday' : ($isWeekend ? 'weekend' : 'weekday');
            $revenue = (float) ($last3RevenueByDate[$dateKey] ?? 0);

            $statsByDow[$dow][] = $revenue;
            $statsByType[$dayType][] = $revenue;
            $allValues[] = $revenue;

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
            $momentumFactor = max(0.80, min(0.98, $rawMomentum));
        }

        // Trend 30 hari terakhir vs 30 hari sebelumnya (maksimal netral, tidak dinaikkan).
        $recent30End = $previousMonthEnd->copy();
        $recent30Start = $recent30End->copy()->subDays(29)->startOfDay();
        $prior30End = $recent30Start->copy()->subDay()->endOfDay();
        $prior30Start = $prior30End->copy()->subDays(29)->startOfDay();

        $recent30Total = (float) DB::table('orders')
            ->where('kode_outlet', $outlet->qr_code)
            ->whereBetween('created_at', [$recent30Start->toDateTimeString(), $recent30End->toDateString() . ' 23:59:59'])
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->sum('grand_total');

        $prior30Total = (float) DB::table('orders')
            ->where('kode_outlet', $outlet->qr_code)
            ->whereBetween('created_at', [$prior30Start->toDateTimeString(), $prior30End->toDateTimeString()])
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->sum('grand_total');

        $trendFactor = 1.00;
        if ($prior30Total > 0) {
            $trendFactor = max(0.80, min(1.00, $recent30Total / $prior30Total));
        }

        // Faktor ekonomi global konservatif (sementara default turun 4%).
        $globalEconomyFactor = 0.96;

        $combinedFactor = max(0.72, min(1.02, $momentumFactor * $trendFactor * $globalEconomyFactor));
        $holidayRatio = $avgWeekday > 0 ? ($avgHoliday / $avgWeekday) : 1.15;
        $holidayBoost = max(1.03, min(1.18, $holidayRatio > 0 ? $holidayRatio : 1.10));

        $suggestions = [];
        $cursor = $monthStart->copy();
        $weightRows = [];
        $totalWeight = 0.0;

        while ($cursor->lte($monthEnd)) {
            $dateKey = $cursor->toDateString();
            $dow = (int) $cursor->dayOfWeek;
            $isHoliday = isset($holidaySet[$dateKey]);
            $isWeekend = in_array($dow, [0, 6], true);
            $dayType = $isHoliday ? 'holiday' : ($isWeekend ? 'weekend' : 'weekday');

            // Prioritas bobot: pola DoW historis -> pola tipe hari -> fallback default.
            $weight = (float) ($avgByDow[$dow] ?? 0);
            if ($weight <= 0) {
                $weight = match ($dayType) {
                    'holiday' => $avgHoliday > 0 ? $avgHoliday : 1.35,
                    'weekend' => $avgWeekend > 0 ? $avgWeekend : 1.20,
                    default => $avgWeekday > 0 ? $avgWeekday : 1.00,
                };
            }

            if ($dayType === 'holiday') {
                $weight *= $holidayBoost;
            }

            $weight *= $combinedFactor;
            $weight = max(0.01, $weight);

            $weightRows[] = [
                'forecast_date' => $dateKey,
                'day_name' => $cursor->locale('id')->isoFormat('dddd'),
                'day_type' => $dayType,
                'weight' => $weight,
            ];
            $totalWeight += $weight;
            $cursor->addDay();
        }

        $suggestedMonthlyTarget = 0.0;
        $normalizationFactor = $totalWeight > 0 ? ($inputMonthlyTarget / $totalWeight) : 0.0;

        foreach ($weightRows as $row) {
            $value = $normalizationFactor > 0
                ? round($row['weight'] * $normalizationFactor, 2)
                : 0.0;

            $suggestions[] = [
                'forecast_date' => $row['forecast_date'],
                'day_name' => $row['day_name'],
                'day_type' => $row['day_type'],
                'forecast_revenue' => $value,
            ];
            $suggestedMonthlyTarget += $value;
        }

        // Safety net: pastikan total forecast selalu mengikuti monthly target input user.
        $finalReconcileFactor = 1.0;
        if ($suggestedMonthlyTarget > 0 && $inputMonthlyTarget > 0) {
            $finalReconcileFactor = $inputMonthlyTarget / $suggestedMonthlyTarget;
            $suggestedMonthlyTarget = 0.0;

            foreach ($suggestions as $idx => $row) {
                $scaled = round(max(0, ((float) $row['forecast_revenue']) * $finalReconcileFactor), 2);
                $suggestions[$idx]['forecast_revenue'] = $scaled;
                $suggestedMonthlyTarget += $scaled;
            }
        }

        // Koreksi rounding difference ke baris terakhir agar total persis.
        $roundingDiff = round($inputMonthlyTarget - $suggestedMonthlyTarget, 2);
        if (!empty($suggestions) && abs($roundingDiff) >= 0.01) {
            $lastIdx = count($suggestions) - 1;
            $lastValue = (float) $suggestions[$lastIdx]['forecast_revenue'];
            $suggestions[$lastIdx]['forecast_revenue'] = round(max(0, $lastValue + $roundingDiff), 2);
            $suggestedMonthlyTarget = round($suggestedMonthlyTarget + $roundingDiff, 2);
        }

        $targetMonthlyFromHistory = $last3AverageMonthly > 0 ? ($last3AverageMonthly * $combinedFactor) : $inputMonthlyTarget;

        return response()->json([
            'outlet' => [
                'id' => (int) $outlet->id_outlet,
                'name' => $outlet->nama_outlet,
            ],
            'month' => $validated['month'],
            'monthly_target_suggested' => round($inputMonthlyTarget, 2),
            'forecasts' => $suggestions,
            'factors' => [
                'momentum_factor' => round($momentumFactor, 4),
                'trend_factor' => round($trendFactor, 4),
                'global_economy_factor' => round($globalEconomyFactor, 4),
                'combined_factor' => round($combinedFactor, 4),
                'holiday_boost' => round($holidayBoost, 4),
                'previous_month_total' => round($previousMonthTotal, 2),
                'last3_average_monthly' => round($last3AverageMonthly, 2),
                'input_monthly_target' => round($inputMonthlyTarget, 2),
                'target_monthly_from_history' => round($targetMonthlyFromHistory, 2),
                'normalization_factor' => round($normalizationFactor, 4),
                'total_weight' => round($totalWeight, 4),
                'final_reconcile_factor' => round($finalReconcileFactor, 6),
                'final_forecast_total' => round($suggestedMonthlyTarget, 2),
                'reference_months' => $referenceMonths,
                'historical_reference_month' => $previousMonthStart->format('Y-m'),
            ],
            'note' => 'Saran AI membagi forecast harian berdasarkan pola 3 bulan terakhir dan kalender, lalu dinormalisasi agar mengikuti monthly target input user.',
        ]);
    }

    /**
     * Hanya membaca agregasi dari orders untuk ditampilkan di UI (kartu ringkasan).
     * Tidak menyimpan apa pun ke outlet_revenue_target_*.
     */
    public function generateHistorical(Request $request)
    {
        $isAdminOutlet = (int) (auth()->user()->id_outlet ?? 0) === 1;

        $validated = $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'end_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'months_back' => 'required|integer|min:1|max:12',
        ]);

        $outletId = (int) $validated['outlet_id'];
        if (!$isAdminOutlet) {
            $outletId = (int) (auth()->user()->id_outlet ?? 0);
        }

        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->select('id_outlet', 'qr_code', 'nama_outlet')
            ->first();

        if (!$outlet || empty($outlet->qr_code)) {
            return response()->json([
                'message' => 'Outlet tidak valid atau QR Code outlet belum tersedia.',
            ], 422);
        }

        $endMonthStart = Carbon::createFromFormat('Y-m', $validated['end_month'])->startOfMonth();
        $monthsBack = (int) $validated['months_back'];

        $monthCards = [];

        // Bulan di dropdown = acuan. N bulan = N bulan SEBELUM acuan (tanpa bulan acuan).
        for ($k = 1; $k <= $monthsBack; $k++) {
            $monthStart = $endMonthStart->copy()->subMonths($k)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $monthKey = $monthStart->format('Y-m');
            $label = $monthStart->locale('id')->translatedFormat('F Y');

            $dailyActual = DB::table('orders')
                ->where('kode_outlet', $outlet->qr_code)
                ->whereBetween('created_at', [
                    $monthStart->toDateString() . ' 00:00:00',
                    $monthEnd->toDateString() . ' 23:59:59',
                ])
                ->where('status', '!=', 'cancelled')
                ->where('grand_total', '>', 0)
                ->selectRaw('DATE(created_at) as dt, SUM(grand_total) as revenue')
                ->groupBy('dt')
                ->pluck('revenue', 'dt');

            $monthlyTotal = (float) collect($dailyActual)->sum();
            $daysWithOrders = $dailyActual->filter(fn ($v) => (float) $v > 0)->count();

            $monthCards[] = [
                'ym' => $monthKey,
                'label' => $label,
                'status' => 'from_orders',
                'monthly_total' => round($monthlyTotal, 2),
                'days_with_orders' => $daysWithOrders,
            ];
        }

        usort($monthCards, fn ($a, $b) => strcmp($a['ym'], $b['ym']));

        $count = count($monthCards);

        return response()->json([
            'message' => "Ringkasan revenue dari orders (tidak disimpan): {$count} bulan.",
            'generated' => $count,
            'skipped' => 0,
            'months' => array_column($monthCards, 'ym'),
            'month_cards' => $monthCards,
        ]);
    }

    /**
     * Detail historis satu bulan dari orders (read-only): harian, weekday/weekend, lunch/dinner.
     * Lunch/Dinner mengikuti Sales Outlet Dashboard: jam <= 17 Lunch, selain itu Dinner.
     */
    public function historicalMonthDetail(Request $request)
    {
        $isAdminOutlet = (int) (auth()->user()->id_outlet ?? 0) === 1;

        $validated = $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $outletId = (int) $validated['outlet_id'];
        if (!$isAdminOutlet) {
            $outletId = (int) (auth()->user()->id_outlet ?? 0);
        }

        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->select('id_outlet', 'qr_code', 'nama_outlet')
            ->first();

        if (!$outlet || empty($outlet->qr_code)) {
            return response()->json([
                'message' => 'Outlet tidak valid atau QR Code outlet belum tersedia.',
            ], 422);
        }

        $monthStart = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $daysInMonth = (int) $monthEnd->day;

        $rangeStart = $monthStart->toDateString() . ' 00:00:00';
        $rangeEnd = $monthEnd->toDateString() . ' 23:59:59';

        $dailyTotals = DB::table('orders')
            ->where('kode_outlet', $outlet->qr_code)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('DATE(created_at) as d, SUM(grand_total) as revenue')
            ->groupBy('d')
            ->pluck('revenue', 'd');

        $dailyRows = [];
        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $key = $cursor->toDateString();
            $dailyRows[] = [
                'date' => $key,
                'day_name' => $cursor->locale('id')->isoFormat('dddd'),
                'revenue' => round((float) ($dailyTotals[$key] ?? 0), 2),
            ];
            $cursor->addDay();
        }

        $weekdayWeekendRows = DB::table('orders')
            ->where('kode_outlet', $outlet->qr_code)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw(
                "CASE WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'weekend' ELSE 'weekday' END as bucket,
                SUM(grand_total) as revenue,
                COUNT(*) as order_count"
            )
            ->groupBy(DB::raw("CASE WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'weekend' ELSE 'weekday' END"))
            ->get();

        $weekdayWeekend = $weekdayWeekendRows->keyBy('bucket');

        $weekdayTotal = (float) ($weekdayWeekend['weekday']->revenue ?? 0);
        $weekendTotal = (float) ($weekdayWeekend['weekend']->revenue ?? 0);
        $weekdayOrders = (int) ($weekdayWeekend['weekday']->order_count ?? 0);
        $weekendOrders = (int) ($weekdayWeekend['weekend']->order_count ?? 0);

        $weekdayCalendarDays = 0;
        $weekendCalendarDays = 0;
        $walk = $monthStart->copy();
        while ($walk->lte($monthEnd)) {
            if ($walk->isWeekend()) {
                $weekendCalendarDays++;
            } else {
                $weekdayCalendarDays++;
            }
            $walk->addDay();
        }

        $avgWeekdayPerCalendarDay = $weekdayCalendarDays > 0
            ? round($weekdayTotal / $weekdayCalendarDays, 2)
            : 0.0;
        $avgWeekendPerCalendarDay = $weekendCalendarDays > 0
            ? round($weekendTotal / $weekendCalendarDays, 2)
            : 0.0;

        $lunchDinnerRows = DB::table('orders')
            ->where('kode_outlet', $outlet->qr_code)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw(
                "CASE WHEN HOUR(created_at) <= 17 THEN 'lunch' ELSE 'dinner' END as bucket,
                SUM(grand_total) as revenue,
                COUNT(*) as order_count"
            )
            ->groupBy(DB::raw('CASE WHEN HOUR(created_at) <= 17 THEN \'lunch\' ELSE \'dinner\' END'))
            ->get();

        $lunchDinner = $lunchDinnerRows->keyBy('bucket');

        $lunchRev = (float) ($lunchDinner['lunch']->revenue ?? 0);
        $dinnerRev = (float) ($lunchDinner['dinner']->revenue ?? 0);
        $lunchOrders = (int) ($lunchDinner['lunch']->order_count ?? 0);
        $dinnerOrders = (int) ($lunchDinner['dinner']->order_count ?? 0);

        return response()->json([
            'outlet' => [
                'id' => (int) $outlet->id_outlet,
                'name' => $outlet->nama_outlet,
            ],
            'month' => $validated['month'],
            'month_label' => $monthStart->locale('id')->translatedFormat('F Y'),
            'days_in_month' => $daysInMonth,
            'daily' => $dailyRows,
            'weekday_weekend' => [
                'weekday_total' => round($weekdayTotal, 2),
                'weekend_total' => round($weekendTotal, 2),
                'weekday_orders' => $weekdayOrders,
                'weekend_orders' => $weekendOrders,
                'weekday_calendar_days' => $weekdayCalendarDays,
                'weekend_calendar_days' => $weekendCalendarDays,
                'avg_weekday_per_calendar_day' => $avgWeekdayPerCalendarDay,
                'avg_weekend_per_calendar_day' => $avgWeekendPerCalendarDay,
            ],
            'lunch_dinner' => [
                'lunch' => [
                    'revenue' => round($lunchRev, 2),
                    'orders' => $lunchOrders,
                    'rule' => 'Jam order <= 17:00 = Lunch (sama seperti Sales Outlet Dashboard)',
                ],
                'dinner' => [
                    'revenue' => round($dinnerRev, 2),
                    'orders' => $dinnerOrders,
                    'rule' => 'Jam order > 17:00 = Dinner',
                ],
            ],
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

