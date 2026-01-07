<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CctvAccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Services\NotificationService;
use Inertia\Inertia;

class CctvAccessRequestController extends Controller
{
    /**
     * Display the index page (Inertia)
     */
    public function indexPage(Request $request)
    {
        $user = auth()->user();
        $userId = auth()->id();
        $isITManager = $this->isITManager($user);
        
        // Check if user is IT team (division_id = 21)
        $isITTeam = $user->division_id == 21 && $user->status === 'A';

        $search = $request->input('search', '');
        $status = $request->input('status', 'all');
        $accessType = $request->input('access_type', 'all');
        $perPage = (int) $request->input('per_page', 15);

        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        $query = CctvAccessRequest::with(['user', 'itManager', 'revokedBy', 'playbackUploadedBy'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Filter by access type
        if ($accessType !== 'all') {
            $query->where('access_type', $accessType);
        }

        // Filter by user: IT team (division_id=21) can see all, others only see their own
        if (!$isITTeam) {
            $query->where('user_id', $userId);
        }

        // Search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('nama_lengkap', 'like', "%{$search}%");
                  });
            });
        }

        $requests = $query->paginate($perPage);

        // Get outlets for form
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        // Transform requests to include outlet names
        $requests->getCollection()->transform(function ($request) use ($outlets) {
            if ($request->outlet_ids && is_array($request->outlet_ids)) {
                $request->outlet_names = array_map(function ($outletId) use ($outlets) {
                    $outlet = $outlets->firstWhere('id_outlet', $outletId);
                    return $outlet ? $outlet->nama_outlet : $outletId;
                }, $request->outlet_ids);
            }
            return $request;
        });

        return Inertia::render('CctvAccessRequest/Index', [
            'requests' => $requests,
            'outlets' => $outlets,
            'isITManager' => $isITManager,
            'isITTeam' => $isITTeam,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'access_type' => $accessType,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Get all CCTV access requests (with filters) - API
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $userId = auth()->id();

        $query = CctvAccessRequest::with(['user', 'itManager', 'revokedBy', 'playbackUploadedBy'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by access type
        if ($request->has('access_type') && $request->access_type !== 'all') {
            $query->where('access_type', $request->access_type);
        }

        // Filter by user: IT team (division_id=21) can see all, others only see their own
        $isITTeam = $user->division_id == 21 && $user->status === 'A';
        $isITManager = $this->isITManager($user);
        if (!$isITTeam) {
            $query->where('user_id', $userId);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('nama_lengkap', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = $request->get('per_page', 15);
        $requests = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Get pending approvals for IT Manager
     */
    public function getPendingApprovals(Request $request)
    {
        $user = auth()->user();
        
        if (!$this->isITManager($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Hanya IT Manager yang dapat melihat pending approvals'
            ], 403);
        }

        $query = CctvAccessRequest::with(['user'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc');

        // Filter by access type
        if ($request->has('access_type') && $request->access_type !== 'all') {
            $query->where('access_type', $request->access_type);
        }

        // For approval card, return all pending (no pagination)
        $requests = $query->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Get single request details
     */
    public function show($id)
    {
        $user = auth()->user();
        $userId = auth()->id();

        $request = CctvAccessRequest::with(['user', 'itManager', 'revokedBy', 'playbackUploadedBy'])
            ->find($id);

        if (!$request) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found'
            ], 404);
        }

        // Check authorization: user can see own requests, IT Manager can see all
        $isITManager = $this->isITManager($user);
        if (!$isITManager && $request->user_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Get outlets for outlet names
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->get();

        // Add outlet names to request
        if ($request->outlet_ids && is_array($request->outlet_ids)) {
            $request->outlet_names = array_map(function ($outletId) use ($outlets) {
                $outlet = $outlets->firstWhere('id_outlet', $outletId);
                return $outlet ? $outlet->nama_outlet : $outletId;
            }, $request->outlet_ids);
        }

        // Add playback validity check
        if ($request->access_type === 'playback') {
            $request->is_playback_valid = $request->isPlaybackValid();
            $request->is_playback_expired = $request->isPlaybackExpired();
        }

        return response()->json([
            'success' => true,
            'data' => $request
        ]);
    }

    /**
     * Create new CCTV access request
     */
    public function store(Request $request)
    {
        $rules = [
            'access_type' => 'required|in:live_view,playback',
            'reason' => 'required|string|max:1000',
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => 'required|integer|exists:tbl_data_outlet,id_outlet'
        ];

        // Validation for live_view
        if ($request->access_type === 'live_view') {
            $rules['email'] = 'required|email|max:255';
        }

        // Validation for playback
        if ($request->access_type === 'playback') {
            $rules['area'] = 'required|string|max:255';
            $rules['date_from'] = 'required|date';
            $rules['date_to'] = 'required|date|after_or_equal:date_from';
            $rules['time_from'] = 'required|date_format:H:i';
            $rules['time_to'] = 'required|date_format:H:i|after:time_from';
            $rules['incident_description'] = 'required|string|max:1000';
        }

        $request->validate($rules);

        $userId = auth()->id();
        $user = auth()->user();

        // Semua user bisa request playback, tapi hanya IT Manager yang bisa approve
        // Validasi dihapus - semua user bisa memilih playback

        try {
            DB::beginTransaction();

            $cctvRequest = CctvAccessRequest::create([
                'user_id' => $userId,
                'access_type' => $request->access_type,
                'reason' => $request->reason,
                'outlet_ids' => $request->outlet_ids,
                'email' => $request->access_type === 'live_view' ? $request->email : null,
                'area' => $request->access_type === 'playback' ? $request->area : null,
                'date_from' => $request->access_type === 'playback' ? $request->date_from : null,
                'date_to' => $request->access_type === 'playback' ? $request->date_to : null,
                'time_from' => $request->access_type === 'playback' ? $request->time_from : null,
                'time_to' => $request->access_type === 'playback' ? $request->time_to : null,
                'incident_description' => $request->access_type === 'playback' ? $request->incident_description : null,
                'status' => 'pending'
            ]);

            // Send notification to IT Manager
            $itManagers = $this->getITManagers();
            foreach ($itManagers as $itManager) {
                NotificationService::insert([
                    'user_id' => $itManager->id,
                    'type' => 'cctv_access_request',
                    'message' => "Permintaan akses CCTV ({$cctvRequest->access_type_text}) dari {$user->nama_lengkap} membutuhkan persetujuan Anda.",
                    'url' => config('app.url') . '/cctv-access-requests',
                    'is_read' => 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permintaan akses CCTV berhasil diajukan',
                'data' => $cctvRequest->load(['user'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating CCTV access request: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan permintaan akses CCTV'
            ], 500);
        }
    }

    /**
     * Update request (only pending requests can be updated)
     */
    public function update(Request $request, $id)
    {
        $rules = [
            'reason' => 'required|string|max:1000',
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => 'required|integer|exists:tbl_data_outlet,id_outlet'
        ];

        // Validation for live_view
        if ($request->access_type === 'live_view') {
            $rules['email'] = 'required|email|max:255';
        }

        // Validation for playback
        if ($request->access_type === 'playback') {
            $rules['area'] = 'required|string|max:255';
            $rules['date_from'] = 'required|date';
            $rules['date_to'] = 'required|date|after_or_equal:date_from';
            $rules['time_from'] = 'required|date_format:H:i';
            $rules['time_to'] = 'required|date_format:H:i|after:time_from';
            $rules['incident_description'] = 'required|string|max:1000';
        }

        $request->validate($rules);

        $userId = auth()->id();
        $cctvRequest = CctvAccessRequest::find($id);

        if (!$cctvRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found'
            ], 404);
        }

        // Only owner can update, and only if status is pending
        if ($cctvRequest->user_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($cctvRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya request dengan status pending yang dapat diubah'
            ], 400);
        }

        $updateData = [
            'reason' => $request->reason,
            'outlet_ids' => $request->outlet_ids
        ];

        if ($cctvRequest->access_type === 'live_view') {
            $updateData['email'] = $request->email;
            $updateData['area'] = null;
            $updateData['date_from'] = null;
            $updateData['date_to'] = null;
            $updateData['time_from'] = null;
            $updateData['time_to'] = null;
            $updateData['incident_description'] = null;
        } else {
            $updateData['email'] = null;
            $updateData['area'] = $request->area;
            $updateData['date_from'] = $request->date_from;
            $updateData['date_to'] = $request->date_to;
            $updateData['time_from'] = $request->time_from;
            $updateData['time_to'] = $request->time_to;
            $updateData['incident_description'] = $request->incident_description;
        }

        $cctvRequest->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Request berhasil diupdate',
            'data' => $cctvRequest->load(['user'])
        ]);
    }

    /**
     * Cancel/Delete request (only pending requests)
     */
    public function destroy($id)
    {
        $userId = auth()->id();
        $cctvRequest = CctvAccessRequest::find($id);

        if (!$cctvRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found'
            ], 404);
        }

        // Only owner can delete, and only if status is pending
        if ($cctvRequest->user_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($cctvRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya request dengan status pending yang dapat dibatalkan'
            ], 400);
        }

        $cctvRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Request berhasil dibatalkan'
        ]);
    }

    /**
     * Approve request (IT Manager only)
     */
    public function approve(Request $request, $id)
    {
        $request->validate([
            'approval_notes' => 'nullable|string|max:500'
        ]);

        $user = auth()->user();
        
        if (!$this->isITManager($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Hanya IT Manager yang dapat menyetujui request'
            ], 403);
        }

        $cctvRequest = CctvAccessRequest::find($id);

        if (!$cctvRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found'
            ], 404);
        }

        if ($cctvRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Request sudah diproses'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $cctvRequest->approve($user->id, $request->approval_notes);

            // Send notification to requester
            NotificationService::insert([
                'user_id' => $cctvRequest->user_id,
                'type' => 'cctv_access_approved',
                'message' => "Permintaan akses CCTV ({$cctvRequest->access_type_text}) Anda telah disetujui oleh IT Manager.",
                'url' => config('app.url') . '/cctv-access-requests',
                'is_read' => 0,
            ]);

            // Send notification to IT team (division_id = 21)
            $itTeamMembers = DB::table('users')
                ->where('division_id', 21)
                ->where('status', 'A')
                ->where('id', '!=', $user->id) // Exclude current user
                ->get();

            foreach ($itTeamMembers as $itMember) {
                NotificationService::insert([
                    'user_id' => $itMember->id,
                    'type' => 'cctv_access_approved',
                    'message' => "Permintaan akses CCTV ({$cctvRequest->access_type_text}) dari {$cctvRequest->user->nama_lengkap} telah disetujui.",
                    'url' => config('app.url') . '/cctv-access-requests',
                    'is_read' => 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request berhasil disetujui',
                'data' => $cctvRequest->load(['user', 'itManager'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error approving CCTV access request: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui request'
            ], 500);
        }
    }

    /**
     * Reject request (IT Manager only)
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'approval_notes' => 'required|string|max:500'
        ]);

        $user = auth()->user();
        
        if (!$this->isITManager($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Hanya IT Manager yang dapat menolak request'
            ], 403);
        }

        $cctvRequest = CctvAccessRequest::find($id);

        if (!$cctvRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found'
            ], 404);
        }

        if ($cctvRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Request sudah diproses'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $cctvRequest->reject($user->id, $request->approval_notes);

            // Send notification to requester
            NotificationService::insert([
                'user_id' => $cctvRequest->user_id,
                'type' => 'cctv_access_rejected',
                'message' => "Permintaan akses CCTV ({$cctvRequest->access_type_text}) Anda telah ditolak oleh IT Manager.",
                'url' => config('app.url') . '/cctv-access-requests',
                'is_read' => 0,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request berhasil ditolak',
                'data' => $cctvRequest->load(['user', 'itManager'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error rejecting CCTV access request: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menolak request'
            ], 500);
        }
    }

    /**
     * Revoke access (IT Manager only)
     */
    public function revoke(Request $request, $id)
    {
        $request->validate([
            'revocation_reason' => 'required|string|max:500'
        ]);

        $user = auth()->user();
        
        if (!$this->isITManager($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Hanya IT Manager yang dapat mencabut akses'
            ], 403);
        }

        $cctvRequest = CctvAccessRequest::find($id);

        if (!$cctvRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found'
            ], 404);
        }

        if ($cctvRequest->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya request yang sudah disetujui yang dapat dicabut'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $cctvRequest->revoke($user->id, $request->revocation_reason);

            // Send notification to requester
            NotificationService::insert([
                'user_id' => $cctvRequest->user_id,
                'type' => 'cctv_access_revoked',
                'message' => "Akses CCTV ({$cctvRequest->access_type_text}) Anda telah dicabut oleh IT Manager.",
                'url' => config('app.url') . '/cctv-access-requests',
                'is_read' => 0,
            ]);

            // Send notification to IT team (division_id = 21)
            $itTeamMembers = DB::table('users')
                ->where('division_id', 21)
                ->where('status', 'A')
                ->where('id', '!=', $user->id) // Exclude current user
                ->get();

            foreach ($itTeamMembers as $itMember) {
                NotificationService::insert([
                    'user_id' => $itMember->id,
                    'type' => 'cctv_access_revoked',
                    'message' => "Akses CCTV ({$cctvRequest->access_type_text}) dari {$cctvRequest->user->nama_lengkap} telah dicabut.",
                    'url' => config('app.url') . '/cctv-access-requests',
                    'is_read' => 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Akses berhasil dicabut',
                'data' => $cctvRequest->load(['user', 'itManager', 'revokedBy'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error revoking CCTV access: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencabut akses'
            ], 500);
        }
    }

    /**
     * Get my requests
     */
    public function getMyRequests(Request $request)
    {
        $userId = auth()->id();

        $query = CctvAccessRequest::with(['itManager', 'revokedBy'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by access type
        if ($request->has('access_type') && $request->access_type !== 'all') {
            $query->where('access_type', $request->access_type);
        }

        $perPage = $request->get('per_page', 15);
        $requests = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Get statistics
     */
    public function getStats()
    {
        $user = auth()->user();
        $userId = auth()->id();
        $isITManager = $this->isITManager($user);

        $query = CctvAccessRequest::query();
        
        if (!$isITManager) {
            $query->where('user_id', $userId);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'revoked' => (clone $query)->where('status', 'revoked')->count(),
            'live_view' => (clone $query)->where('access_type', 'live_view')->count(),
            'playback' => (clone $query)->where('access_type', 'playback')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Check if user is IT Manager
     * Adjust this method based on your actual system structure
     * 
     * Options:
     * 1. Check by division_id (if IT division has specific ID)
     * 2. Check by id_jabatan (if IT Manager has specific position ID)
     * 3. Check by id_role (if IT Manager has specific role ID)
     * 4. Check by jabatan name containing "IT Manager"
     */
    private function isITManager($user)
    {
        if (!$user || $user->status !== 'A') {
            return false;
        }

        // Option 1: Check by division_id
        // Uncomment and set the correct IT division ID
        // if ($user->division_id === X) { // X = IT division ID
        //     return true;
        // }

        // Option 2: Check by id_jabatan (position ID)
        // Uncomment and set the correct IT Manager position ID
        // if ($user->id_jabatan === X) { // X = IT Manager position ID
        //     return true;
        // }

        // Option 3: Check by id_role
        // Uncomment and set the correct IT Manager role ID
        // if ($user->id_role === 'XXXXX') { // IT Manager role ID
        //     return true;
        // }

        // Option 4: Check by jabatan name
        if ($user->jabatan) {
            $jabatanName = strtolower($user->jabatan->nama_jabatan ?? '');
            if (str_contains($jabatanName, 'it manager') || str_contains($jabatanName, 'manager it')) {
                return true;
            }
        }

        // Option 5: Check by divisi name
        if ($user->divisi) {
            $divisiName = strtolower($user->divisi->nama_divisi ?? '');
            if (str_contains($divisiName, 'it') && str_contains($divisiName, 'manager')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get IT Managers
     * Adjust this method based on your actual system structure
     */
    private function getITManagers()
    {
        // Option 1: Get by division_id
        // return User::where('division_id', X)->where('status', 'A')->get();
        
        // Option 2: Get by id_jabatan
        // return User::where('id_jabatan', X)->where('status', 'A')->get();
        
        // Option 3: Get by id_role
        // return User::where('id_role', 'XXXXX')->where('status', 'A')->get();
        
        // Option 4: Get by jabatan name
        $itManagers = User::whereHas('jabatan', function($q) {
            $q->whereRaw('LOWER(nama_jabatan) LIKE ?', ['%it manager%'])
              ->orWhereRaw('LOWER(nama_jabatan) LIKE ?', ['%manager it%']);
        })->where('status', 'A')->get();

        if ($itManagers->isEmpty()) {
            // Fallback: Get by divisi name
            $itManagers = User::whereHas('divisi', function($q) {
                $q->whereRaw('LOWER(nama_divisi) LIKE ?', ['%it%']);
            })->where('status', 'A')->get();
        }

        return $itManagers;
    }

    /**
     * Upload playback file (IT Team only)
     */
    public function uploadPlayback(Request $request, $id)
    {
        $request->validate([
            'files' => 'required|array|min:1|max:5', // Max 5 files
            'files.*' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm|max:102400', // Max 100MB per file, video files only
            'valid_until' => 'required|date|after:today', // Must be after today
        ]);

        $user = auth()->user();
        $userId = auth()->id();

        // Check if user is IT team (division_id = 21)
        $isITTeam = $user->division_id == 21 && $user->status === 'A';
        
        if (!$isITTeam) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Hanya tim IT yang dapat mengupload file playback'
            ], 403);
        }

        $cctvRequest = CctvAccessRequest::find($id);

        if (!$cctvRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found'
            ], 404);
        }

        if ($cctvRequest->access_type !== 'playback') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya request playback yang dapat diupload file'
            ], 400);
        }

        if ($cctvRequest->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya request yang sudah disetujui yang dapat diupload file'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Get existing files
            $existingFiles = $cctvRequest->getPlaybackFiles();
            
            // Check if adding new files would exceed limit
            $filesToUpload = $request->file('files');
            if (count($existingFiles) + count($filesToUpload) > 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maksimal 5 file playback. Anda sudah memiliki ' . count($existingFiles) . ' file.'
                ], 400);
            }

            $uploadedFiles = [];
            foreach ($filesToUpload as $file) {
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $fileName = 'playback_' . $cctvRequest->id . '_' . time() . '_' . uniqid() . '.' . $extension;
                $filePath = $file->storeAs('cctv_playbacks', $fileName, 'public');
                $uploadedFiles[] = $filePath;
            }

            // Merge with existing files
            $allFiles = array_merge($existingFiles, $uploadedFiles);

            // Set uploaded_at only if this is the first upload
            $uploadedAt = $cctvRequest->playback_uploaded_at ?? now();

            $cctvRequest->update([
                'playback_file_path' => $allFiles,
                'playback_uploaded_at' => $uploadedAt,
                'playback_uploaded_by' => $userId,
                'valid_until' => $request->valid_until
            ]);

            // Send notification to requester
            NotificationService::insert([
                'user_id' => $cctvRequest->user_id,
                'type' => 'cctv_playback_uploaded',
                'message' => "File playback untuk request CCTV Anda telah diupload oleh tim IT.",
                'url' => config('app.url') . '/cctv-access-requests',
                'is_read' => 0,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'File playback berhasil diupload',
                'data' => $cctvRequest->load(['user', 'itManager', 'playbackUploadedBy'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error uploading playback file: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload file playback'
            ], 500);
        }
    }

    /**
     * Serve playback file with authorization check
     */
    public function servePlaybackFile($id, $fileIndex)
    {
        $user = auth()->user();
        $userId = auth()->id();

        $cctvRequest = CctvAccessRequest::find($id);

        if (!$cctvRequest) {
            abort(404, 'Request not found');
        }

        // Check authorization: user can access own requests, IT team can access all
        $isITTeam = $user->division_id == 21 && $user->status === 'A';
        if (!$isITTeam && $cctvRequest->user_id !== $userId) {
            abort(403, 'Unauthorized');
        }

        if ($cctvRequest->access_type !== 'playback') {
            abort(404, 'Not a playback request');
        }

        $files = $cctvRequest->getPlaybackFiles();
        
        if (empty($files) || !isset($files[$fileIndex])) {
            abort(404, 'File not found');
        }

        $filePath = $files[$fileIndex];

        // Check if file exists
        if (!Storage::disk('public')->exists($filePath)) {
            abort(404, 'File not found');
        }

        // Check if expired (only for requester, IT team can always access)
        if (!$isITTeam && $cctvRequest->isPlaybackExpired()) {
            abort(403, 'File playback has expired');
        }

        return Storage::disk('public')->response($filePath);
    }
}

