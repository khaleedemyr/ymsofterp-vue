<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\InspectionDetail;
use App\Models\QaGuidance;
use App\Models\QaCategory;
use App\Models\QaParameter;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InspectionController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $outletId = $request->input('outlet_id');
        $departemen = $request->input('departemen');
        $status = $request->input('status');
        $perPage = $request->input('per_page', 15);

        $query = Inspection::with([
            'outlet', 
            'guidance.guidanceCategories.parameters.details', 
            'auditees', 
            'createdByUser', 
            'details.category', 
            'details.parameter', 
            'details.createdByUser'
        ]);

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->whereHas('createdByUser', function($userQuery) use ($search) {
                      $userQuery->where('nama_lengkap', 'like', "%$search%");
                  })
                  ->orWhereHas('outlet', function($outletQuery) use ($search) {
                      $outletQuery->where('nama_outlet', 'like', "%$search%");
                  })
                  ->orWhereHas('guidance', function($guidanceQuery) use ($search) {
                      $guidanceQuery->where('title', 'like', "%$search%");
                  });
            });
        }

        // Filter by outlet
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        // Filter by departemen
        if ($departemen) {
            $query->where('departemen', $departemen);
        }

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        $inspections = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Get filter options
        $outlets = Outlet::select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $departemenOptions = ['Kitchen', 'Bar', 'Service'];
        $statusOptions = ['Draft', 'Completed'];

        // Get statistics
        $total = Inspection::count();
        $draft = Inspection::where('status', 'Draft')->count();
        $completed = Inspection::where('status', 'Completed')->count();

        $statistics = [
            'total' => $total,
            'draft' => $draft,
            'completed' => $completed,
        ];


        return Inertia::render('Inspections/Index', [
            'inspections' => $inspections,
            'filters' => [
                'search' => $search,
                'outlet_id' => $outletId,
                'departemen' => $departemen,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'outlets' => $outlets,
            'departemenOptions' => $departemenOptions,
            'statusOptions' => $statusOptions,
            'statistics' => $statistics,
        ]);
    }

    public function create(Request $request)
    {
        // Get session data
        $selectedOutlet = session('inspection_outlet_id');
        $selectedDepartemen = session('inspection_departemen');

        // Get outlets
        $outlets = Outlet::select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $departemenOptions = ['Kitchen', 'Bar', 'Service'];

        // Get users for auditee selection
        $users = \App\Models\User::select('id', 'nama_lengkap', 'id_outlet')
            ->whereNotNull('id_outlet')
            ->orderBy('nama_lengkap')
            ->get();

        // Get guidances for selection
        $guidances = QaGuidance::where('status', 'A')
            ->orderBy('title')
            ->get();

        // Get existing inspections for the selected outlet and department
        $existingInspections = collect();
        $outletId = $request->input('outlet_id', $selectedOutlet);
        $departemen = $request->input('departemen', $selectedDepartemen);
        
        if ($outletId && $departemen) {
            $existingInspections = Inspection::with(['outlet', 'guidance', 'createdByUser', 'details'])
                ->where('outlet_id', $outletId)
                ->where('departemen', $departemen)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return Inertia::render('Inspections/Create', [
            'outlets' => $outlets,
            'departemenOptions' => $departemenOptions,
            'selectedOutlet' => $selectedOutlet,
            'selectedDepartemen' => $selectedDepartemen,
            'existingInspections' => $existingInspections,
            'users' => $users,
            'guidances' => $guidances,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'departemen' => 'required|in:Kitchen,Bar,Service',
            'guidance_id' => 'required|exists:qa_guidances,id',
            'inspection_mode' => 'required|in:product,cleanliness',
            'inspection_date' => 'required|date',
            'auditees' => 'nullable|array',
            'auditees.*' => 'exists:users,id',
        ]);

        // Get guidance based on selected guidance_id
        $guidance = QaGuidance::where('status', 'A')
            ->where('id', $validated['guidance_id'])
            ->first();

        if (!$guidance) {
            return back()->withErrors(['guidance_id' => 'Selected guidance not found or inactive.']);
        }

        // Store in session for future use
        session([
            'inspection_outlet_id' => $validated['outlet_id'],
            'inspection_departemen' => $validated['departemen'],
        ]);

        // Add created_by
        $validated['created_by'] = auth()->id();

        $inspection = Inspection::create($validated);

        // Store auditees if provided
        if (!empty($validated['auditees'])) {
            $inspection->auditees()->attach($validated['auditees']);
        }

        return redirect()->route('inspections.add-finding', $inspection->id)
            ->with('success', 'Inspection session created successfully!');
    }

    public function show(Inspection $inspection)
    {
        $inspection->load([
            'outlet', 
            'guidance', 
            'createdByUser', 
            'auditees',
            'details.category',
            'details.parameter',
            'details.createdByUser', // Load finding creator
            'cpas.inspectionDetail.category', // Load CPA with finding category
            'cpas.inspectionDetail.parameter', // Load CPA with finding parameter
            'cpas.inspectionDetail.createdByUser', // Load CPA with finding creator
            'cpas.createdBy' // Load CPA creator
        ]);
        
        return Inertia::render('Inspections/Show', [
            'inspection' => $inspection,
        ]);
    }

    public function addFinding(Inspection $inspection)
    {
        // Load inspection with relationships
        $inspection->load(['outlet', 'guidance', 'createdByUser']);
        
        // Load guidance with all relationships
        $guidance = $inspection->guidance()->with([
            'guidanceCategories.category',
            'guidanceCategories.parameters.details.parameter'
        ])->first();

        // Debug: Log guidance data
        \Log::info('Debug AddFinding - Guidance data', [
            'guidance_id' => $guidance->id,
            'guidance_title' => $guidance->title,
            'guidance_categories_count' => $guidance->guidanceCategories->count(),
            'guidance_categories_data' => $guidance->guidanceCategories->toArray()
        ]);

        // Get categories and parameters
        $categories = $guidance->guidanceCategories->pluck('category')->unique('id');
        $parameters = QaParameter::where('status', 'A')->get();

        // Get all inspections in the same outlet and same date (including current one)
        $existingInspections = Inspection::with(['outlet', 'guidance', 'createdByUser', 'details.category', 'details.parameter', 'details.createdByUser'])
            ->where('outlet_id', $inspection->outlet_id)
            ->where('inspection_date', $inspection->inspection_date)
            ->orderBy('created_at', 'desc')
            ->get();


        return Inertia::render('Inspections/AddFinding', [
            'inspection' => $inspection,
            'guidance' => $guidance,
            'categories' => $categories,
            'parameters' => $parameters,
            'existingInspections' => $existingInspections,
        ]);
    }

    public function storeFinding(Request $request, Inspection $inspection)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:qa_categories,id',
            'parameter_pemeriksaan' => 'required|string|max:255',
            'parameter_id' => 'required|exists:qa_parameters,id',
            'point' => 'required|integer',
            'cleanliness_rating' => 'nullable|in:Yes,No,NA',
            'photos.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'notes' => 'nullable|string',
        ]);

        // Handle multiple photo uploads
        $photoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                if ($photo) {
                    $photoPath = $photo->store('inspections', 'public');
                    $photoPaths[] = $photoPath;
                }
            }
        }
        $validated['photo_paths'] = $photoPaths;

        $validated['inspection_id'] = $inspection->id;
        $validated['created_by'] = auth()->id();

        InspectionDetail::create($validated);

        // Update inspection totals
        $inspection->updateTotals();

        return back()->with('success', 'Finding added successfully!');
    }


    public function complete(Inspection $inspection)
    {
        $inspection->update([
            'status' => 'Completed',
            'completed_at' => now()
        ]);
        
        return redirect()->route('inspections.index')
            ->with('success', 'Inspection completed successfully!');
    }

    public function deleteFinding(Inspection $inspection, $findingId)
    {
        try {
            $finding = $inspection->details()->findOrFail($findingId);
            $finding->delete();
            
            // Update inspection totals
            $inspection->updateTotals();
            
            return back()->with('success', 'Finding deleted successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete finding: ' . $e->getMessage());
        }
    }

    public function destroy(Inspection $inspection)
    {
        // Delete associated photos
        foreach ($inspection->details as $detail) {
            if ($detail->photo_path) {
                Storage::disk('public')->delete($detail->photo_path);
            }
        }

        $inspection->delete();

        return redirect()->route('inspections.index')
            ->with('success', 'Inspection deleted successfully!');
    }

    public function cpa(Inspection $inspection)
    {
        // Load inspection with all necessary relationships (same as show method)
        $inspection->load([
            'outlet', 
            'guidance', 
            'createdByUser', 
            'auditees',
            'details.category',
            'details.parameter',
            'details.createdByUser'
        ]);

        // Get all findings for CPA (tidak filter berdasarkan status)
        $findings = $inspection->details;

        // Get users from same outlet with status 'A'
        $users = \App\Models\User::where('id_outlet', $inspection->outlet_id)
            ->where('status', 'A')
            ->with('jabatan')
            ->get();

        return Inertia::render('Inspections/CPA', [
            'inspection' => $inspection,
            'findings' => $findings,
            'users' => $users,
        ]);
    }

    public function storeCPA(Request $request, Inspection $inspection)
    {
        $validated = $request->validate([
            'inspection_detail_id' => 'required|exists:inspection_details,id',
            'action_plan' => 'required|string|max:2000',
            'responsible_person' => 'required|string|max:255',
            'due_date' => 'required|date|after:today',
            'notes' => 'nullable|string|max:1000',
            'documentation.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max per file
        ]);

        try {
            // Handle file uploads (same as inspection)
            $documentationPaths = [];
            if ($request->hasFile('documentation')) {
                foreach ($request->file('documentation') as $file) {
                    if ($file) {
                        $path = $file->store('cpa-documentation', 'public');
                        $documentationPaths[] = $path;
                    }
                }
            }

            $validated['inspection_id'] = $inspection->id;
            $validated['documentation_paths'] = json_encode($documentationPaths);
            $validated['status'] = 'Open';
            $validated['created_by'] = auth()->id();

            \App\Models\InspectionCPA::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'CPA saved successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save CPA: ' . $e->getMessage()
            ], 500);
        }
    }
}
