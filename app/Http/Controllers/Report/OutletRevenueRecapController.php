<?php

namespace App\Http\Controllers\Report;

use App\Exports\OutletRevenueRecapExport;
use App\Http\Controllers\Controller;
use App\Services\OutletRevenueRecapService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class OutletRevenueRecapController extends Controller
{
    public function __construct(
        private readonly OutletRevenueRecapService $service
    ) {}

    public function index(): Response
    {
        return Inertia::render('Report/OutletRevenueRecap');
    }

    public function report(Request $request)
    {
        $periods = $this->resolvePeriods($request);
        $data = $this->service->buildRecap($periods);

        return response()->json($data);
    }

    public function export(Request $request)
    {
        $periods = $this->resolvePeriods($request);
        $data = $this->service->buildRecap($periods);

        $filename = sprintf(
            'rekap_revenue_outlet_%s_%s_%s.xlsx',
            $periods[0]['from'],
            $periods[array_key_last($periods)]['to'],
            now()->format('Ymd_His')
        );

        return Excel::download(new OutletRevenueRecapExport($data), $filename);
    }

    /**
     * @return list<array{from: string, to: string}>
     */
    private function resolvePeriods(Request $request): array
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'compare_from' => 'nullable|required_with:compare_to|date',
            'compare_to' => 'nullable|required_with:compare_from|date|after_or_equal:compare_from',
            'periods' => 'nullable|array|min:1|max:'.OutletRevenueRecapService::MAX_PERIODS,
            'periods.*.from' => 'required|date',
            'periods.*.to' => 'required|date',
        ]);

        $periods = [];

        if ($request->filled('periods') && is_array($request->input('periods'))) {
            foreach ($request->input('periods') as $i => $period) {
                if (empty($period['from']) || empty($period['to'])) {
                    continue;
                }
                if ($period['to'] < $period['from']) {
                    throw ValidationException::withMessages([
                        "periods.$i.to" => 'Tanggal To harus sama atau setelah Tanggal From (Periode '.($i + 1).').',
                    ]);
                }
                $periods[] = [
                    'from' => $period['from'],
                    'to' => $period['to'],
                ];
            }
        } elseif ($request->filled('date_from') && $request->filled('date_to')) {
            $periods[] = [
                'from' => $request->input('date_from'),
                'to' => $request->input('date_to'),
            ];

            if ($request->filled('compare_from') && $request->filled('compare_to')) {
                $periods[] = [
                    'from' => $request->input('compare_from'),
                    'to' => $request->input('compare_to'),
                ];
            }
        }

        if (count($periods) < 1) {
            throw ValidationException::withMessages([
                'date_from' => 'Minimal satu periode (Tanggal From/To) wajib diisi.',
            ]);
        }

        return $periods;
    }
}
