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
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $data = $this->service->buildRecap(
            $validated['date_from'],
            $validated['date_to']
        );

        return response()->json($this->service->stripInternalFields($data));
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $data = $this->service->buildRecap(
            $validated['date_from'],
            $validated['date_to']
        );
        $data = $this->service->stripInternalFields($data);

        $filename = sprintf(
            'rekap_revenue_outlet_%s_%s_%s.xlsx',
            $validated['date_from'],
            $validated['date_to'],
            now()->format('Ymd_His')
        );

        return Excel::download(new OutletRevenueRecapExport($data), $filename);
    }
}
