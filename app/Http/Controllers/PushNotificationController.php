<?php

namespace App\Http\Controllers;

use App\Models\PushNotification;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PushNotificationController extends Controller
{
    /**
     * Display a listing of push notifications
     */
    public function index(Request $request)
    {
        // Build query for push notifications with count of sent devices
        $query = DB::connection('mysql_second')
            ->table('pushnotification as pn')
            ->select('pn.*', DB::raw('COUNT(pps.id) AS sended_devices'))
            ->leftJoin('pushnotification_process_send as pps', function ($join) {
                $join->on('pps.id_pushnotification', '=', 'pn.id')
                    ->where('pps.status_send', '=', '1');
            })
            ->groupBy('pn.id');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('pn.title', 'like', "%{$search}%")
                  ->orWhere('pn.body', 'like', "%{$search}%")
                  ->orWhere('pn.target', 'like', "%{$search}%")
                  ->orWhere('pn.id', 'like', "%{$search}%");
            });
        }

        // Filter by status
        // status_send is enum('0','1','2') as string
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'sent') {
                $query->where('pn.status_send', '=', '1');
            } elseif ($status === 'pending') {
                $query->where('pn.status_send', '=', '0');
            } elseif ($status === 'processing') {
                $query->where('pn.status_send', '=', '2');
            }
        }

        // Filter by target type
        if ($request->filled('target_type')) {
            $targetType = $request->target_type;
            if ($targetType === 'all') {
                $query->where('pn.target', '=', 'all');
            } elseif ($targetType === 'specific') {
                $query->where('pn.target', '!=', 'all')
                      ->where('pn.target', '!=', '');
            }
        }

        // Sort
        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'desc');
        $query->orderBy('pn.' . $sort, $direction);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $notifications = $query->paginate($perPage)->withQueryString();

        // Get total devices (customers with firebase token)
        $totalDevices = DB::connection('mysql_second')
            ->table('costumers')
            ->where('firebase_token_device', '!=', '')
            ->whereNotNull('firebase_token_device')
            ->count();

        // Get statistics
        // status_send is enum('0','1','2') as string
        $stats = [
            'total_notifications' => DB::connection('mysql_second')->table('pushnotification')->count(),
            'sent_notifications' => DB::connection('mysql_second')->table('pushnotification')->where('status_send', '1')->count(),
            'pending_notifications' => DB::connection('mysql_second')->table('pushnotification')->where('status_send', '0')->count(),
        ];

        return Inertia::render('PushNotification/Index', [
            'notifications' => $notifications,
            'totalDevices' => $totalDevices,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'target_type', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new push notification
     */
    public function create()
    {
        return Inertia::render('PushNotification/Create');
    }

    /**
     * Store a newly created push notification
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'txt_title' => 'required|string|max:255',
            'txt_body' => 'required|string',
            'txt_target' => 'required',
            'file_foto' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::connection('mysql_second')->beginTransaction();

            // Create notification record using Eloquent Model (same as MemberController)
            // Clean body text to remove special characters that might cause encoding issues
            $cleanBody = $request->txt_body;
            // Remove RTL marks and other invisible characters
            $cleanBody = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{FEFF}]/u', '', $cleanBody);
            // Ensure UTF-8 encoding
            $cleanBody = mb_convert_encoding($cleanBody, 'UTF-8', 'UTF-8');
            // Remove any remaining invalid UTF-8 characters
            $cleanBody = mb_convert_encoding($cleanBody, 'UTF-8', 'UTF-8');
            
            // Clean title too
            $cleanTitle = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{FEFF}]/u', '', $request->txt_title);
            $cleanTitle = mb_convert_encoding($cleanTitle, 'UTF-8', 'UTF-8');
            
            // status_send is enum('0','1','2') as string
            // First insert always use '0'
            // Format target for storage
            // If array, convert to comma-separated string
            $targetValue = $request->txt_target;
            if (is_array($targetValue)) {
                $targetString = implode(',', $targetValue);
            } else {
                $targetString = $targetValue;
            }
            
            $notificationData = [
                'title' => $cleanTitle,
                'body' => $cleanBody,
                'target' => $targetString,
                'status_send' => '0', // Always '0' (string) for first insert
            ];

            $notification = PushNotification::create($notificationData);
            $notificationId = $notification->id;

            if (!$notificationId || $notificationId <= 0) {
                throw new \Exception('Gagal membuat record notifikasi: ID tidak valid');
            }

            Log::info('Notification record created', [
                'notification_id' => $notificationId,
                'title' => $request->txt_title
            ]);

            // Handle file upload
            $fileName = null;
            if ($request->hasFile('file_foto')) {
                $file = $request->file('file_foto');
                $extension = $file->getClientOriginalExtension();
                $fileName = $notificationId . '.' . $extension;
                
                // Ensure directory exists
                $uploadPath = public_path('assets/file_photo_notification');
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                // Save to public storage
                $file->move($uploadPath, $fileName);
                
                // Update notification with photo filename
                $notification->photo = $fileName;
                $notification->save();
            }

            // Process targets
            // txt_target can be:
            // 1. String "all" - send to all members
            // 2. Comma-separated emails - send to specific members
            // 3. Array of emails (from multiselect)
            $targets = [];
            $targetValue = $request->txt_target;
            
            // Check if it's "all"
            if (is_string($targetValue) && strtolower(trim($targetValue)) === 'all') {
                // Get all customers with firebase token
                $customers = DB::connection('mysql_second')
                    ->table('costumers')
                    ->where('firebase_token_device', '!=', '')
                    ->whereNotNull('firebase_token_device')
                    ->get();
                
                foreach ($customers as $customer) {
                    $targets[] = [
                        'email_member' => $customer->email ?? '',
                        'token' => $customer->firebase_token_device,
                        'id_pushnotification' => $notificationId,
                    ];
                }
            } else {
                // Handle array or comma-separated string
                $emails = [];
                if (is_array($targetValue)) {
                    $emails = $targetValue;
                } else {
                    $emails = array_filter(array_map('trim', explode(",", $targetValue)));
                }
                
                foreach ($emails as $email) {
                    if (empty($email)) {
                        continue;
                    }
                    
                    // Get customer by email
                    $customer = DB::connection('mysql_second')
                        ->table('costumers')
                        ->where('email', $email)
                        ->first();
                    
                    if ($customer && $customer->firebase_token_device) {
                        $targets[] = [
                            'email_member' => $email,
                            'token' => $customer->firebase_token_device,
                            'id_pushnotification' => $notificationId,
                        ];
                    }
                }
            }

            // Insert targets
            if (!empty($targets)) {
                // Insert in chunks to avoid memory issues with large datasets
                $chunks = array_chunk($targets, 500);
                foreach ($chunks as $chunk) {
                    DB::connection('mysql_second')
                        ->table('pushnotification_target')
                        ->insert($chunk);
                }
            }

            // Commit transaction
            DB::connection('mysql_second')->commit();

            // Verify final state (after commit, outside transaction)
            $finalNotification = PushNotification::find($notificationId);
            if (!$finalNotification) {
                Log::error('Notification not found after commit', [
                    'notification_id' => $notificationId
                ]);
                throw new \Exception('Notifikasi tidak ditemukan setelah commit transaction');
            }

            Log::info('Push notification created successfully', [
                'notification_id' => $notificationId,
                'title' => $request->txt_title,
                'targets_count' => count($targets),
                'final_status' => $finalNotification->status_send,
                'has_photo' => !empty($finalNotification->photo)
            ]);

            return redirect()->route('push-notification.index')
                ->with('success', 'Push notification berhasil dibuat!');

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            
            Log::error('Failed to create push notification', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['file_foto'])
            ]);
            
            return back()->withErrors(['error' => 'Gagal membuat push notification: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Send push notification via FCM
     */
    public function sendPushNotification($data)
    {
        $token = $data['token'];
        $title = $data['title'];
        $body = $data['body'];
        $foto = $data['foto'] ?? null;

        // FCM endpoint
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        // Prepare notification data
        $notificationData = [
            "to" => $token,
            "notification" => [
                "title" => $title,
                "body" => $body
            ],
            "data" => [
                "photo" => $foto ? asset('assets/file_photo_notification/' . $foto) : null
            ]
        ];

        $jsonData = json_encode($notificationData);
        
        // FCM Server Key (should be in .env file)
        $serverKey = env('FCM_SERVER_KEY', 'AAAAslzPpRc:APA91bEHothpRmZG8xt9mkS_mqMD8dRJhxAwGnv-7eLudDdfydMBo12cw31GEFYQN7c0tsGbi22Wa3gqObbE17pBmTDXpmxUwtkdN7hqEkpgLxgVCFKkdH--RcpfiN3E1LyXCr1LHRSc');
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key=' . $serverKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode === 200,
            'response' => json_decode($response, true),
            'http_code' => $httpCode
        ];
    }

    /**
     * Send notification to all targets
     */
    public function send($id)
    {
        try {
            $notification = DB::connection('mysql_second')
                ->table('pushnotification')
                ->where('id', $id)
                ->first();

            if (!$notification) {
                return back()->withErrors(['error' => 'Notification tidak ditemukan']);
            }

            // Get all targets
            $targets = DB::connection('mysql_second')
                ->table('pushnotification_target')
                ->where('id_pushnotification', $id)
                ->get();

            $successCount = 0;
            $failCount = 0;

            foreach ($targets as $target) {
                $dataPush = [
                    'token' => $target->token,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'foto' => $notification->photo,
                ];

                $result = $this->sendPushNotification($dataPush);

                // Log process send
                DB::connection('mysql_second')
                    ->table('pushnotification_process_send')
                    ->insert([
                        'id_pushnotification' => $id,
                        'status_send' => $result['success'] ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }

            // Update notification status
            // status_send is enum('0','1','2') as string
            DB::connection('mysql_second')
                ->table('pushnotification')
                ->where('id', $id)
                ->update([
                    'status_send' => '1', // Use string '1' for enum
                ]);

            return back()->with('success', "Notifikasi terkirim: {$successCount} berhasil, {$failCount} gagal");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengirim notifikasi: ' . $e->getMessage()]);
        }
    }

    /**
     * Test push notification
     */
    public function test(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'title' => 'required|string',
            'body' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $dataPush = [
            'token' => $request->token,
            'title' => $request->title,
            'body' => $request->body,
            'foto' => null,
        ];

        $result = $this->sendPushNotification($dataPush);

        return response()->json([
            'message' => 'Test notification sent',
            'result' => $result
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

        $members = DB::connection('mysql_second')
            ->table('costumers')
            ->select('id', 'name', 'email', 'telepon', 'costumers_id')
            ->where('status_aktif', '1')
            ->where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('telepon', 'like', "%{$search}%")
                      ->orWhere('costumers_id', 'like', "%{$search}%");
            })
            ->whereNotNull('firebase_token_device')
            ->where('firebase_token_device', '!=', '')
            ->limit(20)
            ->get()
            ->map(function($member) {
                return [
                    'id' => $member->id,
                    'email' => $member->email ?? '',
                    'name' => $member->name ?? '',
                    'telepon' => $member->telepon ?? '',
                    'costumers_id' => $member->costumers_id ?? '',
                    'label' => ($member->name ?? '') . ' (' . ($member->email ?? '') . ')',
                ];
            });

        return response()->json($members);
    }
}

