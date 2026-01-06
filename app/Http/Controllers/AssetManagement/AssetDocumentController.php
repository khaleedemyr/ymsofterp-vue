<?php

namespace App\Http\Controllers\AssetManagement;

use App\Http\Controllers\Controller;
use App\Models\AssetDocument;
use App\Models\Asset;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AssetDocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $assetId = $request->get('asset_id', '');
        $documentType = $request->get('document_type', '');
        $perPage = $request->get('per_page', 15);

        $query = AssetDocument::with(['asset.category', 'uploader']);

        // Asset filter
        if ($assetId !== '') {
            $query->where('asset_id', $assetId);
        }

        // Document type filter
        if ($documentType !== '') {
            $query->where('document_type', $documentType);
        }

        $documents = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        // Get filter options
        $assets = Asset::orderBy('asset_code')
            ->get(['id', 'asset_code', 'name']);

        return Inertia::render('AssetManagement/Documents/Index', [
            'documents' => $documents,
            'assets' => $assets,
            'filters' => [
                'asset_id' => $assetId,
                'document_type' => $documentType,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $assets = Asset::orderBy('asset_code')
            ->get(['id', 'asset_code', 'name']);

        return Inertia::render('AssetManagement/Documents/Create', [
            'assets' => $assets,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'document_type' => 'required|in:Invoice,Warranty,Manual,Maintenance Record,Other',
            'document_name' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:10240',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $file = $request->file('file');
            $filePath = $file->store('assets/documents', 'public');
            $fileSize = $file->getSize();

            $document = AssetDocument::create([
                'asset_id' => $request->asset_id,
                'document_type' => $request->document_type,
                'document_name' => $request->document_name,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'description' => $request->description,
                'uploaded_by' => auth()->id(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Document uploaded successfully',
                    'data' => $document->load(['asset.category', 'uploader']),
                ], 201);
            }

            return redirect()->route('asset-management.documents.index', ['asset_id' => $request->asset_id])
                ->with('success', 'Document uploaded successfully');
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload document: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()
                ->with('error', 'Failed to upload document: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $document = AssetDocument::with([
            'asset.category',
            'asset.currentOutlet',
            'uploader',
        ])->findOrFail($id);
        
        return Inertia::render('AssetManagement/Documents/Show', [
            'document' => $document,
        ]);
    }

    /**
     * Download document
     */
    public function download($id)
    {
        $document = AssetDocument::findOrFail($id);
        
        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->download($document->file_path, $document->document_name);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $document = AssetDocument::findOrFail($id);
        
        // Delete file from storage
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
            ], 200);
        }

        return redirect()->route('asset-management.documents.index')
            ->with('success', 'Document deleted successfully');
    }
}

