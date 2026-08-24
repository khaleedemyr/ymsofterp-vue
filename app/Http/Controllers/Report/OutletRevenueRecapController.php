<?php

namespace App\Http\Controllers\Report;

use App\Exports\OutletRevenueRecapExport;
use App\Http\Controllers\Controller;
use App\Services\OutletRevenueRecapService;
use Illuminate\Http\Request;
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
        $validated = $this->validateRecapRequest($request);

        $data = $this->service->buildRecap(
            $validated['date_from'],
            $validated['date_to'],
            $validated['compare_from'] ?? null,
            $validated['compare_to'] ?? null
        );

        return response()->json($data);
    }

    public function export(Request $request)
    {
        $validated = $this->validateRecapRequest($request);

        $data = $this->service->buildRecap(
            $validated['date_from'],
            $validated['date_to'],
            $validated['compare_from'] ?? null,
            $validated['compare_to'] ?? null
        );

        $filename = sprintf(
            'rekap_revenue_outlet_%s_%s_%s.xlsx',
            $validated['date_from'],
            $validated['date_to'],
            now()->format('Ymd_His')
        );

        return Excel::download(new OutletRevenueRecapExport($data), $filename);
    }

    /**
     * @return array{date_from: string, date_to: string, compare_from?: string, compare_to?: string}
     */
    private function validateRecapRequest(Request $request): array
    {
        return $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'compare_from' => 'nullable|required_with:compare_to|date',
            'compare_to' => 'nullable|required_with:compare_from|date|after_or_equal:compare_from',
        ]);
    }
}
