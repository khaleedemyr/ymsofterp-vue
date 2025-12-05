<?php

namespace App\Http\Controllers;

use App\Models\QaParameter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QaParameterController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'A'); // Default to active parameters
        $perPage = $request->input('per_page', 15); // Default to 15 per page

        $query = QaParameter::query();
        
        // Filter by status
        if ($status === 'A') {
            $query->where('status', 'A'); // Active parameters only
        } elseif ($status === 'N') {
            $query->where('status', 'N'); // Non-active parameters only
        }
        // If status is 'all', don't filter by status (show all)
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('kode_parameter', 'like', "%$search%")
                  ->orWhere('parameter', 'like', "%$search%");
            });
        }
        
        $parameters = $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();

        // Get statistics
        $total = QaParameter::count();
        $active = QaParameter::where('status', 'A')->count();
        $inactive = QaParameter::where('status', 'N')->count();
        
        $statistics = [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];

        return Inertia::render('QaParameters/Index', [
            'parameters' => $parameters,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'statistics' => $statistics,
        ]);
    }

    private function generateKodeParameter()
    {
        // Get the last kode_parameter
        $lastParameter = QaParameter::orderBy('id', 'desc')->first();
        
        if ($lastParameter) {
            // Extract number from last kode_parameter (e.g., QP001 -> 1)
            $lastNumber = intval(preg_replace('/[^0-9]/', '', $lastParameter->kode_parameter));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return 'QP' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    public function create()
    {
        return Inertia::render('QaParameters/Create', [
            'parameter' => [
                'kode_parameter' => $this->generateKodeParameter(),
                'parameter' => '',
                'status' => 'A',
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_parameter' => 'nullable|string|max:50|unique:qa_parameters,kode_parameter',
            'parameter' => 'required|string|max:255',
            'status' => 'required|string|in:A,N',
        ]);

        // Auto generate kode if not provided
        if (empty($validated['kode_parameter'])) {
            $validated['kode_parameter'] = $this->generateKodeParameter();
        }

        try {
            QaParameter::create($validated);
            return redirect()->route('qa-parameters.index')->with('success', 'QA Parameter berhasil ditambahkan dengan kode: ' . $validated['kode_parameter']);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menyimpan data: ' . $e->getMessage()]);
        }
    }

    public function show(QaParameter $qaParameter)
    {
        return Inertia::render('QaParameters/Show', [
            'parameter' => $qaParameter
        ]);
    }

    public function edit(QaParameter $qaParameter)
    {
        return Inertia::render('QaParameters/Edit', [
            'parameter' => $qaParameter
        ]);
    }

    public function update(Request $request, QaParameter $qaParameter)
    {
        $validated = $request->validate([
            'kode_parameter' => 'required|string|max:50|unique:qa_parameters,kode_parameter,' . $qaParameter->id,
            'parameter' => 'required|string|max:255',
            'status' => 'required|string|in:A,N',
        ]);

        try {
            $qaParameter->update($validated);
            return redirect()->route('qa-parameters.show', $qaParameter->id)->with('success', 'QA Parameter berhasil diupdate');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal update data: ' . $e->getMessage()]);
        }
    }

    public function destroy(QaParameter $qaParameter)
    {
        // Set status to 'N' (Non-aktif) instead of deleting
        $qaParameter->update(['status' => 'N']);
        
        return redirect()->back()->with('success', 'QA Parameter berhasil dinonaktifkan!');
    }

    public function toggleStatus(QaParameter $qaParameter)
    {
        $newStatus = $qaParameter->status === 'A' ? 'N' : 'A';
        $qaParameter->update(['status' => $newStatus]);
        
        return response()->json([
            'success' => true,
            'message' => 'Status QA Parameter berhasil diubah!',
            'new_status' => $newStatus
        ]);
    }
}
