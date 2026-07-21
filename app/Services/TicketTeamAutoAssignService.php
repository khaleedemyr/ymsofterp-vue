<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketHistory;
use App\Models\TicketTeamSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TicketTeamAutoAssignService
{
    /**
     * Resolve team setting and assign users when ticket is created.
     */
    public function assignIfMatch(
        Ticket $ticket,
        ?int $assignedBy = null,
        string $historyLabel = 'Auto-assign team'
    ): bool {
        $setting = $this->resolveForTicket($ticket);
        if (! $setting) {
            return false;
        }

        $setting->loadMissing(['users']);
        $userIds = $setting->users->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
        if ($userIds->isEmpty()) {
            return false;
        }

        $primaryUserId = $setting->users->firstWhere('pivot.is_primary', true)?->id;
        $primaryUserId = $primaryUserId ? (int) $primaryUserId : $userIds->first();
        if (! $userIds->contains($primaryUserId)) {
            $primaryUserId = $userIds->first();
        }

        // Skip if current assignees already match the setting (same users + same primary).
        if ($this->assignmentsAlreadyMatch($ticket, $userIds, $primaryUserId)) {
            return false;
        }

        $this->assignUsers($ticket, $userIds, $primaryUserId, $assignedBy, $setting, true, $historyLabel);

        return true;
    }

    /**
     * Re-resolve team setting after category/outlet change and replace assignees.
     */
    public function reassignIfMatch(Ticket $ticket, ?int $assignedBy = null): bool
    {
        return $this->assignIfMatch($ticket, $assignedBy, 'Re-assign team (category/outlet updated)');
    }

    /**
     * Assign team from settings only when ticket has no assignment yet (for backfill).
     *
     * @return 'assigned'|'already_assigned'|'no_match'|'no_users'
     */
    public function backfillUnassignedTicket(
        Ticket $ticket,
        ?int $assignedBy = null,
        bool $sendNotifications = false
    ): string {
        if (TicketAssignment::where('ticket_id', $ticket->id)->exists()) {
            return 'already_assigned';
        }

        $setting = $this->resolveForTicket($ticket);
        if (! $setting) {
            return 'no_match';
        }

        $setting->loadMissing(['users']);
        $userIds = $setting->users->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
        if ($userIds->isEmpty()) {
            return 'no_users';
        }

        $primaryUserId = $setting->users->firstWhere('pivot.is_primary', true)?->id;
        $primaryUserId = $primaryUserId ? (int) $primaryUserId : $userIds->first();
        if (! $userIds->contains($primaryUserId)) {
            $primaryUserId = $userIds->first();
        }

        $assignedBy = $assignedBy ?: ($ticket->created_by ? (int) $ticket->created_by : $primaryUserId);

        $this->assignUsers(
            $ticket,
            $userIds,
            $primaryUserId,
            $assignedBy,
            $setting,
            $sendNotifications,
            'Backfill assign team'
        );

        return 'assigned';
    }

    public function resolveForTicket(Ticket $ticket): ?TicketTeamSetting
    {
        $ticket->loadMissing('outlet');
        $outletId = (int) $ticket->outlet_id;
        $regionId = $ticket->outlet?->region_id ? (int) $ticket->outlet->region_id : null;
        $categoryId = (int) $ticket->category_id;

        $candidates = TicketTeamSetting::query()
            ->active()
            ->where('category_id', $categoryId)
            ->with(['regions:id', 'outlets:id_outlet', 'users:id,nama_lengkap'])
            ->get();

        $best = null;
        $bestScore = -1;

        foreach ($candidates as $setting) {
            $score = $this->matchScore($setting, $outletId, $regionId);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $setting;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    /**
     * User IDs that should receive ticket notifications (assigned team or team setting match).
     */
    public function notificationRecipientUserIds(Ticket $ticket): Collection
    {
        $ticket->loadMissing(['assignedUsers']);

        $userIds = $ticket->assignedUsers
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            $setting = $this->resolveForTicket($ticket);
            if ($setting) {
                $setting->loadMissing('users');
                $userIds = $setting->users
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();
            }
        }

        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->where('status', 'A')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function matchScore(TicketTeamSetting $setting, int $outletId, ?int $regionId): int
    {
        $outletIds = $setting->outlets->pluck('id_outlet')->map(fn ($id) => (int) $id);
        $regionIds = $setting->regions->pluck('id')->map(fn ($id) => (int) $id);
        $hasOutlets = $outletIds->isNotEmpty();
        $hasRegions = $regionIds->isNotEmpty();

        if ($hasOutlets && $outletIds->contains($outletId)) {
            return 3;
        }
        if ($hasRegions && $regionId && $regionIds->contains($regionId)) {
            return 2;
        }
        if (! $hasOutlets && ! $hasRegions) {
            return 1;
        }

        return 0;
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function assignmentsAlreadyMatch(Ticket $ticket, Collection $userIds, int $primaryUserId): bool
    {
        $current = TicketAssignment::where('ticket_id', $ticket->id)
            ->get(['user_id', 'is_primary']);

        if ($current->isEmpty()) {
            return false;
        }

        $currentIds = $current->pluck('user_id')->map(fn ($id) => (int) $id)->sort()->values();
        $expectedIds = $userIds->map(fn ($id) => (int) $id)->sort()->values();
        if ($currentIds->values()->all() !== $expectedIds->values()->all()) {
            return false;
        }

        $currentPrimary = (int) ($current->firstWhere('is_primary', true)?->user_id ?? 0);

        return $currentPrimary === (int) $primaryUserId;
    }

    private function assignUsers(
        Ticket $ticket,
        Collection $userIds,
        int $primaryUserId,
        ?int $assignedBy,
        TicketTeamSetting $setting,
        bool $sendNotifications = true,
        string $historyLabel = 'Auto-assign team'
    ): void {
        $oldNames = TicketAssignment::query()
            ->with('user:id,nama_lengkap')
            ->where('ticket_id', $ticket->id)
            ->orderByDesc('is_primary')
            ->get()
            ->pluck('user.nama_lengkap')
            ->filter()
            ->values()
            ->all();

        TicketAssignment::where('ticket_id', $ticket->id)->delete();

        foreach ($userIds as $userId) {
            TicketAssignment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
                'is_primary' => (int) $userId === (int) $primaryUserId,
            ]);
        }

        $assignedNames = User::whereIn('id', $userIds)->pluck('nama_lengkap')->toArray();
        $label = $setting->name ?: ('Setting #' . $setting->id);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $assignedBy,
            'action' => 'assigned',
            'field_name' => 'assigned_users',
            'old_value' => $oldNames !== [] ? implode(', ', $oldNames) : null,
            'new_value' => implode(', ', $assignedNames),
            'description' => $historyLabel . ' (' . $label . '): ' . implode(', ', $assignedNames),
        ]);

        if ($sendNotifications) {
            $this->sendAssignmentNotifications($ticket, $userIds->all(), $primaryUserId, $assignedBy);
        }

        Log::info('Ticket auto-assigned from team setting', [
            'ticket_id' => $ticket->id,
            'setting_id' => $setting->id,
            'user_ids' => $userIds->all(),
            'primary_user_id' => $primaryUserId,
            'send_notifications' => $sendNotifications,
            'history_label' => $historyLabel,
        ]);
    }

    private function sendAssignmentNotifications(
        Ticket $ticket,
        array $userIds,
        int $primaryUserId,
        ?int $assignedBy
    ): void {
        try {
            if (empty($userIds)) {
                return;
            }

            $ticket->loadMissing(['divisi', 'outlet']);
            $assignerName = $assignedBy
                ? (User::where('id', $assignedBy)->value('nama_lengkap') ?? 'System')
                : 'System (auto-assign)';
            $outletName = $ticket->outlet->nama_outlet ?? '-';
            $divisiName = $ticket->divisi->nama_divisi ?? '-';

            foreach ($userIds as $userId) {
                $isPrimary = (int) $userId === (int) $primaryUserId;
                $roleLabel = $isPrimary ? 'PIC Utama' : 'Team Support';

                $message = "Anda di-assign ke ticket:\n\n";
                $message .= "No: {$ticket->ticket_number}\n";
                $message .= "Judul: {$ticket->title}\n";
                $message .= "Peran: {$roleLabel}\n";
                $message .= "Divisi: {$divisiName}\n";
                $message .= "Outlet: {$outletName}\n";
                $message .= "Assigned by: {$assignerName}";

                NotificationService::insert([
                    'user_id' => $userId,
                    'task_id' => $ticket->id,
                    'type' => 'ticket_assigned',
                    'message' => $message,
                    'url' => config('app.url') . '/tickets/' . $ticket->id,
                    'is_read' => 0,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send auto-assign ticket notifications', [
                'ticket_id' => $ticket->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
