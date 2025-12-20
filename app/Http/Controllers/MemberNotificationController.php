<?php

namespace App\Http\Controllers;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsOccupation;
use App\Models\MemberAppsDeviceToken;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class MemberNotificationController extends Controller
{
    /**
     * Display the notification form
     */
    public function index(Request $request)
    {
        // Get member levels
        $memberLevels = DB::table('member_apps_members')
            ->select('member_level')
            ->distinct()
            ->whereNotNull('member_level')
            ->orderBy('member_level')
            ->pluck('member_level')
            ->toArray();

        // Get occupations
        $occupations = MemberAppsOccupation::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get stats
        $stats = [
            'total_members' => MemberAppsMember::where('is_active', true)->count(),
            'members_with_tokens' => MemberAppsMember::where('is_active', true)
                ->whereHas('deviceTokens', function ($query) {
                    $query->where('is_active', true);
                })
                ->count(),
            'total_device_tokens' => MemberAppsDeviceToken::where('is_active', true)->count(),
        ];

        return Inertia::render('MemberNotification/Index', [
            'memberLevels' => $memberLevels,
            'occupations' => $occupations,
            'stats' => $stats,
        ]);
    }

    /**
     * Search members for autocomplete
     */
    public function searchMembers(Request $request)
    {
        $search = $request->get('search', '');

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $members = MemberAppsMember::where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile_phone', 'like', "%{$search}%")
                    ->orWhere('member_id', 'like', "%{$search}%");
            })
            ->whereHas('deviceTokens', function ($query) {
                $query->where('is_active', true);
            })
            ->select('id', 'member_id', 'nama_lengkap', 'email', 'mobile_phone', 'member_level')
            ->limit(20)
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'member_id' => $member->member_id,
                    'nama_lengkap' => $member->nama_lengkap,
                    'email' => $member->email,
                    'mobile_phone' => $member->mobile_phone,
                    'member_level' => $member->member_level,
                    'label' => $member->nama_lengkap . ' (' . $member->email . ') - ' . $member->member_level,
                ];
            });

        return response()->json($members);
    }

    /**
     * Get members based on filters
     */
    public function getMembers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_level' => 'nullable|string',
            'pekerjaan_id' => 'nullable|integer|exists:member_apps_occupations,id',
            'is_exclusive_member' => 'nullable|boolean',
            'has_device_token' => 'nullable|boolean',
            'search' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $query = MemberAppsMember::where('is_active', true);

        // Filter by member level
        if ($request->filled('member_level')) {
            $query->where('member_level', $request->member_level);
        }

        // Filter by pekerjaan
        if ($request->filled('pekerjaan_id')) {
            $query->where('pekerjaan_id', $request->pekerjaan_id);
        }

        // Filter by exclusive member
        if ($request->filled('is_exclusive_member')) {
            $query->where('is_exclusive_member', $request->is_exclusive_member ? 1 : 0);
        }

        // Filter by device token
        if ($request->filled('has_device_token') && $request->has_device_token) {
            $query->whereHas('deviceTokens', function ($q) {
                $q->where('is_active', true);
            });
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile_phone', 'like', "%{$search}%")
                    ->orWhere('member_id', 'like', "%{$search}%");
            });
        }

        $members = $query->select('id', 'member_id', 'nama_lengkap', 'email', 'mobile_phone', 'member_level')
            ->limit(1000) // Limit to prevent memory issues
            ->get();

        return response()->json([
            'success' => true,
            'data' => $members,
            'count' => $members->count()
        ]);
    }

    /**
     * Send notification to members
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'image_url' => 'nullable|url|max:500',
            'target_type' => 'required|in:all,selected,filtered',
            'member_ids' => 'required_if:target_type,selected|array',
            'member_ids.*' => 'integer|exists:member_apps_members,id',
            'member_level' => 'required_if:target_type,filtered|nullable|string',
            'pekerjaan_id' => 'nullable|integer|exists:member_apps_occupations,id',
            'is_exclusive_member' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $title = $request->title;
            $message = $request->message;
            $imageUrl = $request->image_url;
            $targetType = $request->target_type;

            // Get target members
            $members = collect();

            if ($targetType === 'all') {
                // Get all active members with device tokens
                $members = MemberAppsMember::where('is_active', true)
                    ->where('allow_notification', true)
                    ->whereHas('deviceTokens', function ($query) {
                        $query->where('is_active', true);
                    })
                    ->get();
            } elseif ($targetType === 'selected') {
                // Get selected members
                $memberIds = $request->member_ids ?? [];
                $members = MemberAppsMember::whereIn('id', $memberIds)
                    ->where('is_active', true)
                    ->where('allow_notification', true)
                    ->whereHas('deviceTokens', function ($query) {
                        $query->where('is_active', true);
                    })
                    ->get();
            } elseif ($targetType === 'filtered') {
                // Get filtered members
                $query = MemberAppsMember::where('is_active', true)
                    ->where('allow_notification', true)
                    ->whereHas('deviceTokens', function ($q) {
                        $q->where('is_active', true);
                    });

                if ($request->filled('member_level')) {
                    $query->where('member_level', $request->member_level);
                }

                if ($request->filled('pekerjaan_id')) {
                    $query->where('pekerjaan_id', $request->pekerjaan_id);
                }

                if ($request->filled('is_exclusive_member')) {
                    $query->where('is_exclusive_member', $request->is_exclusive_member ? 1 : 0);
                }

                $members = $query->get();
            }

            if ($members->isEmpty()) {
                return back()->withErrors(['error' => 'Tidak ada member yang memenuhi kriteria. Pastikan member memiliki device token aktif dan mengizinkan notifikasi.'])->withInput();
            }

            Log::info('Sending manual notification to members', [
                'target_type' => $targetType,
                'member_count' => $members->count(),
                'title' => $title,
            ]);

            // Send notification using FCMService
            $fcmService = app(FCMService::class);
            $totalSuccess = 0;
            $totalFailed = 0;

            // Process in chunks to avoid memory issues
            $chunks = $members->chunk(100);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $member) {
                    $result = $fcmService->sendToMember(
                        $member,
                        $title,
                        $message,
                        [
                            'type' => 'manual_notification',
                            'title' => $title,
                            'message' => $message,
                        ],
                        $imageUrl
                    );

                    $totalSuccess += $result['success_count'] ?? 0;
                    $totalFailed += $result['failed_count'] ?? 0;
                }
            }

            $message = "Notifikasi berhasil dikirim ke {$members->count()} member. Berhasil: {$totalSuccess}, Gagal: {$totalFailed}";

            return redirect()->route('member-notification.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to send member notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->except(['image_url']),
            ]);

            return back()->withErrors(['error' => 'Gagal mengirim notifikasi: ' . $e->getMessage()])->withInput();
        }
    }
}

