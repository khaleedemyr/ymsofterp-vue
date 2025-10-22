<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LiveSupportController extends Controller
{
    // Get user's conversations
    public function getUserConversations()
    {
        try {
            $userId = auth()->id();
            
            $conversations = DB::table('support_conversations as sc')
                ->leftJoin('support_messages as sm', function($join) {
                    $join->on('sc.id', '=', 'sm.conversation_id')
                         ->whereRaw('sm.id = (SELECT MAX(id) FROM support_messages WHERE conversation_id = sc.id)');
                })
                ->leftJoin('users as u', 'sm.sender_id', '=', 'u.id')
                ->where('sc.user_id', $userId)
                ->select(
                    'sc.id',
                    'sc.subject',
                    'sc.status',
                    'sc.priority',
                    'sc.created_at',
                    'sc.updated_at',
                    'sm.message as last_message',
                    'sm.created_at as last_message_at',
                    'u.nama_lengkap as last_sender_name',
                    'sm.sender_type as last_sender_type',
                    DB::raw('(SELECT COUNT(*) FROM support_messages WHERE conversation_id = sc.id AND sender_type = "admin" AND is_read = FALSE) as unread_count')
                )
                ->orderBy('sc.updated_at', 'desc')
                ->get();


            return response()->json($conversations);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal mengambil data percakapan'], 500);
        }
    }

    // Get messages for a conversation
    public function getConversationMessages($conversationId)
    {
        try {
            // Verify conversation exists (no user restriction since admin panel already has permission check)
            $conversation = DB::table('support_conversations')
                ->where('id', $conversationId)
                ->first();

            if (!$conversation) {
                return response()->json(['error' => 'Percakapan tidak ditemukan'], 404);
            }

            $messages = DB::table('support_messages as sm')
                ->leftJoin('users as u', 'sm.sender_id', '=', 'u.id')
                ->where('sm.conversation_id', $conversationId)
                ->select(
                    'sm.id',
                    'sm.message',
                    'sm.message_type',
                    'sm.file_path',
                    'sm.file_name',
                    'sm.file_size',
                    'sm.sender_type',
                    'sm.created_at',
                    'u.nama_lengkap as sender_name',
                    'u.avatar as sender_avatar'
                )
                ->orderBy('sm.created_at', 'asc')
                ->get();

            // Mark admin messages as read
            $markedRead = DB::table('support_messages')
                ->where('conversation_id', $conversationId)
                ->where('sender_type', 'admin')
                ->update(['is_read' => true]);


            return response()->json($messages);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal mengambil data pesan'], 500);
        }
    }

    // Mark messages as read
    public function markMessagesAsRead($conversationId)
    {
        try {
            $userId = auth()->id();
            
            // Verify conversation belongs to user
            $conversation = DB::table('support_conversations')
                ->where('id', $conversationId)
                ->where('user_id', $userId)
                ->first();

            if (!$conversation) {
                return response()->json(['error' => 'Percakapan tidak ditemukan'], 404);
            }

            // Get count of admin messages before update
            $beforeCount = DB::table('support_messages')
                ->where('conversation_id', $conversationId)
                ->where('sender_type', 'admin')
                ->where('is_read', false)
                ->count();

            // Mark all admin messages as read
            $updated = DB::table('support_messages')
                ->where('conversation_id', $conversationId)
                ->where('sender_type', 'admin')
                ->update(['is_read' => true]);


            return response()->json(['success' => true, 'updated' => $updated]);
        } catch (\Exception $e) {
            \Log::error('Error marking messages as read', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Gagal menandai pesan sebagai dibaca'], 500);
        }
    }

    // Create new conversation
    public function createConversation(Request $request)
    {
        try {
            $request->validate([
                'subject' => 'required|string|max:255',
                'message' => 'nullable|string',
                'priority' => 'nullable|in:low,medium,high,urgent',
                'files.*' => 'nullable|file|max:10240' // 10MB max per file
            ]);

            $userId = auth()->id();

            // Create conversation
            $conversationId = DB::table('support_conversations')->insertGetId([
                'user_id' => $userId,
                'subject' => $request->subject,
                'priority' => $request->priority ?? 'medium',
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Handle file uploads for first message
            $messageType = 'text';
            $filePath = null;
            $fileName = null;
            $uploadedFiles = [];
            
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $filePath = $file->storeAs('support', $filename, 'public');
                    
                    $uploadedFiles[] = [
                        'original_name' => $originalName,
                        'file_path' => $filePath,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType()
                    ];
                }
                
                if (!empty($uploadedFiles)) {
                    $filePath = json_encode($uploadedFiles);
                    $fileName = count($uploadedFiles) . ' file(s)';
                    $messageType = 'file';
                }
            }

            // Create first message
            DB::table('support_messages')->insert([
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'sender_type' => 'user',
                'message' => $request->message ?: ($messageType === 'file' ? 'File attachment' : ''),
                'message_type' => $messageType,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'created_at' => now()
            ]);

            // Get the created conversation with last message
            $conversation = $this->getConversationWithLastMessage($conversationId);

            // Send notifications to users with division_id=21 and status='A'
            $this->sendConversationNotifications($conversationId, $request->subject, $userId);

            return response()->json($conversation, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal membuat percakapan'], 500);
        }
    }

    // Send message to conversation
    public function sendMessage(Request $request, $conversationId)
    {
        try {
            $request->validate([
                'message' => 'nullable|string',
                'message_type' => 'nullable|in:text,image,file',
                'file' => 'nullable|file|max:10240', // 10MB max
                'files.*' => 'nullable|file|max:10240' // Multiple files support
            ]);

            $userId = auth()->id();

            // Verify user owns this conversation
            $conversation = DB::table('support_conversations')
                ->where('id', $conversationId)
                ->where('user_id', $userId)
                ->first();

            if (!$conversation) {
                return response()->json(['error' => 'Percakapan tidak ditemukan'], 404);
            }

            // Check if conversation is closed
            if ($conversation->status === 'closed') {
                return response()->json([
                    'error' => 'Percakapan ini telah ditutup oleh tim support. Silakan buat percakapan baru jika Anda memerlukan bantuan lebih lanjut.',
                    'conversation_closed' => true,
                    'status' => 'closed'
                ], 403);
            }

            $messageData = [
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'sender_type' => 'user',
                'message' => $request->message,
                'message_type' => $request->message_type ?? 'text',
                'created_at' => now()
            ];

            // Handle file uploads (single or multiple)
            $uploadedFiles = [];
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $originalName = $file->getClientOriginalName();
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('support', $filename, 'public');
                
                $uploadedFiles[] = [
                    'original_name' => $originalName,
                    'file_path' => $filePath,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType()
                ];
            }
            
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $filePath = $file->storeAs('support', $filename, 'public');
                    
                    $uploadedFiles[] = [
                        'original_name' => $originalName,
                        'file_path' => $filePath,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType()
                    ];
                }
            }
            
            if (!empty($uploadedFiles)) {
                $messageData['file_path'] = json_encode($uploadedFiles);
                $messageData['file_name'] = count($uploadedFiles) . ' file(s)';
                $messageData['message_type'] = 'file';
                
                // If no message text, set default
                if (empty($messageData['message'])) {
                    $messageData['message'] = 'File attachment';
                }
            }

            $messageId = DB::table('support_messages')->insertGetId($messageData);

            // Update conversation timestamp
            DB::table('support_conversations')
                ->where('id', $conversationId)
                ->update(['updated_at' => now()]);

            // Send notification to support team for new chat message
            $this->sendChatMessageNotifications($conversationId, $messageData['message'], $userId);

            // Get the created message
            $message = DB::table('support_messages as sm')
                ->leftJoin('users as u', 'sm.sender_id', '=', 'u.id')
                ->where('sm.id', $messageId)
                ->select(
                    'sm.id',
                    'sm.message',
                    'sm.message_type',
                    'sm.file_path',
                    'sm.file_name',
                    'sm.file_size',
                    'sm.sender_type',
                    'sm.created_at',
                    'u.nama_lengkap as sender_name',
                    'u.avatar as sender_avatar'
                )
                ->first();

            return response()->json($message, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal mengirim pesan'], 500);
        }
    }

    // Serve support attachment files
    public function serveAttachment($conversationId, $messageId, $fileIndex = 0)
    {
        try {
            // Verify conversation exists (no user restriction since admin panel already has permission check)
            $conversation = DB::table('support_conversations')
                ->where('id', $conversationId)
                ->first();
                
            if (!$conversation) {
                abort(404, 'Conversation not found');
            }
            
            // Get message with file info
            $message = DB::table('support_messages')
                ->where('id', $messageId)
                ->where('conversation_id', $conversationId)
                ->first();
                
            if (!$message || !$message->file_path) {
                abort(404, 'File tidak ditemukan');
            }
            
            $files = json_decode($message->file_path, true);
            
            if (!is_array($files) || !isset($files[$fileIndex])) {
                abort(404, 'File tidak ditemukan');
            }
            
            $file = $files[$fileIndex];
            $filePath = storage_path('app/public/' . $file['file_path']);
            
            if (!file_exists($filePath)) {
                abort(404, 'File tidak ditemukan di disk');
            }
            
            return response()->file($filePath, [
                'Content-Disposition' => 'inline; filename="' . $file['original_name'] . '"',
                'Content-Type' => $file['mime_type']
            ]);
            
        } catch (\Exception $e) {
            abort(404, 'File not found');
        }
    }

    // Admin: Get all conversations
    public function getAllConversations(Request $request)
    {
        try {
            // Check if user has support admin panel view permission using the same system as HandleInertiaRequests
            $userId = auth()->id();
            $hasPermission = DB::table('users as u')
                ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
                ->join('erp_role as r', 'ur.role_id', '=', 'r.id')
                ->join('erp_role_permission as rp', 'rp.role_id', '=', 'r.id')
                ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
                ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
                ->where('u.id', $userId)
                ->where('m.code', 'support_admin_panel')
                ->where('p.action', 'view')
                ->exists();

            if (!$hasPermission) {
                return response()->json(['error' => 'Tidak memiliki izin'], 403);
            }

            $status = $request->get('status', 'all');
            $priority = $request->get('priority', 'all');
            $search = $request->get('search', '');
            $dateFrom = $request->get('date_from', '');
            $dateTo = $request->get('date_to', '');
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            $query = DB::table('support_conversations as sc')
                ->leftJoin('support_messages as sm', function($join) {
                    $join->on('sc.id', '=', 'sm.conversation_id')
                         ->whereRaw('sm.id = (SELECT MAX(id) FROM support_messages WHERE conversation_id = sc.id)');
                })
                ->leftJoin('users as u', 'sm.sender_id', '=', 'u.id')
                ->leftJoin('users as cu', 'sc.user_id', '=', 'cu.id')
                ->leftJoin('tbl_data_outlet as o', 'cu.id_outlet', '=', 'o.id_outlet')
                ->leftJoin('tbl_data_divisi as d', 'cu.division_id', '=', 'd.id')
                ->leftJoin('tbl_data_jabatan as j', 'cu.id_jabatan', '=', 'j.id_jabatan')
                ->select(
                    'sc.id',
                    'sc.subject',
                    'sc.status',
                    'sc.priority',
                    'sc.created_at',
                    'sc.updated_at',
                    'sm.message as last_message',
                    'sm.created_at as last_message_at',
                    'u.nama_lengkap as last_sender_name',
                    'sm.sender_type as last_sender_type',
                    'cu.nama_lengkap as customer_name',
                    'cu.email as customer_email',
                    'o.nama_outlet as customer_outlet',
                    'd.nama_divisi as customer_divisi',
                    'j.nama_jabatan as customer_jabatan',
                    DB::raw('(SELECT COUNT(*) FROM support_messages WHERE conversation_id = sc.id AND sender_type = "user" AND is_read = FALSE) as unread_count')
                );

            // Status filter
            if ($status !== 'all') {
                $query->where('sc.status', $status);
            }

            // Priority filter
            if ($priority !== 'all') {
                $query->where('sc.priority', $priority);
            }

            // Date range filter
            if ($dateFrom) {
                $query->whereDate('sc.created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('sc.created_at', '<=', $dateTo);
            }

            // Smart search - search across multiple fields
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('sc.subject', 'like', "%{$search}%")
                      ->orWhere('cu.nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('cu.email', 'like', "%{$search}%")
                      ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                      ->orWhere('d.nama_divisi', 'like', "%{$search}%")
                      ->orWhere('j.nama_jabatan', 'like', "%{$search}%")
                      ->orWhere('sm.message', 'like', "%{$search}%")
                      ->orWhere('u.nama_lengkap', 'like', "%{$search}%");
                });
            }

            // Get total count for pagination
            $total = $query->count();

            // Apply pagination
            $conversations = $query->orderBy('sc.updated_at', 'desc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            return response()->json([
                'data' => $conversations,
                'pagination' => [
                    'current_page' => (int) $page,
                    'per_page' => (int) $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                    'to' => min($page * $perPage, $total)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal mengambil data percakapan'], 500);
        }
    }

    // Admin: Reply to conversation
    public function adminReply(Request $request, $conversationId)
    {
        try {
            // Check if user has support admin panel create permission using the same system as HandleInertiaRequests
            $userId = auth()->id();
            $hasPermission = DB::table('users as u')
                ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
                ->join('erp_role as r', 'ur.role_id', '=', 'r.id')
                ->join('erp_role_permission as rp', 'rp.role_id', '=', 'r.id')
                ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
                ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
                ->where('u.id', $userId)
                ->where('m.code', 'support_admin_panel')
                ->where('p.action', 'create')
                ->exists();

            if (!$hasPermission) {
                return response()->json(['error' => 'Tidak memiliki izin'], 403);
            }

            $request->validate([
                'message' => 'required|string',
                'files.*' => 'nullable|file|max:10240'
            ]);

            $userId = auth()->id();

            // Verify conversation exists
            $conversation = DB::table('support_conversations')
                ->where('id', $conversationId)
                ->first();

            if (!$conversation) {
                return response()->json(['error' => 'Percakapan tidak ditemukan'], 404);
            }

            // If conversation is closed, admin can reply and it will be reopened
            $wasClosed = $conversation->status === 'closed';

            $messageData = [
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'sender_type' => 'admin',
                'message' => $request->message,
                'message_type' => 'text',
                'created_at' => now()
            ];

            // Handle multiple file uploads
            if ($request->hasFile('files')) {
                $files = $request->file('files');
                $fileData = [];
                
                foreach ($files as $file) {
                    $originalName = $file->getClientOriginalName();
                    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $filePath = $file->storeAs('support', $filename, 'public');
                    
                    $fileData[] = [
                        'original_name' => $originalName,
                        'file_path' => $filePath,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType()
                    ];
                }
                
                $messageData['file_path'] = json_encode($fileData);
                $messageData['message_type'] = 'file';
            }

            $messageId = DB::table('support_messages')->insertGetId($messageData);

            // Update conversation timestamp and status
            DB::table('support_conversations')
                ->where('id', $conversationId)
                ->update([
                    'updated_at' => now(),
                    'status' => 'open'
                ]);

            // Mark the new admin message as unread for the user
            DB::table('support_messages')
                ->where('id', $messageId)
                ->update(['is_read' => false]);

            // Log if conversation was reopened
            if ($wasClosed) {
                \Log::info('Support conversation reopened by admin', [
                    'conversation_id' => $conversationId,
                    'admin_id' => $userId,
                    'previous_status' => 'closed',
                    'new_status' => 'open'
                ]);
            }

            // Send notification to support team for new chat message
            $this->sendChatMessageNotifications($conversationId, $messageData['message'], $userId);

            // Get the created message
            $message = DB::table('support_messages as sm')
                ->leftJoin('users as u', 'sm.sender_id', '=', 'u.id')
                ->where('sm.id', $messageId)
                ->select(
                    'sm.id',
                    'sm.message',
                    'sm.message_type',
                    'sm.file_path',
                    'sm.created_at',
                    'sm.sender_type',
                    'u.nama_lengkap as sender_name',
                    'u.avatar as sender_avatar'
                )
                ->first();

            return response()->json($message, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal mengirim balasan'], 500);
        }
    }

    // Admin: Update conversation status
    public function updateConversationStatus(Request $request, $conversationId)
    {
        try {
            // Check if user has support admin panel update permission using the same system as HandleInertiaRequests
            $userId = auth()->id();
            $hasPermission = DB::table('users as u')
                ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
                ->join('erp_role as r', 'ur.role_id', '=', 'r.id')
                ->join('erp_role_permission as rp', 'rp.role_id', '=', 'r.id')
                ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
                ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
                ->where('u.id', $userId)
                ->where('m.code', 'support_admin_panel')
                ->where('p.action', 'update')
                ->exists();

            if (!$hasPermission) {
                return response()->json(['error' => 'Tidak memiliki izin'], 403);
            }

            $request->validate([
                'status' => 'required|in:open,closed,pending',
                'priority' => 'nullable|in:low,medium,high,urgent'
            ]);

            $updateData = [
                'status' => $request->status,
                'updated_at' => now()
            ];

            if ($request->has('priority')) {
                $updateData['priority'] = $request->priority;
            }

            $updated = DB::table('support_conversations')
                ->where('id', $conversationId)
                ->update($updateData);

            if ($updated) {
                return response()->json(['message' => 'Status berhasil diperbarui']);
            } else {
                return response()->json(['error' => 'Percakapan tidak ditemukan'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal memperbarui status'], 500);
        }
    }

    // Helper method to get conversation with last message
    private function getConversationWithLastMessage($conversationId)
    {
        return DB::table('support_conversations as sc')
            ->leftJoin('support_messages as sm', function($join) {
                $join->on('sc.id', '=', 'sm.conversation_id')
                     ->whereRaw('sm.id = (SELECT MAX(id) FROM support_messages WHERE conversation_id = sc.id)');
            })
            ->leftJoin('users as u', 'sm.sender_id', '=', 'u.id')
            ->where('sc.id', $conversationId)
            ->select(
                'sc.id',
                'sc.subject',
                'sc.status',
                'sc.priority',
                'sc.created_at',
                'sc.updated_at',
                'sm.message as last_message',
                'sm.created_at as last_message_at',
                'u.nama_lengkap as last_sender_name',
                'sm.sender_type as last_sender_type'
            )
            ->first();
    }

    // Send notifications to support team (division_id=21)
    private function sendConversationNotifications($conversationId, $subject, $userId)
    {
        try {
            // Get users with division_id=21 and status='A'
            $supportUsers = DB::table('users')
                ->where('division_id', 21)
                ->where('status', 'A')
                ->pluck('id');

            if ($supportUsers->isEmpty()) {
                \Log::info('No support users found for notifications', [
                    'conversation_id' => $conversationId,
                    'division_id' => 21
                ]);
                return;
            }

            // Get user details who created the conversation
            $user = DB::table('users as u')
                ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
                ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->where('u.id', $userId)
                ->select(
                    'u.nama_lengkap',
                    'u.email',
                    'o.nama_outlet',
                    'd.nama_divisi',
                    'j.nama_jabatan'
                )
                ->first();

            // Create notification message
            $message = "Live Support: Percakapan baru telah dibuat\n\n";
            $message .= "Subjek: {$subject}\n";
            $message .= "Dari: {$user->nama_lengkap}\n";
            $message .= "Email: {$user->email}\n";
            if ($user->nama_outlet) {
                $message .= "Outlet: {$user->nama_outlet}\n";
            }
            if ($user->nama_divisi) {
                $message .= "Divisi: {$user->nama_divisi}\n";
            }
            if ($user->nama_jabatan) {
                $message .= "Jabatan: {$user->nama_jabatan}\n";
            }
            $message .= "\nSilakan segera tanggapi percakapan ini melalui Live Support Admin Panel.";

            // Send notification to each support user
            foreach ($supportUsers as $supportUserId) {
                DB::table('notifications')->insert([
                    'user_id' => $supportUserId,
                    'task_id' => $conversationId, // Using task_id field to store conversation_id
                    'type' => 'live_support_conversation',
                    'message' => $message,
                    'url' => config('app.url') . '/support/admin',
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            \Log::info('Live Support notifications sent successfully', [
                'conversation_id' => $conversationId,
                'subject' => $subject,
                'user_id' => $userId,
                'support_users_count' => $supportUsers->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error sending Live Support notifications', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    // Send notifications to support team for new chat messages
    private function sendChatMessageNotifications($conversationId, $message, $userId)
    {
        try {
            // Get users with division_id=21 and status='A'
            $supportUsers = DB::table('users')
                ->where('division_id', 21)
                ->where('status', 'A')
                ->pluck('id');

            if ($supportUsers->isEmpty()) {
                \Log::warning('No support users found for chat message notification', [
                    'conversation_id' => $conversationId,
                    'division_id' => 21
                ]);
                return;
            }

            // Get user details who sent the message
            $user = DB::table('users as u')
                ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
                ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->where('u.id', $userId)
                ->select(
                    'u.nama_lengkap',
                    'u.email',
                    'o.nama_outlet',
                    'd.nama_divisi',
                    'j.nama_jabatan'
                )
                ->first();

            // Get conversation subject
            $conversation = DB::table('support_conversations')
                ->where('id', $conversationId)
                ->select('subject')
                ->first();

            // Create notification message
            $notificationMessage = "Live Support: Chat baru diterima\n\n";
            $notificationMessage .= "Percakapan: " . ($conversation->subject ?? 'Tanpa subjek') . "\n";
            $notificationMessage .= "Dari: {$user->nama_lengkap}\n";
            $notificationMessage .= "Email: {$user->email}\n";
            if ($user->nama_outlet) {
                $notificationMessage .= "Outlet: {$user->nama_outlet}\n";
            }
            if ($user->nama_divisi) {
                $notificationMessage .= "Divisi: {$user->nama_divisi}\n";
            }
            if ($user->nama_jabatan) {
                $notificationMessage .= "Jabatan: {$user->nama_jabatan}\n";
            }
            $notificationMessage .= "\nPesan: " . (strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message);
            $notificationMessage .= "\n\nSilakan segera tanggapi chat ini melalui Live Support Admin Panel.";

            // Send notification to each support user
            foreach ($supportUsers as $supportUserId) {
                DB::table('notifications')->insert([
                    'user_id' => $supportUserId,
                    'task_id' => $conversationId, // Using task_id field to store conversation_id
                    'type' => 'live_support_chat',
                    'message' => $notificationMessage,
                    'url' => config('app.url') . '/support/admin',
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            \Log::info('Live Support chat notifications sent successfully', [
                'conversation_id' => $conversationId,
                'message_preview' => substr($message, 0, 50) . '...',
                'user_id' => $userId,
                'support_users_count' => $supportUsers->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error sending Live Support chat notifications', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
