<?php

namespace App\Http\Controllers;

use App\Models\SharedDocument;
use App\Models\DocumentPermission;
use App\Models\DocumentVersion;
use App\Models\DocumentFolder;
use App\Models\DocumentAccessScope;
use App\Models\Jabatan;
use App\Models\Divisi;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User;

class SharedDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $folderId = $request->integer('folder_id');

        $folders = DocumentFolder::with(['parent', 'scopes'])
            ->visibleTo($user)
            ->orderBy('name')
            ->get();

        $selectedFolder = null;
        if (!empty($folderId)) {
            $selectedFolder = $folders->firstWhere('id', $folderId);
            if (!$selectedFolder) {
                abort(403, 'Anda tidak memiliki akses ke folder ini.');
            }
        }

        $documents = SharedDocument::with(['creator', 'folder', 'permissions.user', 'scopes'])
            ->visibleTo($user)
            ->when($folderId, function ($query, $folderId) {
                $query->where('folder_id', $folderId);
            }, function ($query) {
                $query->whereNull('folder_id');
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (SharedDocument $document) use ($user) {
                $document->can_manage_permissions = $document->hasPermission($user, 'admin');
                $document->can_move = $document->hasPermission($user, 'edit');
                return $document;
            });

        return Inertia::render('SharedDocuments/Index', [
            'documents' => $documents,
            'folders' => $folders,
            'selectedFolderId' => $folderId,
            'folderTreeItems' => $this->buildFolderTreeItems($folders),
            'breadcrumbs' => $this->buildFolderBreadcrumbs($folders, $selectedFolder),
            'selectedFolder' => $selectedFolder,
            'selectedFolderCanManage' => $selectedFolder ? $selectedFolder->hasPermission($user, 'admin') : false,
        ]);
    }

    public function create(): Response
    {
        $user = Auth::user();

        $folders = DocumentFolder::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        return Inertia::render('SharedDocuments/Create', [
            'folders' => $folders,
            'scopeOptions' => $this->getScopeOptionsData(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'folder_id' => 'nullable|exists:document_folders,id',
            'file' => 'required|file|mimes:pdf,xlsx,xls,docx,doc,pptx,ppt,csv,txt,zip,rar|max:20480',
            'is_public' => 'boolean',
            'shared_users' => 'array',
            'shared_users.*.user_id' => 'exists:users,id',
            'shared_users.*.permission' => 'in:view,edit,admin',
            'scope_permissions' => 'array',
            'scope_permissions.*.scope_type' => 'required|in:user,jabatan,divisi,outlet',
            'scope_permissions.*.scope_id' => 'required|integer',
            'scope_permissions.*.permission' => 'required|in:view,edit,admin',
        ]);

        $folder = null;
        if ($request->filled('folder_id')) {
            $folder = DocumentFolder::findOrFail($request->folder_id);
            if (!$folder->hasPermission(Auth::user(), 'edit')) {
                abort(403, 'Anda tidak memiliki izin untuk upload pada folder ini.');
            }
        }

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $storageDirectory = $folder
            ? 'shared-documents/' . $this->buildFolderStoragePath($folder)
            : 'shared-documents/root';
        $filePath = $file->storeAs($storageDirectory, $filename, 'public');

        $document = SharedDocument::create([
            'folder_id' => $request->folder_id,
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

        if ($request->has('scope_permissions')) {
            $this->syncDocumentScopes($document, $request->input('scope_permissions', []));
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

        $document->load(['creator', 'folder', 'permissions.user', 'versions.creator', 'scopes']);

        return Inertia::render('SharedDocuments/Show', [
            'document' => $document,
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
            'permissions' => 'nullable|array',
            'permissions.*.user_id' => 'required|exists:users,id',
            'permissions.*.permission' => 'required|in:view,edit,admin',
            'scope_permissions' => 'nullable|array',
            'scope_permissions.*.scope_type' => 'required|in:user,jabatan,divisi,outlet',
            'scope_permissions.*.scope_id' => 'required|integer',
            'scope_permissions.*.permission' => 'required|in:view,edit,admin',
            'is_public' => 'boolean',
        ]);

        // Update document public status
        $document->update([
            'is_public' => $request->is_public ?? false,
        ]);

        // Delete existing permissions
        DocumentPermission::where('document_id', $document->id)->delete();

        // Add new permissions
        foreach ($request->input('permissions', []) as $permission) {
            DocumentPermission::create([
                'document_id' => $document->id,
                'user_id' => $permission['user_id'],
                'permission' => $permission['permission'],
            ]);
        }

        if ($request->has('scope_permissions')) {
            $this->syncDocumentScopes($document, $request->input('scope_permissions', []));
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

        $scopePermissions = $document->scopes()->get();

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'scope_permissions' => $scopePermissions,
            'scope_options' => $this->getScopeOptionsData(),
            'is_public' => $document->is_public
        ]);
    }

    public function createFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'parent_id' => 'nullable|exists:document_folders,id',
            'is_public' => 'boolean',
            'scope_permissions' => 'array',
            'scope_permissions.*.scope_type' => 'required|in:user,jabatan,divisi,outlet',
            'scope_permissions.*.scope_id' => 'required|integer',
            'scope_permissions.*.permission' => 'required|in:view,edit,admin',
        ]);

        $parent = null;
        if ($request->filled('parent_id')) {
            $parent = DocumentFolder::findOrFail($request->parent_id);
            if (!$parent->hasPermission(Auth::user(), 'edit')) {
                abort(403, 'Anda tidak memiliki izin membuat subfolder di folder ini.');
            }
        }

        $folder = DocumentFolder::create([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
            'created_by' => Auth::id(),
            'is_public' => $request->boolean('is_public'),
        ]);

        $this->syncFolderScopes($folder, $request->input('scope_permissions', []));

        return back()->with('success', 'Folder berhasil dibuat.');
    }

    public function renameFolder(Request $request, $id)
    {
        $folder = DocumentFolder::findOrFail($id);

        if (!$folder->hasPermission(Auth::user(), 'edit')) {
            abort(403, 'Anda tidak memiliki izin untuk mengubah nama folder ini.');
        }

        $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $folder->update([
            'name' => $request->name,
        ]);

        return back()->with('success', 'Nama folder berhasil diperbarui.');
    }

    public function moveDocument(Request $request, $id)
    {
        $document = SharedDocument::findOrFail($id);
        $user = Auth::user();

        if (!$document->hasPermission($user, 'edit')) {
            abort(403, 'Anda tidak memiliki izin memindahkan dokumen ini.');
        }

        $request->validate([
            'target_folder_id' => 'nullable|exists:document_folders,id',
        ]);

        $targetFolderId = $request->input('target_folder_id');
        if (!empty($targetFolderId)) {
            $targetFolder = DocumentFolder::findOrFail($targetFolderId);
            if (!$targetFolder->hasPermission($user, 'edit')) {
                abort(403, 'Anda tidak memiliki izin ke folder tujuan.');
            }
        }

        $document->update([
            'folder_id' => $targetFolderId,
        ]);

        return back()->with('success', 'Dokumen berhasil dipindahkan.');
    }

    public function bulkMoveDocuments(Request $request)
    {
        $request->validate([
            'document_ids' => 'required|array|min:1',
            'document_ids.*' => 'required|integer|exists:shared_documents,id',
            'target_folder_id' => 'nullable|exists:document_folders,id',
        ]);

        $user = Auth::user();
        $targetFolderId = $request->input('target_folder_id');

        if (!empty($targetFolderId)) {
            $targetFolder = DocumentFolder::findOrFail($targetFolderId);
            if (!$targetFolder->hasPermission($user, 'edit')) {
                abort(403, 'Anda tidak memiliki izin ke folder tujuan.');
            }
        }

        $documents = SharedDocument::query()
            ->whereIn('id', $request->input('document_ids', []))
            ->get();

        foreach ($documents as $document) {
            if (!$document->hasPermission($user, 'edit')) {
                abort(403, "Anda tidak memiliki izin memindahkan dokumen {$document->title}.");
            }
        }

        SharedDocument::query()
            ->whereIn('id', $documents->pluck('id'))
            ->update([
                'folder_id' => $targetFolderId,
            ]);

        return back()->with('success', 'Dokumen terpilih berhasil dipindahkan.');
    }

    public function moveFolder(Request $request, $id)
    {
        $folder = DocumentFolder::findOrFail($id);
        $user = Auth::user();

        if (!$folder->hasPermission($user, 'edit')) {
            abort(403, 'Anda tidak memiliki izin memindahkan folder ini.');
        }

        $request->validate([
            'target_parent_id' => 'nullable|exists:document_folders,id',
        ]);

        $targetParentId = $request->input('target_parent_id');
        if (!empty($targetParentId)) {
            if ((int) $targetParentId === (int) $folder->id) {
                return back()->with('error', 'Folder tidak bisa dipindah ke dirinya sendiri.');
            }

            if ($this->isDescendantFolder((int) $folder->id, (int) $targetParentId)) {
                return back()->with('error', 'Folder tidak bisa dipindah ke subfolder-nya sendiri.');
            }

            $targetParentFolder = DocumentFolder::findOrFail($targetParentId);
            if (!$targetParentFolder->hasPermission($user, 'edit')) {
                abort(403, 'Anda tidak memiliki izin ke folder tujuan.');
            }
        }

        $folder->update([
            'parent_id' => $targetParentId,
        ]);

        return back()->with('success', 'Folder berhasil dipindahkan.');
    }

    public function deleteFolder(Request $request, $id)
    {
        $folder = DocumentFolder::findOrFail($id);
        $user = Auth::user();

        if (!$folder->hasPermission($user, 'admin')) {
            abort(403, 'Anda tidak memiliki izin menghapus folder ini.');
        }

        $request->validate([
            'mode' => 'required|in:move_to_root,move_to_folder',
            'target_folder_id' => 'nullable|exists:document_folders,id',
        ]);

        $mode = $request->input('mode');
        $targetFolderId = $mode === 'move_to_folder' ? $request->input('target_folder_id') : null;

        if ($mode === 'move_to_folder') {
            if (empty($targetFolderId)) {
                return back()->with('error', 'Folder tujuan wajib dipilih.');
            }

            if ((int) $targetFolderId === (int) $folder->id) {
                return back()->with('error', 'Folder tujuan tidak boleh folder yang sama.');
            }

            if ($this->isDescendantFolder((int) $folder->id, (int) $targetFolderId)) {
                return back()->with('error', 'Folder tujuan tidak boleh subfolder dari folder yang dihapus.');
            }

            $targetFolder = DocumentFolder::findOrFail($targetFolderId);
            if (!$targetFolder->hasPermission($user, 'edit')) {
                abort(403, 'Anda tidak memiliki izin ke folder tujuan.');
            }
        }

        DB::transaction(function () use ($folder, $targetFolderId, $mode) {
            $newParentId = $mode === 'move_to_folder' ? $targetFolderId : null;
            $newDocumentFolderId = $mode === 'move_to_folder' ? $targetFolderId : null;

            DocumentFolder::query()
                ->where('parent_id', $folder->id)
                ->update([
                    'parent_id' => $newParentId,
                ]);

            SharedDocument::query()
                ->where('folder_id', $folder->id)
                ->update([
                    'folder_id' => $newDocumentFolderId,
                ]);

            DocumentAccessScope::query()
                ->where('resource_type', DocumentAccessScope::RESOURCE_FOLDER)
                ->where('resource_id', $folder->id)
                ->delete();

            $folder->delete();
        });

        return back()->with('success', 'Folder berhasil dihapus.');
    }

    public function getFolderPermissions($id)
    {
        $user = Auth::user();
        $folder = DocumentFolder::with('scopes')->findOrFail($id);

        if (!$folder->hasPermission($user, 'view')) {
            abort(403, 'Anda tidak memiliki akses ke folder ini.');
        }

        return response()->json([
            'success' => true,
            'data' => [],
            'scope_permissions' => $folder->scopes,
            'scope_options' => $this->getScopeOptionsData(),
            'is_public' => $folder->is_public,
        ]);
    }

    public function updateFolderPermissions(Request $request, $id)
    {
        $folder = DocumentFolder::findOrFail($id);
        $user = Auth::user();

        if (!$folder->hasPermission($user, 'admin')) {
            abort(403, 'Anda tidak memiliki izin mengelola akses folder ini.');
        }

        $request->validate([
            'scope_permissions' => 'nullable|array',
            'scope_permissions.*.scope_type' => 'required|in:user,jabatan,divisi,outlet',
            'scope_permissions.*.scope_id' => 'required|integer',
            'scope_permissions.*.permission' => 'required|in:view,edit,admin',
            'is_public' => 'boolean',
        ]);

        $folder->update([
            'is_public' => $request->boolean('is_public'),
        ]);

        $this->syncFolderScopes($folder, $request->input('scope_permissions', []));

        return back()->with('success', 'Permission folder berhasil diperbarui.');
    }

    public function getScopeOptions()
    {
        return response()->json([
            'success' => true,
            'data' => $this->getScopeOptionsData(),
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

    private function syncDocumentScopes(SharedDocument $document, array $scopePermissions): void
    {
        DocumentAccessScope::query()
            ->where('resource_type', DocumentAccessScope::RESOURCE_DOCUMENT)
            ->where('resource_id', $document->id)
            ->delete();

        foreach ($scopePermissions as $scopePermission) {
            DocumentAccessScope::create([
                'resource_type' => DocumentAccessScope::RESOURCE_DOCUMENT,
                'resource_id' => $document->id,
                'scope_type' => $scopePermission['scope_type'],
                'scope_id' => $scopePermission['scope_id'],
                'permission' => $scopePermission['permission'],
            ]);
        }
    }

    private function syncFolderScopes(DocumentFolder $folder, array $scopePermissions): void
    {
        DocumentAccessScope::query()
            ->where('resource_type', DocumentAccessScope::RESOURCE_FOLDER)
            ->where('resource_id', $folder->id)
            ->delete();

        foreach ($scopePermissions as $scopePermission) {
            DocumentAccessScope::create([
                'resource_type' => DocumentAccessScope::RESOURCE_FOLDER,
                'resource_id' => $folder->id,
                'scope_type' => $scopePermission['scope_type'],
                'scope_id' => $scopePermission['scope_id'],
                'permission' => $scopePermission['permission'],
            ]);
        }
    }

    private function getScopeOptionsData(): array
    {
        return [
            'users' => User::query()
                ->where('status', 'A')
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap']),
            'jabatans' => Jabatan::active()->orderBy('nama_jabatan')->get(['id_jabatan', 'nama_jabatan']),
            'divisis' => Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']),
            'outlets' => Outlet::active()->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
        ];
    }

    private function buildFolderStoragePath(DocumentFolder $folder): string
    {
        $segments = [];
        $cursor = $folder;

        while ($cursor) {
            $segments[] = str()->slug($cursor->name, '-');
            $cursor = $cursor->parent;
        }

        return implode('/', array_reverse($segments));
    }

    private function buildFolderTreeItems($folders): array
    {
        $childrenMap = [];
        foreach ($folders as $folder) {
            $childrenMap[$folder->parent_id ?? 0][] = $folder;
        }

        $items = [];
        $walker = function ($parentId, $depth) use (&$walker, &$items, $childrenMap) {
            foreach ($childrenMap[$parentId] ?? [] as $folder) {
                $items[] = [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'parent_id' => $folder->parent_id,
                    'depth' => $depth,
                ];
                $walker($folder->id, $depth + 1);
            }
        };

        $walker(0, 0);

        return $items;
    }

    private function buildFolderBreadcrumbs($folders, ?DocumentFolder $selectedFolder): array
    {
        $breadcrumbs = [
            ['id' => null, 'name' => 'Root'],
        ];

        if (!$selectedFolder) {
            return $breadcrumbs;
        }

        $foldersById = $folders->keyBy('id');
        $stack = [];
        $cursor = $selectedFolder;
        while ($cursor) {
            $stack[] = ['id' => $cursor->id, 'name' => $cursor->name];
            $cursor = $cursor->parent_id ? $foldersById->get($cursor->parent_id) : null;
        }

        return array_merge($breadcrumbs, array_reverse($stack));
    }

    private function isDescendantFolder(int $folderId, int $candidateParentId): bool
    {
        $cursor = DocumentFolder::find($candidateParentId);
        while ($cursor) {
            if ((int) $cursor->id === $folderId) {
                return true;
            }

            if (!$cursor->parent_id) {
                break;
            }

            $cursor = DocumentFolder::find($cursor->parent_id);
        }

        return false;
    }
} 