<?php

namespace App\Http\Controllers;

use App\Models\ManualMonthlyLaborCost;
use App\Models\ManualMonthlyLaborCostItem;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ManualMonthlyLaborCostController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->get('month', '');
        $year = $request->get('year', '');

        $query = ManualMonthlyLaborCost::query()
            ->with(['creator', 'items.outlet'])
            ->withCount('items')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id');

        if ($month !== '') {
            $query->where('month', (int) $month);
        }
        if ($year !== '') {
            $query->where('year', (int) $year);
        }

        $records = $query->paginate(15)->withQueryString();

        return Inertia::render('ManualMonthlyLaborCost/Index', [
            'records' => $records,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ManualMonthlyLaborCost/Form', [
            'record' => null,
            'outlets' => $this->outletOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $exists = ManualMonthlyLaborCost::where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'Data untuk periode bulan dan tahun ini sudah ada.',
            ]);
        }

        DB::beginTransaction();
        try {
            $record = ManualMonthlyLaborCost::create([
                'month' => $validated['month'],
                'year' => $validated['year'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncItems($record, $validated['items']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('manual-monthly-labor-cost.index')
            ->with('success', 'Manual Monthly Labor Cost berhasil disimpan.');
    }

    public function show(ManualMonthlyLaborCost $manualMonthlyLaborCost): Response
    {
        $manualMonthlyLaborCost->load(['creator', 'items.outlet']);

        return Inertia::render('ManualMonthlyLaborCost/Show', [
            'record' => $manualMonthlyLaborCost,
            'monthLabel' => $this->monthLabel((int) $manualMonthlyLaborCost->month),
        ]);
    }

    public function edit(ManualMonthlyLaborCost $manualMonthlyLaborCost): Response
    {
        $manualMonthlyLaborCost->load(['items.outlet']);

        return Inertia::render('ManualMonthlyLaborCost/Form', [
            'record' => $manualMonthlyLaborCost,
            'outlets' => $this->outletOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    public function update(Request $request, ManualMonthlyLaborCost $manualMonthlyLaborCost)
    {
        $validated = $this->validatePayload($request);

        $exists = ManualMonthlyLaborCost::where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->where('id', '!=', $manualMonthlyLaborCost->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'Data untuk periode bulan dan tahun ini sudah ada.',
            ]);
        }

        DB::beginTransaction();
        try {
            $manualMonthlyLaborCost->update([
                'month' => $validated['month'],
                'year' => $validated['year'],
                'updated_by' => auth()->id(),
            ]);

            $manualMonthlyLaborCost->items()->delete();
            $this->syncItems($manualMonthlyLaborCost, $validated['items']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('manual-monthly-labor-cost.index')
            ->with('success', 'Manual Monthly Labor Cost berhasil diperbarui.');
    }

    public function destroy(ManualMonthlyLaborCost $manualMonthlyLaborCost)
    {
        $manualMonthlyLaborCost->delete();

        return redirect()
            ->route('manual-monthly-labor-cost.index')
            ->with('success', 'Data berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'items' => 'required|array|min:1',
            'items.*.outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'items.*.labor_cost_value' => 'nullable|numeric',
            'items.*.labor_cost_percent' => 'nullable|numeric',
        ], [
            'items.required' => 'Minimal satu outlet harus diisi.',
            'items.min' => 'Minimal satu outlet harus diisi.',
        ]);

        $outletIds = collect($validated['items'])->pluck('outlet_id')->map(fn ($id) => (int) $id);
        if ($outletIds->unique()->count() !== $outletIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'Outlet tidak boleh duplikat dalam satu periode.',
            ]);
        }

        return $validated;
    }

    private function syncItems(ManualMonthlyLaborCost $record, array $items): void
    {
        foreach ($items as $item) {
            ManualMonthlyLaborCostItem::create([
                'manual_monthly_labor_cost_id' => $record->id,
                'outlet_id' => (int) $item['outlet_id'],
                'labor_cost_value' => $item['labor_cost_value'] ?? 0,
                'labor_cost_percent' => $item['labor_cost_percent'] ?? 0,
            ]);
        }
    }

    private function outletOptions(): array
    {
        return Outlet::where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet'])
            ->toArray();
    }

    private function monthOptions(): array
    {
        return collect(range(1, 12))->map(fn ($m) => [
            'value' => $m,
            'label' => $this->monthLabel($m),
        ])->all();
    }

    private function yearOptions(): array
    {
        $current = (int) date('Y');

        return collect(range($current - 2, $current + 1))->map(fn ($y) => [
            'value' => $y,
            'label' => (string) $y,
        ])->reverse()->values()->all();
    }

    private function monthLabel(int $month): string
    {
        $labels = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $labels[$month] ?? (string) $month;
    }
}
