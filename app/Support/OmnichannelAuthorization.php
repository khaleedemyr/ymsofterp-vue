<?php

namespace App\Support;

use App\Models\OmniConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OmnichannelAuthorization
{
    public static function userHasPermission(int $userId, string $permissionCode): bool
    {
        $isAdmin = DB::table('users')->where('id', $userId)->value('is_admin');
        if ($isAdmin === true || (int) $isAdmin === 1) {
            return true;
        }

        return DB::table('erp_user_role as ur')
            ->join('erp_role_permission as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
            ->where('ur.user_id', $userId)
            ->where('p.code', $permissionCode)
            ->exists();
    }

    public static function canViewInbox(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return self::userHasPermission($user->id, 'omnichannel_inbox_view');
    }

    public static function canSeeAllChats(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return self::userHasPermission($user->id, 'omnichannel_inbox_see_all');
    }

    /**
     * @return list<int>
     */
    public static function teamIdsForUser(int $userId): array
    {
        return DB::table('omni_team_user')
            ->where('user_id', $userId)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public static function userCanAccessConversation(User $user, OmniConversation $conversation, bool $canSeeAll): bool
    {
        if ($canSeeAll) {
            return true;
        }

        $uid = (int) $user->id;

        if ((int) $conversation->assigned_user_id === $uid) {
            return true;
        }

        if ($conversation->assignees()->where('users.id', $uid)->exists()) {
            return true;
        }

        $teamIds = self::teamIdsForUser($uid);
        if ($teamIds !== [] && $conversation->teams()->whereIn('omni_teams.id', $teamIds)->exists()) {
            return true;
        }

        return ! $conversation->assignees()->exists()
            && ! $conversation->teams()->exists()
            && $conversation->assigned_user_id === null;
    }

    public static function applyInboxVisibility(Builder $query, User $user, string $inbox, bool $canSeeAll): void
    {
        if ($inbox === 'mine') {
            $uid = (int) $user->id;
            $teamIds = self::teamIdsForUser($uid);
            $query->where(function (Builder $q) use ($uid, $teamIds) {
                $q->where('assigned_user_id', $uid)
                    ->orWhereHas('assignees', fn ($sub) => $sub->where('users.id', $uid));
                if ($teamIds !== []) {
                    $q->orWhereHas('teams', fn ($sub) => $sub->whereIn('omni_teams.id', $teamIds));
                }
            });

            return;
        }

        if ($inbox === 'unassigned') {
            $query->whereDoesntHave('assignees')
                ->whereDoesntHave('teams')
                ->whereNull('assigned_user_id');

            return;
        }

        if ($canSeeAll) {
            return;
        }

        $uid = (int) $user->id;
        $teamIds = self::teamIdsForUser($uid);
        $query->where(function (Builder $q) use ($uid, $teamIds) {
            $q->where('assigned_user_id', $uid)
                ->orWhereHas('assignees', fn ($sub) => $sub->where('users.id', $uid));
            if ($teamIds !== []) {
                $q->orWhereHas('teams', fn ($sub) => $sub->whereIn('omni_teams.id', $teamIds));
            }
            $q->orWhere(function (Builder $q2) {
                $q2->whereDoesntHave('assignees')
                    ->whereDoesntHave('teams')
                    ->whereNull('assigned_user_id');
            });
        });
    }
}
