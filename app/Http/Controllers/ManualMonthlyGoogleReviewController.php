<?php

namespace App\Http\Controllers;

use App\Models\ManualMonthlyGoogleReview;
use App\Models\ManualMonthlyGoogleReviewItem;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ManualMonthlyGoogleReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->get('month', '');
        $year = $request->get('year', '');

        $query = ManualMonthlyGoogleReview::query()
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

        return Inertia::render('ManualMonthlyGoogleReview/Index', [
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
        return Inertia::render('ManualMonthlyGoogleReview/Form', [
            'record' => null,
            'outlets' => $this->outletOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $exists = ManualMonthlyGoogleReview::where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'Data untuk periode bulan dan tahun ini sudah ada.',
            ]);
        }

        DB::beginTransaction();
        try {
            $record = ManualMonthlyGoogleReview::create([
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
            ->route('manual-monthly-google-review.index')
            ->with('success', 'Manual Monthly Google Review berhasil disimpan.');
    }

    public function show(ManualMonthlyGoogleReview $manualMonthlyGoogleReview): Response
    {
        $manualMonthlyGoogleReview->load(['creator', 'items.outlet']);

        return Inertia::render('ManualMonthlyGoogleReview/Show', [
            'record' => $manualMonthlyGoogleReview,
            'monthLabel' => $this->monthLabel((int) $manualMonthlyGoogleReview->month),
        ]);
    }

    public function edit(ManualMonthlyGoogleReview $manualMonthlyGoogleReview): Response
    {
        $manualMonthlyGoogleReview->load(['items.outlet']);

        return Inertia::render('ManualMonthlyGoogleReview/Form', [
            'record' => $manualMonthlyGoogleReview,
            'outlets' => $this->outletOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    public function update(Request $request, ManualMonthlyGoogleReview $manualMonthlyGoogleReview)
    {
        $validated = $this->validatePayload($request);

        $exists = ManualMonthlyGoogleReview::where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->where('id', '!=', $manualMonthlyGoogleReview->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'Data untuk periode bulan dan tahun ini sudah ada.',
            ]);
        }

        DB::beginTransaction();
        try {
            $manualMonthlyGoogleReview->update([
                'month' => $validated['month'],
                'year' => $validated['year'],
                'updated_by' => auth()->id(),
            ]);

            $manualMonthlyGoogleReview->items()->delete();
            $this->syncItems($manualMonthlyGoogleReview, $validated['items']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('manual-monthly-google-review.index')
            ->with('success', 'Manual Monthly Google Review berhasil diperbarui.');
    }

    public function destroy(ManualMonthlyGoogleReview $manualMonthlyGoogleReview)
    {
        $manualMonthlyGoogleReview->delete();

        return redirect()
            ->route('manual-monthly-google-review.index')
            ->with('success', 'Data berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'items' => 'required|array|min:1',
            'items.*.outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'items.*.rating' => 'required|numeric|min:0|max:5',
        ], [
            'items.required' => 'Minimal satu outlet harus diisi.',
            'items.min' => 'Minimal satu outlet harus diisi.',
            'items.*.rating.required' => 'Rating Google Review wajib diisi.',
            'items.*.rating.min' => 'Rating minimal 0.',
            'items.*.rating.max' => 'Rating maksimal 5.',
        ]);

        $outletIds = collect($validated['items'])->pluck('outlet_id')->map(fn ($id) => (int) $id);
        if ($outletIds->unique()->count() !== $outletIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'Outlet tidak boleh duplikat dalam satu periode.',
            ]);
        }

        return $validated;
    }

    private function syncItems(ManualMonthlyGoogleReview $record, array $items): void
    {
        foreach ($items as $item) {
            ManualMonthlyGoogleReviewItem::create([
                'manual_monthly_google_review_id' => $record->id,
                'outlet_id' => (int) $item['outlet_id'],
                'rating' => $item['rating'] ?? 0,
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
