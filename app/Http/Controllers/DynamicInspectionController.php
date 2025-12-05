<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\DynamicInspection;
use App\Models\InspectionSubject;
use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DynamicInspectionController extends Controller
{
    public function index()
    {
        $inspections = DynamicInspection::with(['outlet', 'creator'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('DynamicInspections/Index', [
            'inspections' => $inspections
        ]);
    }

    public function create()
    {
        $outlets = Outlet::where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);

        $subjects = InspectionSubject::with('activeItems')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Get user with joined jabatan and divisi data
        $user = auth()->user();
        $userWithRelations = \App\Models\User::with(['jabatan', 'divisi'])
            ->where('id', $user->id)
            ->first();

        return Inertia::render('DynamicInspections/Create', [
            'outlets' => $outlets,
            'subjects' => $subjects,
            'user' => $userWithRelations
        ]);
    }

    public function store(Request $request)
    {
        // Log incoming request
        $detailsInput = $request->input('details', []);
        $detailsArray = is_string($detailsInput) ? json_decode($detailsInput, true) : $detailsInput;
        
        \Log::info('Dynamic Inspection Store - Request Data', [
            'outlet_id' => $request->input('outlet_id'),
            'inspection_date' => $request->input('inspection_date'),
            'selected_subjects' => $request->input('selected_subjects'),
            'general_notes' => $request->input('general_notes'),
            'outlet_leader' => $request->input('outlet_leader'),
            'details_count' => is_array($detailsArray) ? count($detailsArray) : 0,
            'details_type' => gettype($detailsInput),
            'has_files' => $request->hasFile('details')
        ]);

        // Handle details data properly
        $details = $detailsArray;
        if (is_string($detailsInput)) {
            $details = json_decode($detailsInput, true);
        }
        
        // Validate basic fields first
        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'inspection_date' => 'required|date',
            'selected_subjects' => 'required|array|min:1',
            'selected_subjects.*' => 'exists:inspection_subjects,id',
            'general_notes' => 'nullable|string',
            'outlet_leader' => 'nullable|string',
            'details' => 'required'
        ]);
        
        // Validate details array
        if (!is_array($details)) {
            return back()->withErrors(['details' => 'Details must be a valid array.']);
        }
        
        // Validate each detail
        foreach ($details as $index => $detail) {
            if (!isset($detail['subject_id']) || !isset($detail['item_id'])) {
                return back()->withErrors(["details.{$index}" => 'Subject ID and Item ID are required.']);
            }
        }

        \Log::info('Dynamic Inspection Store - Validation Passed', $validated);

        DB::beginTransaction();
        try {
            // Generate inspection number
            $inspectionNumber = 'DI-' . date('Ymd') . '-' . str_pad(
                DynamicInspection::whereDate('created_at', today())->count() + 1, 
                4, '0', STR_PAD_LEFT
            );

            // Get user info with relations
            $user = Auth::user();
            $userWithRelations = \App\Models\User::with(['jabatan', 'divisi'])->find($user->id);
            
            // Create inspection
            $inspection = DynamicInspection::create([
                'inspection_number' => $inspectionNumber,
                'outlet_id' => $validated['outlet_id'],
                'pic_name' => $user->nama_lengkap,
                'pic_position' => $userWithRelations->jabatan->nama_jabatan ?? 'Staff',
                'pic_division' => $userWithRelations->divisi->nama_divisi ?? 'General',
                'inspection_date' => $validated['inspection_date'],
                'status' => 'completed',
                'general_notes' => $validated['general_notes'],
                'outlet_leader' => $validated['outlet_leader'],
                'created_by' => $user->id
            ]);

            \Log::info('Dynamic Inspection Store - Inspection Created', [
                'inspection_id' => $inspection->id,
                'inspection_number' => $inspection->inspection_number
            ]);

            // Handle file uploads and create details
            foreach ($details as $index => $detail) {
                $documentationPaths = [];
                
                // Check for files in the request with the correct structure
                $fileKey = "details.{$index}.documentation";
                if ($request->hasFile($fileKey)) {
                    $files = $request->file($fileKey);
                    if (is_array($files)) {
                        foreach ($files as $file) {
                            if ($file && $file->isValid()) {
                                $path = $file->store('dynamic-inspection-docs', 'public');
                                $documentationPaths[] = $path;
                            }
                        }
                    }
                }
                
                // Also check for files in the detail array (fallback)
                if (isset($detail['documentation']) && is_array($detail['documentation'])) {
                    foreach ($detail['documentation'] as $file) {
                        if ($file && $file->isValid()) {
                            $path = $file->store('dynamic-inspection-docs', 'public');
                            $documentationPaths[] = $path;
                        }
                    }
                }

                $detailRecord = $inspection->details()->create([
                    'inspection_subject_id' => $detail['subject_id'],
                    'inspection_subject_item_id' => $detail['item_id'],
                    'is_checked' => $detail['is_checked'] ?? false,
                    'notes' => $detail['notes'],
                    'documentation_paths' => $documentationPaths
                ]);

                \Log::info('Dynamic Inspection Store - Detail Created', [
                    'detail_id' => $detailRecord->id,
                    'subject_id' => $detail['subject_id'],
                    'item_id' => $detail['item_id'],
                    'is_checked' => $detail['is_checked'],
                    'documentation_count' => count($documentationPaths)
                ]);
            }

            DB::commit();
            
            \Log::info('Dynamic Inspection Store - Success', [
                'inspection_id' => $inspection->id,
                'details_count' => $inspection->details()->count()
            ]);
            
            return redirect()->route('dynamic-inspections.index')
                ->with('success', 'Dynamic Inspection berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Dynamic Inspection Store - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Gagal menyimpan inspection: ' . $e->getMessage());
        }
    }

    public function storeSubject(Request $request)
    {
        // Log incoming request
        $detailsInput = $request->input('details', []);
        $detailsArray = is_string($detailsInput) ? json_decode($detailsInput, true) : $detailsInput;
        
        \Log::info('Dynamic Inspection Store Subject - Request Data', [
            'outlet_id' => $request->input('outlet_id'),
            'inspection_date' => $request->input('inspection_date'),
            'subject_id' => $request->input('subject_id'),
            'general_notes' => $request->input('general_notes'),
            'outlet_leader' => $request->input('outlet_leader'),
            'details_count' => is_array($detailsArray) ? count($detailsArray) : 0,
            'details_type' => gettype($detailsInput),
            'has_files' => $request->hasFile('details')
        ]);

        // Handle details data properly
        $details = $detailsArray;
        if (is_string($detailsInput)) {
            $details = json_decode($detailsInput, true);
        }
        
        // Validate basic fields first
        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'inspection_date' => 'required|date',
            'subject_id' => 'required|exists:inspection_subjects,id',
            'general_notes' => 'nullable|string',
            'outlet_leader' => 'nullable|string',
            'details' => 'required'
        ]);
        
        // Validate details array
        if (!is_array($details)) {
            return response()->json(['error' => 'Details must be a valid array.'], 400);
        }
        
        // Validate each detail
        foreach ($details as $index => $detail) {
            if (!isset($detail['subject_id']) || !isset($detail['item_id'])) {
                return response()->json(['error' => "Subject ID and Item ID are required for detail {$index}."], 400);
            }
        }

        \Log::info('Dynamic Inspection Store Subject - Validation Passed', $validated);

        DB::beginTransaction();
        try {
            // Generate inspection number
            $inspectionNumber = 'DI-' . date('Ymd') . '-' . str_pad(
                DynamicInspection::whereDate('created_at', today())->count() + 1, 
                4, '0', STR_PAD_LEFT
            );

            // Get user info with relations
            $user = Auth::user();
            $userWithRelations = \App\Models\User::with(['jabatan', 'divisi'])->find($user->id);
            
            // Create inspection
            $inspection = DynamicInspection::create([
                'inspection_number' => $inspectionNumber,
                'outlet_id' => $validated['outlet_id'],
                'pic_name' => $user->nama_lengkap,
                'pic_position' => $userWithRelations->jabatan->nama_jabatan ?? 'Staff',
                'pic_division' => $userWithRelations->divisi->nama_divisi ?? 'General',
                'inspection_date' => $validated['inspection_date'],
                'status' => 'draft',
                'general_notes' => $validated['general_notes'],
                'outlet_leader' => $validated['outlet_leader'],
                'created_by' => $user->id
            ]);

            \Log::info('Dynamic Inspection Store Subject - Inspection Created', [
                'inspection_id' => $inspection->id,
                'inspection_number' => $inspection->inspection_number
            ]);

            // Handle file uploads and create details
            foreach ($details as $index => $detail) {
                $documentationPaths = [];
                
                // Check for files in the request with the correct structure
                $fileKey = "details.{$index}.documentation";
                if ($request->hasFile($fileKey)) {
                    $files = $request->file($fileKey);
                    if (is_array($files)) {
                        foreach ($files as $file) {
                            if ($file && $file->isValid()) {
                                $path = $file->store('dynamic-inspection-docs', 'public');
                                $documentationPaths[] = $path;
                            }
                        }
                    }
                }
                
                // Also check for files in the detail array (fallback)
                if (isset($detail['documentation']) && is_array($detail['documentation'])) {
                    foreach ($detail['documentation'] as $file) {
                        if ($file && $file->isValid()) {
                            $path = $file->store('dynamic-inspection-docs', 'public');
                            $documentationPaths[] = $path;
                        }
                    }
                }

                $detailRecord = $inspection->details()->create([
                    'inspection_subject_id' => $detail['subject_id'],
                    'inspection_subject_item_id' => $detail['item_id'],
                    'is_checked' => $detail['is_checked'] ?? false,
                    'notes' => $detail['notes'],
                    'documentation_paths' => $documentationPaths
                ]);

                \Log::info('Dynamic Inspection Store Subject - Detail Created', [
                    'detail_id' => $detailRecord->id,
                    'subject_id' => $detail['subject_id'],
                    'item_id' => $detail['item_id'],
                    'is_checked' => $detail['is_checked'],
                    'documentation_count' => count($documentationPaths)
                ]);
            }

            DB::commit();
            
            \Log::info('Dynamic Inspection Store Subject - Success', [
                'inspection_id' => $inspection->id,
                'details_count' => $inspection->details()->count()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Subject berhasil disimpan!',
                'inspection_id' => $inspection->id,
                'inspection_number' => $inspection->inspection_number
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Dynamic Inspection Store Subject - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Gagal menyimpan subject: ' . $e->getMessage()], 500);
        }
    }

    public function show(DynamicInspection $dynamicInspection)
    {
        $dynamicInspection->load([
            'outlet',
            'creator',
            'details.subject',
            'details.subjectItem'
        ]);

        // Debug data
        \Log::info('Dynamic Inspection Show Data', [
            'inspection_id' => $dynamicInspection->id,
            'details_count' => $dynamicInspection->details->count(),
            'details_data' => $dynamicInspection->details->map(function($detail) {
                return [
                    'id' => $detail->id,
                    'subject_id' => $detail->inspection_subject_id,
                    'item_id' => $detail->inspection_subject_item_id,
                    'subject_name' => $detail->subject?->name,
                    'item_name' => $detail->subjectItem?->name,
                    'documentation_paths' => $detail->documentation_paths,
                    'documentation_type' => gettype($detail->documentation_paths)
                ];
            })
        ]);

        return Inertia::render('DynamicInspections/Show', [
            'inspection' => $dynamicInspection
        ]);
    }

    public function edit(DynamicInspection $dynamicInspection)
    {
        $outlets = Outlet::where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);

        $subjects = InspectionSubject::with(['items' => function($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Debug subjects data
        \Log::info('Edit - Subjects Data', [
            'subjects_count' => $subjects->count(),
            'subjects_data' => $subjects->map(function($subject) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'items_count' => $subject->items ? $subject->items->count() : 0,
                    'items_data' => $subject->items ? $subject->items->toArray() : null
                ];
            })
        ]);

        $dynamicInspection->load([
            'details.subject',
            'details.subjectItem'
        ]);

        // Debug inspection data
        \Log::info('Edit - Inspection Data', [
            'inspection_id' => $dynamicInspection->id,
            'inspection_date' => $dynamicInspection->inspection_date,
            'inspection_date_formatted' => $dynamicInspection->inspection_date ? $dynamicInspection->inspection_date->format('Y-m-d') : null,
            'outlet_id' => $dynamicInspection->outlet_id,
            'pic_name' => $dynamicInspection->pic_name,
            'details_count' => $dynamicInspection->details->count(),
            'details_data' => $dynamicInspection->details->map(function($detail) {
                return [
                    'id' => $detail->id,
                    'subject_id' => $detail->inspection_subject_id,
                    'item_id' => $detail->inspection_subject_item_id,
                    'is_checked' => $detail->is_checked,
                    'notes' => $detail->notes,
                    'documentation_paths' => $detail->documentation_paths
                ];
            })
        ]);

        return Inertia::render('DynamicInspections/Edit', [
            'inspection' => $dynamicInspection,
            'outlets' => $outlets,
            'subjects' => $subjects
        ]);
    }

    public function update(Request $request, DynamicInspection $dynamicInspection)
    {
        // Debug request data
        \Log::info('Update Request Data', [
            'outlet_id' => $request->input('outlet_id'),
            'inspection_date' => $request->input('inspection_date'),
            'selected_subjects' => $request->input('selected_subjects'),
            'details_count' => count($request->input('details', [])),
            'details' => $request->input('details'),
            'all_request_data' => $request->all()
        ]);

        // Handle details data properly - check if it's JSON string
        $detailsInput = $request->input('details', []);
        $detailsArray = [];
        
        if (is_string($detailsInput)) {
            $detailsArray = json_decode($detailsInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::error('JSON decode error for details:', [
                    'error' => json_last_error_msg(),
                    'input' => $detailsInput
                ]);
                return back()->withErrors(['details' => 'Invalid details format.']);
            }
        } elseif (is_array($detailsInput)) {
            $detailsArray = $detailsInput;
        }
        
        \Log::info('Details Processing', [
            'details_input_type' => gettype($detailsInput),
            'details_array_type' => gettype($detailsArray),
            'details_array_count' => is_array($detailsArray) ? count($detailsArray) : 0,
            'details_array' => $detailsArray
        ]);

        // Handle selected_subjects - check if it's JSON string
        $selectedSubjectsInput = $request->input('selected_subjects', []);
        $selectedSubjectsArray = [];
        
        if (is_string($selectedSubjectsInput)) {
            $selectedSubjectsArray = json_decode($selectedSubjectsInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::error('JSON decode error for selected_subjects:', [
                    'error' => json_last_error_msg(),
                    'input' => $selectedSubjectsInput
                ]);
                return back()->withErrors(['selected_subjects' => 'Invalid selected subjects format.']);
            }
        } elseif (is_array($selectedSubjectsInput)) {
            $selectedSubjectsArray = $selectedSubjectsInput;
        }
        
        \Log::info('Selected Subjects Processing', [
            'selected_subjects_input_type' => gettype($selectedSubjectsInput),
            'selected_subjects_array_type' => gettype($selectedSubjectsArray),
            'selected_subjects_array_count' => is_array($selectedSubjectsArray) ? count($selectedSubjectsArray) : 0,
            'selected_subjects_array' => $selectedSubjectsArray
        ]);

        // Validate basic fields - always validate required fields
        $validationRules = [
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'inspection_date' => 'required|date',
            'general_notes' => 'nullable|string',
            'outlet_leader' => 'nullable|string'
        ];
        
        $validated = $request->validate($validationRules);
        
        // Prepare update data
        $updateData = [
            'outlet_id' => $validated['outlet_id'],
            'inspection_date' => $validated['inspection_date'],
            'general_notes' => $validated['general_notes'],
            'outlet_leader' => $validated['outlet_leader']
        ];

        // Validate details array
        if (!is_array($detailsArray) || empty($detailsArray)) {
            \Log::error('Details validation failed:', [
                'details_array' => $detailsArray,
                'is_array' => is_array($detailsArray),
                'empty' => empty($detailsArray)
            ]);
            return back()->withErrors(['details' => 'Details must be a valid non-empty array.']);
        }
        
        // Validate each detail
        foreach ($detailsArray as $index => $detail) {
            if (!isset($detail['subject_id']) || !isset($detail['item_id'])) {
                \Log::error('Detail validation failed:', [
                    'index' => $index,
                    'detail' => $detail,
                    'has_subject_id' => isset($detail['subject_id']),
                    'has_item_id' => isset($detail['item_id'])
                ]);
                return back()->withErrors(["details.{$index}" => 'Subject ID and Item ID are required.']);
            }
        }

        // Validate selected subjects
        if (!is_array($selectedSubjectsArray) || empty($selectedSubjectsArray)) {
            \Log::error('Selected subjects validation failed:', [
                'selected_subjects_array' => $selectedSubjectsArray,
                'is_array' => is_array($selectedSubjectsArray),
                'empty' => empty($selectedSubjectsArray)
            ]);
            return back()->withErrors(['selected_subjects' => 'At least one subject must be selected.']);
        }

        \Log::info('Update - Validation Passed', [
            'validated' => $validated,
            'update_data' => $updateData
        ]);

        DB::beginTransaction();
        try {
            // Update inspection
            $dynamicInspection->update($updateData);
            
            \Log::info('Update - Inspection Updated', [
                'inspection_id' => $dynamicInspection->id,
                'updated_fields' => array_keys($updateData),
                'update_data' => $updateData
            ]);

            // Delete existing details
            $dynamicInspection->details()->delete();

            // Create new details
            foreach ($detailsArray as $index => $detail) {
                $documentationPaths = [];
                
                // Handle file uploads for this detail
                $fileKey = "details.{$index}.documentation";
                if ($request->hasFile($fileKey)) {
                    $files = $request->file($fileKey);
                    if (is_array($files)) {
                        foreach ($files as $file) {
                            if ($file && $file->isValid()) {
                                $path = $file->store('dynamic-inspection-docs', 'public');
                                $documentationPaths[] = $path;
                            }
                        }
                    }
                }
                
                // Also check for files in the detail array (fallback)
                if (isset($detail['documentation']) && is_array($detail['documentation'])) {
                    foreach ($detail['documentation'] as $file) {
                        if ($file && $file->isValid()) {
                            $path = $file->store('dynamic-inspection-docs', 'public');
                            $documentationPaths[] = $path;
                        }
                    }
                }

                $detailRecord = $dynamicInspection->details()->create([
                    'inspection_subject_id' => $detail['subject_id'],
                    'inspection_subject_item_id' => $detail['item_id'],
                    'is_checked' => $detail['is_checked'] ?? false,
                    'notes' => $detail['notes'] ?? '',
                    'documentation_paths' => $documentationPaths
                ]);

                \Log::info('Update - Detail Created', [
                    'detail_id' => $detailRecord->id,
                    'subject_id' => $detail['subject_id'],
                    'item_id' => $detail['item_id'],
                    'is_checked' => $detail['is_checked'],
                    'documentation_count' => count($documentationPaths)
                ]);
            }

            DB::commit();
            
            \Log::info('Update - Success', [
                'inspection_id' => $dynamicInspection->id,
                'details_count' => $dynamicInspection->details()->count()
            ]);
            
            return redirect()->route('dynamic-inspections.index')
                ->with('success', 'Dynamic Inspection berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Update Dynamic Inspection Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return back()->with('error', 'Gagal memperbarui inspection: ' . $e->getMessage());
        }
    }

    public function updateSubject(Request $request, DynamicInspection $dynamicInspection)
    {
        // Log incoming request
        $detailsInput = $request->input('details', []);
        $detailsArray = is_string($detailsInput) ? json_decode($detailsInput, true) : $detailsInput;
        
        \Log::info('Dynamic Inspection Update Subject - Request Data', [
            'inspection_id' => $dynamicInspection->id,
            'outlet_id' => $request->input('outlet_id'),
            'inspection_date' => $request->input('inspection_date'),
            'subject_id' => $request->input('subject_id'),
            'general_notes' => $request->input('general_notes'),
            'outlet_leader' => $request->input('outlet_leader'),
            'details_count' => is_array($detailsArray) ? count($detailsArray) : 0,
            'details_type' => gettype($detailsInput),
            'has_files' => $request->hasFile('details')
        ]);

        // Handle details data properly
        $details = $detailsArray;
        if (is_string($detailsInput)) {
            $details = json_decode($detailsInput, true);
        }
        
        // Validate basic fields first
        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'inspection_date' => 'required|date',
            'subject_id' => 'required|exists:inspection_subjects,id',
            'general_notes' => 'nullable|string',
            'outlet_leader' => 'nullable|string',
            'details' => 'required'
        ]);
        
        // Validate details array
        if (!is_array($details)) {
            return response()->json(['error' => 'Details must be a valid array.'], 400);
        }
        
        // Validate each detail
        foreach ($details as $index => $detail) {
            if (!isset($detail['subject_id']) || !isset($detail['item_id'])) {
                return response()->json(['error' => "Subject ID and Item ID are required for detail {$index}."], 400);
            }
        }

        \Log::info('Dynamic Inspection Update Subject - Validation Passed', $validated);

        DB::beginTransaction();
        try {
            // Update inspection basic info
            $dynamicInspection->update([
                'outlet_id' => $validated['outlet_id'],
                'inspection_date' => $validated['inspection_date'],
                'general_notes' => $validated['general_notes'],
                'outlet_leader' => $validated['outlet_leader']
            ]);

            \Log::info('Dynamic Inspection Update Subject - Inspection Updated', [
                'inspection_id' => $dynamicInspection->id
            ]);

            // Delete existing details for this subject
            $dynamicInspection->details()->where('inspection_subject_id', $validated['subject_id'])->delete();

            // Create new details for this subject
            foreach ($details as $index => $detail) {
                $documentationPaths = [];
                
                // Check for files in the request with the correct structure
                $fileKey = "details.{$index}.documentation";
                if ($request->hasFile($fileKey)) {
                    $files = $request->file($fileKey);
                    if (is_array($files)) {
                        foreach ($files as $file) {
                            if ($file && $file->isValid()) {
                                $path = $file->store('dynamic-inspection-docs', 'public');
                                $documentationPaths[] = $path;
                            }
                        }
                    }
                }
                
                // Also check for files in the detail array (fallback)
                if (isset($detail['documentation']) && is_array($detail['documentation'])) {
                    foreach ($detail['documentation'] as $file) {
                        if ($file && $file->isValid()) {
                            $path = $file->store('dynamic-inspection-docs', 'public');
                            $documentationPaths[] = $path;
                        }
                    }
                }

                $detailRecord = $dynamicInspection->details()->create([
                    'inspection_subject_id' => $detail['subject_id'],
                    'inspection_subject_item_id' => $detail['item_id'],
                    'is_checked' => $detail['is_checked'] ?? false,
                    'notes' => $detail['notes'],
                    'documentation_paths' => $documentationPaths
                ]);

                \Log::info('Dynamic Inspection Update Subject - Detail Created', [
                    'detail_id' => $detailRecord->id,
                    'subject_id' => $detail['subject_id'],
                    'item_id' => $detail['item_id'],
                    'is_checked' => $detail['is_checked'],
                    'documentation_count' => count($documentationPaths)
                ]);
            }

            DB::commit();
            
            \Log::info('Dynamic Inspection Update Subject - Success', [
                'inspection_id' => $dynamicInspection->id,
                'details_count' => $dynamicInspection->details()->count()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Subject berhasil diperbarui!',
                'inspection_id' => $dynamicInspection->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Dynamic Inspection Update Subject - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Gagal memperbarui subject: ' . $e->getMessage()], 500);
        }
    }

    public function complete(Request $request, DynamicInspection $dynamicInspection)
    {
        // Validate general_notes if provided
        $validated = $request->validate([
            'general_notes' => 'nullable|string',
            'outlet_leader' => 'nullable|string'
        ]);
        
        DB::beginTransaction();
        try {
            $updateData = ['status' => 'completed'];
            
            // Update general_notes and outlet_leader if provided
            if (isset($validated['general_notes'])) {
                $updateData['general_notes'] = $validated['general_notes'];
            }
            if (isset($validated['outlet_leader'])) {
                $updateData['outlet_leader'] = $validated['outlet_leader'];
            }
            
            $dynamicInspection->update($updateData);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Inspection berhasil diselesaikan!',
                'inspection_id' => $dynamicInspection->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Complete Dynamic Inspection Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Gagal menyelesaikan inspection: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(DynamicInspection $dynamicInspection)
    {
        DB::beginTransaction();
        try {
            $dynamicInspection->details()->delete();
            $dynamicInspection->delete();
            
            DB::commit();
            
            return redirect()->route('dynamic-inspections.index')
                ->with('success', 'Dynamic Inspection berhasil dihapus!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus inspection: ' . $e->getMessage());
        }
    }
}
