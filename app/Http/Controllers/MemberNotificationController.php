<?php

namespace App\Http\Controllers;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsOccupation;
use App\Models\MemberAppsDeviceToken;
use App\Models\MemberAppsPushNotification;
use App\Models\MemberAppsPushNotificationRecipient;
use App\Models\MemberAppsNotification;
use App\Jobs\SendMemberNotificationJob;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class MemberNotificationController extends Controller
{
    /**
     * Display list of sent notifications
     */
    public function index(Request $request)
    {
        $query = MemberAppsPushNotification::withCount([
            'recipients as total_recipients',
            'recipients as sent_count' => function ($q) {
                $q->whereIn('status', ['sent', 'delivered', 'opened']);
            },
            'recipients as opened_count' => function ($q) {
                $q->where('status', 'opened');
            }
        ])
        ->orderBy('created_at', 'desc');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // Filter by target type
        if ($request->filled('target_type')) {
            $query->where('target_type', $request->target_type);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $notifications = $query->paginate($perPage)->withQueryString();

        // Get stats
        $stats = [
            'total_notifications' => MemberAppsPushNotification::count(),
            'total_sent' => MemberAppsPushNotification::whereNotNull('sent_at')->count(),
            'total_recipients' => MemberAppsPushNotificationRecipient::count(),
            'total_opened' => MemberAppsPushNotificationRecipient::where('status', 'opened')->count(),
        ];

        return Inertia::render('MemberNotification/List', [
            'notifications' => $notifications,
            'stats' => $stats,
            'filters' => $request->only(['search', 'target_type', 'per_page']),
        ]);
    }

    /**
     * Display the notification form
     */
    public function create()
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

        return Inertia::render('MemberNotification/Create', [
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

            // Prepare target data for storage
            $targetMemberIds = null;
            $targetFilterCriteria = null;

            if ($targetType === 'selected') {
                $targetMemberIds = $request->member_ids ?? [];
            } elseif ($targetType === 'filtered') {
                $targetFilterCriteria = [
                    'member_level' => $request->member_level,
                    'pekerjaan_id' => $request->pekerjaan_id,
                    'is_exclusive_member' => $request->is_exclusive_member,
                ];
            }

            // Create notification record
            $notification = MemberAppsPushNotification::create([
                'title' => $title,
                'message' => $message,
                'notification_type' => 'general',
                'target_type' => $targetType === 'all' ? 'all' : ($targetType === 'selected' ? 'specific' : 'filter'),
                'target_member_ids' => $targetMemberIds,
                'target_filter_criteria' => $targetFilterCriteria,
                'data' => [
                    'type' => 'manual_notification',
                    'title' => $title,
                    'message' => $message,
                ],
                'created_by' => auth()->id(),
                'sent_at' => now(),
            ]);

            // Get member IDs
            $memberIds = $members->pluck('id')->toArray();
            $totalMembers = count($memberIds);

            // Determine batch size based on total members
            // For large batches (1000+), use queue jobs
            // For small batches (<1000), process directly
            $batchSize = 100; // Process 100 members per job
            $useQueue = $totalMembers >= 1000; // Use queue if 1000+ members

            if ($useQueue) {
                // Use Queue Jobs for large batches
                $memberBatches = array_chunk($memberIds, $batchSize);
                $totalJobs = count($memberBatches);

                Log::info('Dispatching notification jobs', [
                    'notification_id' => $notification->id,
                    'total_members' => $totalMembers,
                    'total_jobs' => $totalJobs,
                    'batch_size' => $batchSize,
                ]);

                // Dispatch jobs for each batch
                foreach ($memberBatches as $batch) {
                    SendMemberNotificationJob::dispatch(
                        $notification->id,
                        $batch,
                        $title,
                        $message,
                        [
                            'type' => 'manual_notification',
                            'title' => $title,
                            'message' => $message,
                        ]
                    )->onQueue('notifications'); // Use dedicated queue for notifications
                }

                $message = "Notifikasi sedang diproses di background untuk {$totalMembers} member. Proses akan berjalan secara bertahap melalui {$totalJobs} job. Silakan cek status di halaman detail notifikasi.";

                return redirect()->route('member-notification.show', $notification->id)
                    ->with('success', $message);

            } else {
                // Process directly for small batches (< 1000 members)
                $fcmService = app(FCMService::class);
                $totalSuccess = 0;
                $totalFailed = 0;
                $membersProcessed = [];

                // Process in smaller chunks to avoid memory issues
                $chunks = $members->chunk(50);

                foreach ($chunks as $chunk) {
                    foreach ($chunk as $member) {
                        // Get device tokens for this member
                        $deviceTokens = MemberAppsDeviceToken::where('member_id', $member->id)
                            ->where('is_active', true)
                            ->get();

                        if ($deviceTokens->isEmpty()) {
                            // Create recipient record with failed status
                            MemberAppsPushNotificationRecipient::create([
                                'notification_id' => $notification->id,
                                'member_id' => $member->id,
                                'status' => 'failed',
                                'error_message' => 'No active device token',
                            ]);
                            $totalFailed++;
                            continue;
                        }

                        // Use sendToMember like other controllers
                        try {
                            $result = $fcmService->sendToMember(
                                $member,
                                $title,
                                $message,
                                [
                                    'type' => 'manual_notification',
                                    'title' => $title,
                                    'message' => $message,
                                    'notification_id' => $notification->id,
                                ]
                            );

                            $memberSuccessCount = $result['success_count'] ?? 0;
                            $memberFailedCount = $result['failed_count'] ?? 0;

                            // Create recipient records for each device token
                            foreach ($deviceTokens as $deviceToken) {
                                $status = ($memberSuccessCount > 0) ? 'sent' : 'failed';
                                $errorMessage = ($memberSuccessCount > 0) ? null : 'FCM send failed';

                                MemberAppsPushNotificationRecipient::create([
                                    'notification_id' => $notification->id,
                                    'member_id' => $member->id,
                                    'device_token_id' => $deviceToken->id,
                                    'status' => $status,
                                    'error_message' => $errorMessage,
                                ]);
                            }

                            $totalSuccess += $memberSuccessCount;
                            $totalFailed += $memberFailedCount;

                            // Save to member_apps_notifications for read tracking
                            if ($memberSuccessCount > 0 && !in_array($member->id, $membersProcessed)) {
                                MemberAppsNotification::create([
                                    'member_id' => $member->id,
                                    'type' => 'manual_notification',
                                    'title' => $title,
                                    'message' => $message,
                                    'data' => [
                                        'type' => 'manual_notification',
                                        'notification_id' => $notification->id,
                                    ],
                                    'is_read' => false,
                                ]);
                                $membersProcessed[] = $member->id;
                            }
                        } catch (\Exception $e) {
                            // Mark all device tokens as failed
                            foreach ($deviceTokens as $deviceToken) {
                                MemberAppsPushNotificationRecipient::create([
                                    'notification_id' => $notification->id,
                                    'member_id' => $member->id,
                                    'device_token_id' => $deviceToken->id,
                                    'status' => 'failed',
                                    'error_message' => 'Exception: ' . $e->getMessage(),
                                ]);
                            }
                            $totalFailed += $deviceTokens->count();
                            
                            Log::error('Error sending notification to member', [
                                'member_id' => $member->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                // Update notification counts
                $notification->update([
                    'sent_count' => $totalSuccess,
                    'delivered_count' => $totalSuccess,
                ]);

                $message = "Notifikasi berhasil dikirim ke {$totalMembers} member. Berhasil: {$totalSuccess}, Gagal: {$totalFailed}";

                return redirect()->route('member-notification.index')
                    ->with('success', $message);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send member notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return back()->withErrors(['error' => 'Gagal mengirim notifikasi: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show notification detail with recipients
     */
    public function show($id)
    {
        $notification = MemberAppsPushNotification::with(['recipients.member', 'recipients.deviceToken'])
            ->findOrFail($id);

        // Get recipients with pagination
        $recipientsQuery = MemberAppsPushNotificationRecipient::with(['member', 'deviceToken'])
            ->where('notification_id', $id);

        // Filter by status
        if (request()->filled('status')) {
            $recipientsQuery->where('status', request()->status);
        }

        // Search
        if (request()->filled('search')) {
            $search = request()->search;
            $recipientsQuery->whereHas('member', function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('member_id', 'like', "%{$search}%");
            });
        }

        $perPage = request()->get('per_page', 20);
        $recipients = $recipientsQuery->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        // Get read status from member_apps_notifications for each recipient
        $recipientIds = $recipients->pluck('member_id')->unique()->toArray();
        $readNotifications = MemberAppsNotification::where('type', 'manual_notification')
            ->whereIn('member_id', $recipientIds)
            ->where('is_read', true)
            ->whereRaw('JSON_EXTRACT(data, "$.notification_id") = ?', [$id])
            ->get()
            ->keyBy('member_id');

        // Enrich recipients with read status
        $recipients->getCollection()->transform(function ($recipient) use ($readNotifications) {
            $readNotif = $readNotifications->get($recipient->member_id);
            if ($readNotif && $recipient->status !== 'failed') {
                $recipient->is_read = true;
                $recipient->read_at = $readNotif->read_at;
                // Update status to opened if read
                if ($recipient->status === 'sent' || $recipient->status === 'delivered') {
                    $recipient->status = 'opened';
                    $recipient->opened_at = $readNotif->read_at;
                }
            } else {
                $recipient->is_read = false;
                $recipient->read_at = null;
            }
            return $recipient;
        });

        // Stats - include read status from member_apps_notifications
        $stats = [
            'total_recipients' => MemberAppsPushNotificationRecipient::where('notification_id', $id)->count(),
            'sent_count' => MemberAppsPushNotificationRecipient::where('notification_id', $id)
                ->whereIn('status', ['sent', 'delivered', 'opened'])->count(),
            'delivered_count' => MemberAppsPushNotificationRecipient::where('notification_id', $id)
                ->whereIn('status', ['delivered', 'opened'])->count(),
            'opened_count' => MemberAppsNotification::where('type', 'manual_notification')
                ->whereRaw('JSON_EXTRACT(data, "$.notification_id") = ?', [$id])
                ->where('is_read', true)
                ->count(),
            'failed_count' => MemberAppsPushNotificationRecipient::where('notification_id', $id)
                ->where('status', 'failed')->count(),
        ];

        return Inertia::render('MemberNotification/Show', [
            'notification' => $notification,
            'recipients' => $recipients,
            'stats' => $stats,
            'filters' => request()->only(['status', 'search', 'per_page']),
        ]);
    }
}

