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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User;
use Illuminate\Support\Str;

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
                $document->can_delete = $this->canDeleteDocument($document, $user);
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
            'selectedFolderCanDelete' => $selectedFolder ? $this->canDeleteFolder($selectedFolder, $user) : false,
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

        $canEdit = $document->hasPermission($user, 'edit');
        $onlyOfficeConfig = $this->isOnlyOfficeEditable((string) $document->file_type)
            ? $this->buildOnlyOfficeConfig($document, $user, $canEdit)
            : null;

        if ($onlyOfficeConfig) {
            \Log::info('OnlyOffice config generated', [
                'document_id' => $document->id,
                'callback_url' => $onlyOfficeConfig['editorConfig']['callbackUrl'] ?? 'N/A',
                'document_url' => $onlyOfficeConfig['document']['url'] ?? 'N/A',
                'jwt_enabled' => $this->isOnlyOfficeJwtEnabled(),
                'app_url' => config('app.url'),
            ]);
        }

        return Inertia::render('SharedDocuments/Show', [
            'document' => $document,
            'onlyoffice' => [
                'enabled' => (bool) $onlyOfficeConfig,
                'config' => $onlyOfficeConfig,
            ],
        ]);
    }

    public function edit($id): Response
    {
        return $this->show($id);
    }

    public function update(Request $request, $id)
    {
        $document = SharedDocument::findOrFail($id);
        $user = Auth::user();

        if (!$document->hasPermission($user, 'edit')) {
            abort(403, 'Anda tidak memiliki izin mengedit metadata dokumen ini.');
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $document->update([
            'title' => $request->input('title', $document->title),
            'description' => $request->input('description', $document->description),
        ]);

        return back()->with('success', 'Metadata dokumen berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        
        $document = SharedDocument::findOrFail($id);
        
        if (!$this->canDeleteDocument($document, $user)) {
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
        abort(403, 'Dokumen bersifat read-only. Fitur berbagi dinonaktifkan.');
    }

    public function removeShare(Request $request, $id)
    {
        abort(403, 'Dokumen bersifat read-only. Fitur berbagi dinonaktifkan.');
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

        if (!$this->canDeleteFolder($folder, $user)) {
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

    public function preview($id)
    {
        $user = Auth::user();

        $document = SharedDocument::findOrFail($id);

        if (!$document->hasPermission($user)) {
            abort(403, 'Anda tidak memiliki akses ke dokumen ini.');
        }

        $filePath = storage_path('app/public/' . $document->file_path);

        if (!file_exists($filePath)) {
            abort(404, 'File tidak ditemukan.');
        }

        return response()->file($filePath, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Content-Type' => $this->getMimeType($document->file_type),
            'Content-Disposition' => 'inline; filename="' . addslashes($document->filename) . '"',
        ]);
    }

    public function apiIndex(Request $request)
    {
        $user = Auth::user();
        $folderId = $request->integer('folder_id');
        $search = trim((string) $request->get('search', ''));

        $allFolders = DocumentFolder::with(['parent'])
            ->visibleTo($user)
            ->orderBy('name')
            ->get();

        $selectedFolder = null;
        if (!empty($folderId)) {
            $selectedFolder = $allFolders->firstWhere('id', $folderId);
            if (!$selectedFolder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke folder ini.',
                ], 403);
            }
        }

        $folders = $allFolders
            ->where('parent_id', $folderId)
            ->values()
            ->map(function (DocumentFolder $folder) use ($user) {
                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'parent_id' => $folder->parent_id,
                    'is_public' => (bool) $folder->is_public,
                    'can_manage' => $folder->hasPermission($user, 'admin'),
                    'can_edit' => $folder->hasPermission($user, 'edit'),
                ];
            });

        $documents = SharedDocument::with(['creator', 'folder'])
            ->visibleTo($user)
            ->when($folderId, function ($query, $folderId) {
                $query->where('folder_id', $folderId);
            }, function ($query) {
                $query->whereNull('folder_id');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('filename', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (SharedDocument $document) use ($user) {
                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'filename' => $document->filename,
                    'file_type' => strtolower((string) $document->file_type),
                    'file_size' => (int) $document->file_size,
                    'description' => $document->description,
                    'folder_id' => $document->folder_id,
                    'is_public' => (bool) $document->is_public,
                    'created_at' => optional($document->created_at)->toISOString(),
                    'creator_name' => optional($document->creator)->nama_lengkap,
                    'permission' => $this->resolvePermissionLabel($document, $user),
                    'can_move' => $document->hasPermission($user, 'edit'),
                    'can_delete' => $this->canDeleteDocument($document, $user),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'current_folder_id' => $folderId,
                'current_folder' => $selectedFolder ? [
                    'id' => $selectedFolder->id,
                    'name' => $selectedFolder->name,
                    'parent_id' => $selectedFolder->parent_id,
                    'can_manage' => $selectedFolder->hasPermission($user, 'admin'),
                    'can_edit' => $selectedFolder->hasPermission($user, 'edit'),
                ] : null,
                'folders' => $folders->values(),
                'documents' => $documents->values(),
                'breadcrumbs' => $this->buildFolderBreadcrumbs($allFolders, $selectedFolder),
                'folder_tree_items' => $this->buildFolderTreeItems($allFolders),
            ],
        ]);
    }

    public function apiShow($id)
    {
        $user = Auth::user();
        $document = SharedDocument::with(['creator', 'folder'])
            ->findOrFail($id);

        if (!$document->hasPermission($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke dokumen ini.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $document->id,
                'title' => $document->title,
                'filename' => $document->filename,
                'file_type' => strtolower((string) $document->file_type),
                'file_size' => (int) $document->file_size,
                'description' => $document->description,
                'folder_id' => $document->folder_id,
                'folder_name' => optional($document->folder)->name,
                'is_public' => (bool) $document->is_public,
                'created_at' => optional($document->created_at)->toISOString(),
                'creator_name' => optional($document->creator)->nama_lengkap,
                'permission' => $this->resolvePermissionLabel($document, $user),
                'can_move' => $document->hasPermission($user, 'edit'),
                'can_delete' => $this->canDeleteDocument($document, $user),
                'download_url' => route('api.approval-app.shared-documents.download', ['id' => $document->id]),
                'preview_url' => route('api.approval-app.shared-documents.preview', ['id' => $document->id]),
            ],
        ]);
    }

    public function apiStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'folder_id' => 'nullable|exists:document_folders,id',
            'file' => 'required|file|mimes:pdf,xlsx,xls,docx,doc,pptx,ppt,csv,txt,zip,rar|max:20480',
        ]);

        $user = Auth::user();
        $folder = null;
        if ($request->filled('folder_id')) {
            $folder = DocumentFolder::findOrFail($request->folder_id);
            if (!$folder->hasPermission($user, 'edit')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk upload pada folder ini.',
                ], 403);
            }
        }

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $storageDirectory = $folder
            ? 'shared-documents/' . $this->buildFolderStoragePath($folder)
            : 'shared-documents/root';
        $filePath = $file->storeAs($storageDirectory, $filename, 'public');

        $document = SharedDocument::create([
            'folder_id' => $request->input('folder_id'),
            'title' => $request->input('title'),
            'filename' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'description' => $request->input('description'),
            'created_by' => $user->id,
            'is_public' => false,
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => '1.0',
            'file_path' => $filePath,
            'change_description' => 'Initial version',
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil diupload.',
            'data' => [
                'id' => $document->id,
                'title' => $document->title,
                'filename' => $document->filename,
                'folder_id' => $document->folder_id,
            ],
        ]);
    }

    public function apiDestroy($id)
    {
        $user = Auth::user();
        $document = SharedDocument::findOrFail($id);

        if (!$this->canDeleteDocument($document, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk menghapus dokumen ini.',
            ], 403);
        }

        Storage::disk('public')->delete($document->file_path);

        foreach ($document->versions as $version) {
            Storage::disk('public')->delete($version->file_path);
        }

        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil dihapus.',
        ]);
    }

    public function apiMove(Request $request, $id)
    {
        $user = Auth::user();
        $document = SharedDocument::findOrFail($id);

        if (!$document->hasPermission($user, 'edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin memindahkan dokumen ini.',
            ], 403);
        }

        $request->validate([
            'target_folder_id' => 'nullable|exists:document_folders,id',
        ]);

        $targetFolderId = $request->input('target_folder_id');
        if (!empty($targetFolderId)) {
            $targetFolder = DocumentFolder::findOrFail($targetFolderId);
            if (!$targetFolder->hasPermission($user, 'edit')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin ke folder tujuan.',
                ], 403);
            }
        }

        $document->update([
            'folder_id' => $targetFolderId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil dipindahkan.',
        ]);
    }

    public function apiCreateFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'parent_id' => 'nullable|exists:document_folders,id',
        ]);

        $user = Auth::user();
        $parent = null;

        if ($request->filled('parent_id')) {
            $parent = DocumentFolder::findOrFail($request->parent_id);
            if (!$parent->hasPermission($user, 'edit')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin membuat subfolder di folder ini.',
                ], 403);
            }
        }

        $folder = DocumentFolder::create([
            'name' => $request->name,
            'parent_id' => $request->input('parent_id'),
            'created_by' => $user->id,
            'is_public' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil dibuat.',
            'data' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'parent_id' => $folder->parent_id,
            ],
        ]);
    }

    public function apiRenameFolder(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $user = Auth::user();
        $folder = DocumentFolder::findOrFail($id);

        if (!$folder->hasPermission($user, 'edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk mengubah nama folder ini.',
            ], 403);
        }

        $folder->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Nama folder berhasil diperbarui.',
        ]);
    }

    public function apiMoveFolder(Request $request, $id)
    {
        $request->validate([
            'target_parent_id' => 'nullable|exists:document_folders,id',
        ]);

        $user = Auth::user();
        $folder = DocumentFolder::findOrFail($id);

        if (!$folder->hasPermission($user, 'edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin memindahkan folder ini.',
            ], 403);
        }

        $targetParentId = $request->input('target_parent_id');
        if (!empty($targetParentId)) {
            if ((int) $targetParentId === (int) $folder->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Folder tidak bisa dipindah ke dirinya sendiri.',
                ], 422);
            }

            if ($this->isDescendantFolder((int) $folder->id, (int) $targetParentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Folder tidak bisa dipindah ke subfolder-nya sendiri.',
                ], 422);
            }

            $targetParentFolder = DocumentFolder::findOrFail($targetParentId);
            if (!$targetParentFolder->hasPermission($user, 'edit')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin ke folder tujuan.',
                ], 403);
            }
        }

        $folder->update([
            'parent_id' => $targetParentId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil dipindahkan.',
        ]);
    }

    public function apiDeleteFolder(Request $request, $id)
    {
        $request->validate([
            'mode' => 'nullable|in:move_to_root,move_to_folder',
            'target_folder_id' => 'nullable|exists:document_folders,id',
        ]);

        $user = Auth::user();
        $folder = DocumentFolder::findOrFail($id);

        if (!$this->canDeleteFolder($folder, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin menghapus folder ini.',
            ], 403);
        }

        $mode = $request->input('mode', 'move_to_root');
        $targetFolderId = $mode === 'move_to_folder' ? $request->input('target_folder_id') : null;

        if ($mode === 'move_to_folder') {
            if (empty($targetFolderId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Folder tujuan wajib dipilih.',
                ], 422);
            }

            if ((int) $targetFolderId === (int) $folder->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Folder tujuan tidak boleh folder yang sama.',
                ], 422);
            }

            if ($this->isDescendantFolder((int) $folder->id, (int) $targetFolderId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Folder tujuan tidak boleh subfolder dari folder yang dihapus.',
                ], 422);
            }

            $targetFolder = DocumentFolder::findOrFail($targetFolderId);
            if (!$targetFolder->hasPermission($user, 'edit')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin ke folder tujuan.',
                ], 403);
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

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil dihapus.',
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

    private function buildOnlyOfficeConfig(SharedDocument $document, User $user, bool $canEdit): ?array
    {
        $documentServerUrl = rtrim((string) config('app.onlyoffice_url'), '/');
        if ($documentServerUrl === '') {
            return null;
        }

        $config = [
            'document' => [
                'title' => $document->filename,
                'url' => $document->getFileUrl(),
                'fileType' => strtolower((string) $document->file_type),
                'key' => (string) $document->document_key,
            ],
            'documentType' => $this->mapDocumentType((string) $document->file_type),
            'editorConfig' => [
                'mode' => $canEdit ? 'edit' : 'view',
                'lang' => 'id',
                'callbackUrl' => route('shared-documents.callback', ['id' => $document->id]),
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->nama_lengkap ?: $user->name,
                ],
                'customization' => [
                    'forcesave' => true,
                ],
            ],
            'permissions' => [
                'edit' => $canEdit,
                'download' => true,
                'print' => true,
                'comment' => true,
            ],
        ];

        $token = $this->generateOnlyOfficeToken($config);
        if ($token) {
            $config['token'] = $token;
        }

        return $config;
    }

    private function mapDocumentType(string $extension): string
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            return 'spreadsheet';
        }

        if (in_array($extension, ['pptx', 'ppt'], true)) {
            return 'presentation';
        }

        return 'text';
    }

    private function isOnlyOfficeEditable(string $extension): bool
    {
        return in_array(strtolower($extension), ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true);
    }

    private function generateOnlyOfficeToken(array $payload): ?string
    {
        if (!$this->isOnlyOfficeJwtEnabled()) {
            return null;
        }

        $secret = (string) config('app.onlyoffice_jwt_secret', '');
        if ($secret === '') {
            return null;
        }

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function isOnlyOfficeJwtEnabled(): bool
    {
        return filter_var(config('app.onlyoffice_jwt_enabled', true), FILTER_VALIDATE_BOOL);
    }

    private function isOnlyOfficeCallbackAuthorized(Request $request): bool
    {
        if (!$this->isOnlyOfficeJwtEnabled()) {
            return true;
        }

        $token = '';
        $authHeader = (string) $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = trim(substr($authHeader, 7));
        }

        if ($token === '') {
            $token = (string) $request->input('token', '');
        }

        if ($token === '') {
            return false;
        }

        return $this->isValidOnlyOfficeToken($token);
    }

    private function isValidOnlyOfficeToken(string $token): bool
    {
        $secret = (string) config('app.onlyoffice_jwt_secret', '');
        if ($secret === '') {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;
        $signingInput = $header . '.' . $payload;
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $signingInput, $secret, true));

        return hash_equals($expectedSignature, $signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function nextVersionNumber(SharedDocument $document): string
    {
        $lastVersion = DocumentVersion::query()
            ->where('document_id', $document->id)
            ->orderByDesc('id')
            ->value('version_number');

        if (!$lastVersion) {
            return '1.0';
        }

        $lastNumeric = (float) preg_replace('/[^0-9.]/', '', (string) $lastVersion);
        if ($lastNumeric <= 0) {
            return '1.0';
        }

        return number_format($lastNumeric + 0.1, 1, '.', '');
    }

    private function resolvePermissionLabel(SharedDocument $document, User $user): string
    {
        if ($document->created_by === $user->id) {
            return 'owner';
        }

        if ($document->hasPermission($user, 'admin')) {
            return 'admin';
        }

        if ($document->hasPermission($user, 'edit')) {
            return 'edit';
        }

        return 'view';
    }

    private function isSuperadmin(User $user): bool
    {
        return $user->id_role === '5af56935b011a' && $user->status === 'A';
    }

    private function canDeleteDocument(SharedDocument $document, User $user): bool
    {
        return $document->created_by === $user->id || $this->isSuperadmin($user);
    }

    private function canDeleteFolder(DocumentFolder $folder, User $user): bool
    {
        return $folder->created_by === $user->id || $this->isSuperadmin($user);
    }

    public function callback(Request $request, $id)
    {
        \Log::info('OnlyOffice callback received', [
            'document_id' => $id,
            'status' => $request->input('status'),
            'has_url' => !empty($request->input('url')),
            'auth_header' => $request->header('Authorization') ? 'present' : 'missing',
            'ip' => $request->ip(),
        ]);

        $document = SharedDocument::find($id);
        if (!$document) {
            return response()->json(['error' => 0]);
        }

        if (!$this->isOnlyOfficeCallbackAuthorized($request)) {
            \Log::warning('OnlyOffice callback auth failed', [
                'document_id' => $id,
                'jwt_enabled' => $this->isOnlyOfficeJwtEnabled(),
                'auth_header' => $request->header('Authorization') ? substr($request->header('Authorization'), 0, 30).'...' : 'none',
                'token_in_body' => !empty($request->input('token')),
            ]);
            return response()->json(['error' => 1], 401);
        }

        $status = (int) $request->input('status', 0);
        $downloadUrl = (string) $request->input('url', '');

        if (in_array($status, [2, 6], true) && $downloadUrl !== '') {
            try {
                $response = Http::timeout(60)->get($downloadUrl);
                if (!$response->successful()) {
                    Log::error('OnlyOffice callback download failed', [
                        'document_id' => $document->id,
                        'status' => $status,
                        'http_status' => $response->status(),
                    ]);

                    return response()->json(['error' => 1]);
                }

                $binary = $response->body();
                $extension = strtolower(pathinfo($document->filename, PATHINFO_EXTENSION));
                $versionPath = 'shared-documents/versions/' . $document->id . '/' . now()->format('YmdHis') . '_' . Str::uuid() . '.' . $extension;

                Storage::disk('public')->put($versionPath, $binary);
                Storage::disk('public')->put($document->file_path, $binary);

                $document->update([
                    'file_size' => strlen($binary),
                    'document_key' => (string) Str::uuid(),
                ]);

                DocumentVersion::create([
                    'document_id' => $document->id,
                    'version_number' => $this->nextVersionNumber($document),
                    'file_path' => $versionPath,
                    'change_description' => $status === 6 ? 'Autosave (forcesave) from OnlyOffice' : 'Saved from OnlyOffice editor',
                    'created_by' => $document->created_by,
                ]);
            } catch (\Throwable $exception) {
                Log::error('OnlyOffice callback processing failed', [
                    'document_id' => $document->id,
                    'status' => $status,
                    'message' => $exception->getMessage(),
                ]);

                return response()->json(['error' => 1]);
            }
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