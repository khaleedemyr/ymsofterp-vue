<?php

namespace App\Http\Controllers\Report;

use App\Exports\FbProductCalibrationReportExport;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Services\FbProductCalibrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class FbProductCalibrationReportController extends Controller
{
    public function __construct(
        private readonly FbProductCalibrationService $service
    ) {}

    public function index(): Response
    {
        return Inertia::render('Report/FbProductCalibrationReport', [
            'parameterOptions' => $this->service->parameterOptions(),
            'outlets' => Outlet::where('status', 'A')
                ->where('is_outlet', 1)
                ->orderBy('nama_outlet')
                ->get(['id_outlet', 'nama_outlet']),
        ]);
    }

    public function report(Request $request)
    {
        $validated = $this->validateFilters($request);

        return response()->json($this->service->buildReport(
            $validated['date_from'],
            $validated['date_to'],
            isset($validated['outlet_id']) ? (int) $validated['outlet_id'] : null,
            $validated['employee_search'] ?? null
        ));
    }

    public function apiFilters()
    {
        return response()->json([
            'success' => true,
            'parameter_options' => $this->service->parameterOptions(),
            'outlets' => Outlet::where('status', 'A')
                ->where('is_outlet', 1)
                ->orderBy('nama_outlet')
                ->get(['id_outlet', 'nama_outlet']),
        ]);
    }

    public function apiReport(Request $request)
    {
        try {
            $validated = $this->validateFilters($request);
            $data = $this->service->buildReport(
                $validated['date_from'],
                $validated['date_to'],
                isset($validated['outlet_id']) ? (int) $validated['outlet_id'] : null,
                $validated['employee_search'] ?? null
            );

            return response()->json([
                'success' => true,
                ...$data,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function apiExport(Request $request)
    {
        try {
            $validated = $this->validateFilters($request);

            $data = $this->service->buildReport(
                $validated['date_from'],
                $validated['date_to'],
                isset($validated['outlet_id']) ? (int) $validated['outlet_id'] : null,
                $validated['employee_search'] ?? null
            );

            $filename = sprintf(
                'fb_product_calibration_report_%s_%s_%s.xlsx',
                $validated['date_from'],
                $validated['date_to'],
                now()->format('Ymd_His')
            );

            return Excel::download(new FbProductCalibrationReportExport($data), $filename);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function export(Request $request)
    {
        $validated = $this->validateFilters($request);

        $data = $this->service->buildReport(
            $validated['date_from'],
            $validated['date_to'],
            isset($validated['outlet_id']) ? (int) $validated['outlet_id'] : null,
            $validated['employee_search'] ?? null
        );

        $filename = sprintf(
            'fb_product_calibration_report_%s_%s_%s.xlsx',
            $validated['date_from'],
            $validated['date_to'],
            now()->format('Ymd_His')
        );

        return Excel::download(new FbProductCalibrationReportExport($data), $filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'outlet_id' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'employee_search' => 'nullable|string|max:120',
        ]);
    }
}
