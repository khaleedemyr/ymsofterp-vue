<?php

namespace App\Http\Controllers;

use App\Models\KpiKeyStrategy;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KpiKeyStrategyController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'A');

        $query = KpiKeyStrategy::query();

        if ($status === 'A' || $status === 'N') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $strategies = $query->orderBy('sort_order')->orderBy('code')->paginate(15)->withQueryString();

        return Inertia::render('KpiKeyStrategies/Index', [
            'strategies' => $strategies,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateStrategy($request);
        KpiKeyStrategy::create($validated);

        return redirect()->route('kpi-key-strategies.index')->with('success', 'Key Strategy berhasil ditambahkan.');
    }

    public function update(Request $request, KpiKeyStrategy $kpiKeyStrategy)
    {
        $validated = $this->validateStrategy($request, $kpiKeyStrategy->id);
        $kpiKeyStrategy->update($validated);

        return redirect()->route('kpi-key-strategies.index')->with('success', 'Key Strategy berhasil diperbarui.');
    }

    public function destroy(KpiKeyStrategy $kpiKeyStrategy)
    {
        $kpiKeyStrategy->update(['status' => 'N']);

        return redirect()->back()->with('success', 'Key Strategy berhasil dinonaktifkan.');
    }

    public function toggleStatus(KpiKeyStrategy $kpiKeyStrategy)
    {
        $newStatus = $kpiKeyStrategy->status === 'A' ? 'N' : 'A';
        $kpiKeyStrategy->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Status Key Strategy berhasil diubah.',
            'new_status' => $newStatus,
        ]);
    }

    protected function validateStrategy(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:kpi_key_strategies,code';
        if ($ignoreId) {
            $uniqueRule .= ',' . $ignoreId;
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:50', $uniqueRule],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:A,N',
        ]);
    }
}
