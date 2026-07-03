<?php

namespace App\Http\Controllers;

use App\Models\ManualCogsDeviationCatcost;
use App\Models\ManualCogsDeviationCatcostItem;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ManualCogsDeviationCatcostController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->get('month', '');
        $year = $request->get('year', '');

        $query = ManualCogsDeviationCatcost::query()
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

        return Inertia::render('ManualCogsDeviationCatcost/Index', [
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
        return Inertia::render('ManualCogsDeviationCatcost/Form', [
            'record' => null,
            'outlets' => $this->outletOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $exists = ManualCogsDeviationCatcost::where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'Data untuk periode bulan dan tahun ini sudah ada.',
            ]);
        }

        DB::beginTransaction();
        try {
            $record = ManualCogsDeviationCatcost::create([
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
            ->route('manual-cogs-deviation-catcost.index')
            ->with('success', 'Manual COGS, Deviation & Catcost berhasil disimpan.');
    }

    public function show(ManualCogsDeviationCatcost $manualCogsDeviationCatcost): Response
    {
        $manualCogsDeviationCatcost->load(['creator', 'items.outlet']);

        return Inertia::render('ManualCogsDeviationCatcost/Show', [
            'record' => $manualCogsDeviationCatcost,
            'monthLabel' => $this->monthLabel((int) $manualCogsDeviationCatcost->month),
        ]);
    }

    public function edit(ManualCogsDeviationCatcost $manualCogsDeviationCatcost): Response
    {
        $manualCogsDeviationCatcost->load(['items.outlet']);

        return Inertia::render('ManualCogsDeviationCatcost/Form', [
            'record' => $manualCogsDeviationCatcost,
            'outlets' => $this->outletOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    public function update(Request $request, ManualCogsDeviationCatcost $manualCogsDeviationCatcost)
    {
        $validated = $this->validatePayload($request);

        $exists = ManualCogsDeviationCatcost::where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->where('id', '!=', $manualCogsDeviationCatcost->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'Data untuk periode bulan dan tahun ini sudah ada.',
            ]);
        }

        DB::beginTransaction();
        try {
            $manualCogsDeviationCatcost->update([
                'month' => $validated['month'],
                'year' => $validated['year'],
                'updated_by' => auth()->id(),
            ]);

            $manualCogsDeviationCatcost->items()->delete();
            $this->syncItems($manualCogsDeviationCatcost, $validated['items']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('manual-cogs-deviation-catcost.index')
            ->with('success', 'Manual COGS, Deviation & Catcost berhasil diperbarui.');
    }

    public function destroy(ManualCogsDeviationCatcost $manualCogsDeviationCatcost)
    {
        $manualCogsDeviationCatcost->delete();

        return redirect()
            ->route('manual-cogs-deviation-catcost.index')
            ->with('success', 'Data berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'items' => 'required|array|min:1',
            'items.*.outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'items.*.cogs_value' => 'nullable|numeric',
            'items.*.cogs_percent' => 'nullable|numeric',
            'items.*.deviation_value' => 'nullable|numeric',
            'items.*.deviation_percent' => 'nullable|numeric',
            'items.*.catcost_value' => 'nullable|numeric',
            'items.*.catcost_percent' => 'nullable|numeric',
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

    private function syncItems(ManualCogsDeviationCatcost $record, array $items): void
    {
        foreach ($items as $item) {
            ManualCogsDeviationCatcostItem::create([
                'manual_cogs_deviation_catcost_id' => $record->id,
                'outlet_id' => (int) $item['outlet_id'],
                'cogs_value' => $item['cogs_value'] ?? 0,
                'cogs_percent' => $item['cogs_percent'] ?? 0,
                'deviation_value' => $item['deviation_value'] ?? 0,
                'deviation_percent' => $item['deviation_percent'] ?? 0,
                'catcost_value' => $item['catcost_value'] ?? 0,
                'catcost_percent' => $item['catcost_percent'] ?? 0,
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
