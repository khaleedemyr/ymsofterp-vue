<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class SupportAdminController extends Controller
{
    public function index(Request $request)
    {
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

        $openConversationId = (int) $request->get('conversation', 0);
        $mentioned = $openConversationId > 0 && $this->userWasMentioned($userId, $openConversationId);

        if (! $hasPermission && ! $mentioned) {
            abort(403, 'Unauthorized access to support admin panel');
        }

        return Inertia::render('Support/AdminPanel', [
            'openConversationId' => $openConversationId ?: null,
            'mentionOnly' => ! $hasPermission && $mentioned,
        ]);
    }

    private function userWasMentioned($userId, $conversationId): bool
    {
        $userId = (int) $userId;
        $conversationId = (int) $conversationId;
        if ($userId <= 0 || $conversationId <= 0) {
            return false;
        }

        if (Schema::hasColumn('support_messages', 'mentioned_user_ids')) {
            $rows = DB::table('support_messages')
                ->where('conversation_id', $conversationId)
                ->whereNotNull('mentioned_user_ids')
                ->pluck('mentioned_user_ids');

            foreach ($rows as $raw) {
                $ids = is_array($raw) ? $raw : json_decode((string) $raw, true);
                if (! is_array($ids)) {
                    continue;
                }
                if (in_array($userId, array_map('intval', $ids), true)) {
                    return true;
                }
            }
        }

        return DB::table('notifications')
            ->where('user_id', $userId)
            ->where('type', 'live_support_mention')
            ->where('task_id', $conversationId)
            ->exists();
    }
}
