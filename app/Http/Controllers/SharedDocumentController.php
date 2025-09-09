<?php

namespace App\Http\Controllers;

use App\Models\SharedDocument;
use App\Models\DocumentPermission;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User; // Added this import for searchUsers and getDropdownData

class SharedDocumentController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        
        // Show all documents that user has access to
        $documents = SharedDocument::with(['creator', 'permissions.user'])
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                      ->orWhere('is_public', true)
                      ->orWhereHas('permissions', function ($q) use ($user) {
                          $q->where('user_id', $user->id);
                      });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('SharedDocuments/Index', [
            'documents' => $documents,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('SharedDocuments/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|mimes:xlsx,xls,docx,doc,pptx,ppt|max:10240', // 10MB max
            'is_public' => 'boolean',
            'shared_users' => 'array',
            'shared_users.*.user_id' => 'exists:users,id',
            'shared_users.*.permission' => 'in:view,edit,admin',
        ]);

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('shared-documents', $filename, 'public');

        $document = SharedDocument::create([
            'title' => $request->title,
            'filename' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'description' => $request->description,
            'created_by' => Auth::id(),
            'is_public' => $request->is_public ?? false,
        ]);

        // Create initial version
        DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => '1.0',
            'file_path' => $filePath,
            'change_description' => 'Initial version',
            'created_by' => Auth::id(),
        ]);

        // Add permissions for shared users
        if ($request->shared_users) {
            foreach ($request->shared_users as $sharedUser) {
                DocumentPermission::create([
                    'document_id' => $document->id,
                    'user_id' => $sharedUser['user_id'],
                    'permission' => $sharedUser['permission'] ?? 'view',
                ]);
            }
        }

        return redirect()->route('shared-documents.index')
            ->with('success', 'Document berhasil diupload dan dibagikan!');
    }

    public function show($id): Response
    {
        $user = Auth::user();
        
        // Find document manually to debug
        $document = SharedDocument::find($id);
        
        \Log::info('Document lookup', [
            'requested_id' => $id,
            'document_found' => $document ? 'YES' : 'NO',
            'document_data' => $document ? $document->toArray() : null,
        ]);
        
        if (!$document) {
            \Log::error('Document not found', [
                'document_id' => $id,
                'user_id' => $user->id,
                'user_name' => $user->nama_lengkap,
            ]);
            abort(404, 'Dokumen tidak ditemukan.');
        }
        
        // Debug logging
        \Log::info('Document access check', [
            'document_id' => $document->id,
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap,
            'document_title' => $document->title,
            'is_public' => $document->is_public,
            'created_by' => $document->created_by,
            'user_permissions' => $document->permissions()->where('user_id', $user->id)->get()->toArray(),
        ]);
        
        if (!$document->hasPermission($user)) {
            \Log::error('Permission denied', [
                'document_id' => $document->id,
                'user_id' => $user->id,
                'user_name' => $user->nama_lengkap,
            ]);
            abort(403, 'Anda tidak memiliki akses ke dokumen ini.');
        }

        $document->load(['creator', 'permissions.user', 'versions.creator']);

        return Inertia::render('SharedDocuments/Show', [
            'document' => $document,
            'canEdit' => $document->hasPermission($user, 'edit'),
            'canAdmin' => $document->hasPermission($user, 'admin'),
            'onlyOfficeUrl' => config('app.onlyoffice_url', 'http://localhost:80'),
        ]);
    }

    public function edit($id): Response
    {
        $user = Auth::user();
        
        $document = SharedDocument::findOrFail($id);
        
        if (!$document->hasPermission($user, 'edit')) {
            abort(403, 'Anda tidak memiliki izin untuk mengedit dokumen ini.');
        }

        $document->load(['permissions.user']);

        return Inertia::render('SharedDocuments/Edit', [
            'document' => $document,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        
        $document = SharedDocument::findOrFail($id);
        
        if (!$document->hasPermission($user, 'edit')) {
            abort(403, 'Anda tidak memiliki izin untuk mengedit dokumen ini.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $document->update([
            'title' => $request->title,
            'description' => $request->description,
            'is_public' => $request->is_public ?? false,
        ]);

        return redirect()->route('shared-documents.show', $document)
            ->with('success', 'Dokumen berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        
        $document = SharedDocument::findOrFail($id);
        
        if (!$document->hasPermission($user, 'admin')) {
            abort(403, 'Anda tidak memiliki izin untuk menghapus dokumen ini.');
        }

        // Delete file from storage
        Storage::disk('public')->delete($document->file_path);
        
        // Delete all versions
        foreach ($document->versions as $version) {
            Storage::disk('public')->delete($version->file_path);
        }

        $document->delete();

        return redirect()->route('shared-documents.index')
            ->with('success', 'Dokumen berhasil dihapus!');
    }

    public function share(Request $request, $id)
    {
        $user = Auth::user();
        
        $document = SharedDocument::findOrFail($id);
        
        if (!$document->hasPermission($user, 'edit')) {
            abort(403, 'Anda tidak memiliki izin untuk membagikan dokumen ini.');
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission' => 'required|in:view,edit,admin',
        ]);

        DocumentPermission::updateOrCreate(
            [
                'document_id' => $document->id,
                'user_id' => $request->user_id,
            ],
            [
                'permission' => $request->permission,
            ]
        );

        return back()->with('success', 'Dokumen berhasil dibagikan!');
    }

    public function removeShare(Request $request, $id)
    {
        $user = Auth::user();
        $document = SharedDocument::findOrFail($id);
        
        if (!$document->hasPermission($user, 'edit')) {
            abort(403, 'Anda tidak memiliki izin untuk mengelola akses dokumen ini.');
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        DocumentPermission::where([
            'document_id' => $document->id,
            'user_id' => $request->user_id,
        ])->delete();

        return back()->with('success', 'Akses dokumen berhasil dihapus!');
    }

    /**
     * Update document permissions in bulk
     */
    public function updatePermissions(Request $request, $id)
    {
        $user = Auth::user();
        $document = SharedDocument::findOrFail($id);
        
        if (!$document->hasPermission($user, 'edit')) {
            abort(403, 'Anda tidak memiliki izin untuk mengelola akses dokumen ini.');
        }

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*.user_id' => 'required|exists:users,id',
            'permissions.*.permission' => 'required|in:view,edit,admin',
            'is_public' => 'boolean',
        ]);

        // Update document public status
        $document->update([
            'is_public' => $request->is_public ?? false,
        ]);

        // Delete existing permissions
        DocumentPermission::where('document_id', $document->id)->delete();

        // Add new permissions
        foreach ($request->permissions as $permission) {
            DocumentPermission::create([
                'document_id' => $document->id,
                'user_id' => $permission['user_id'],
                'permission' => $permission['permission'],
            ]);
        }

        return back()->with('success', 'Permission dokumen berhasil diperbarui!');
    }

    /**
     * Get document permissions for management
     */
    public function getPermissions($id)
    {
        $user = Auth::user();
        $document = SharedDocument::findOrFail($id);
        
        if (!$document->hasPermission($user, 'view')) {
            abort(403, 'Anda tidak memiliki akses ke dokumen ini.');
        }

        $permissions = DocumentPermission::with('user')
            ->where('document_id', $document->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'is_public' => $document->is_public
        ]);
    }

    public function download($id)
    {
        $user = Auth::user();
        
        $document = SharedDocument::findOrFail($id);
        
        if (!$document->hasPermission($user)) {
            abort(403, 'Anda tidak memiliki akses ke dokumen ini.');
        }

        $filePath = storage_path('app/public/' . $document->file_path);
        
        \Log::info('File download attempt', [
            'document_id' => $document->id,
            'file_path' => $filePath,
            'file_exists' => file_exists($filePath),
            'user_id' => $user->id,
        ]);
        
        if (!file_exists($filePath)) {
            \Log::error('File not found', [
                'document_id' => $document->id,
                'file_path' => $filePath,
                'storage_path' => storage_path('app/public/'),
            ]);
            abort(404, 'File tidak ditemukan.');
        }

        // Set proper headers for OnlyOffice
        return response()->download($filePath, $document->filename, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Content-Type' => $this->getMimeType($document->file_type),
        ]);
    }

    private function getMimeType($fileType)
    {
        $mimeTypes = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pdf' => 'application/pdf',
        ];
        
        return $mimeTypes[$fileType] ?? 'application/octet-stream';
    }

    public function callback(Request $request)
    {
        // Handle OnlyOffice callback for document changes
        $status = $request->input('status');
        $documentKey = $request->input('key');
        
        $document = SharedDocument::where('document_key', $documentKey)->first();
        
        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        if ($status === 2) { // Document is being edited
            // Document is ready for editing
            return response()->json(['error' => 0]);
        } elseif ($status === 3) { // Document is being saved
            // Document has been saved
            return response()->json(['error' => 0]);
        } elseif ($status === 6) { // Document is being closed
            // Document has been closed
            return response()->json(['error' => 0]);
        }

        return response()->json(['error' => 0]);
    }

    /**
     * Search users for sharing documents
     */
    public function searchUsers(Request $request)
    {
        $search = $request->get('search', '');
        
        $users = User::select(
                'users.id', 
                'users.nama_lengkap', 
                'users.email', 
                'tbl_data_divisi.nama_divisi as divisi',
                'tbl_data_jabatan.nama_jabatan as jabatan'
            )
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where('users.status', 'A')
            ->where(function($query) use ($search) {
                $query->where('users.nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%")
                      ->orWhere('tbl_data_divisi.nama_divisi', 'like', "%{$search}%")
                      ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            })
            ->where('users.id', '!=', auth()->id()) // Exclude current user
            ->limit(10)
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Get dropdown data for users (for backward compatibility)
     */
    public function getDropdownData()
    {
        $users = User::select(
                'users.id', 
                'users.nama_lengkap', 
                'users.email', 
                'tbl_data_divisi.nama_divisi as divisi',
                'tbl_data_jabatan.nama_jabatan as jabatan'
            )
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where('users.status', 'A')
            ->where('users.id', '!=', auth()->id())
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
} 